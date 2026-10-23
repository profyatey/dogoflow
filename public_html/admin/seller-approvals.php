<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';
Auth::requireRole('admin');

$pdo = db();

$stmt = $pdo->query("
    SELECT
        sp.id,
        sp.user_id,
        sp.seller_code,
        sp.business_name,
        sp.business_phone,
        sp.store_slug,
        sp.approval_status,
        sp.rejection_reason,
        sp.created_at,
        u.name,
        u.email,
        u.phone,
        u.status AS user_status
    FROM seller_profiles sp
    INNER JOIN users u ON u.id = sp.user_id
    ORDER BY
        CASE sp.approval_status
            WHEN 'pending' THEN 0
            WHEN 'rejected' THEN 1
            WHEN 'approved' THEN 2
            ELSE 3
        END,
        sp.created_at DESC
");
$sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$active = 'sellers';
$pageTitle = 'Seller Approvals';
$pageDescription = 'Review seller applications and control marketplace access';
?>
<!doctype html><html lang="en"><head><link rel="stylesheet" href="<?= url('/assets/css/admin.css') ?>"><link rel="stylesheet" href="<?= url('/assets/css/admin-ui-consistency.css') ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Seller Approvals · DogoFlow Admin</title><link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>"><?php require __DIR__ . '/../partials/tailwind.php'; ?></head><body class="admin-shell"><?php require __DIR__ . '/../partials/admin_sidebar.php'; ?><section class="admin-main"><?php require __DIR__ . '/../partials/admin_topbar.php'; ?><main class="admin-content">
<div class="admin-page">
    <div class="admin-page-head">
        <div>
            <p class="eyebrow">MARKETPLACE CONTROL</p>
            <h1>Seller Approvals</h1>
            <p>Review seller applications and control who can operate a public store.</p>
        </div>
    </div>

    <div class="panel-df" style="padding:22px">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Seller</th>
                        <th>Business</th>
                        <th>Store</th>
                        <th>Application</th>
                        <th>Account</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sellers as $seller): ?>
                    <tr>
                        <td>
                            <strong><?= e($seller['seller_code'] ?: 'DF-' . str_pad((string)$seller['user_id'], 5, '0', STR_PAD_LEFT)) ?></strong><br>
                            <?= e($seller['name']) ?><br>
                            <small><?= e($seller['email']) ?></small>
                        </td>
                        <td><?= e($seller['business_name']) ?></td>
                        <td><?= e($seller['store_slug'] ?: 'Not set') ?></td>
                        <td><span class="status-pill status-<?= e($seller['approval_status']) ?>"><?= e(ucfirst($seller['approval_status'])) ?></span></td>
                        <td><?= e(ucfirst($seller['user_status'])) ?></td>
                        <td><a class="btn-df primary" href="<?= e(url('/admin/seller-review.php?id=' . (int)$seller['id'])) ?>">Review</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$sellers): ?>
                    <tr><td colspan="6">No seller applications found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</main></section></body></html>
