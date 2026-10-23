<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

$notice = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);
    $email = strtolower(trim((string)($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Do not reveal whether an account exists.
        $notice = 'If an account exists for that email, a password reset link has been sent. Please check your inbox and spam folder.';

        if ($user) {
            $raw = bin2hex(random_bytes(32));
            $hash = hash('sha256', $raw);

            db()->prepare('DELETE FROM password_resets WHERE user_id = ?')
                ->execute([(int)$user['id']]);

            db()->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
            )->execute([(int)$user['id'], $hash]);

            $resetUrl = url('/auth/reset-password.php?token=' . urlencode($raw));
            $message = "Hello,\n\nA password reset was requested for your DogoFlow account.\n\nUse the link below to choose a new password:\n{$resetUrl}\n\nThis link expires in 1 hour and can only be used once. If you did not request this, you can safely ignore this email.\n\nDogoFlow";

            try {
                // NotificationService sends through the configured DirectAdmin SMTP
                // immediately and leaves a queued notification for retry if SMTP fails.
                NotificationService::queue(
                    db(),
                    (int)$user['id'],
                    'Reset your DogoFlow password',
                    $message,
                    true, false);
            } catch (Throwable $mailError) {
                error_log('DogoFlow password reset email failed: ' . $mailError->getMessage());
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
    <title>Forgot password · DogoFlow</title>
    <?php require __DIR__ . '/../partials/tailwind.php'; ?>
</head>
<body>
<main class="container auth-card">
    <a class="brand" href="<?= e(url('/')) ?>">DogoFlow</a>
    <h1>Reset password</h1>

    <?php if ($error): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($notice): ?>
        <div class="card">
            <p><?= e($notice) ?></p>
            <p>For security, the reset link is only sent to your email and is never displayed on this page.</p>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label>
            Email
            <input type="email" name="email" required autocomplete="email">
        </label>
        <button class="button" type="submit">Send reset link</button>
    </form>

    <p><a href="<?= e(url('/auth/login.php')) ?>">Back to login</a></p>
</main>
</body>
</html>
