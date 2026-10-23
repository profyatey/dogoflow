<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('seller');

$stmt = db()->prepare(
    'SELECT
        u.status AS user_status,
        u.email_verified_at,
        sp.id AS seller_profile_id,
        sp.business_name,
        sp.approval_status,
        sp.status AS seller_status,
        sp.rejection_reason
     FROM users u
     LEFT JOIN seller_profiles sp ON sp.user_id = u.id
     WHERE u.id = ?
     LIMIT 1'
);
$stmt->execute([Auth::userId()]);
$state = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$userStatus = strtolower((string) ($state['user_status'] ?? ''));
$approval = strtolower((string) ($state['approval_status'] ?? ''));
$sellerStatus = strtolower((string) ($state['seller_status'] ?? ''));
$emailVerified = !empty($state['email_verified_at']);
$businessName = (string) ($state['business_name'] ?? 'Seller account');

if (empty($state['seller_profile_id'])) {
    $statusLabel = 'Profile unavailable';
    $heading = 'Seller profile unavailable';
    $copy = 'We could not find a seller profile for this account. Please contact DogoFlow support so the account can be checked.';
    $icon = '!';
} elseif (
    $userStatus === 'suspended'
    || $sellerStatus === 'suspended'
    || $approval === 'suspended'
) {
    $statusLabel = 'Suspended';
    $heading = 'Seller account suspended';
    $copy = 'Seller access is currently suspended. Please contact DogoFlow support if you believe this is a mistake.';
    $icon = '⛔';
} elseif ($approval === 'rejected') {
    $statusLabel = 'Rejected';
    $heading = 'Seller application needs attention';
    $copy = trim((string) ($state['rejection_reason'] ?? '')) !== ''
        ? (string) $state['rejection_reason']
        : 'Seller access has not been enabled for this account. Please contact DogoFlow support for next steps.';
    $icon = '!';
} elseif (!$emailVerified) {
    $statusLabel = 'Email not verified';
    $heading = 'Verify your email address';
    $copy = 'Open the verification link sent to your email address. Eligible sellers are automatically approved after successful email verification.';
    $icon = '✉';
} elseif ($approval === 'approved' && $sellerStatus === 'active' && $userStatus === 'active') {
    redirect('/seller/dashboard.php');
} elseif (in_array($approval, ['pending', ''], true) || in_array($sellerStatus, ['pending', ''], true)) {
    $statusLabel = 'Activation pending';
    $heading = 'Seller activation is not complete yet';
    $copy = 'DogoFlow normally approves eligible sellers automatically after email verification. Your account has not finished activating. Please sign out and sign back in; if the status persists, contact DogoFlow support. Routine Admin approval is not required.';
    $icon = '⏳';
} else {
    $statusLabel = 'Access needs attention';
    $heading = 'Seller access needs attention';
    $copy = 'Your email is verified, but the seller profile and account status do not match the expected active state. Please contact DogoFlow support for help.';
    $icon = '!';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($heading) ?> · DogoFlow</title>
    <link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>">
    <?php require __DIR__ . '/../partials/tailwind.php'; ?>
</head>
<body class="auth-page">
    <main class="auth-box-df">
        <a class="auth-brand-df" href="<?= e(url('/')) ?>">
            <img src="<?= e(url('/assets/images/df-logo.png')) ?>" alt="DogoFlow">
        </a>
        <div class="auth-icon-df" aria-hidden="true"><?= e($icon) ?></div>
        <h1><?= e($heading) ?></h1>
        <p class="sub"><?= e($businessName) ?></p>
        <div class="auth-notice-df">
            <strong>Status: <?= e($statusLabel) ?></strong>
            <span><?= e($copy) ?></span>
        </div>
        <div class="auth-actions-df">
            <a class="btn-df primary" href="<?= e(url('/seller/access-status.php')) ?>">Refresh status</a>
            <a class="btn-df" href="<?= e(url('/')) ?>">Return to DogoFlow</a>
            <a class="btn-df" href="<?= e(url('/auth/logout.php')) ?>">Sign out</a>
        </div>
    </main>
</body>
</html>
