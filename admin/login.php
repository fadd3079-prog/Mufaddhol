<?php

declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_valid()) {
        $error = 'Session expired. Reload the page and try again.';
    } else {
        try {
            $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Verify against a dummy hash when the user is missing to keep timing uniform.
            $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
            $valid = password_verify($password, $hash) && $user;

            if ($valid) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int) $user['id'];
                header('Location: index.php');
                exit;
            }
            $error = 'Wrong username or password.';
        } catch (PDOException $e) {
            $error = 'Database unavailable. Check the connection and that schema.sql is imported.';
        }
    }
}

admin_start('Log In', false, 'admin--login');
?>
<section class="login" aria-labelledby="login-title">
    <header class="login_head">
        <div class="login_brand" oncontextmenu="return false">
            <img src="../assets/favicon.ico" alt="" width="28" height="28" draggable="false">
            <span>Mufaddhol</span>
            <span class="login_sep" aria-hidden="true">/</span>
            <span class="login_scope">Admin</span>
        </div>
        <h1 id="login-title" class="login_title">Log In</h1>
    </header>
    <?php if ($error): ?>
        <p class="flash flash--error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" class="form" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="username">Username</label>
            <input class="input" type="text" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn">Log In</button>
    </form>
    <p class="login_back"><a class="login_back-link" href="../index.php">Back to site</a></p>
</section>
<?php admin_end(false);
