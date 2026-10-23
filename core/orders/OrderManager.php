<?php
declare(strict_types=1);

final class OrderManager
{
    private const SELLER_TRANSITIONS = [
        'paid' => ['processing'],
        'processing' => ['shipped'],
        'shipped' => ['completed'],
    ];

    public static function addHistory(int $orderId, ?int $actorId, ?string $from, string $to, ?string $note = null): void
    {
        $stmt = db()->prepare('INSERT INTO order_status_history (order_id,actor_user_id,from_status,to_status,note) VALUES (?,?,?,?,?)');
        $stmt->execute([$orderId, $actorId, $from, $to, $note]);
    }

    public static function sellerCanTransition(string $from, string $to): bool
    {
        return in_array($to, self::SELLER_TRANSITIONS[$from] ?? [], true);
    }

    public static function recalculateOrderStatus(int $orderId): string
    {
        $stmt = db()->prepare('SELECT fulfillment_status FROM order_items WHERE order_id=? ORDER BY id');
        $stmt->execute([$orderId]);
        $statuses = array_map(static fn($r): string => (string)$r['fulfillment_status'], $stmt->fetchAll());
        if (!$statuses) return 'paid';
        if (count(array_unique($statuses)) === 1 && $statuses[0] === 'completed') return 'completed';
        if (count(array_unique($statuses)) === 1 && $statuses[0] === 'shipped') return 'shipped';
        if (in_array('processing', $statuses, true) || in_array('shipped', $statuses, true) || in_array('completed', $statuses, true)) return 'processing';
        if (count(array_unique($statuses)) === 1 && $statuses[0] === 'cancelled') return 'cancelled';
        return 'paid';
    }

    public static function recalculateAndRecord(int $orderId, ?int $actorId, ?string $note = null): string
    {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT status FROM orders WHERE id=? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) throw new RuntimeException('Order not found.');
        $from = (string)$order['status'];
        $to = self::recalculateOrderStatus($orderId);
        if ($from !== $to) {
            $pdo->prepare('UPDATE orders SET status=?,updated_at=NOW() WHERE id=?')->execute([$to, $orderId]);
            self::addHistory($orderId, $actorId, $from, $to, $note);
        }
        return $to;
    }
}
