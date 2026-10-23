<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/payments/Paystack.php';
require_once DOGOFLOW_ROOT . '/core/payments/SellerSubscriptionPayment.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function subscription_status_json(string $state, string $message = '', int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['state' => $state, 'message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

if (!Auth::check() || Auth::userId() === null) {
    subscription_status_json('error', 'Please sign in again.', 401);
}

$reference = trim((string)($_GET['reference'] ?? ''));
if (!preg_match('/^DFPRO-\d+-[A-F0-9]{16}$/', $reference)) {
    subscription_status_json('error', 'Invalid payment reference.', 400);
}

$payment = SellerSubscriptionPayment::findByReference($reference);
if (!$payment || (int)$payment['user_id'] !== (int)Auth::userId()) {
    subscription_status_json('error', 'This payment does not belong to your account.', 403);
}

if (($payment['status'] ?? '') === 'success') {
    flash('success', 'Payment verified. Your DogoFlow Pro plan is now active.');
    subscription_status_json('success');
}

try {
    // Server-to-server confirmation with Paystack (the same check a webhook would make).
    $result = (new Paystack())->verify($reference);
    $verified = (array)($result['data'] ?? []);
    $gatewayStatus = strtolower((string)($verified['status'] ?? ''));

    if (SellerSubscriptionPayment::complete($reference, $verified)) {
        flash('success', 'Payment verified. Your DogoFlow Pro plan is now active.');
        subscription_status_json('success');
    }

    if ($gatewayStatus === 'success') {
        // Paystack says it was paid, but it does not match this Pro request (amount, currency or reference).
        error_log('DogoFlow Pro subscription could not be matched [' . $reference . ']');
        subscription_status_json('failed', 'Paystack confirmed the payment, but it does not match this Pro request (amount, currency or reference). Please contact support with reference ' . $reference . '.');
    }

    if (in_array($gatewayStatus, ['failed', 'abandoned', 'reversed'], true)) {
        subscription_status_json('failed', 'Paystack did not complete this payment, so Pro was not activated.');
    }

    subscription_status_json('pending', 'Paystack is still confirming your payment.');
} catch (Throwable $e) {
    error_log('DogoFlow Pro subscription verification failed [' . $reference . ']: ' . $e->getMessage());
    SellerSubscriptionPayment::recordCheckError($reference, $e->getMessage());
    subscription_status_json('pending', 'Paystack check failed: ' . $e->getMessage() . '. Retrying…');
}
