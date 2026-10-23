<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('admin');
require_once DOGOFLOW_ROOT . '/core/security/AdminAudit.php';

$active = 'sellers';
$error = flash('error');
$success = flash('success');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: 0;
    $profileId = filter_input(INPUT_POST, 'seller_profile_id', FILTER_VALIDATE_INT) ?: 0;
    try {
        if ($action === 'remove_seller' && $userId > 0) {
            $pdo->beginTransaction();
            $q = $pdo->prepare("SELECT id,name,email FROM users WHERE id=? AND role IN ('seller','vendor') FOR UPDATE");
            $q->execute([$userId]); $target = $q->fetch(PDO::FETCH_ASSOC);
            if (!$target) throw new RuntimeException('Seller account not found.');
            $q = $pdo->prepare('SELECT id FROM products WHERE seller_id=?'); $q->execute([$userId]); $productIds = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
            if ($productIds) { $marks = implode(',', array_fill(0,count($productIds),'?')); $pdo->prepare("DELETE FROM product_images WHERE product_id IN ($marks)")->execute($productIds); $pdo->prepare("DELETE FROM product_categories WHERE product_id IN ($marks)")->execute($productIds); $pdo->prepare("DELETE FROM products WHERE id IN ($marks)")->execute($productIds); }
            dogoflow_admin_audit($pdo, 'seller_removed', 'user', (int)$target['id'], ['name'=>$target['name'],'email'=>$target['email'],'products_removed'=>count($productIds)]);
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
            $pdo->commit(); flash('success', 'Seller, store profile and their products were removed.');
        } elseif ($action === 'remove_store' && $profileId > 0) {
            $q=$pdo->prepare('SELECT sp.id,sp.user_id,sp.business_name,sp.store_slug FROM seller_profiles sp WHERE sp.id=?'); $q->execute([$profileId]); $store=$q->fetch(PDO::FETCH_ASSOC);
            if (!$store) throw new RuntimeException('Store not found.');
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE products SET status='inactive' WHERE seller_id=?")->execute([(int)$store['user_id']]);
            dogoflow_admin_audit($pdo, 'store_removed', 'seller_profile', (int)$store['id'], ['business_name'=>$store['business_name'],'store_slug'=>$store['store_slug']]);
            $pdo->prepare('DELETE FROM seller_profiles WHERE id=?')->execute([$profileId]);
            $pdo->commit(); flash('success', 'Store profile removed. Seller account retained and their products set inactive.');
        } else { throw new RuntimeException('Invalid removal request.'); }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Admin seller removal failed: '.$e->getMessage()); flash('error', 'Could not complete the removal. Check related order or payment records and try again.');
    }
    header('Location: '.url('/admin/sellers.php'), true, 303); exit;
}

$stmt = $pdo->query(
    'SELECT
        u.id,
        u.name,
        u.email,
        u.phone,
        u.status AS user_status,
        u.email_verified_at,
        u.created_at,
        sp.id AS seller_profile_id,
        sp.business_name,
        sp.store_slug,
        sp.status AS store_status
     FROM users u
     INNER JOIN seller_profiles sp ON sp.user_id = u.id
     WHERE u.role IN ("seller", "vendor")
     ORDER BY u.id DESC'
);
$sellers = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A1628">
    <title>Sellers · DogoFlow Admin</title>
    <link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/admin.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/admin-ui-consistency.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/responsive.css') ?>">
    <style>
        .admin-action-row { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
        .admin-action-row form { display:inline-flex; margin:0; }
        .admin-action-row .btn-df { white-space:nowrap; }
        @media (max-width: 760px) {
            .admin-action-row { align-items:stretch; }
            .admin-action-row form, .admin-action-row .btn-df { width:100%; }
        }
    </style>
</head>
<body class="admin-shell">
    <?php require __DIR__ . '/../partials/admin_sidebar.php'; ?>

    <section class="admin-main">
        <?php require __DIR__ . '/../partials/admin_topbar.php'; ?>

        <main class="admin-content">
            <div class="page-heading">
                <div>
                    <h1>Sellers</h1>
                    <p>Review seller accounts and control marketplace store approval.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert danger"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert success"><?= e($success) ?></div>
            <?php endif; ?>

            <article class="panel-df">
                <div class="table-wrap-df">
                    <table class="table-df">
                        <thead>
                            <tr>
                                <th>Seller</th>
                                <th>Store</th>
                                <th>Account</th>
                                <th>Store status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sellers as $seller): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($seller['name']) ?></strong>
                                        <small><?= e($seller['email']) ?></small>
                                    </td>
                                    <td>
                                        <?= e($seller['business_name']) ?>
                                        <?php if ($seller['store_slug']): ?>
                                            <small><?= e($seller['store_slug']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($seller['user_status']) ?></td>
                                    <td>
                                        <span class="badge-df <?= $seller['store_status'] === 'active' ? 'success' : ($seller['store_status'] === 'suspended' ? 'danger' : 'warning') ?>">
                                            <?= e($seller['store_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="admin-action-row">
                                            <a class="btn-df primary" href="<?= e(url('/admin/seller-review.php?id=' . (int) $seller['seller_profile_id'])) ?>">Review</a>
                                            <form method="post" onsubmit="return confirm('Remove this store profile? The seller account will remain and its products will be set inactive. This cannot be undone.');">
                                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="remove_store">
                                                <input type="hidden" name="seller_profile_id" value="<?= (int) $seller['seller_profile_id'] ?>">
                                                <button class="btn-df secondary" type="submit">Remove store</button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('PERMANENTLY remove this seller account, store profile, and its products? This cannot be undone.');">
                                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="remove_seller">
                                                <input type="hidden" name="user_id" value="<?= (int) $seller['id'] ?>">
                                                <button class="btn-df danger" type="submit">Remove seller</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$sellers): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-df">No seller accounts have been created yet.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </main>
    </section>
</body>
</html>
