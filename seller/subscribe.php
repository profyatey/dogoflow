<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/payments/Paystack.php';
require_once DOGOFLOW_ROOT . '/core/payments/SellerSubscriptionPayment.php';

Auth::requireRole('seller');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
verify_csrf((string) ($_POST['_csrf'] ?? ''));

$userId = (int) Auth::userId();
$pdo = db();
$stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? AND role = "seller" LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || !filter_var((string) $user['email'], FILTER_VALIDATE_EMAIL)) {
    flash('error', 'A valid email address is required before you can purchase Pro.');
    redirect('/seller/upgrade.php');
}

$reference = 'DFPRO-' . $userId . '-' . bin2hex(random_bytes(12));
$callbackUrl = url('/seller/subscription-callback.php');

try {
    $stmt = $pdo->prepare(
        'INSERT INTO seller_subscription_payments
         (user_id, reference, amount, currency, gateway, status)
         VALUES (?, ?, 30.00, "GHS", "paystack", "pending")'
    );
    $stmt->execute([$userId, $reference]);

    $gateway = new Paystack();
    $response = $gateway->initialize(
        (string) $user['email'],
        SellerSubscriptionPayment::AMOUNT_MINOR,
        $reference,
        $callbackUrl,
        [
            'type' => 'seller_subscription',
            'plan' => 'pro',
            'seller_id' => $userId,
            'custom_fields' => [
                ['display_name' => 'Plan', 'variable_name' => 'plan', 'value' => 'DogoFlow Pro — 1 year'],
                ['display_name' => 'Seller ID', 'variable_name' => 'seller_id', 'value' => (string) $userId],
            ],
        ]
    );

    $data = $response['data'] ?? [];
    $authorizationUrl = (string) ($data['authorization_url'] ?? '');
    $accessCode = (string) ($data['access_code'] ?? '');
    if ($authorizationUrl === '' || !filter_var($authorizationUrl, FILTER_VALIDATE_URL)
        || !str_starts_with($authorizationUrl, 'https://')) {
        throw new RuntimeException('Paystack did not return a valid secure checkout URL.');
    }

    $pdo->prepare(
        'UPDATE seller_subscription_payments SET access_code = ? WHERE reference = ?'
    )->execute([$accessCode !== '' ? $accessCode : null, $reference]);

    header('Location: ' . $authorizationUrl, true, 303);
    exit;
} catch (Throwable $e) {
    SellerSubscriptionPayment::markFailed($pdo, $reference, ['error' => $e->getMessage()]);
    error_log('DogoFlow Pro payment initialization: ' . $e->getMessage());
    flash('error', APP_ENV === 'development'
        ? $e->getMessage()
        : 'We could not start your payment. Please try again or contact support.');
    redirect('/seller/upgrade.php');
}
