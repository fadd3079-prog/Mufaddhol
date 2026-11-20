<?php

declare(strict_types=1);

require_once __DIR__ . '/config/layout.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$item = null;
$details = [];
$prevWork = null;
$nextWork = null;
$dbError = false;

if ($id !== false && $id !== null) {
  try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM portfolios WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch() ?: null;

    if ($item) {
      $detailStmt = $pdo->prepare(
        'SELECT id, file_path, media_type, caption FROM portfolio_details
                 WHERE portfolio_id = ? ORDER BY sort_order ASC, id ASC'
      );
      $detailStmt->execute([$id]);
      $details = $detailStmt->fetchAll();

      $navRows = $pdo->query(
        'SELECT id, title FROM portfolios ORDER BY sort_order ASC, created_at DESC, id DESC'
      )->fetchAll();
      foreach ($navRows as $idx => $navRow) {
        if ((int) $navRow['id'] === $id) {
          $prevWork = $navRows[$idx - 1] ?? null;
          $nextWork = $navRows[$idx + 1] ?? null;
          break;
        }
      }
    }
  } catch (PDOException $e) {
    $dbError = true;
  }
}

if (!$item && !$dbError) {
  http_response_code(404);
}

$pageTitle = $item ? $item['title'] . ' — Mufaddhol' : 'Work Not Found — Mufaddhol';
page_start($pageTitle, 'page-detail');
render_nav('portfolio');
?>
<main id="main" class="detail-page">
  <section class="section detail-section">
    <div class="container">
      <?php if ($dbError): ?>
        <div class="state" role="alert">
          <p class="state_title">Database connection error</p>
          <p>Could not load this work. Check the connection in <code>config/database.php</code>.</p>
          <div class="state_actions">
            <a class="btn btn--outline" href="portfolio.php">Back to Archive</a>
          </div>
        </div>
      <?php elseif (!$item): ?>
        <div class="state">
          <p class="state_title">Work not found</p>
          <p>The requested portfolio entry does not exist or may have been removed.</p>
          <div class="state_actions">
            <a class="btn" href="portfolio.php">Browse Archive</a>
            <a class="btn btn--outline" href="index.php">Home</a>
          </div>
        </div>
      <?php else: ?>
        <?php
        $tags = splitTags($item['tech_tags'] ?? null);
        $rawDemo = trim((string) ($item['demo_url'] ?? ''));
        $rawGithub = trim((string) ($item['github_url'] ?? ''));
        $demoUrl = ($rawDemo !== '' && preg_match('#^https?://#i', $rawDemo) && filter_var($rawDemo, FILTER_VALIDATE_URL)) ? $rawDemo : null;
        $githubUrl = ($rawGithub !== '' && preg_match('#^https?://#i', $rawGithub) && filter_var($rawGithub, FILTER_VALIDATE_URL)) ? $rawGithub : null;
        $mainRatio = (float) ($item['aspect_ratio'] ?? 1.0);
        if ($mainRatio <= 0) {
          $mainRatio = 1.0;
        }
        $hasMainVideo = !empty($item['main_video']) && $item['media_mode'] !== 'image_only';
        $detailItems = [];
        foreach ($details as $detail) {
          $w = 800;
          $h = 600;
          $ratio = 0.75;
          if ($detail['media_type'] !== 'video') {
            $fullPath = __DIR__ . '/' . ltrim((string) $detail['file_path'], '/');
            $size = is_file($fullPath) ? @getimagesize($fullPath) : false;
            if ($size && $size[0] > 0 && $size[1] > 0) {
              $w = (int) $size[0];
              $h = (int) $size[1];
              $ratio = $h / $w;
            } else {
              $h = 800;
              $ratio = 1.0;
            }
          } else {
            $w = 1280;
            $h = 720;
            $ratio = 0.5625;
          }
          $detailItems[] = [
            'file_path' => (string) $detail['file_path'],
            'media_type' => (string) $detail['media_type'],
            'caption' => (string) ($detail['caption'] ?? ''),
            'width' => $w,
            'height' => $h,
            'ratio' => number_format($ratio, 4, '.', ''),
          ];
        }
        ?>
        <nav class="detail_crumb" aria-label="Breadcrumb">
          <a class="detail_back" href="portfolio.php"><span aria-hidden="true">&larr;</span> Archive</a>
          <span class="detail_crumb-sep" aria-hidden="true">/</span>
          <a class="detail_crumb-cat" href="portfolio.php?category=<?= urlencode($item['category']) ?>"><?= e($item['category']) ?></a>
        </nav>

        <header class="detail_header">
          <div class="detail_intro">
            <div class="detail_meta">
              <a class="tag" href="portfolio.php?category=<?= urlencode($item['category']) ?>"><?= e($item['category']) ?></a>
            </div>

            <h1 class="detail_title"><?= e($item['title']) ?></h1>

            <?php if (trim((string) ($item['description'] ?? '')) !== ''): ?>
              <p class="detail_description"><?= e((string) $item['description']) ?></p>
            <?php endif; ?>

            <?php if (count($tags) > 0): ?>
              <ul class="detail_stack" aria-label="Technology stack">
                <?php foreach ($tags as $tag): ?>
                  <li class="stack-chip"><?= e($tag) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <?php if ($demoUrl || $githubUrl): ?>
              <div class="detail_actions">
                <?php if ($demoUrl): ?>
                  <a class="btn" href="<?= e($demoUrl) ?>" target="_blank" rel="noopener noreferrer">Live Demo</a>
                <?php endif; ?>
                <?php if ($githubUrl): ?>
                  <a class="btn btn--outline" href="<?= e($githubUrl) ?>" target="_blank" rel="noopener noreferrer">GitHub Repository</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="detail_showcase">
            <?php if ($hasMainVideo): ?>
              <div class="detail_media" style="--ratio:<?= e(number_format($mainRatio, 4, '.', '')) ?>">
                <video controls controlsList="nodownload noplaybackrate" disablePictureInPicture autoplay loop muted playsinline poster="<?= e($item['main_image']) ?>" src="<?= e((string) $item['main_video']) ?>"></video>
              </div>
            <?php else: ?>
              <button
                type="button"
                class="detail_media detail_media--zoom"
                style="--ratio:<?= e(number_format($mainRatio, 4, '.', '')) ?>"
                data-lightbox-src="<?= e($item['main_image']) ?>"
                data-lightbox-alt="<?= e($item['title']) ?>"
                data-lightbox-caption="<?= e($item['title']) ?>"
                aria-label="Zoom <?= e($item['title']) ?>">
                <img src="<?= e($item['main_image']) ?>" alt="<?= e($item['title']) ?>" draggable="false">
              </button>
            <?php endif; ?>
          </div>
        </header>

        <?php if (count($detailItems) > 0): ?>
          <section class="detail_gallery" aria-labelledby="gallery-title">
            <div class="detail_gallery-head">
              <h2 id="gallery-title" class="detail_gallery-title">Gallery</h2>
              <span class="detail_gallery-count"><?= count($detailItems) ?> <?= count($detailItems) === 1 ? 'item' : 'items' ?></span>
            </div>

            <div id="detail-masonry-grid" class="detail-masonry">
              <?php foreach ($detailItems as $detail): ?>
                <figure class="detail-tile" data-ratio="<?= e($detail['ratio']) ?>" style="--ratio:<?= e($detail['ratio']) ?>">
                  <?php if ($detail['media_type'] === 'video'): ?>
                    <div class="detail-tile_media">
                      <video controls controlsList="nodownload noplaybackrate" disablePictureInPicture playsinline preload="metadata" width="<?= $detail['width'] ?>" height="<?= $detail['height'] ?>" src="<?= e($detail['file_path']) ?>"></video>
                    </div>
                  <?php else: ?>
                    <button
                      type="button"
                      class="detail-tile_zoom"
                      data-lightbox-src="<?= e($detail['file_path']) ?>"
                      data-lightbox-alt="<?= e($detail['caption'] !== '' ? $detail['caption'] : $item['title']) ?>"
                      data-lightbox-caption="<?= e($detail['caption']) ?>"
                      aria-label="Zoom image<?= $detail['caption'] !== '' ? ': ' . e($detail['caption']) : '' ?>">
                      <img src="<?= e($detail['file_path']) ?>" alt="<?= e($detail['caption'] !== '' ? $detail['caption'] : $item['title']) ?>" width="<?= $detail['width'] ?>" height="<?= $detail['height'] ?>" loading="lazy" decoding="async" draggable="false">
                    </button>
                  <?php endif; ?>
                  <?php if ($detail['caption'] !== ''): ?>
                    <figcaption class="detail-tile_caption"><?= e($detail['caption']) ?></figcaption>
                  <?php endif; ?>
                </figure>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($prevWork || $nextWork): ?>
          <nav class="detail_pager" aria-label="Adjacent works">
            <div>
              <?php if ($prevWork): ?>
                <a class="detail_pager-link" href="detail.php?id=<?= (int) $prevWork['id'] ?>">
                  <span class="detail_pager-dir">&larr; Previous</span>
                  <span class="detail_pager-title"><?= e($prevWork['title']) ?></span>
                </a>
              <?php endif; ?>
            </div>
            <div class="detail_pager-next">
              <?php if ($nextWork): ?>
                <a class="detail_pager-link" href="detail.php?id=<?= (int) $nextWork['id'] ?>">
                  <span class="detail_pager-dir">Next &rarr;</span>
                  <span class="detail_pager-title"><?= e($nextWork['title']) ?></span>
                </a>
              <?php endif; ?>
            </div>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <?php render_contact_band(); ?>
</main>
<?php
render_footer();
render_lightbox();
page_end();
