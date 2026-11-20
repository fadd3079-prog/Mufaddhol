<?php

declare(strict_types=1);

require_once __DIR__ . '/config/layout.php';

$works = [];
$tags = [];
$categoryCounts = array_fill_keys(CATEGORIES, 0);
$loadError = false;
try {
  $works = db()->query('SELECT * FROM portfolios ORDER BY sort_order ASC, created_at DESC, id DESC')->fetchAll();
  $seen = [];
  foreach ($works as $row) {
    if (isset($categoryCounts[$row['category']])) {
      $categoryCounts[$row['category']]++;
    }
    if ($row['category'] !== 'Software Development') {
      continue;
    }
    foreach (splitTags($row['tech_tags']) as $tag) {
      $key = mb_strtolower($tag);
      if (!isset($seen[$key])) {
        $seen[$key] = true;
        $tags[] = $tag;
      }
    }
  }
  sort($tags, SORT_NATURAL | SORT_FLAG_CASE);
} catch (PDOException $e) {
  $loadError = true;
}

$total = count($works);
page_start('Portfolio Archive | Mufaddhol', 'page-portfolio');
render_nav('portfolio');
?>
<main id="main">
  <section class="page" aria-labelledby="portfolio-title">
    <div class="container">
      <div class="page-head" id="portfolio-hero" data-nav-hero>
        <h1 id="portfolio-title" class="page-title">Portfolio.</h1>
        <div class="page-head_row">
          <p class="page-lead">Graphic design, video editing, presentation design, and software development, collected in one archive. Select any piece to see the full project, media, and details.</p>
          <p class="page-count" aria-live="polite">
            <span id="result-count"><?= $total ?></span>
            <span id="result-label"><?= $total === 1 ? 'work' : 'works' ?></span>
          </p>
        </div>
      </div>

      <?php if ($loadError): ?>
        <div class="state state--error" role="alert">
          <p class="state_title">Database connection error</p>
          <p>Works could not be loaded. Verify that MySQL is running and <code>database/schema.sql</code> is imported.</p>
        </div>
      <?php elseif (count($works) === 0): ?>
        <div class="state">
          <p class="state_title">No works published yet</p>
          <p>Add the first portfolio item from the administrative panel to populate the archive.</p>
          <a class="btn btn--outline" href="admin/index.php">Open Admin Panel</a>
        </div>
      <?php else: ?>
        <div class="filters" id="filters" data-nav-section="Explorer">
          <div class="filters_row">
            <div class="filters_tabs" role="group" aria-label="Filter by category">
              <button type="button" class="pill is-active" data-category="All" aria-pressed="true">
                <span>All</span>
                <span class="pill_count"><?= $total ?></span>
              </button>
              <?php foreach (CATEGORIES as $category): ?>
                <button type="button" class="pill" data-category="<?= e($category) ?>" aria-pressed="false">
                  <span><?= e($category) ?></span>
                  <span class="pill_count"><?= (int) $categoryCounts[$category] ?></span>
                </button>
              <?php endforeach; ?>
            </div>
            <div class="filters_search search-field">
              <svg class="search-field_icon" viewBox="0 0 20 20" width="18" height="18" aria-hidden="true">
                <circle cx="8.5" cy="8.5" r="5.5" fill="none" stroke="currentColor" stroke-width="1.5" />
                <path d="m13 13 4 4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
              </svg>
              <label class="visually-hidden" for="search">Search title or technology stack</label>
              <input type="search" id="search" class="input" placeholder="Search title or stack (press /)" autocomplete="off">
              <button type="button" id="search-clear" class="search-field_clear" aria-label="Clear search query" hidden>
                <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
                  <path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                </svg>
              </button>
            </div>
          </div>
          <?php if (count($tags) > 0): ?>
            <div class="filters_tags" id="tag-strip" role="group" aria-label="Filter software by technology stack" hidden>
              <span class="filters_label" aria-hidden="true">Stack</span>
              <?php foreach ($tags as $tag): ?>
                <button type="button" class="chip" data-tag="<?= e(mb_strtolower($tag)) ?>" aria-pressed="false"><?= e($tag) ?></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div id="masonry-grid" class="masonry" data-nav-section="Works">
          <?php foreach ($works as $row) {
            echo render_card($row), "\n";
          } ?>
        </div>
        <div id="empty-state" class="state" hidden>
          <p class="state_title">No works match the active filters</p>
          <p>Try clearing the search query or switching to another discipline category.</p>
          <button type="button" class="btn btn--outline" id="reset-filters">Clear All Filters</button>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php render_contact_band(); ?>
</main>
<?php
render_footer();
page_end();
