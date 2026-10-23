<?php
declare(strict_types=1);

final class SellerPlan
{
    public static function get(int $sellerId, ?PDO $connection = null): array
    {
        $connection ??= db();
        $stmt = $connection->prepare(
            'SELECT seller_code, plan, photo_limit, subscription_started_at, subscription_expires_at
             FROM seller_profiles
             WHERE user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$sellerId]);
        $profile = $stmt->fetch() ?: [];

        $plan = ($profile['plan'] ?? 'free') === 'pro' ? 'pro' : 'free';
        $expiresAt = $profile['subscription_expires_at'] ?? null;
        $isPro = $plan === 'pro' && $expiresAt !== null && strtotime((string) $expiresAt) > time();
        $limit = $isPro ? 10 : 5; // product slots: Free 5, Pro 10
        $photosPerProduct = $isPro ? 5 : 3; // photos per product incl. the main photo: Free 3 (1 + 2), Pro 5 (1 + 4)
        $extraPhotos = $photosPerProduct - 1;

        // Count gallery rows and legacy products.image paths not represented
        // in product_images. This counts old primary images exactly once.
        $countStmt = $connection->prepare(
            'SELECT
                (SELECT COUNT(*)
                 FROM product_images pi
                 INNER JOIN products p ON p.id = pi.product_id
                 WHERE p.seller_id = ?) +
                (SELECT COUNT(*)
                 FROM products legacy_p
                 WHERE legacy_p.seller_id = ?
                   AND legacy_p.image IS NOT NULL
                   AND legacy_p.image <> \'\'
                   AND NOT EXISTS (
                       SELECT 1 FROM product_images legacy_pi
                       WHERE legacy_pi.product_id = legacy_p.id
                         AND legacy_pi.image_path = legacy_p.image
                   )) AS photo_count'
        );
        $countStmt->execute([$sellerId, $sellerId]);
        $photoCount = (int) $countStmt->fetchColumn();

        // Each product uses one slot, regardless of how many photos it has.
        $slotStmt = $connection->prepare('SELECT COUNT(*) FROM products WHERE seller_id = ?');
        $slotStmt->execute([$sellerId]);
        $used = (int) $slotStmt->fetchColumn();

        return [
            'seller_code' => (string) ($profile['seller_code'] ?? ''),
            'plan' => $isPro ? 'pro' : 'free',
            'photo_limit' => $photosPerProduct,
            'product_limit' => $limit,
            'slot_limit' => $limit,
            'extra_photos' => $extraPhotos,
            'photos_per_product' => $photosPerProduct,
            'slots_used' => $used,
            'photo_count' => $photoCount,
            'remaining' => max(0, $limit - $used),
            'subscription_started_at' => $profile['subscription_started_at'] ?? null,
            'subscription_expires_at' => $isPro ? $expiresAt : null,
            'is_pro' => $isPro,
        ];
    }

    /** Check the per-product image allowance (not the seller's remaining product slots). */
    public static function canUpload(int $sellerId, int $numberOfPhotos = 1): bool
    {
        $plan = self::get($sellerId);
        return $numberOfPhotos >= 0 && $numberOfPhotos <= (int) $plan['photos_per_product'];
    }
}
