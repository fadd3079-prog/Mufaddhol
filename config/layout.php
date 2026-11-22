<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

const LINK_GITHUB = 'https://github.com/fadd3079-prog';
const LINK_LINKEDIN = 'https://www.linkedin.com/in/mufaddhol-01b60333a/';
const LINK_WHATSAPP = 'https://wa.me/6285111393079';
const LINK_INSTAGRAM = 'https://www.instagram.com/fadd.fadhol';

function page_start(string $title, string $bodyClass = ''): void
{
  send_security_headers(true);
?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f3f2ee">
    <meta name="description" content="Mufaddhol: Informatics student at Universitas Jenderal Soedirman, graphic designer, video editor, presentation designer, and software developer.">
    <title><?= e($title) ?></title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="assets/favicon.ico">
    <link rel="preload" href="assets/fonts/AdwaitaSans-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/main.css')) ?>">
  </head>

  <body class="<?= e($bodyClass) ?>">
    <a class="skip-link" href="#main">Skip to content</a>
  <?php
}

function render_nav(string $active): void
{
  $isHome = $active === 'home';
  // [label, href, id of the on-page section it points to (home only, for scroll-spy)]
  $links = [
    'works' => ['Work', $isHome ? '#featured-works' : 'index.php#featured-works', $isHome ? 'featured-works' : ''],
    'services' => ['Services', $isHome ? '#about' : 'index.php#about', $isHome ? 'about' : ''],
    'portfolio' => ['Archive', 'portfolio.php', ''],
  ];
  ?>
    <header id="site-header" class="site-nav">
      <div class="site-nav_shell">
        <a class="site-nav_brand" href="index.php" aria-label="Mufaddhol, home">
          <img class="site-nav_logo" src="assets/favicon.ico" alt="" width="36" height="36" draggable="false">
          <span class="site-nav_wordmark">Mufaddhol</span>
        </a>

        <button type="button" class="site-nav_toggle" aria-expanded="false" aria-controls="site-menu">
          <span class="site-nav_toggle-label">Menu</span>
          <span class="site-nav_toggle-icon" aria-hidden="true"><span></span></span>
        </button>

        <div class="site-nav_drawer">
          <nav id="site-menu" class="site-nav_menu" aria-label="Primary">
            <?php foreach ($links as $key => [$label, $href, $sectionTarget]): ?>
              <a class="site-nav_link" href="<?= e($href) ?>" <?= $sectionTarget !== '' ? ' data-section-link="' . e($sectionTarget) . '"' : '' ?><?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            <a class="site-nav_cta" href="#contact" data-section-link="contact">Contact</a>
          </nav>
        </div>
      </div>
    </header>
  <?php
}

function render_contact_band(): void
{
  $channels = [
    ['LinkedIn', 'Mufaddhol', LINK_LINKEDIN],
    ['WhatsApp', '62851 1139 3079', LINK_WHATSAPP],
    ['Instagram', '@fadd.fadhol', LINK_INSTAGRAM],
    ['GitHub', 'github.com/fadd3079-prog', LINK_GITHUB],
  ];
  ?>
    <section class="band" id="contact" aria-labelledby="contact-title">
      <div class="container band_inner">
        <div class="band_lead">
          <h2 id="contact-title" class="band_title">Get in touch.</h2>
          <p class="band_summary">Open for graphic design, video editing, presentation design, and software development work. Email is the quickest way to reach me.</p>
        </div>
        <div class="band_channels">
          <?php if (CONTACT_EMAIL !== ''): ?>
            <a class="band_email" href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>
          <?php endif; ?>
          <ul class="band_links" aria-label="Contact channels and profiles">
            <?php foreach ($channels as [$platform, $handle, $href]): ?>
              <li>
                <a href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer">
                  <span class="band_channel-platform"><?= e($platform) ?></span>
                  <span class="band_channel-handle"><?= e($handle) ?></span>
                  <span class="band_arrow" aria-hidden="true">&nearr;</span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </section>
  <?php
}

function render_footer(): void
{
  ?>
    <footer class="site-footer">
      <div class="container">
        <div class="site-footer_top">
          <div class="site-footer_identity">
            <a class="site-footer_brand" href="index.php">
              <img src="assets/favicon.ico" alt="" width="40" height="40" loading="lazy" draggable="false">
              <span>Mufaddhol.</span>
            </a>
            <p class="site-footer_role">Informatics student at Universitas Jenderal Soedirman. Graphic design, video, presentations, and software.</p>
          </div>
          <nav aria-label="Footer navigation">
            <ul class="site-footer_links">
              <li><a href="index.php">Home</a></li>
              <li><a href="portfolio.php">Archive</a></li>
              <li><a href="<?= e(LINK_GITHUB) ?>" target="_blank" rel="noopener noreferrer">GitHub</a></li>
              <li><a href="admin/index.php">Admin</a></li>
            </ul>
          </nav>
        </div>
        <div class="site-footer_bottom">
          <p>&copy; 2026 Mufaddhol</p>
          <a class="site-footer_top-link" href="#main">Back to top</a>
        </div>
      </div>
    </footer>
  <?php
}

function render_lightbox(): void
{
  ?>
    <dialog id="lightbox" class="lightbox" aria-label="Image zoom preview">
      <div class="lightbox_bar">
        <p class="lightbox_caption" id="lightbox-caption"></p>
        <button type="button" class="lightbox_close" data-close aria-label="Close zoom preview">
          <svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true">
            <path d="M4 4l12 12M16 4 4 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <div class="lightbox_stage" id="lightbox-stage">
        <img id="lightbox-img" alt="" width="1200" height="900" draggable="false">
      </div>
    </dialog>
  <?php
}

function page_end(): void
{
  ?>
    <script src="<?= e(asset_url('assets/js/portfolio-engine.js')) ?>" defer></script>
  </body>

  </html>
<?php
}
