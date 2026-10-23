<?php
declare(strict_types=1);

/** Write a non-fatal Admin audit event using the installed audit_logs schema. */
function dogoflow_admin_audit(PDO $pdo, string $action, ?string $entityType = null, ?int $entityId = null, ?array $oldValues = null, ?array $newValues = null): void {
    try {
        $actorId = null;
        if (class_exists('Auth') && method_exists('Auth', 'user')) { $user = Auth::user(); $actorId = isset($user['id']) ? (int)$user['id'] : null; }
        if (!$actorId && isset($_SESSION['user']['id'])) $actorId = (int)$_SESSION['user']['id'];
        $stmt = $pdo->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, old_values, new_values, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$actorId ?: null, $action, $entityType, $entityId, $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) : null, $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) : null, substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null]);
    } catch (Throwable $e) { error_log('DogoFlow admin audit write failed: '.$e->getMessage()); }
}
