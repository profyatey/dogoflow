<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

// The reset token is delivered only by email. Keep a server-side copy so a
// normal page reload does not lose the token, while never rendering it as text.
$queryToken = trim((string)($_GET['token'] ?? ''));
$postToken = trim((string)($_POST['token'] ?? ''));
$sessionToken = trim((string)($_SESSION['_dogoflow_reset_token'] ?? ''));

$token = '';
if ($queryToken !== '' && preg_match('/^[a-f0-9]{64}$/i', $queryToken)) {
    $token = $queryToken;
    $_SESSION['_dogoflow_reset_token'] = $queryToken;
} elseif ($postToken !== '' && preg_match('/^[a-f0-9]{64}$/i', $postToken)) {
    $token = $postToken;
    $_SESSION['_dogoflow_reset_token'] = $postToken;
} elseif ($sessionToken !== '' && preg_match('/^[a-f0-9]{64}$/i', $sessionToken)) {
    $token = $sessionToken;
}

$error = null;
$success = false;

if ($token === '') {
    $error = 'Invalid or expired reset link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    verify_csrf($_POST['_csrf'] ?? null);
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');

    if (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'Password confirmation does not match.';
    } else {
        $tokenHash = hash('sha256', $token);
        $stmt = db()->prepare(
            'SELECT id,user_id FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $error = 'Invalid or expired reset link. Please request a new password reset email.';
            unset($_SESSION['_dogoflow_reset_token']);
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$reset['user_id']]);
                $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
                    ->execute([(int)$reset['id']]);
                $pdo->commit();
                unset($_SESSION['_dogoflow_reset_token']);
                $success = true;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('DogoFlow password reset failed: ' . $e->getMessage());
                $error = 'Password reset failed. Please request a new reset link and try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Set new password · DogoFlow</title>
    <?php require __DIR__ . '/../partials/tailwind.php'; ?>
</head>
<body>
<main class="container auth-card">
    <a class="brand" href="<?= e(url('/')) ?>">DogoFlow</a>
    <h1>Set a new password</h1>

    <?php if ($success): ?>
        <div class="card">
            <p>Password changed successfully.</p>
            <a href="<?= e(url('/auth/login.php')) ?>">Login to your account</a>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$error): ?>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label>
                    New password
                    <input type="password" name="password" minlength="8" required autocomplete="new-password">
                </label>
                <label>
                    Confirm new password
                    <input type="password" name="password_confirmation" minlength="8" required autocomplete="new-password">
                </label>
                <button class="button" type="submit">Change password</button>
            </form>
        <?php else: ?>
            <p><a href="<?= e(url('/auth/forgot-password.php')) ?>">Request a new reset link</a></p>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
