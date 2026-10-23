<?php
declare(strict_types=1);

/**
 * Affiliate reward lifecycle for referred sellers (table: affiliate_rewards).
 *
 *   approved seller        -> reward created as PENDING   (awaiting first payment)
 *   first Pro payment ok   -> PENDING becomes AVAILABLE
 *   rejected / suspended   -> PENDING or AVAILABLE becomes REVERSED
 *   reward already PAID    -> left alone; admin recovers the money manually
 *
 * All methods expect to run inside the caller's open transaction and are safe to repeat.
 */
final class AffiliateRewards
{
    public const AMOUNT = 10.00;

    /** Create (or re-open) the reward for a referral once its seller is approved. */
    public static function holdForSeller(PDO $pdo, int $referralId, int $affiliateId): void
    {
        $check = $pdo->prepare('SELECT id, status, paid_at FROM affiliate_rewards WHERE referral_id = ? LIMIT 1 FOR UPDATE');
        $check->execute([$referralId]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Re-approved after a rejection or suspension: restore the reward only if it was never paid out.
            if ($existing['status'] === 'reversed' && $existing['paid_at'] === null) {
                $pdo->prepare("UPDATE affiliate_rewards SET status = 'pending' WHERE id = ?")
                    ->execute([(int) $existing['id']]);
            }
            return;
        }

        $pdo->prepare(
            "INSERT INTO affiliate_rewards (affiliate_id, referral_id, amount, status, reference)
             VALUES (?, ?, ?, 'pending', ?)"
        )->execute([$affiliateId, $referralId, self::AMOUNT, 'DFAR-' . strtoupper(bin2hex(random_bytes(8)))]);
    }

    /** Called once a referred seller's Pro payment succeeds. Only pending rewards move, so renewals do nothing. */
    public static function releaseForSeller(PDO $pdo, int $sellerUserId): void
    {
        $pdo->prepare(
            "UPDATE affiliate_rewards ar
             JOIN affiliate_referrals r ON r.id = ar.referral_id
             SET ar.status = 'available'
             WHERE r.referred_user_id = ? AND ar.status = 'pending'"
        )->execute([$sellerUserId]);
    }

    /** Called when an admin rejects or suspends a seller. Unpaid rewards are cancelled. */
    public static function reverseForSeller(PDO $pdo, int $sellerUserId): void
    {
        $pdo->prepare(
            "UPDATE affiliate_rewards ar
             JOIN affiliate_referrals r ON r.id = ar.referral_id
             SET ar.status = 'reversed'
             WHERE r.referred_user_id = ? AND ar.status IN ('pending', 'available')"
        )->execute([$sellerUserId]);
    }
}
