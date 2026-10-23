<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
Auth::requireApprovedSeller();

$plan = SellerPlan::get((int) Auth::userId());
$error = flash('error');
$success = flash('success');
$proUntil = !empty($plan['is_pro']) && !empty($plan['subscription_expires_at'])
    ? date('j M Y', strtotime((string) $plan['subscription_expires_at']))
    : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plans · DogoFlow</title>
    <link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>">
    <?php require __DIR__ . '/../partials/tailwind.php'; ?>
    <style>
        .seller-plan-pro{background:linear-gradient(135deg,#020617 0%,#0F172A 55%,#075985 100%)!important;border:0!important;color:#fff!important;box-shadow:0 18px 40px rgba(2,6,23,.25)}
        .seller-plan-pro h2,.seller-plan-pro .seller-price-df{color:#fff!important}
        .seller-plan-pro .eyebrow-df{color:#7DD3FC!important}
        .seller-plan-pro li{color:#E2E8F0}
        .seller-pro-btn{width:100%;height:46px;margin-top:16px;border-radius:12px;border:0;background:#fff;color:#0F172A;font-weight:700;font-size:15px;cursor:pointer}
    </style>
</head>
<body class="seller-page">
    <main class="container-wide seller-shell-df">
        <?php $sellerActive = 'upgrade'; require __DIR__ . '/../partials/seller_header.php'; ?>

        <section class="seller-page-heading-df" style="padding-top: 38px; margin-bottom: 24px;">
            <div>
                <h1>Plans</h1>
                <p>Pro is GH₵30 a year.</p>
            </div>
        </section>

        <?php if ($error): ?>
            <div class="alert danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert success"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($proUntil): ?>
            <div class="alert success">Pro active until <?= e($proUntil) ?>.</div>
        <?php endif; ?>

        <section class="seller-plan-grid-df">
            <article class="card seller-plan-option-df <?= $plan['plan'] === 'free' ? 'current' : '' ?>">
                <span class="eyebrow-df">Free</span>
                <h2>GH₵0</h2>
                <ul>
                    <li>5 products</li>
                    <li>3 photos per product</li>
                </ul>
                <?php if ($plan['plan'] === 'free'): ?>
                    <span class="plan-current-df">Current</span>
                <?php endif; ?>
            </article>

            <article class="card seller-plan-option-df seller-plan-pro">
                <span class="eyebrow-df">Pro</span>
                <h2>GH₵30 / year</h2>
                <ul>
                    <li>10 products</li>
                    <li>5 photos per product</li>
                </ul>
                <form method="post" action="<?= url('/seller/subscribe.php') ?>">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <button class="seller-pro-btn" type="submit"><?= $proUntil ? 'Renew for GH₵30' : 'Pay GH₵30' ?></button>
                </form>
            </article>
        </section>
    </main>
</body>
</html>
