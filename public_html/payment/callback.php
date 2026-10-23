<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/payments/Paystack.php';
require_once DOGOFLOW_ROOT . '/core/payments/PaymentProcessor.php';

Auth::requireLogin();
if (Auth::role() !== 'customer') redirect(Auth::dashboardPath());

$reference = trim((string)($_GET['reference'] ?? ''));
if ($reference === '' || !preg_match('/^[A-Za-z0-9_.=-]{3,100}$/', $reference)) redirect('/customer/orders.php');

$pdo = db();
$stmt = $pdo->prepare('SELECT p.*,o.order_number,o.customer_id,o.status AS order_status FROM payments p INNER JOIN orders o ON o.id=p.order_id WHERE p.reference=? LIMIT 1');
$stmt->execute([$reference]);
$payment = $stmt->fetch();
if (!$payment || (int)$payment['customer_id'] !== (int)Auth::userId()) { http_response_code(404); exit('Payment not found.'); }

$message = null;
$success = false;
$pending = false;
try {
    if ($payment['status'] === 'success') {
        // Already confirmed (webhook or an earlier visit); no need to ask Paystack again.
        $outcome = PaymentProcessor::SUCCESS;
    } else {
        $result = (new Paystack())->verify($reference);
        $outcome = PaymentProcessor::apply($pdo, $reference, (array)($result['data'] ?? []), $result);
    }
    if ($outcome === PaymentProcessor::SUCCESS) {
        $success = true;
        $message = 'Payment verified successfully.';
    } elseif ($outcome === PaymentProcessor::PENDING) {
        $pending = true;
        $message = 'Your payment is still being confirmed. This can take a few minutes for mobile money. Check My orders shortly; your order is reserved and you do not need to pay again.';
    } elseif ($outcome === PaymentProcessor::FAILED) {
        $message = 'The payment was not completed. Your order was cancelled and reserved stock was released.';
    } else {
        $message = 'We could not match this payment to your order. Please contact support if you were charged.';
    }
} catch (Throwable $e) {
    error_log('Payment callback ' . $reference . ': ' . $e->getMessage());
    $message = APP_ENV === 'development' ? $e->getMessage() : 'We could not verify this payment yet. Please contact support if you were charged.';
}
?>
<!doctype html><html lang="en"><head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $success?'Payment successful':($pending?'Payment processing':'Payment status') ?> · DogoFlow</title><?php require __DIR__ . '/../partials/tailwind.php'; ?></head><body><main class="container" style="max-width:720px;padding:70px 20px"><div class="card" style="text-align:center;padding:44px 28px"><div style="font-size:3.4rem"><?= $success?'✅':($pending?'⏳':'⚠️') ?></div><h1 style="color:#0A1628"><?= $success?'Payment successful':($pending?'Payment processing':'Payment not completed') ?></h1><p style="color:#64748B;line-height:1.7"><?=e((string)$message)?></p><p><strong>Order:</strong> <?=e((string)$payment['order_number'])?></p><div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:24px"><a class="btn btn-primary" href="<?=e(url('/customer/orders.php'))?>">View my orders</a><a class="btn btn-outline" href="<?=e(url('/products.php'))?>">Continue shopping</a></div></div></main></body></html>
