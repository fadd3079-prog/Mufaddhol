<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';

function admin_start(string $title, bool $withBar = true, string $bodyClass = ''): void
{
?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f3f2ee">
    <title><?= e($title) ?> | Mufaddhol Admin</title>
    <link rel="icon" href="../assets/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="../assets/favicon.ico">
    <link rel="preload" href="../assets/fonts/AdwaitaSans-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/tokens.css', '../')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/main.css', '../')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/admin.css', '../')) ?>">
  </head>

  <body class="admin <?= e($bodyClass) ?>">
    <a class="skip-link" href="#main">Skip to content</a>
    <?php if ($withBar): ?>
      <header class="admin-bar">
        <div class="admin-bar_inner">
          <a class="admin-bar_brand" href="index.php" oncontextmenu="return false">
            <img class="admin-bar_logo" src="../assets/favicon.ico" alt="" width="32" height="32" draggable="false">
            <span class="admin-bar_name">Mufaddhol</span>
            <span class="admin-bar_sep" aria-hidden="true">/</span>
            <span class="admin-bar_scope">Admin</span>
          </a>
          <div class="admin-bar_actions">
            <a class="admin-bar_link" href="account.php">Account</a>
            <a class="admin-bar_link" href="../index.php">View Site</a>
            <form method="post" action="logout.php">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn--outline admin-bar_logout">Log Out</button>
            </form>
          </div>
        </div>
      </header>
    <?php endif; ?>
    <main id="main" class="admin-main">
      <?php
      $flash = flash_get();
      if ($flash) {
        echo '<p class="flash flash--', e($flash['type']), '" role="', $flash['type'] === 'error' ? 'alert' : 'status', '">', e($flash['message']), '</p>';
      }
    }

    function admin_end(bool $withScript = true): void
    {
      ?>
    </main>
    <?php if ($withScript): ?>
      <script src="<?= e(asset_url('assets/js/admin-form.js', '../')) ?>" defer></script>
    <?php endif; ?>
  </body>

  </html>
<?php
    }
