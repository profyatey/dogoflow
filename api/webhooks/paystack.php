<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/payments/SellerSubscriptionPayment.php';

$payload = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$secret = trim((string)(getenv('DOGOFLOW_PAYSTACK_SECRET') ?: ''));
if ($secret === '' || $signature === '' || !hash_equals(hash_hmac('sha512', $payload, $secret), $signature)) { http_response_code(401); exit('Unauthorized'); }
$data = json_decode($payload, true);
if (!is_array($data)) { http_response_code(400); exit('Invalid JSON'); }
if (($data['event'] ?? '') !== 'charge.success') { http_response_code(200); exit('Ignored'); }
$reference = (string)($data['data']['reference'] ?? '');
$amountMinor = (int)($data['data']['amount'] ?? 0);
if ($reference === '' || $amountMinor <= 0) { http_response_code(400); exit('Invalid event'); }
$pdo = db();
try {
    // Pro seller subscriptions are independent of customer product orders.
    $subscriptionStmt = $pdo->prepare(
        'SELECT id FROM seller_subscription_payments WHERE reference = ? LIMIT 1'
    );
    $subscriptionStmt->execute([$reference]);
    if ($subscriptionStmt->fetchColumn()) {
        SellerSubscriptionPayment::complete(
            $pdo,
            $reference,
            $amountMinor,
            (string) ($data['data']['currency'] ?? ''),
            (int) ($data['data']['id'] ?? 0),
            $data
        );
        http_response_code(200);
        echo 'Subscription payment processed';
        exit;
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT p.*,o.status AS order_status FROM payments p INNER JOIN orders o ON o.id=p.order_id WHERE p.reference=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$reference]);
    $payment = $stmt->fetch();
    if (!$payment) { $pdo->commit(); http_response_code(200); exit('Unknown reference'); }
    if ($payment['status'] === 'success') { $pdo->commit(); http_response_code(200); exit('Already processed'); }
    $expectedMinor = (int)round((float)$payment['amount'] * 100);
    if ($amountMinor !== $expectedMinor) { $pdo->rollBack(); http_response_code(400); exit('Amount mismatch'); }
    $pdo->prepare('UPDATE payments SET status="success",gateway_transaction_id=?,gateway_response=?,verified_at=NOW() WHERE id=?')
        ->execute([(int)($data['data']['id'] ?? 0), json_encode($data, JSON_UNESCAPED_SLASHES), (int)$payment['id']]);
    $pdo->prepare('UPDATE orders SET status="paid",paid_at=NOW() WHERE id=? AND status="pending_payment"')->execute([(int)$payment['order_id']]);
    $pdo->commit(); http_response_code(200); echo 'OK';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Paystack webhook: ' . $e->getMessage());
    http_response_code(500); echo 'Server error';
}
