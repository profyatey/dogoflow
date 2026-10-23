<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/config/SiteSettings.php';
Auth::requireRole('admin');

$message = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf($_POST['_csrf'] ?? null);
        foreach (SiteSettings::accountTypes() as $type) {
            SiteSettings::setSignupOpen($type, isset($_POST['open'][$type]));
        }
        $message = 'Sign-up settings saved.';
    } catch (Throwable $e) {
        $error = 'Could not save settings. Make sure the site_settings migration has been run.';
    }
}

$labels = ['customer' => ['Customer accounts', 'Shoppers who buy products.'],
           'seller' => ['Seller accounts', 'Store owners who list products.'],
           'affiliate' => ['Affiliate accounts', 'People who refer new sellers.']];
?><!doctype html><html lang="en"><head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Account Activation · DogoFlow Admin</title><link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>"><link rel="stylesheet" href="<?= e(url('/assets/css/admin-ui-consistency.css')) ?>"><?php require __DIR__ . '/../partials/tailwind.php'; ?></head>
<body class="admin-shell"><?php $active = 'signup'; require __DIR__ . '/../partials/admin_sidebar.php'; ?><section class="admin-main"><?php require __DIR__ . '/../partials/admin_topbar.php'; ?><main class="admin-content">
<div class="page-heading"><div><h1>Account Activation</h1><p>Control which account types may create new accounts. Existing accounts are not affected.</p></div></div>
<?php if ($message): ?><div style="margin-bottom:16px;padding:12px 14px;border-radius:10px;background:#ecfdf3;color:#067647;border:1px solid #abefc6"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div style="margin-bottom:16px;padding:12px 14px;border-radius:10px;background:#fef3f2;color:#b42318;border:1px solid #fecdca"><?= e($error) ?></div><?php endif; ?>
<article class="panel-df" style="padding:24px;max-width:720px">
<form method="post" style="display:grid;gap:14px">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <?php foreach ($labels as $type => [$title, $help]): $open = SiteSettings::isSignupOpen($type); ?>
  <label style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 18px;border:1px solid #E2E8F0;border-radius:14px;cursor:pointer">
    <span><strong style="display:block;font-size:1rem"><?= e($title) ?></strong><span style="font-size:.85rem;color:#64748B"><?= e($help) ?></span></span>
    <input type="checkbox" name="open[<?= e($type) ?>]" value="1" <?= $open ? 'checked' : '' ?> style="width:22px;height:22px">
  </label>
  <?php endforeach; ?>
  <div><button class="btn-df primary" type="submit">Save settings</button></div>
</form>
</article>
</main></section></body></html>
