<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('admin');

$pdo = db();

/** Tables that hold records tied to an account. Checked and removed in this order. */
const RELATED_TABLES = [
    ['products', 'seller_id', 'Products'],
    ['notifications', 'user_id', 'Notifications'],
    ['wallet_transactions', 'user_id', 'Wallet entries'],
    ['withdrawals', 'user_id', 'Payout requests'],
    ['seller_subscription_payments', 'user_id', 'Pro payments'],
    ['affiliate_referrals', 'referred_user_id', 'Affiliate referrals'],
    ['orders', 'customer_id', 'Orders'],
];

function selected_ids(): array
{
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
}

/** Returns only ids that exist and are not admin accounts. */
function deletable_ids(PDO $pdo, array $ids): array
{
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id IN ($in) AND role <> 'admin' ORDER BY id");
    $stmt->execute($ids);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function related_counts(PDO $pdo, array $ids): array
{
    $in = implode(',', array_fill(0, count($ids), '?'));
    $out = [];
    foreach (RELATED_TABLES as [$table, $column, $label]) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $column IN ($in)");
        $stmt->execute($ids);
        $out[$label] = (int)$stmt->fetchColumn();
    }
    return $out;
}

function delete_accounts(PDO $pdo, array $ids): int
{
    if (!$ids) {
        return 0;
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $pdo->beginTransaction();
    try {
        foreach (RELATED_TABLES as [$table, $column]) {
            $pdo->prepare("DELETE FROM $table WHERE $column IN ($in)")->execute($ids);
        }
        // Seller profiles, email verifications and affiliate profiles cascade from the user row.
        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($in) AND role <> 'admin'");
        $stmt->execute($ids);
        $removed = $stmt->rowCount();
        $pdo->commit();
        return $removed;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('DogoFlow account deletion failed: ' . $e->getMessage());
        throw $e;
    }
}

$message = null;
$error = null;
$preview = null;      // ['accounts' => [...], 'counts' => [...], 'ids' => [...]]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf($_POST['_csrf'] ?? null);
        $action = (string)($_POST['action'] ?? '');
        $ids = deletable_ids($pdo, selected_ids());

        if ($action === 'preview') {
            if (!$ids) {
                throw new RuntimeException('Select at least one account to preview.');
            }
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id, name, email, role, status, created_at FROM users WHERE id IN ($in) ORDER BY id");
            $stmt->execute($ids);
            $preview = ['accounts' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'counts' => related_counts($pdo, $ids), 'ids' => $ids];
        } elseif ($action === 'delete') {
            if (!$ids) {
                throw new RuntimeException('No deletable accounts were selected. Admin accounts cannot be removed here.');
            }
            if (($_POST['confirm'] ?? '') !== '1') {
                throw new RuntimeException('Tick the confirmation box to delete these accounts.');
            }
            $count = delete_accounts($pdo, $ids);
            $message = "Deleted $count account" . ($count === 1 ? '' : 's') . ' and their related records.';
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $error = 'Nothing was deleted. ' . $e->getMessage();
    }
}

// Search and list (step 1)
$q = trim((string)($_GET['q'] ?? ''));
$role = (string)($_GET['role'] ?? '');
$roles = ['customer', 'seller', 'affiliate'];
$where = ["role <> 'admin'"];
$params = [];
if ($q !== '') {
    $where[] = '(email LIKE ? OR name LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if (in_array($role, $roles, true)) {
    $where[] = 'role = ?';
    $params[] = $role;
}
$stmt = $pdo->prepare('SELECT id, name, email, role, status, created_at FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 200');
$stmt->execute($params);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?><!doctype html>
<html lang="en">
<head>
    <link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Delete accounts · DogoFlow Admin</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/admin-ui-consistency.css')) ?>">
    <?php require __DIR__ . '/../partials/tailwind.php'; ?>
