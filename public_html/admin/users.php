<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('admin');

$pdo = db();
$active = 'users';
$error = flash('error');
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['action'] ?? 'update_status');
    $id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        flash('error', 'Select a valid account.');
    } elseif ((int) $id === (int) Auth::userId()) {
        flash('error', 'You cannot change or remove your own administrator account here.');
    } elseif ($action === 'delete_unverified') {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id, name, email, role, status, email_verified_at FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([(int) $id]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                throw new RuntimeException('That account no longer exists. Refresh the page and try again.');
            }
            if ($target['role'] === 'admin') {
                throw new RuntimeException('Administrator accounts cannot be removed from this page.');
            }
            if ($target['email_verified_at'] !== null) {
                throw new RuntimeException('Only accounts that have not completed email verification can be removed here.');
            }

            // This follows the established DogoFlow cleanup flow for abandoned, unverified
            // accounts. All changes remain inside one transaction and roll back on failure.
            // Check table/column existence first because hosting schemas can lag behind migrations.
            $tableHasColumn = static function (PDO $pdo, string $table, string $column): bool {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
                );
                $stmt->execute([$table, $column]);
                return (int) $stmt->fetchColumn() > 0;
            };
            $tableExists = static function (PDO $pdo, string $table): bool {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
                );
                $stmt->execute([$table]);
                return (int) $stmt->fetchColumn() > 0;
            };
            $deleteForUser = static function (PDO $pdo, string $table, string $column, int $userId) use ($tableHasColumn, $tableExists): void {
                if ($tableExists($pdo, $table) && $tableHasColumn($pdo, $table, $column)) {
                    $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE `{$column}` = ?");
                    $stmt->execute([$userId]);
                }
            };

            // Remove seller-owned catalogue data first, matching the manual cleanup workflow.
            $deleteForUser($pdo, 'products', 'seller_id', (int) $id);

            // Remove order line items before customer orders when the schema does not cascade.
            if ($tableExists($pdo, 'orders') && $tableHasColumn($pdo, 'orders', 'customer_id')) {
                if ($tableExists($pdo, 'order_items') && $tableHasColumn($pdo, 'order_items', 'order_id')) {
                    $pdo->prepare('DELETE oi FROM `order_items` oi INNER JOIN `orders` o ON o.id = oi.order_id WHERE o.customer_id = ?')->execute([(int) $id]);
                }
                $deleteForUser($pdo, 'orders', 'customer_id', (int) $id);
            }

            // Remove dependent account records explicitly; cascades may still clean profile/token rows.
            $deleteForUser($pdo, 'notifications', 'user_id', (int) $id);
            $deleteForUser($pdo, 'wallet_transactions', 'user_id', (int) $id);
            $deleteForUser($pdo, 'withdrawals', 'user_id', (int) $id);
            $deleteForUser($pdo, 'seller_subscription_payments', 'user_id', (int) $id);

            // Preserve a durable audit record before deleting the account.
            if ($tableExists($pdo, 'audit_logs')) {
                $auditColumns = ['actor_user_id','action','entity_type','entity_id','old_values','new_values','ip_address'];
                $hasAuditSchema = true;
                foreach ($auditColumns as $column) {
                    if (!$tableHasColumn($pdo, 'audit_logs', $column)) { $hasAuditSchema = false; break; }
                }
                if ($hasAuditSchema) {
                    $audit = $pdo->prepare(
                        'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, old_values, new_values, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $audit->execute([
                        (int) Auth::userId(),
                        'delete_unverified_account',
                        'user',
                        (int) $id,
                        json_encode(['name' => $target['name'], 'email' => $target['email'], 'role' => $target['role'], 'status' => $target['status'], 'email_verified_at' => null], JSON_UNESCAPED_SLASHES),
                        json_encode(['deleted' => true], JSON_UNESCAPED_SLASHES),
                        substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                    ]);
                }
            }

            $deleteUserStmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $deleteUserStmt->execute([(int) $id]);
            if ($deleteUserStmt->rowCount() !== 1) {
                throw new RuntimeException('The account was not deleted. Refresh the page and try again.');
            }
            $pdo->commit();
            flash('success', 'Unverified account and its linked cleanup records were removed.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Admin unverified-account deletion failed: ' . $e->getMessage());
            $message = $e instanceof RuntimeException ? $e->getMessage() : 'The account could not be removed. The transaction was rolled back. Check the hosting PHP error log for the exact database constraint.';
            flash('error', $message);
        }
    } elseif ($action === 'update_status') {
        $status = (string) ($_POST['status'] ?? '');
        $allowed = ['active', 'pending', 'suspended', 'blocked'];
        if (!in_array($status, $allowed, true)) {
            flash('error', 'Choose a valid account status.');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ? AND role <> \'admin\'');
            $stmt->execute([$status, (int) $id]);
            flash('success', 'Account status updated.');
        }
    }

    header('Location: ' . url('/admin/users.php'), true, 303);
    exit;
}

