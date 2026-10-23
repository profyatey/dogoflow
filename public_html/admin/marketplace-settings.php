<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/config/SiteSettings.php';
Auth::requireRole('admin');

$message = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf($_POST['_csrf'] ?? null);
        SiteSettings::setCartEnabled(isset($_POST['cart_enabled']) && $_POST['cart_enabled'] === '1');
        $message = 'Marketplace settings saved.';
    } catch (Throwable $e) {
        error_log('Marketplace settings save failed: ' . $e->getMessage());
        $error = 'Could not save settings. Please confirm the site_settings migration has been applied.';
    }
}

$cartEnabled = SiteSettings::isCartEnabled();
$active = 'marketplace';
$pageTitle = 'Marketplace settings';
$pageDescription = 'Control how customers place product orders';
?><!doctype html>
<html lang="en"><head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Marketplace settings · DogoFlow Admin</title><link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>"><link rel="stylesheet" href="<?= e(url('/assets/css/admin-ui-consistency.css')) ?>"><?php require __DIR__ . '/../partials/tailwind.php'; ?></head>
<body class="admin-shell"><?php require __DIR__ . '/../partials/admin_sidebar.php'; ?><section class="admin-main"><?php require __DIR__ . '/../partials/admin_topbar.php'; ?><main class="admin-content">
<div class="page-heading"><div><h1>Marketplace settings</h1><p>Choose how customers contact sellers to place orders. Subscription payments remain available regardless of this setting.</p></div></div>
<?php if ($message): ?><div role="status" style="margin-bottom:16px;padding:12px 14px;border-radius:10px;background:#ecfdf3;color:#067647;border:1px solid #abefc6"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div role="alert" style="margin-bottom:16px;padding:12px 14px;border-radius:10px;background:#fef3f2;color:#b42318;border:1px solid #fecdca"><?= e($error) ?></div><?php endif; ?>
<div style="display:grid;grid-template-columns:minmax(0,720px);gap:18px">
<article class="panel-df" style="padding:24px"><div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:18px"><div style="display:grid;place-items:center;width:46px;height:46px;border-radius:14px;background:#eff6ff;color:#075fba;font-size:22px">🛒</div><div><h2 style="margin:0;font-size:1.1rem;color:#0b2545">Customer cart and checkout</h2><p style="margin:5px 0 0;color:#64748b;font-size:.88rem;line-height:1.6">When enabled, customers can add products to a cart and use the product checkout flow. When disabled, the cart and product checkout are blocked and customers order by calling or messaging the seller on WhatsApp.</p></div></div>
<form method="post" style="display:grid;gap:18px"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="cart_enabled" value="0">
<label style="display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px;border:1px solid #e2e8f0;border-radius:14px"><span><strong style="display:block;color:#0b2545">Enable cart ordering</strong><span style="display:block;margin-top:4px;color:#64748b;font-size:.83rem">Default: disabled. Paystack subscription payments are not affected.</span></span><input type="checkbox" name="cart_enabled" value="1" <?= $cartEnabled ? 'checked' : '' ?> style="width:22px;height:22px;flex:none"></label>
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><button class="btn-df primary" type="submit">Save marketplace settings</button><span style="font-size:.82rem;color:<?= $cartEnabled ? '#047857' : '#475569' ?>"><?= $cartEnabled ? 'Cart ordering is currently enabled.' : 'Direct seller contact is currently the ordering method.' ?></span></div></form></article>
<article class="panel-df" style="padding:20px 24px"><h2 style="margin:0 0 8px;font-size:1rem;color:#0b2545">Order methods</h2><ul style="margin:0;padding-left:20px;color:#475569;font-size:.88rem;line-height:1.9"><li><strong>Call to Order:</strong> opens the seller’s phone number.</li><li><strong>WhatsApp Order:</strong> opens a prepared message with product details and its link.</li><li><strong>Subscriptions:</strong> seller Pro-plan payments continue through Paystack.</li></ul></article>
</div></main></section></body></html>