</head>
<body class="admin-shell">
<?php $active = 'delete-accounts'; require __DIR__ . '/../partials/admin_sidebar.php'; ?>
<section class="admin-main">
<?php require __DIR__ . '/../partials/admin_topbar.php'; ?>
<main class="admin-content">
    <div class="page-heading"><div>
        <h1>Delete accounts</h1>
        <p>Search, select the accounts, preview what will be removed, then press Go. Admin accounts are never shown here.</p>
    </div></div>

    <?php if ($message): ?>
        <div style="margin-bottom:16px;padding:12px 14px;border-radius:10px;background:#ecfdf3;color:#067647;border:1px solid #abefc6"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="margin-bottom:16px;padding:12px 14px;border-radius:10px;background:#fef3f2;color:#b42318;border:1px solid #fecdca"><?= e($error) ?></div>
    <?php endif; ?>

<?php if ($preview): ?>
    <article class="panel-df" style="padding:22px;margin-bottom:18px">
        <h2 style="margin:0 0 12px;font-size:18px">Preview: these will be deleted</h2>
        <ul style="margin:0 0 14px;padding-left:18px;line-height:1.7">
            <?php foreach ($preview['accounts'] as $a): ?>
                <li><?= e((string)$a['email']) ?> <span style="color:#64748B">(<?= e((string)$a['role']) ?>, <?= e((string)$a['status']) ?>)</span></li>
            <?php endforeach; ?>
        </ul>
        <p style="margin:0 0 6px;font-weight:700">Related records that will also be removed:</p>
        <ul style="margin:0 0 16px;padding-left:18px;line-height:1.7">
            <?php foreach ($preview['counts'] as $label => $n): ?>
                <li><?= e($label) ?>: <?= (int)$n ?></li>
            <?php endforeach; ?>
        </ul>
        <form method="post" onsubmit="return confirm('Delete these accounts and their records? This cannot be undone.')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <?php foreach ($preview['ids'] as $id): ?>
                <input type="hidden" name="ids[]" value="<?= (int)$id ?>">
            <?php endforeach; ?>
            <label style="display:flex;gap:8px;align-items:center;margin-bottom:14px">
                <input type="checkbox" name="confirm" value="1" required> I understand this cannot be undone.
            </label>
            <button type="submit" class="btn-df danger">Go: delete these accounts</button>
            <a href="<?= e(url('/admin/delete-accounts.php')) ?>" style="margin-left:12px">Cancel</a>
        </form>
    </article>
<?php endif; ?>

    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
        <input name="q" value="<?= e($q) ?>" placeholder="Search name or email" class="checkout-input" style="min-width:260px">
        <select name="role" class="checkout-input">
            <option value="">All roles</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn-df secondary" type="submit">Search</button>
    </form>

    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="preview">
        <article class="panel-df" style="padding:0;overflow:auto">
            <table style="width:100%;border-collapse:collapse;font-size:14px">
                <thead><tr style="text-align:left;background:#F8FAFC">
                    <th style="padding:12px;width:40px"></th><th style="padding:12px">ID</th><th style="padding:12px">Name</th><th style="padding:12px">Email</th><th style="padding:12px">Role</th><th style="padding:12px">Status</th><th style="padding:12px">Created</th>
                </tr></thead>
                <tbody>
                <?php if (!$accounts): ?>
                    <tr><td colspan="7" style="padding:16px;color:#64748B">No accounts match this search.</td></tr>
                <?php endif; ?>
                <?php foreach ($accounts as $a): ?>
                    <tr style="border-top:1px solid #E2E8F0">
                        <td style="padding:12px"><input type="checkbox" name="ids[]" value="<?= (int)$a['id'] ?>"></td>
                        <td style="padding:12px"><?= (int)$a['id'] ?></td>
                        <td style="padding:12px"><?= e((string)$a['name']) ?></td>
                        <td style="padding:12px"><?= e((string)$a['email']) ?></td>
                        <td style="padding:12px"><?= e((string)$a['role']) ?></td>
                        <td style="padding:12px"><?= e((string)$a['status']) ?></td>
                        <td style="padding:12px"><?= e((string)$a['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </article>
        <div style="margin-top:16px"><button type="submit" class="btn-df primary">Preview selected</button></div>
    </form>
</main>
</section>
</body>
</html>
