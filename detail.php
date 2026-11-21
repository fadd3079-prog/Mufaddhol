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
        $isVideoMode = $item['media_mode'] !== 'image_only';
        $hasExternalVideo = $isVideoMode && !empty($item['video_url']);
        $externalVideo = $hasExternalVideo ? parse_external_video((string) $item['video_url']) : null;
        $hasMainVideo = $isVideoMode && !$hasExternalVideo && !empty($item['main_video']);
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
        <div class="detail_topbar">
          <nav class="detail_crumb" aria-label="Breadcrumb">
            <a class="detail_back" href="portfolio.php"><span aria-hidden="true">&larr;</span> Archive</a>
            <span class="detail_crumb-sep" aria-hidden="true">/</span>
            <a class="detail_crumb-cat" href="portfolio.php?category=<?= urlencode($item['category']) ?>"><?= e($item['category']) ?></a>
          </nav>

          <?php if ($prevWork || $nextWork): ?>
            <nav class="detail_pager" aria-label="Adjacent works">
              <?php if ($prevWork): ?>
                <a class="detail_pager-btn" href="detail.php?id=<?= (int) $prevWork['id'] ?>" title="<?= e($prevWork['title']) ?>" aria-label="Previous: <?= e($prevWork['title']) ?>">
                  <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
                    <path d="M9.75 3.5 5.25 8l4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                  <span class="detail_pager-label">Prev</span>
                </a>
              <?php else: ?>
                <span class="detail_pager-btn is-disabled" aria-disabled="true">
                  <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
                    <path d="M9.75 3.5 5.25 8l4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                  <span class="detail_pager-label">Prev</span>
                </span>
              <?php endif; ?>
              <?php if ($nextWork): ?>
                <a class="detail_pager-btn" href="detail.php?id=<?= (int) $nextWork['id'] ?>" title="<?= e($nextWork['title']) ?>" aria-label="Next: <?= e($nextWork['title']) ?>">
                  <span class="detail_pager-label">Next</span>
                  <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
                    <path d="M6.25 3.5 10.75 8l-4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </a>
              <?php else: ?>
                <span class="detail_pager-btn is-disabled" aria-disabled="true">
                  <span class="detail_pager-label">Next</span>
                  <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
                    <path d="M6.25 3.5 10.75 8l-4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </span>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        </div>

        <article class="detail_header editorial-content">
          <figure class="detail_showcase wrap-media">
            <?php if ($hasMainVideo): ?>
              <div class="detail_media" style="--ratio:<?= e(number_format($mainRatio, 4, '.', '')) ?>">
                <video controls controlsList="nodownload noplaybackrate" disablePictureInPicture autoplay loop muted playsinline poster="<?= e($item['main_image']) ?>" src="<?= e((string) $item['main_video']) ?>"></video>
              </div>
            <?php elseif ($externalVideo && $externalVideo['embed_url'] !== null): ?>
              <div class="detail_media detail_media--embed" style="--embed-aspect:<?= e($externalVideo['aspect']) ?>">
                <iframe
                  src="<?= e($externalVideo['embed_url']) ?>"
                  title="<?= e($item['title']) ?>"
                  loading="lazy"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                  referrerpolicy="strict-origin-when-cross-origin"
                  allowfullscreen></iframe>
              </div>
              <figcaption class="detail_embed-source">
                <a class="link-arrow" href="<?= e($externalVideo['url']) ?>" target="_blank" rel="noopener noreferrer">Open on <?= e($externalVideo['provider']) ?><span aria-hidden="true">&nearr;</span></a>
              </figcaption>
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
              <?php if ($externalVideo): ?>
                <figcaption class="detail_embed-source">
                  <a class="btn btn--outline" href="<?= e($externalVideo['url']) ?>" target="_blank" rel="noopener noreferrer">Watch Video<span aria-hidden="true">&nearr;</span></a>
                </figcaption>
              <?php endif; ?>
            <?php endif; ?>
          </figure>

          <h1 class="detail_title"><?= e($item['title']) ?></h1>

          <?php if (trim((string) ($item['description'] ?? '')) !== ''): ?>
            <div class="detail_description"><?= render_markdown((string) $item['description']) ?></div>
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
                <a class="btn" href="<?= e($demoUrl) ?>" target="_blank" rel="noopener noreferrer">
                  <svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true">
                    <path d="M6.5 3.5H4A1.5 1.5 0 0 0 2.5 5v7A1.5 1.5 0 0 0 4 13.5h7a1.5 1.5 0 0 0 1.5-1.5V9.5M9.5 2.5h4m0 0v4m0-4L7.5 8.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                  <span>Live Demo</span>
                </a>
              <?php endif; ?>
              <?php if ($githubUrl): ?>
                <a class="btn btn--outline" href="<?= e($githubUrl) ?>" target="_blank" rel="noopener noreferrer">
                  <svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true">
                    <path d="M8 1.5a6.5 6.5 0 0 0-2.055 12.668c.325.06.444-.14.444-.312 0-.154-.006-.665-.009-1.207-1.807.392-2.188-.767-2.188-.767-.295-.75-.721-.95-.721-.95-.59-.403.045-.395.045-.395.652.046.996.67.996.67.58.994 1.522.707 1.893.54.058-.42.227-.707.413-.87-1.443-.164-2.96-.721-2.96-3.21 0-.71.253-1.29.669-1.744-.067-.164-.29-.826.063-1.722 0 0 .546-.175 1.788.666a6.22 6.22 0 0 1 3.254 0c1.242-.841 1.787-.666 1.787-.666.354.896.131 1.558.064 1.722.417.454.668 1.034.668 1.744 0 2.495-1.52 3.044-2.967 3.205.233.201.441.598.441 1.206 0 .87-.008 1.572-.008 1.786 0 .173.117.375.447.311A6.5 6.5 0 0 0 8 1.5Z" />
                  </svg>
                  <span>GitHub Repository</span>
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </article>

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
      <?php endif; ?>
    </div>
  </section>

  <?php render_contact_band(); ?>
</main>
<?php
render_footer();
render_lightbox();
page_end();
