<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('seller');

$plan = SellerPlan::get((int) Auth::userId());
$error = flash('error');
$success = flash('success');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seller Plans · DogoFlow</title>
    <link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>">
    <?php require __DIR__ . '/../partials/tailwind.php'; ?>
</head>
<body class="seller-page">
    <main class="container-wide seller-shell-df">
        <header class="seller-topbar-df">
            <a class="seller-brand-df" href="<?= url('/seller/dashboard.php') ?>">
                <img src="<?= url('/assets/images/df-logo-sm.png') ?>" alt="DogoFlow">
                <span>DogoFlow <small>Seller</small></span>
            </a>
            <nav class="seller-nav-df" aria-label="Seller navigation">
                <a href="<?= url('/seller/dashboard.php') ?>">Dashboard</a>
                <a href="<?= url('/seller/products.php') ?>">Products</a>
                <a href="<?= url('/seller/orders.php') ?>">Orders</a>
                <a class="active" href="<?= url('/seller/upgrade.php') ?>">Plans</a>
                <a href="<?= url('/auth/logout.php') ?>">Logout</a>
            </nav>
        </header>

        <section class="seller-page-heading-df" style="padding-top: 38px; margin-bottom: 28px;">
            <div>
                <span class="eyebrow-df">Seller subscription</span>
                <h1>Choose your plan</h1>
                <p>Your photo allowance applies across all products in your store.</p>
            </div>
        </section>

        <?php if ($error): ?>
            <div class="alert danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert success"><?= e($success) ?></div>
        <?php endif; ?>

        <section class="seller-plan-grid-df">
            <article class="card seller-plan-option-df <?= $plan['plan'] === 'free' ? 'current' : '' ?>">
                <span class="eyebrow-df">Free</span>
                <h2>Free plan</h2>
                <div class="seller-price-df">GH₵0 <small>forever</small></div>
                <ul>
                    <li>Up to <strong>5 product photos</strong> total</li>
                    <li>Seller dashboard</li>
                    <li>Storefront</li>
                    <li>Product management</li>
                </ul>
                <?php if ($plan['plan'] === 'free'): ?>
                    <span class="plan-current-df">Current plan</span>
                <?php else: ?>
                    <span class="plan-note-df">Available after Pro expiry</span>
                <?php endif; ?>
            </article>

            <article class="card seller-plan-option-df featured">
                <span class="eyebrow-df">Pro</span>
                <h2>Pro plan</h2>
                <div class="seller-price-df">GH₵30 <small>/ year</small></div>
                <ul>
                    <li>Up to <strong>30 product photos</strong> total</li>
                    <li>One-year subscription</li>
                    <li>All Free seller features</li>
                    <li>Priority plan controls</li>
                </ul>
                <?php if ($plan['is_pro']): ?>
                    <p class="plan-note-df">Your Pro plan is active until <?= e(date('j M Y', strtotime((string) $plan['subscription_expires_at']))) ?>. A new payment adds another year to your current expiry.</p>
                <?php else: ?>
                    <p class="plan-note-df">Pay securely with Paystack. Your plan is activated only after the payment is verified.</p>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/seller/subscribe.php')) ?>" style="margin-top:18px">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <button class="btn-df primary" type="submit"><?= $plan['is_pro'] ? 'Renew Pro with Paystack · GH₵30' : 'Pay with Paystack · GH₵30' ?></button>
                </form>
            </article>
        </section>
    </main>
</body>
</html>
