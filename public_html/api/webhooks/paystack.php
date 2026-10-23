<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/payments/Paystack.php';
require_once DOGOFLOW_ROOT . '/core/payments/PaymentProcessor.php';
require_once DOGOFLOW_ROOT . '/core/payments/SellerSubscriptionPayment.php';

$payload = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
if (!Paystack::validSignature($payload, (string)$signature)) { http_response_code(401); exit('Unauthorized'); }

$data = json_decode($payload, true);
if (!is_array($data)) { http_response_code(400); exit('Invalid JSON'); }
if (($data['event'] ?? '') !== 'charge.success') { http_response_code(200); exit('Ignored'); }

$gateway = (array)($data['data'] ?? []);
$reference = (string)($gateway['reference'] ?? '');
if ($reference === '' || (int)($gateway['amount'] ?? 0) <= 0) { http_response_code(400); exit('Invalid event'); }

try {
    if (str_starts_with($reference, 'DFPRO-')) {
        // Seller Pro subscription. Unknown or mismatching payments are logged and acknowledged so Paystack does not retry forever.
        $ok = SellerSubscriptionPayment::complete($reference, $gateway);
        http_response_code(200);
        echo $ok ? 'OK' : 'Subscription payment not applied';
        exit;
    }

    // Marketplace order payment.
    $result = PaymentProcessor::apply(db(), $reference, $gateway, $data);
    http_response_code(200);
    echo $result === PaymentProcessor::SUCCESS ? 'OK' : ucfirst($result);
} catch (Throwable $e) {
    error_log('Paystack webhook: ' . $e->getMessage());
    http_response_code(500);
    echo 'Server error';
}
