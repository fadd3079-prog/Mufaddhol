<?php

declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_admin();

$adminId = (int) $_SESSION['admin_id'];
$errors = [];
$user = null;

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE id = ?');
    $stmt->execute([$adminId]);
    $user = $stmt->fetch() ?: null;
} catch (PDOException $e) {
    flash_set('error', 'Database unavailable.');
    header('Location: index.php');
    exit;
}

if (!$user) {
    unset($_SESSION['admin_id']);
    flash_set('error', 'Admin account not found.');
    header('Location: login.php');
    exit;
}

$username = (string) $user['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $usernameInput = trim((string) ($_POST['username'] ?? ''));
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $username = $usernameInput;
        $ip = client_ip();
        $rateScope = 'acct:' . (string) $user['username'];
        $attempts = login_attempts_count($pdo, $rateScope, $ip);

        if ($attempts >= 5) {
            usleep(min(5, $attempts - 4) * 300000);
            login_attempt_fail($pdo, $rateScope, $ip);
            $errors[] = 'Too many incorrect password attempts. Try again later.';
        } elseif ($currentPassword === '' || !password_verify($currentPassword, (string) $user['password_hash'])) {
            login_attempt_fail($pdo, $rateScope, $ip);
            $errors[] = 'Current password is incorrect.';
        } else {
            login_attempt_clear($pdo, $rateScope, $ip);
        }

        if ($usernameInput === '') {
            $errors[] = 'Username is required.';
        } elseif (mb_strlen($usernameInput) > 50) {
            $errors[] = 'Username must be at most 50 characters.';
        } elseif (preg_match('/\s/', $usernameInput)) {
            $errors[] = 'Username cannot contain spaces.';
        }

        $usernameChanged = $usernameInput !== (string) $user['username'];
        $passwordChanged = $newPassword !== '' || $confirmPassword !== '';

        if ($passwordChanged) {
            if (strlen($newPassword) < 12) {
                $errors[] = 'New password must be at least 12 characters.';
            } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
                $errors[] = 'New password must include uppercase, lowercase, and numeric characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'New password and confirmation do not match.';
            }
        }

        if (!$errors && !$usernameChanged && !$passwordChanged) {
            $errors[] = 'Enter a new password or change the username.';
        }

        if (!$errors && $usernameChanged) {
            try {
                $dupStmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
                $dupStmt->execute([$usernameInput, $adminId]);
                if ($dupStmt->fetch()) {
                    $errors[] = 'That username is already in use.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Could not verify username availability.';
            }
        }

        if (!$errors) {
            try {
                if ($passwordChanged) {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare('UPDATE users SET username = ?, password_hash = ? WHERE id = ?');
                    $upd->execute([$usernameInput, $newHash, $adminId]);
                } else {
                    $upd = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
                    $upd->execute([$usernameInput, $adminId]);
                }

                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                $message = ($usernameChanged && $passwordChanged)
                    ? 'Username and password updated.'
                    : ($passwordChanged ? 'Password updated.' : 'Username updated.');
                flash_set('success', $message);
                header('Location: account.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Could not update account credentials.';
            }
        }
    }
}

admin_start('Account Settings', true, 'admin--form');
?>
<div class="admin-head">
    <div class="admin-head_lead">
        <h1>Account Settings</h1>
        <p class="admin-head_sub">Update your admin username or password. Leave the new password fields blank to keep your current password.</p>
    </div>
    <a class="btn btn--outline" href="index.php">Back to list</a>
</div>

<?php if ($errors): ?>
    <div class="flash flash--error" role="alert">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="form" novalidate>
    <?= csrf_field() ?>

    <div class="form_section">
        <div class="field">
            <label for="username">Username</label>
            <input class="input" type="text" id="username" name="username" maxlength="50" value="<?= e($username) ?>" autocomplete="username" required>
        </div>

        <div class="field">
            <label for="current_password">Current Password</label>
            <input class="input" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
        </div>

        <div class="form_row">
            <div class="field">
                <label for="new_password">New Password</label>
                <input class="input" type="password" id="new_password" name="new_password" minlength="12" autocomplete="new-password" placeholder="Min. 12 chars (A-Z, a-z, 0-9)">
            </div>
            <div class="field">
                <label for="confirm_password">Confirm New Password</label>
                <input class="input" type="password" id="confirm_password" name="confirm_password" minlength="12" autocomplete="new-password" placeholder="Repeat new password">
            </div>
        </div>
    </div>

    <div class="form_actions">
        <button type="submit" class="btn">Save Credentials</button>
        <a class="btn btn--outline" href="index.php">Cancel</a>
    </div>
</form>
<?php admin_end(true);
