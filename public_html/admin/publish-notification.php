<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('admin');

$pdo = db();
$audiences = [
    'all' => ['label' => 'Everyone', 'where' => "u.status = 'active'"],
    'customers' => ['label' => 'Customers', 'where' => "u.role = 'customer' AND u.status = 'active'"],
    'sellers' => ['label' => 'All sellers', 'where' => "u.role IN ('seller','vendor') AND u.status = 'active'"],
    'approved_sellers' => ['label' => 'Approved sellers only', 'where' => "u.role IN ('seller','vendor') AND u.status = 'active' AND EXISTS (SELECT 1 FROM seller_profiles sp WHERE sp.user_id = u.id AND sp.approval_status = 'approved')"],
    'affiliates' => ['label' => 'Affiliates', 'where' => "u.role = 'affiliate' AND u.status = 'active'"],
    'one_user' => ['label' => 'One user (by email)', 'where' => 'u.email = ?'],
];

$message = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $audience = (string) ($_POST['audience'] ?? '');
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!isset($audiences[$audience])) throw new RuntimeException('Choose who should see this notification.');
        if ($title === '' || mb_strlen($title) > 180) throw new RuntimeException('Enter a title up to 180 characters.');
        if ($body === '' || mb_strlen($body) > 2000) throw new RuntimeException('Enter a message up to 2000 characters.');
        $params = [$title, $body];
        if ($audience === 'one_user') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter the registered email of the user.');
            $params[] = $email;
        }
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, channel, subject, message, status)
            SELECT u.id, 'in_app', ?, ?, 'sent' FROM users u WHERE " . $audiences[$audience]['where']);
        $stmt->execute($params);
        $count = $stmt->rowCount();
        if ($count === 0) throw new RuntimeException('No matching users found for that audience.');
        $message = 'Published to ' . $count . ' ' . ($count === 1 ? 'user' : 'users') . ' (' . $audiences[$audience]['label'] . ').';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html><html lang="en"><head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Publish notification · DogoFlow Admin</title><link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>"><link rel="stylesheet" href="<?= e(url('/assets/css/admin-ui-consistency.css')) ?>"><?php require __DIR__ . '/../partials/tailwind.php'; ?></head>
<body class="admin-shell"><?php require __DIR__ . '/../partials/admin_sidebar.php'; ?><section class="admin-main"><?php require __DIR__ . '/../partials/admin_topbar.php'; ?><main class="admin-content">
<div class="page-heading"><div><h1>Publish notification</h1><p>Send an in-app notification to everyone, a group, or one user. It appears in their notifications list.</p></div></div>
<?php if ($message): ?><div style="margin-bottom:16px;padding:12px 14px;border-radius:10px;background:#ecfdf3;color:#067647;border:1px solid #abefc6"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div style="margin-bottom:16px;padding:12px 14px;border-radius:10px;background:#fef3f2;color:#b42318;border:1px solid #fecdca"><?= e($error) ?></div><?php endif; ?>
<article class="panel-df" style="padding:24px;max-width:720px">
<form method="post" style="display:grid;gap:16px">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <label style="display:grid;gap:6px;font-weight:700">Who should see it
    <select name="audience" class="checkout-input" id="audience" required>
      <?php foreach ($audiences as $key => $item): ?><option value="<?= e($key) ?>"><?= e($item['label']) ?></option><?php endforeach; ?>
    </select></label>
  <label id="emailRow" style="display:none;gap:6px;font-weight:700">User email
    <input class="checkout-input" type="email" name="email" placeholder="user@example.com"></label>
  <label style="display:grid;gap:6px;font-weight:700">Title
    <input class="checkout-input" type="text" name="title" maxlength="180" required></label>
  <label style="display:grid;gap:6px;font-weight:700">Message
    <textarea class="checkout-input" name="body" rows="6" maxlength="2000" required></textarea></label>
  <div><button class="btn-df primary" type="submit">Publish notification</button></div>
</form>
</article>
<script>
(function () {
  var select = document.getElementById('audience');
  var row = document.getElementById('emailRow');
  function sync() { row.style.display = select.value === 'one_user' ? 'grid' : 'none'; }
  select.addEventListener('change', sync); sync();
})();
</script>
</main></section></body></html>
