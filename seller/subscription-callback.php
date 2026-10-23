<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/payments/Paystack.php';
require_once DOGOFLOW_ROOT . '/core/payments/SellerSubscriptionPayment.php';

$reference = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? ''));
if ($reference === '' || !preg_match('/^DFPRO-[0-9]+-[a-f0-9]{24}$/', $reference)) {
    http_response_code(400);
    exit('Invalid subscription payment reference.');
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT p.*, u.name FROM seller_subscription_payments p
     INNER JOIN users u ON u.id = p.user_id
     WHERE p.reference = ? LIMIT 1'
);
$stmt->execute([$reference]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) {
    http_response_code(404);
    exit('Subscription payment not found.');
}

$success = false;
$message = 'We could not verify this payment yet. If you were charged, please contact support with your payment reference.';
try {
    $result = (new Paystack())->verify($reference);
    $data = $result['data'] ?? [];
    $gatewayReference = (string) ($data['reference'] ?? '');
    $gatewayStatus = (string) ($data['status'] ?? '');
    $amountMinor = (int) ($data['amount'] ?? 0);
    $currency = (string) ($data['currency'] ?? '');

    if ($gatewayReference !== $reference) {
        throw new RuntimeException('Paystack returned a different transaction reference.');
    }

    if ($gatewayStatus === 'success') {
        SellerSubscriptionPayment::complete(
            $pdo,
            $reference,
            $amountMinor,
            $currency,
            (int) ($data['id'] ?? 0),
            $result
        );
        $success = true;
        $message = 'Your Pro subscription is active for one year. You can return to your seller dashboard.';
    } else {
        SellerSubscriptionPayment::markFailed($pdo, $reference, $result);
        $message = 'The payment was not completed. Your seller plan has not been upgraded.';
    }
} catch (Throwable $e) {
    error_log('DogoFlow subscription callback: ' . $e->getMessage());
    if (APP_ENV === 'development') {
        $message = $e->getMessage();
    }
}

$stmt = $pdo->prepare('SELECT status FROM seller_subscription_payments WHERE reference = ? LIMIT 1');
$stmt->execute([$reference]);
$paymentStatus = (string) $stmt->fetchColumn();
if ($paymentStatus === 'success') {
    $success = true;
    $message = 'Your Pro subscription is active for one year. You can return to your seller dashboard.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $success ? 'Pro activated' : 'Payment status' ?> · DogoFlow</title>
    <?php require __DIR__ . '/../partials/tailwind.php'; ?>
</head>
<body class="seller-page">
<main class="container-wide seller-shell-df" style="max-width:760px;padding-top:64px">
    <section class="card" style="padding:36px 28px;text-align:center">
        <div style="font-size:3rem"><?= $success ? '✅' : '⚠️' ?></div>
        <span class="eyebrow-df">Seller subscription</span>
        <h1><?= $success ? 'Pro plan activated' : 'Payment not confirmed' ?></h1>
        <p><?= e($message) ?></p>
        <p><strong>Reference:</strong> <?= e($reference) ?></p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:24px">
            <a class="btn-df primary" href="<?= e(url('/seller/upgrade.php')) ?>">View plans</a>
            <a class="btn-df" href="<?= e(url('/seller/dashboard.php')) ?>">Seller dashboard</a>
        </div>
    </section>
</main>
</body>
</html>
