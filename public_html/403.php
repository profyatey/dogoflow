<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

http_response_code(403);

$role = Auth::role();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A1628">
    <title>Access denied · DogoFlow</title>
    <link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>">
    <?php require __DIR__ . '/partials/tailwind.php'; ?>
</head>
<body class="auth-page">
    <main class="auth-box-df">
        <a class="auth-brand-df" href="<?= url('/') ?>">
            <img src="<?= url('/assets/images/df-logo.png') ?>" alt="DogoFlow">
        </a>

        <div class="auth-icon-df" aria-hidden="true">403</div>
        <h1>Access denied</h1>
        <p class="sub">
            Your account does not have permission to open this area.
        </p>

        <?php if ($role): ?>
            <div class="auth-notice-df">
                <strong>Signed in as <?= e(ucfirst($role)) ?></strong>
                <span>
                    Please use the dashboard assigned to your account.
                </span>
            </div>
        <?php endif; ?>

        <div class="auth-actions-df">
            <a class="btn-df primary" href="<?= url('/auth/login.php') ?>">
                Sign in with another account
            </a>
            <a class="btn-df" href="<?= url('/') ?>">
                Return to DogoFlow
            </a>
        </div>
    </main>
</body>
</html>
