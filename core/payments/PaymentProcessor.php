<?php
declare(strict_types=1);

/**
 * Single place that turns a Paystack result into order/payment state.
 * Used by the browser callback, the signed webhook and the reconcile cron so
 * all three behave identically and can safely run more than once per payment.
 */
final class PaymentProcessor {
    public const SUCCESS = 'success';
    public const FAILED = 'failed';
    public const PENDING = 'pending';
    public const UNKNOWN = 'unknown';
    public const MISMATCH = 'mismatch';

    /** Map a Paystack transaction status to a final outcome. */
    public static function outcome(string $gatewayStatus): string {
        return match (strtolower($gatewayStatus)) {
            'success' => self::SUCCESS,
            'failed', 'abandoned', 'reversed' => self::FAILED,
            default => self::PENDING, // ongoing, pending, processing, queued: not final yet
        };
    }

    /**
     * @param array $gateway Paystack "data" object (status, amount, currency, reference, id)
     * @return string one of the class constants
     */
    public static function apply(PDO $pdo, string $reference, array $gateway, ?array $rawPayload = null): string {
        $outcome = self::outcome((string)($gateway['status'] ?? ''));
        $startedHere = !$pdo->inTransaction();
        if ($startedHere) $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT p.*, o.status AS order_status FROM payments p INNER JOIN orders o ON o.id=p.order_id WHERE p.reference=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$reference]);
            $payment = $stmt->fetch();
            if (!$payment) { if ($startedHere) $pdo->commit(); return self::UNKNOWN; }

            // Already settled: nothing more to do (idempotent).
            if ($payment['status'] === 'success') { if ($startedHere) $pdo->commit(); return self::SUCCESS; }

            if ($outcome === self::SUCCESS) {
                $expectedMinor = (int)round((float)$payment['amount'] * 100);
                $amountMinor = (int)($gateway['amount'] ?? 0);
                $currency = strtoupper((string)($gateway['currency'] ?? $payment['currency']));
                $gatewayRef = (string)($gateway['reference'] ?? $reference);
                if ($amountMinor !== $expectedMinor || $currency !== strtoupper((string)$payment['currency']) || $gatewayRef !== $reference) {
                    error_log("Paystack mismatch for {$reference}: amount {$amountMinor} vs {$expectedMinor}, currency {$currency}");
                    if ($startedHere) $pdo->commit();
                    return self::MISMATCH;
                }
                $pdo->prepare('UPDATE payments SET status="success",gateway_transaction_id=?,gateway_response=?,verified_at=NOW() WHERE id=?')
                    ->execute([(int)($gateway['id'] ?? 0) ?: null, json_encode($rawPayload ?? ['data' => $gateway], JSON_UNESCAPED_SLASHES), (int)$payment['id']]);

                $orderId = (int)$payment['order_id'];
                $paid = $pdo->prepare('UPDATE orders SET status="paid",paid_at=NOW() WHERE id=? AND status="pending_payment"');
                $paid->execute([$orderId]);
                if ($paid->rowCount() === 0 && $payment['order_status'] === 'cancelled') {
                    // The customer really paid after we released the order (late MoMo/abandoned page). Honour the payment.
                    $items = $pdo->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=?');
                    $items->execute([$orderId]);
                    foreach ($items->fetchAll() as $row) {
                        // stock is INT UNSIGNED: "stock-qty" would error (not go negative) when stock is short, so clamp with IF().
                        $pdo->prepare('UPDATE products SET stock=IF(stock>=?, stock-?, 0) WHERE id=?')->execute([(int)$row['quantity'], (int)$row['quantity'], (int)$row['product_id']]);
                    }
                    $pdo->prepare('UPDATE orders SET status="paid",paid_at=NOW() WHERE id=? AND status="cancelled"')->execute([$orderId]);
                    error_log("Paystack: late payment {$reference} reactivated cancelled order {$orderId}; check stock.");
                }
                if ($startedHere) $pdo->commit();
                return self::SUCCESS;
            }

            if ($outcome === self::FAILED) {
                $pdo->prepare('UPDATE payments SET status="failed",gateway_response=?,verified_at=NOW() WHERE id=? AND status="pending"')
                    ->execute([json_encode($rawPayload ?? ['data' => $gateway], JSON_UNESCAPED_SLASHES), (int)$payment['id']]);
                // Cancel and release stock exactly once: only when this call moves the order out of pending_payment.
                $cancel = $pdo->prepare('UPDATE orders SET status="cancelled" WHERE id=? AND status="pending_payment"');
                $cancel->execute([(int)$payment['order_id']]);
                if ($cancel->rowCount() === 1) {
                    $items = $pdo->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=?');
                    $items->execute([(int)$payment['order_id']]);
                    foreach ($items->fetchAll() as $row) {
                        $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?')->execute([(int)$row['quantity'], (int)$row['product_id']]);
                    }
                }
                if ($startedHere) $pdo->commit();
                return self::FAILED;
            }

            if ($startedHere) $pdo->commit();
            return self::PENDING;
        } catch (Throwable $e) {
            if ($startedHere && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
