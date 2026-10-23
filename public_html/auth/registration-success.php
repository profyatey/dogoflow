<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

unset($_SESSION['_registered_account_type']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A1628">
    <title>Check your email · DogoFlow</title>
    <link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>">
    <?php require __DIR__ . '/../partials/tailwind.php'; ?>
</head>
<body class="auth-page">
    <main class="auth-box-df">
        <a class="auth-brand-df" href="<?= url('/') ?>">
            <img src="<?= url('/assets/images/df-logo.png') ?>" alt="DogoFlow">
        </a>

        <div class="auth-icon-df" aria-hidden="true">✉</div>
        <h1>Check your email</h1>
        <p class="sub">We sent you a verification link. Open it to activate your account.</p>
        <p class="sub" style="font-size:.9rem">Can't see it? Check your Junk folder.</p>

        <div class="auth-actions-df">
            <a class="btn-df primary" href="<?= url('/auth/login.php') ?>">Continue to sign in</a>
        </div>
    </main>
</body>
</html>
