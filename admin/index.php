<?php

declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pdo = db();
  $action = (string) ($_POST['action'] ?? '');

  if ($action === 'reorder') {
    header('Content-Type: application/json; charset=utf-8');
    $rawOrder = $_POST['order'] ?? [];
    $ids = array_values(array_unique(array_filter(
      array_map(static fn($v) => filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), is_array($rawOrder) ? $rawOrder : []),
      static fn($v) => $v !== false
    )));
    if (!csrf_valid() || count($ids) === 0) {
      http_response_code(400);
      echo json_encode(['status' => 'error', 'message' => 'Invalid reorder request.']);
      exit;
    }
    try {
      $pdo->beginTransaction();
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $slotStmt = $pdo->prepare("SELECT sort_order FROM portfolios WHERE id IN ($placeholders) ORDER BY sort_order ASC, created_at DESC, id DESC");
      $slotStmt->execute($ids);
      $slots = array_map('intval', $slotStmt->fetchAll(PDO::FETCH_COLUMN));
      if (count($slots) !== count($ids)) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Portfolio item missing.']);
        exit;
      }
      // Normalize flat 0 slots (e.g. freshly created items) to unique sequential positions
      if (count(array_unique($slots)) < count($slots)) {
        $allIds = $pdo->query('SELECT id FROM portfolios ORDER BY sort_order ASC, created_at DESC, id DESC')->fetchAll(PDO::FETCH_COLUMN);
        $posMap = [];
        foreach ($allIds as $idx => $pid) {
          $posMap[(int) $pid] = $idx + 1;
        }
        $slots = array_map(static fn($pid) => $posMap[$pid], $ids);
        sort($slots);
        $initStmt = $pdo->prepare('UPDATE portfolios SET sort_order = ? WHERE id = ?');
        foreach ($posMap as $pid => $pos) {
          $initStmt->execute([$pos, $pid]);
        }
      }
      $upd = $pdo->prepare('UPDATE portfolios SET sort_order = ? WHERE id = ?');
      foreach ($ids as $idx => $pid) {
        $upd->execute([$slots[$idx], $pid]);
      }
      $pdo->commit();
      echo json_encode(['status' => 'ok']);
    } catch (PDOException $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      http_response_code(500);
      echo json_encode(['status' => 'error', 'message' => 'Could not save order.']);
    }
    exit;
  }

  if ($action === 'randomize') {
    if (!csrf_valid()) {
      flash_set('error', 'Request rejected. Reload the page and try again.');
    } else {
      try {
        $pdo->beginTransaction();
        $ids = array_map('intval', $pdo->query('SELECT id FROM portfolios')->fetchAll(PDO::FETCH_COLUMN));
        if (count($ids) > 1) {
          shuffle($ids);
          $upd = $pdo->prepare('UPDATE portfolios SET sort_order = ? WHERE id = ?');
          foreach ($ids as $pos => $pid) {
            $upd->execute([$pos + 1, $pid]);
          }
        }
        $pdo->commit();
        flash_set('success', 'Portfolio order randomized.');
      } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        flash_set('error', 'Could not randomize order.');
      }
    }
    header('Location: index.php');
    exit;
  }

  $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

  if (!csrf_valid() || $id === false) {
    flash_set('error', 'Request rejected. Reload the page and try again.');
  } elseif ($action === 'delete') {
    $files = [];
    try {
      $pdo->beginTransaction();
      $stmt = $pdo->prepare('SELECT main_image, main_video FROM portfolios WHERE id = ? FOR UPDATE');
      $stmt->execute([$id]);
      $row = $stmt->fetch();
      if (!$row) {
        $pdo->rollBack();
        flash_set('error', 'Portfolio not found.');
      } else {
        $files = [$row['main_image'], $row['main_video']];
        $stmt = $pdo->prepare('SELECT file_path FROM portfolio_details WHERE portfolio_id = ?');
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll() as $detail) {
          $files[] = $detail['file_path'];
        }
        $pdo->prepare('DELETE FROM portfolios WHERE id = ?')->execute([$id]);
        $pdo->commit();
        delete_stored_files($files);
        flash_set('success', 'Portfolio deleted.');
      }
    } catch (PDOException $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      flash_set('error', 'Delete failed. Nothing was removed.');
    }
  }
  header('Location: index.php');
  exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$categoryFilter = (string) ($_GET['category'] ?? '');
if (!in_array($categoryFilter, CATEGORIES, true)) {
  $categoryFilter = '';
}

$categoryCounts = array_fill_keys(CATEGORIES, 0);
$counters = ['total' => 0];
$rows = [];
$loadError = false;

try {
  $pdo = db();
  $counters['total'] = (int) $pdo->query('SELECT COUNT(*) FROM portfolios')->fetchColumn();

  $catStmt = $pdo->query('SELECT category, COUNT(*) AS cnt FROM portfolios GROUP BY category');
  foreach ($catStmt->fetchAll() as $catRow) {
    $catName = (string) $catRow['category'];
    if (array_key_exists($catName, $categoryCounts)) {
      $categoryCounts[$catName] = (int) $catRow['cnt'];
    }
  }

  $sql = 'SELECT p.id, p.title, p.category, p.media_mode, p.main_image, COUNT(d.id) AS detail_count
            FROM portfolios p
            LEFT JOIN portfolio_details d ON d.portfolio_id = p.id';
  $where = [];
  $params = [];
  if ($categoryFilter !== '') {
    $where[] = 'p.category = ?';
    $params[] = $categoryFilter;
  }
  if ($search !== '') {
    $where[] = '(p.title LIKE ? OR p.tech_tags LIKE ?)';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    array_push($params, $like, $like);
  }
  if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }
  $sql .= ' GROUP BY p.id ORDER BY p.sort_order ASC, p.created_at DESC, p.id DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();
} catch (PDOException $e) {
  $loadError = true;
}

