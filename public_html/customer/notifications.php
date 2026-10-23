<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('customer');
$pdo = db();
$userId = (int)Auth::userId();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);
    if (isset($_POST['mark_all'])) {
        $stmt = $pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE user_id=?');
        $stmt->execute([$userId]);
    } elseif (isset($_POST['notification_id'])) {
        $stmt = $pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND user_id=?');
        $stmt->execute([(int)$_POST['notification_id'], $userId]);
    }
    redirect('/customer/notifications.php');
}
$stmt = $pdo->prepare('SELECT id,subject,message,channel,status,read_at,created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100');
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();
?>
<!doctype html><html lang="en"><head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Notifications · DogoFlow</title><?php require __DIR__ . '/../partials/tailwind.php'; ?></head>
<body><header class="site-header"><a href="<?=e(url('/'))?>"><img src="<?=e(url('/assets/images/df-logo.png'))?>" alt="DogoFlow" style="height:40px;width:auto"></a><nav class="nav-links"><a href="<?=e(url('/customer/dashboard.php'))?>">Account</a><a href="<?=e(url('/products.php'))?>">Products</a></nav></header><main class="container customer-form-page-df"><div class="page-heading"><div><span class="eyebrow-df">ACCOUNT UPDATES</span><h1>Notifications</h1><p>Order, account and platform updates appear here.</p></div><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><button class="btn btn-outline" name="mark_all" value="1" type="submit">Mark all as read</button></form></div><section class="card customer-panel-df"><div class="customer-notification-list-df"><?php foreach($notifications as $n): ?><div class="customer-notification-row-df <?= $n['read_at'] ? '' : 'unread' ?>"><div class="notification-dot-df"></div><div><div class="notification-title-row-df"><strong><?=e((string)($n['subject'] ?: 'DogoFlow update'))?></strong><?php if(!$n['read_at']): ?><span class="badge-df info">New</span><?php endif; ?></div><p><?=e((string)$n['message'])?></p><small><?=e(date('d M Y · H:i',strtotime((string)$n['created_at'])))?> · <?=e((string)$n['channel'])?></small></div><?php if(!$n['read_at']): ?><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>"><button class="btn btn-outline" type="submit">Read</button></form><?php endif; ?></div><?php endforeach; if(!$notifications): ?><div class="empty-df">You have no notifications.</div><?php endif; ?></div></section></main></body></html>
