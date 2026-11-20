<?php

declare(strict_types=1);

require_once __DIR__ . '/config/layout.php';

$works = [];
$categoryCounts = array_fill_keys(CATEGORIES, 0);
$totalWorks = 0;
$loadError = false;
try {
  $countStmt = db()->query('SELECT category, COUNT(*) AS cnt FROM portfolios GROUP BY category');
  foreach ($countStmt->fetchAll() as $cRow) {
    if (isset($categoryCounts[$cRow['category']])) {
      $categoryCounts[$cRow['category']] = (int) $cRow['cnt'];
    }
    $totalWorks += (int) $cRow['cnt'];
  }

  $stmt = db()->query('SELECT * FROM portfolios WHERE is_featured = 1 ORDER BY sort_order ASC, created_at DESC, id DESC LIMIT 8');
  $works = $stmt->fetchAll();
  if (count($works) < 1) {
    $stmt = db()->query('SELECT * FROM portfolios ORDER BY sort_order ASC, created_at DESC, id DESC LIMIT 8');
    $works = $stmt->fetchAll();
  }
} catch (PDOException $e) {
  $loadError = true;
}

$disciplines = [
  'Graphic Design' => [
    'role' => 'Graphic Designer',
    'scope' => 'Visual identity systems, campaign key visuals, and print-ready editorial compositions.',
    'items' => ['Posters & key visuals', 'Social media feeds', 'Logo marks', 'Brand identity guidelines', 'Print and banner designs'],
  ],
  'Video Editing' => [
    'role' => 'Video Editor',
    'scope' => 'Rhythm-driven narrative cuts, commercial reels, and motion graphics sequences.',
    'items' => ['Short-form vertical reels', 'Promotional brand edits', 'Motion graphics sequences', 'Audio-synced visual cuts'],
  ],
  'Presentation Design' => [
    'role' => 'Presentation Designer',
    'scope' => 'Structured pitch decks and animated slide systems built for clarity on stage and on screen.',
    'items' => ['Investor & corporate pitch decks', 'Animated slides as looping video', 'Static slide plate systems', 'Data & diagram layouts'],
  ],
  'Software Development' => [
    'role' => 'Software Developer',
    'scope' => 'Full-stack web applications, point-of-sale platforms, cross-platform mobile apps, and command-line utilities.',
    'items' => ['Web applications', 'Desktop and mobile apps', 'POS & inventory systems', 'CLI & automation tools'],
  ],
];

$tools = [

  ['illustrator.svg', 'Adobe Illustrator'],
  ['Affinity.svg', 'Affinity'],
  ['Figma.svg', 'Figma'],
  ['Lightroom.svg', 'Adobe Lightroom'],
  ['Canva.svg', 'Canva'],
  ['Pixellab.svg', 'PixelLab'],
  ['Capcut.svg', 'CapCut'],
  ['html.svg', 'HTML'],
  ['css.svg', 'CSS'],
  ['Js.svg', 'JavaScript'],
  ['React.svg', 'React'],
  ['Nextjs.svg', 'Next.js'],
  ['mysql.svg', 'MySQL'],
];

