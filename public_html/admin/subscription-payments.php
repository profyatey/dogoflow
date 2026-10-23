<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('admin');

$status = strtolower(trim((string)($_GET['status'] ?? '')));
$allowed = ['pending', 'success', 'failed'];
$params = [];
$where = '';
if (in_array($status, $allowed, true)) {
    $where = 'WHERE s.status = ?';
    $params[] = $status;
}
$sql = 'SELECT s.id, s.reference, s.amount, s.currency, s.status, s.gateway_transaction_id,
               s.created_at, COALESCE(s.paid_at, s.verified_at) AS verified_at, u.name, u.email, sp.business_name
        FROM seller_subscription_payments s
        INNER JOIN users u ON u.id = s.user_id
        LEFT JOIN seller_profiles sp ON sp.user_id = s.user_id '
        . $where . ' ORDER BY s.id DESC LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$summary = db()->query('SELECT COUNT(*) AS attempts,
    SUM(status = "success") AS successful,
    SUM(status = "pending") AS pending,
    SUM(status = "failed") AS failed,
    COALESCE(SUM(CASE WHEN status = "success" THEN amount ELSE 0 END), 0) AS revenue
    FROM seller_subscription_payments')->fetch() ?: [];
$active = 'subscriptions';
$pageTitle = 'Seller subscriptions';
$pageDescription = 'Verified Paystack subscription attempts and payment records';
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Seller subscriptions · DogoFlow Admin</title><link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>"><?php require __DIR__ . '/../partials/tailwind.php'; ?></head>
<body class="admin-shell"><?php require __DIR__ . '/../partials/admin_sidebar.php'; ?><section class="admin-main"><?php require __DIR__ . '/../partials/admin_topbar.php'; ?><main class="admin-content">
<div class="page-heading"><div><h1>Seller subscription payments</h1><p>Every checkout attempt is recorded here, including pending and failed test-mode transactions. Only verified successful payments activate Pro.</p></div></div>
<section class="stats-grid-df" style="margin-bottom:20px">
<?php foreach ([['Total attempts',(int)($summary['attempts']??0)],['Successful',(int)($summary['successful']??0)],['Pending',(int)($summary['pending']??0)],['Failed',(int)($summary['failed']??0)],['Verified subscription revenue','GH₵ '.number_format((float)($summary['revenue']??0),2)]] as [$label,$value]): ?><article class="stat-card-df"><div class="stat-label-df"><?= e((string)$label) ?></div><div class="stat-value-df"><?= e((string)$value) ?></div></article><?php endforeach; ?>
</section>
<article class="panel-df"><div class="panel-head-df"><h2>Transaction records</h2><form method="get" style="display:flex;gap:8px;align-items:center"><select name="status" class="checkout-input" aria-label="Filter by status"><option value="">All statuses</option><?php foreach($allowed as $s): ?><option value="<?= e($s) ?>" <?= $status===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select><button class="btn-df secondary" type="submit">Filter</button><a class="btn-df secondary" href="<?= e(url('/admin/subscription-payments.php')) ?>">Reset</a></form></div>
<div class="table-wrap-df"><table class="table-df"><thead><tr><th>Seller</th><th>Reference</th><th>Amount</th><th>Status</th><th>Paystack transaction</th><th>Started</th><th>Paid / verified</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><strong><?= e((string)($r['business_name'] ?: $r['name'])) ?></strong><small><?= e((string)$r['email']) ?></small></td><td><code><?= e((string)$r['reference']) ?></code></td><td><?= e((string)$r['currency']) ?> <?= number_format((float)$r['amount'],2) ?></td><td><span class="badge-df <?= $r['status']==='success'?'success':($r['status']==='failed'?'danger':'warning') ?>"><?= e(ucfirst((string)$r['status'])) ?></span></td><td><?= e((string)($r['gateway_transaction_id'] ?? '—')) ?></td><td><?= e(date('d M Y · H:i',strtotime((string)$r['created_at']))) ?></td><td><?= !empty($r['verified_at']) ? e(date('d M Y · H:i',strtotime((string)$r['verified_at']))) : '—' ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7"><div class="empty-df">No subscription records match this filter. If a checkout was attempted, check the PHP error log and confirm the subscription table migration is installed.</div></td></tr><?php endif; ?>
</tbody></table></div></article>
</main></section></body></html>
