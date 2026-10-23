<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/payments/Paystack.php';
require_once DOGOFLOW_ROOT . '/core/payments/SellerSubscriptionPayment.php';

Auth::requireApprovedSeller();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/seller/upgrade.php');
}

verify_csrf($_POST['_csrf'] ?? null);

$userId = (int)Auth::userId();
$plan = SellerPlan::get($userId);

if ($plan['is_pro']) {
    flash('success', 'Your Pro subscription is already active until ' . date('j M Y', strtotime((string)$plan['subscription_expires_at'])) . '.');
    redirect('/seller/upgrade.php');
}

$stmt = db()->prepare('SELECT id, email, name FROM users WHERE id = ? AND role = "seller" LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
    flash('error', 'We could not find a valid seller email address for this account.');
    redirect('/seller/upgrade.php');
}

$reference = 'DFPRO-' . $userId . '-' . strtoupper(bin2hex(random_bytes(8)));
$paymentId = null;

try {
    $paymentId = SellerSubscriptionPayment::createPending($userId, $reference);

    $paystack = new Paystack();
    $response = $paystack->initialize(
        (string)$user['email'],
        SellerSubscriptionPayment::PRO_AMOUNT_MINOR,
        $reference,
        url('/seller/subscription-callback.php'),
        [
            'purpose' => 'seller_pro_subscription',
            'user_id' => $userId,
            'plan' => 'pro',
        ]
    );

    $authorizationUrl = trim((string)($response['data']['authorization_url'] ?? ''));

    if ($authorizationUrl === '') {
        throw new RuntimeException('Paystack did not return a checkout URL.');
    }

    // Store the initialization response using only columns in the production schema.
    db()->prepare(
        'UPDATE seller_subscription_payments
         SET gateway_response = ?
         WHERE id = ?'
    )->execute([
        json_encode($response, JSON_UNESCAPED_SLASHES),
        (int)$paymentId,
    ]);

    header('Location: ' . $authorizationUrl, true, 303);
    exit;
} catch (Throwable $e) {
    error_log('DogoFlow Pro subscription initialization failed: ' . $e->getMessage());

    // Match on the reference (it exists from the start) so a failure can never leave the payment stuck as pending.
    try {
        SellerSubscriptionPayment::markFailed($reference, ['error' => 'Initialization failed']);
    } catch (Throwable $ignored) {
    }

    flash(
        'error',
        (defined('APP_ENV') && APP_ENV === 'production')
            ? 'We could not start the Paystack payment. Please try again.'
            : $e->getMessage()
    );
    redirect('/seller/upgrade.php');
}
