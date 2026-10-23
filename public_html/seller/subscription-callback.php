<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/payments/SellerSubscriptionPayment.php';

Auth::requireApprovedSeller();

$reference = trim((string)($_GET['reference'] ?? ''));
if ($reference === '' || !preg_match('/^DFPRO-\d+-[A-F0-9]{16}$/', $reference)) {
    flash('error', 'Invalid Paystack payment reference.');
    redirect('/seller/upgrade.php');
}

$payment = SellerSubscriptionPayment::findByReference($reference);
if (!$payment || (int)$payment['user_id'] !== (int)Auth::userId()) {
    flash('error', 'This payment does not belong to your seller account.');
    redirect('/seller/upgrade.php');
}

if (($payment['status'] ?? '') === 'success') {
    flash('success', 'Your DogoFlow Pro plan is active.');
    redirect('/seller/upgrade.php');
}

// Confirmation happens on the processing page, which keeps checking with Paystack.
redirect('/seller/subscription-processing.php?reference=' . rawurlencode($reference));
