<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

Auth::requireRole('admin');

$stats = [
    'users' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'products' => (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'orders' => (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'payments' => (int) db()->query('SELECT COUNT(*) FROM payments WHERE status = "success"')->fetchColumn(),
];

$revenue = (float) db()
    ->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = "success"')
    ->fetchColumn();

$pendingOrders = (int) db()
    ->query('SELECT COUNT(*) FROM orders WHERE status IN ("pending_payment", "paid", "processing")')
    ->fetchColumn();

$recentUsers = db()
    ->query('SELECT name, email, role, status, created_at FROM users ORDER BY id DESC LIMIT 6')
    ->fetchAll();

$recentOrders = db()
    ->query('SELECT order_number, total, status, created_at FROM orders ORDER BY id DESC LIMIT 6')
    ->fetchAll();

$active = 'dashboard';
$pageTitle = 'Dashboard';
$pageDescription = 'Platform overview and operational activity';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#071A33">
    <title>Admin Dashboard · DogoFlow</title>
    <link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/admin.css') ?>">
</head>
<body class="admin-shell">

<?php require __DIR__ . '/../partials/admin_sidebar.php'; ?>

<section class="admin-main">
    <?php require __DIR__ . '/../partials/admin_topbar.php'; ?>

    <main class="admin-content">
        <div class="page-heading">
            <div>
                <h1>Good to see you, Administrator.</h1>
                <p>Here is what's happening across DogoFlow today.</p>
            </div>

            <a class="btn-df primary" href="<?= url('/') ?>" target="_blank">
                🌐 View storefront
            </a>
        </div>

        <section class="panel-df" style="margin-bottom:22px; padding:18px 20px;">
            <div class="panel-head-df" style="padding:0 0 14px; border-bottom:0;">
                <div><h2 style="margin:0; color:#0B2545;">Operations</h2><p style="margin:4px 0 0; color:#64748B; font-size:.82rem;">Jump straight into the areas that need attention.</p></div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;">
                <a class="btn-df secondary" href="<?= url('/admin/sellers.php') ?>">Seller approvals</a>
                <a class="btn-df secondary" href="<?= url('/admin/orders.php') ?>">Manage orders</a>
                <a class="btn-df secondary" href="<?= url('/admin/payments.php') ?>">Review payments</a>
                <a class="btn-df secondary" href="<?= url('/admin/withdrawals.php') ?>">Process withdrawals</a>
            </div>
        </section>

        <section class="stats-grid-df">
            <?php
            $cards = [
                ['👥', 'Total users', number_format($stats['users'])],
                ['📦', 'Products', number_format($stats['products'])],
                ['🛍️', 'Orders', number_format($stats['orders'])],
                ['💳', 'Successful payments', number_format($stats['payments'])],
                ['💰', 'Processed revenue', 'GH₵ ' . number_format($revenue, 2)],
                ['⏳', 'Open orders', number_format($pendingOrders)],
            ];
            ?>

            <?php foreach ($cards as $card): ?>
                <article class="stat-card-df">
                    <div class="stat-icon-df">
                        <?= $card[0] ?>
                    </div>
                    <div class="stat-label-df">
                        <?= e($card[1]) ?>
                    </div>
                    <div class="stat-value-df">
                        <?= e($card[2]) ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="dashboard-grid">
            <article class="panel-df">
                <div class="panel-head-df">
                    <h2>Recent orders</h2>
                    <a href="<?= url('/admin/orders.php') ?>">View all →</a>
                </div>

                <div class="panel-body-df">
                    <div class="table-wrap-df">
                        <table class="table-df">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($order['order_number']) ?></strong>
                                        </td>
                                        <td>
                                            GH₵ <?= number_format((float) $order['total'], 2) ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = match ($order['status']) {
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'info',
                                            };
                                            ?>
                                            <span class="badge-df <?= $statusClass ?>">
                                                <?= e(str_replace('_', ' ', $order['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= e(date('d M Y', strtotime($order['created_at']))) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$recentOrders): ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="empty-df">
                                                No orders have been created yet.
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>

            <article class="panel-df">
                <div class="panel-head-df">
                    <h2>New users</h2>
                    <a href="<?= url('/admin/users.php') ?>">Manage →</a>
                </div>

                <div class="panel-body-df">
                    <div class="table-wrap-df">
                        <table class="table-df">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($user['name']) ?></strong>
                                            <div style="font-size: .7rem; color: #64748B; margin-top: 2px;">
                                                <?= e($user['email']) ?>
                                            </div>
                                        </td>
                                        <td><?= e($user['role']) ?></td>
                                        <td>
                                            <?php
                                            $userStatusClass = match ($user['status']) {
                                                'active' => 'success',
                                                'blocked' => 'danger',
                                                default => 'warning',
                                            };
                                            ?>
                                            <span class="badge-df <?= $userStatusClass ?>">
                                                <?= e($user['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$recentUsers): ?>
                                    <tr>
                                        <td colspan="3">
                                            <div class="empty-df">No users yet.</div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </section>
    </main>
</section>

<style>@media(max-width:820px){.admin-content .panel-df [style*="grid-template-columns"]{grid-template-columns:repeat(2,minmax(0,1fr)) !important;}}</style></body>
</html>