page_start('Mufaddhol | Graphic Design, Video, Presentation, Software', 'page-home');
render_nav('home');
?>
<main id="main">
  <section class="hero" id="hero" data-nav-hero aria-labelledby="hero-title">
    <div class="container hero_inner">
      <h1 id="hero-title" class="hero_title">Mufaddhol.</h1>
      <div class="hero_meta">
        <div class="hero_lead">
          <p class="hero_bio">Active S1 Informatics student at Jenderal Soedirman University, working as a graphic designer, video editor, presentation designer, and software developer.</p>
          <div class="hero_actions">
            <a class="btn" href="#featured-works">View selected work</a>
            <a class="btn btn--outline" href="#contact">Get in touch</a>
          </div>
        </div>
        <ul class="hero_roles" aria-label="Browse work by discipline">
          <?php foreach ($disciplines as $catName => $meta): ?>
            <li><a href="portfolio.php?category=<?= urlencode($catName) ?>"><?= e($meta['role']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <section class="section" id="featured-works" aria-labelledby="featured-title">
    <div class="container">
      <div class="section-head">
        <h2 id="featured-title" class="section_title">Selected work.</h2>
        <?php if (!$loadError && count($works) > 0): ?>
          <div class="section-head_controls" id="filters">
            <div class="filters_tabs" role="group" aria-label="Filter selected works by category">
              <button type="button" class="pill is-active" data-category="All" aria-pressed="true">All</button>
              <?php foreach (CATEGORIES as $category): ?>
                <button type="button" class="pill" data-category="<?= e($category) ?>" aria-pressed="false"><?= e($category) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($loadError): ?>
        <div class="state state--error" role="alert">
          <p class="state_title">Database connection error</p>
          <p>Works could not be loaded. Verify that MySQL is running and <code>database/schema.sql</code> is imported.</p>
        </div>
      <?php elseif (count($works) === 0): ?>
        <div class="state">
          <p class="state_title">No works published yet</p>
          <p>Add the first portfolio item from the administrative panel to populate this grid.</p>
          <a class="btn btn--outline" href="admin/index.php">Open Admin Panel</a>
        </div>
      <?php else: ?>
        <div id="masonry-grid" class="masonry">
          <?php foreach ($works as $row) {
            echo render_card($row), "\n";
          } ?>
        </div>
        <div id="empty-state" class="state" hidden>
          <p class="state_title">No featured works in this category</p>
          <p>Reset the category filter or browse the complete portfolio archive.</p>
          <div class="state_actions">
            <button type="button" class="btn btn--outline" id="reset-filters">Show all selected</button>
            <a class="btn" href="portfolio.php">Open full archive</a>
          </div>
        </div>
        <div class="section-foot">
          <p class="section-foot_meta">Showing <?= count($works) ?> of <?= $totalWorks ?> published <?= $totalWorks === 1 ? 'work' : 'works' ?>.</p>
          <a class="link-arrow" href="portfolio.php">See the full archive<span aria-hidden="true">&rarr;</span></a>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="section section--services" id="about" aria-labelledby="about-title">
    <div class="container">
      <div class="section-head section-head--split">
        <h2 id="about-title" class="section_title">What I do.</h2>
        <p class="section-head_desc">Four disciplines, practiced side by side: visual design for print and screen, edited video, presentation systems, and the software behind them.</p>
      </div>
      <div class="disciplines">
        <?php foreach ($disciplines as $name => $meta):
          $count = (int) $categoryCounts[$name];
        ?>
          <article class="discipline">
            <div class="discipline_head">
              <h3 class="discipline_name"><?= e($name) ?></h3>
              <a class="discipline_link" href="portfolio.php?category=<?= urlencode($name) ?>" aria-label="Browse <?= $count ?> <?= e($name) ?> <?= $count === 1 ? 'work' : 'works' ?>">
                <span><?= $count ?> <?= $count === 1 ? 'work' : 'works' ?></span>
                <span aria-hidden="true">&rarr;</span>
              </a>
            </div>
            <p class="discipline_scope"><?= e($meta['scope']) ?></p>
            <ul class="discipline_list">
              <?php foreach ($meta['items'] as $item): ?>
                <li><?= e($item) ?></li>
              <?php endforeach; ?>
            </ul>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section section--tools" id="tools" aria-labelledby="tools-title">
    <div class="container tools_inner">
      <div class="tools_lead">
        <h2 id="tools-title" class="tools_title">Tools &amp; technologies.</h2>
        <p class="tools_desc">Software and technologies I use across visual design, editing, and development.</p>
      </div>
      <ul class="tools_grid">
        <?php foreach ($tools as [$iconFile, $toolName]): ?>
          <li class="tool-item" data-tooltip="<?= e($toolName) ?>">
            <img src="assets/icons/<?= e($iconFile) ?>" alt="<?= e($toolName) ?>" width="32" height="32" loading="lazy" decoding="async" draggable="false">
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <?php render_contact_band(); ?>
</main>
<?php
render_footer();
page_end();
