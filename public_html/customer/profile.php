<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('customer');

$pdo = db();
$userId = (int)Auth::userId();
$stmt = $pdo->prepare('SELECT name,email,phone,created_at FROM users WHERE id=? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) { http_response_code(404); exit('Account not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) {
        flash('error', 'Please enter a valid name.');
        redirect('/customer/profile.php');
    }
    if (mb_strlen($phone) > 30) {
        flash('error', 'Phone number is too long.');
        redirect('/customer/profile.php');
    }
    $stmt = $pdo->prepare('UPDATE users SET name=?,phone=?,updated_at=NOW() WHERE id=?');
    $stmt->execute([$name, $phone !== '' ? $phone : null, $userId]);
    flash('success', 'Your profile has been updated.');
    redirect('/customer/profile.php');
}

$flashError = flash('error');
$flashSuccess = flash('success');
?>
<!doctype html><html lang="en"><head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Profile · DogoFlow</title><?php require __DIR__ . '/../partials/tailwind.php'; ?></head>
<body><header class="site-header"><a href="<?=e(url('/'))?>"><img src="<?=e(url('/assets/images/df-logo.png'))?>" alt="DogoFlow" style="height:40px;width:auto"></a><nav class="nav-links"><a href="<?=e(url('/products.php'))?>">Products</a><a href="<?=e(url('/customer/dashboard.php'))?>">Account</a></nav></header>
<main class="container customer-form-page-df"><div class="page-heading"><div><span class="eyebrow-df">ACCOUNT SETTINGS</span><h1>My Profile</h1><p>Keep your contact information up to date.</p></div></div>
<?php if ($flashError): ?><div class="flash-df error"><?=e($flashError)?></div><?php endif; ?>
<?php if ($flashSuccess): ?><div class="flash-df success"><?=e($flashSuccess)?></div><?php endif; ?>
<form method="post" class="card customer-form-card-df"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><label>Full name<input required maxlength="120" name="name" value="<?=e((string)$user['name'])?>"></label><label>Email<input readonly value="<?=e((string)$user['email'])?>"><small>Email is your login identifier.</small></label><label>Phone number<input maxlength="30" name="phone" value="<?=e((string)($user['phone'] ?? ''))?>" placeholder="024 000 0000"></label><div class="customer-form-actions-df"><a class="btn btn-outline" href="<?=e(url('/customer/dashboard.php'))?>">Cancel</a><button class="btn btn-primary" type="submit">Save changes</button></div></form></main></body></html>
