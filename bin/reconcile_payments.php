<?php
declare(strict_types=1);
/**
 * Cron: php bin/reconcile_payments.php   (run every 10-15 minutes)
 * - Confirms payments the customer paid but never returned from (and missed webhooks).
 * - Cancels abandoned checkouts after 30 minutes and releases their reserved stock.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/payments/Paystack.php';
require_once DOGOFLOW_ROOT . '/core/payments/PaymentProcessor.php';

$pdo = db();
$paystack = new Paystack();
$rows = $pdo->query('SELECT reference, created_at FROM payments WHERE status="pending" AND gateway="paystack" AND created_at < (NOW() - INTERVAL 5 MINUTE) ORDER BY id ASC LIMIT 100')->fetchAll();
$counts = ['success' => 0, 'failed' => 0, 'pending' => 0, 'errors' => 0];
foreach ($rows as $row) {
    $reference = (string)$row['reference'];
    $old = strtotime((string)$row['created_at']) < time() - 1800;
    try {
        try {
            $result = $paystack->verify($reference);
            $gateway = (array)($result['data'] ?? []);
        } catch (RuntimeException $e) {
            // Paystack never saw this reference (initialize failed or the page was never opened).
            if (!$old || stripos($e->getMessage(), 'not found') === false) throw $e;
            $gateway = ['status' => 'abandoned', 'reference' => $reference];
            $result = ['status' => false, 'message' => $e->getMessage()];
        }
        if ($old && PaymentProcessor::outcome((string)($gateway['status'] ?? '')) === PaymentProcessor::PENDING) {
            $gateway['status'] = 'abandoned'; // still unpaid after 30 minutes
        }
        $out = PaymentProcessor::apply($pdo, $reference, $gateway, $result);
        $counts[in_array($out, ['success', 'failed', 'pending'], true) ? $out : 'errors']++;
    } catch (Throwable $e) {
        $counts['errors']++;
        fwrite(STDERR, "{$reference}: " . $e->getMessage() . "\n");
    }
}
printf("Checked %d pending payment(s): %d paid, %d cancelled, %d still pending, %d errors.\n", count($rows), $counts['success'], $counts['failed'], $counts['pending'], $counts['errors']);