$clearDraftKey = (string) ($_SESSION['clear_draft_key'] ?? '');
unset($_SESSION['clear_draft_key']);

admin_start('Portfolio');
?>
<div class="admin-head"<?= $clearDraftKey !== '' ? ' data-clear-draft="' . e($clearDraftKey) . '"' : '' ?>>
  <h1>Portfolio</h1>
  <div class="admin-head_actions">
    <?php if (!$loadError && $counters['total'] > 1): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="randomize">
        <button type="submit" class="btn btn--outline">Randomize Order</button>
      </form>
    <?php endif; ?>
    <a class="btn" href="form.php">+ Add Portfolio</a>
  </div>
</div>

<?php if ($loadError): ?>
  <p class="state state--error" role="alert">Database unavailable. Check the connection and that <code>database/schema.sql</code> is imported.</p>
<?php else: ?>
  <dl class="counters">
    <div>
      <dt>Total Works</dt>
      <dd><?= $counters['total'] ?></dd>
    </div>
    <?php foreach ($categoryCounts as $catLabel => $catTotal): ?>
      <div>
        <dt><?= e($catLabel) ?></dt>
        <dd><?= $catTotal ?></dd>
      </div>
    <?php endforeach; ?>
  </dl>

  <form method="get" class="toolbar">
    <div class="field">
      <label for="q">Search</label>
      <input class="input" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Title or tag">
    </div>
    <div class="field">
      <label for="category">Category</label>
      <select class="input" id="category" name="category">
        <option value="">All</option>
        <?php foreach (CATEGORIES as $category): ?>
          <option value="<?= e($category) ?>" <?= $category === $categoryFilter ? ' selected' : '' ?>><?= e($category) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn--outline">Filter</button>
    <?php if ($search !== '' || $categoryFilter !== ''): ?>
      <a class="link toolbar_reset" href="index.php">Clear</a>
    <?php endif; ?>
  </form>

  <?php if (count($rows) === 0): ?>
    <p class="state">
      <?= ($search !== '' || $categoryFilter !== '') ? 'No portfolio matches the filter.' : 'No portfolio yet. Use + Add Portfolio to create the first one.' ?>
    </p>
  <?php else: ?>
    <p id="table-status" class="table-status" role="status" aria-live="polite" hidden></p>
    <div class="table-wrap">
      <table class="table" id="portfolio-table" data-csrf="<?= e(csrf_token()) ?>">
        <thead>
          <tr>
            <th scope="col" class="table_sort-head"><span class="visually-hidden">Order</span></th>
            <th scope="col">Cover</th>
            <th scope="col">Title</th>
            <th scope="col">Category</th>
            <th scope="col">Media</th>
            <th scope="col" class="table_num">Details</th>
            <th scope="col" class="table_actions-head"><span class="visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr data-id="<?= (int) $row['id'] ?>">
              <td class="table_sort">
                <button type="button" class="sort-handle" aria-label="Reorder <?= e($row['title']) ?> (drag or press Up/Down)" title="Drag to reorder">
                  <img src="../assets/icons/sort.svg" alt="" width="16" height="16" aria-hidden="true">
                </button>
              </td>
              <td><img class="table_thumb" src="../<?= e($row['main_image']) ?>" alt="" width="52" height="52" loading="lazy"></td>
              <td class="table_title"><?= e($row['title']) ?></td>
              <td class="table_cell-muted"><?= e($row['category']) ?></td>
              <td class="table_cell-muted"><?= e(MEDIA_MODES[$row['media_mode']] ?? $row['media_mode']) ?></td>
              <td class="table_num"><?= (int) $row['detail_count'] ?></td>
              <td class="table_actions">
                <div class="table_actions-inner">
                  <a class="btn btn--outline table_icon-btn" href="form.php?id=<?= (int) $row['id'] ?>" aria-label="Edit <?= e($row['title']) ?>" title="Edit">
                    <img src="../assets/icons/edit.svg" alt="" width="16" height="16" aria-hidden="true">
                  </a>
                  <form method="post" data-confirm="Confirm delete?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button type="submit" class="btn btn--outline btn--danger table_icon-btn" aria-label="Delete <?= e($row['title']) ?>" title="Delete">
                      <img src="../assets/icons/delete.svg" alt="" width="16" height="16" aria-hidden="true">
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>
<dialog id="confirm-dialog" class="confirm-dialog" aria-labelledby="confirm-dialog-title">
  <p id="confirm-dialog-title" class="confirm-dialog_title">Confirm delete?</p>
  <div class="confirm-dialog_actions">
    <button type="button" class="btn btn--outline" data-confirm-cancel>Cancel</button>
    <button type="button" class="btn btn--danger" data-confirm-ok>Delete</button>
  </div>
</dialog>
<?php admin_end();