$users = $pdo->query('SELECT id, name, email, phone, role, status, email_verified_at, created_at FROM users ORDER BY id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Users';
$pageDescription = 'Manage customer, seller, affiliate and administrator accounts';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Users · DogoFlow Admin</title>
    <link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>">
    <style>
        .users-actions{display:flex;flex-wrap:wrap;gap:.45rem;align-items:center;min-width:220px}
        .users-actions form{display:inline-flex;gap:.35rem;align-items:center;margin:0}
        .users-actions select{max-width:125px}
        .verify-badge{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:.28rem .55rem;font-size:.72rem;font-weight:700;white-space:nowrap}
        .verify-badge.verified{background:#dcfce7;color:#166534}
        .verify-badge.unverified{background:#fff7ed;color:#9a3412}
        .btn-df.danger{background:#fff;color:#b42318;border:1px solid #fecaca}
        .btn-df.danger:hover{background:#fef2f2}
        .user-identity small{display:block;color:#64748b;margin-top:.2rem}
        @media(max-width:760px){.users-actions{min-width:190px}.users-actions form:first-child{flex-wrap:wrap}}
    </style>
</head>
<body class="admin-shell">
<?php require __DIR__ . '/../partials/admin_sidebar.php'; ?>
<section class="admin-main">
    <?php require __DIR__ . '/../partials/admin_topbar.php'; ?>
    <main class="admin-content">
        <div class="page-heading" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><div><h1>Users</h1><p>Manage account access and remove abandoned, unverified registrations.</p></div><a href="<?= e(url('/admin/delete-accounts.php')) ?>" style="display:inline-flex;align-items:center;justify-content:center;width:auto!important;min-height:42px;padding:0 20px;border-radius:10px;background:#DC2626;color:#fff;font-size:14px;font-weight:700;text-decoration:none;box-shadow:0 8px 18px rgba(220,38,38,.25);white-space:nowrap">Delete accounts</a></div>
        <?php if ($error): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success" role="status"><?= e($success) ?></div><?php endif; ?>
        <div class="toolbar-df"><div class="search-df"><span aria-hidden="true">⌕</span><input id="tableSearch" type="search" placeholder="Search name, email, role or status…" autocomplete="off"></div></div>
        <article class="panel-df">
            <div class="table-wrap-df"><table class="table-df" id="dataTable">
                <thead><tr><th>User</th><th>Phone</th><th>Role</th><th>Account status</th><th>Email verification</th><th>Joined</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): $isUnverified = empty($u['email_verified_at']); ?>
                    <tr>
                        <td class="user-identity"><strong><?= e((string) $u['name']) ?></strong><small><?= e((string) $u['email']) ?></small></td>
                        <td><?= e((string) ($u['phone'] ?: '—')) ?></td>
                        <td><span class="badge-df info"><?= e((string) $u['role']) ?></span></td>
                        <td><span class="badge-df <?= $u['status'] === 'active' ? 'success' : (in_array($u['status'], ['blocked','suspended'], true) ? 'danger' : 'warning') ?>"><?= e((string) $u['status']) ?></span></td>
                        <td><span class="verify-badge <?= $isUnverified ? 'unverified' : 'verified' ?>"><?= $isUnverified ? 'Not verified' : 'Verified' ?></span></td>
                        <td><?= e(date('d M Y', strtotime((string) $u['created_at']))) ?></td>
                        <td>
                            <?php if ((int) $u['id'] === (int) Auth::userId() || $u['role'] === 'admin'): ?>
                                <span style="font-size:.78rem;color:#64748b">Protected account</span>
                            <?php else: ?>
                                <div class="users-actions">
                                    <form method="post">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <select name="status" aria-label="Status for <?= e((string) $u['email']) ?>">
                                            <?php foreach (['active','pending','suspended','blocked'] as $s): ?><option value="<?= $s ?>" <?= $u['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                                        </select>
                                        <button class="btn-df secondary" type="submit">Update</button>
                                    </form>
                                    <?php if ($isUnverified): ?>
                                        <form method="post" class="delete-user-form" data-user-name="<?= e((string) $u['name']) ?>" data-user-email="<?= e((string) $u['email']) ?>">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_unverified">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button class="btn-df danger" type="submit">Remove account</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?><tr><td colspan="7"><div class="empty-df">No users found.</div></td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </article>
    </main>
</section>
<script>
(function(){
    const search=document.getElementById('tableSearch');
    search?.addEventListener('input',function(){const q=this.value.toLowerCase();document.querySelectorAll('#dataTable tbody tr').forEach(row=>row.style.display=row.innerText.toLowerCase().includes(q)?'':'none');});
    document.querySelectorAll('.delete-user-form').forEach(form=>form.addEventListener('submit',function(event){
        const name=this.dataset.userName||'this account';
        const email=this.dataset.userEmail||'';
        if(!window.confirm('Remove this unverified account?\n\n'+name+'\n'+email+'\n\nThis permanently deletes the unverified account and associated products, orders, notifications and wallet/payout/subscription records. This cannot be undone.')) event.preventDefault();
    }));
})();
</script>
</body>
</html>
