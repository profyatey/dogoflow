<?php
declare(strict_types=1);

/** Lightweight, privacy-conscious marketplace view tracking. */
final class MarketplaceAnalytics
{
    public static function track(PDO $pdo, int $sellerProfileId, ?int $productId, string $eventType): void
    {
        if ($sellerProfileId < 1 || !in_array($eventType, ['store_view', 'product_view'], true)) {
            return;
        }
        if ($eventType === 'product_view' && (!$productId || $productId < 1)) {
            return;
        }

        $day = date('Y-m-d');
        $key = $eventType . ':' . $sellerProfileId . ':' . ($productId ?? 0) . ':' . $day;
        $seen = $_SESSION['_marketplace_view_events'] ?? [];
        if (!is_array($seen)) {
            $seen = [];
        }
        if (isset($seen[$key])) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO marketplace_view_events (seller_profile_id, product_id, event_type, created_at)
                 VALUES (?, ?, ?, NOW())'
            );
            $stmt->execute([$sellerProfileId, $productId, $eventType]);
            $seen[$key] = 1;
            if (count($seen) > 500) {
                $seen = array_slice($seen, -400, null, true);
            }
            $_SESSION['_marketplace_view_events'] = $seen;
        } catch (Throwable $e) {
            // Analytics must not break public browsing if the optional migration is not present.
            error_log('DogoFlow marketplace view tracking unavailable: ' . $e->getMessage());
        }
    }
}
