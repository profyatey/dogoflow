<?php
declare(strict_types=1);

require_once __DIR__ . '/../affiliates/AffiliateRewards.php';

/**
 * Seller Pro subscription payments (table: seller_subscription_payments).
 * Columns used: id, user_id, reference, access_code, amount, currency, gateway,
 * status, gateway_transaction_id, gateway_response, verified_at, created_at.
 * Every query below uses positional placeholders only, and each value list
 * matches its placeholder count exactly.
 */
final class SellerSubscriptionPayment
{
    public const PRO_AMOUNT = 30.00;
    public const PRO_AMOUNT_MINOR = 3000;
    public const AMOUNT_MINOR = 3000; // legacy alias
    public const CURRENCY = 'GHS';
    public const PRO_PHOTO_LIMIT = 5;

    /** Create the pending payment row and return its id. */
    public static function createPending(int $userId, string $reference): int
    {
        $stmt = db()->prepare(
            'INSERT INTO seller_subscription_payments (user_id, reference, amount, currency, status)
             VALUES (?, ?, ?, ?, "pending")'
        );
        $stmt->execute([$userId, $reference, self::PRO_AMOUNT, self::CURRENCY]);

        return (int) db()->lastInsertId();
    }

    public static function findByReference(string $reference): ?array
    {
        $stmt = db()->prepare('SELECT * FROM seller_subscription_payments WHERE reference = ? LIMIT 1');
        $stmt->execute([$reference]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** Keeps the last verification error on a payment that is still pending. Never changes its status. */
    public static function recordCheckError(string $reference, string $message): void
    {
        db()->prepare(
            "UPDATE seller_subscription_payments
             SET gateway_response = ?
             WHERE reference = ? AND status = 'pending'"
        )->execute([
            json_encode(['last_check_error' => $message, 'checked_at' => date('c')], JSON_UNESCAPED_SLASHES),
            $reference,
        ]);
    }

    /** Mark a still-pending payment as failed. Never touches a successful payment. */
    public static function markFailed(string $reference, array $payload = []): void
    {
        db()->prepare(
            'UPDATE seller_subscription_payments
             SET status = "failed", gateway_response = ?
             WHERE reference = ? AND status = "pending"'
        )->execute([json_encode($payload, JSON_UNESCAPED_SLASHES), $reference]);
    }

    /**
     * Apply a verified Paystack transaction ("data" object).
     * Returns true when the payment is (or already was) successful and Pro is active.
     * Safe to call repeatedly (callback, webhook): the seller is only extended once.
     */
    public static function complete(string $reference, array $gateway): bool
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM seller_subscription_payments WHERE reference = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$reference]);
            $payment = $stmt->fetch();

            if (!$payment) {
                $pdo->commit();
                return false;
            }

            if ($payment['status'] === 'success') {
                $pdo->commit();
                return true; // already activated
            }

            $status = strtolower((string) ($gateway['status'] ?? ''));

            if ($status !== 'success') {
                // Only final failures close the payment. Pending/ongoing/processing stay pending.
                if (in_array($status, ['failed', 'abandoned', 'reversed'], true)) {
                    $pdo->prepare(
                        'UPDATE seller_subscription_payments
                         SET status = "failed", gateway_response = ?
                         WHERE id = ? AND status = "pending"'
                    )->execute([json_encode($gateway, JSON_UNESCAPED_SLASHES), (int) $payment['id']]);
                }
                $pdo->commit();
                return false;
            }

            $expectedMinor = (int) round((float) $payment['amount'] * 100);
            $amountMinor = (int) ($gateway['amount'] ?? 0);
            $currency = strtoupper((string) ($gateway['currency'] ?? $payment['currency']));
            $gatewayRef = (string) ($gateway['reference'] ?? $reference);

            if (
                $amountMinor !== $expectedMinor
                || $expectedMinor !== self::PRO_AMOUNT_MINOR
                || $currency !== strtoupper((string) $payment['currency'])
                || $gatewayRef !== $reference
            ) {
                error_log("Pro subscription mismatch for {$reference}: amount {$amountMinor} vs {$expectedMinor}, currency {$currency}");
                $pdo->commit();
                return false;
            }

            $pdo->prepare(
                'UPDATE seller_subscription_payments
                 SET status = "success", gateway_transaction_id = ?, gateway_response = ?, paid_at = NOW()
                 WHERE id = ?'
            )->execute([
                ((int) ($gateway['id'] ?? 0)) ?: null,
                json_encode($gateway, JSON_UNESCAPED_SLASHES),
                (int) $payment['id'],
            ]);

            // Renewing while still active extends from the current expiry instead of losing paid time.
            $activate = $pdo->prepare(
                'UPDATE seller_profiles
                 SET plan = "pro",
                     photo_limit = ?,
                     subscription_started_at = IF(subscription_expires_at IS NOT NULL AND subscription_expires_at > NOW(), subscription_started_at, NOW()),
                     subscription_expires_at = DATE_ADD(IF(subscription_expires_at IS NOT NULL AND subscription_expires_at > NOW(), subscription_expires_at, NOW()), INTERVAL 1 YEAR)
                 WHERE user_id = ?'
            );
            $activate->execute([self::PRO_PHOTO_LIMIT, (int) $payment['user_id']]);

            if ($activate->rowCount() < 1) {
                throw new RuntimeException('No seller profile found to activate Pro for user ' . (int) $payment['user_id']);
            }

            // First successful subscription releases the affiliate reward (pending -> available).
            AffiliateRewards::releaseForSeller($pdo, (int) $payment['user_id']);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
