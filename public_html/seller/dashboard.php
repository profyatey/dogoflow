<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
Auth::requireApprovedSeller();

$userId = Auth::userId();
$pdo = db();

$stmt = $pdo->prepare('SELECT name, email, phone FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch() ?: [];

$stmt = $pdo->prepare('SELECT * FROM seller_profiles WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$store = $stmt->fetch() ?: null;

$stmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END), 0) AS active_products,
        COALESCE(SUM(stock), 0) AS total_stock
     FROM products
     WHERE seller_id = ?'
);
$stmt->execute([$userId]);
$productStats = $stmt->fetch() ?: [];

$stmt = $pdo->prepare(
    'SELECT COUNT(DISTINCT oi.order_id)
     FROM order_items oi
     WHERE oi.seller_id = ?'
);
try {
    $stmt->execute([$userId]);
    $orderCount = (int) $stmt->fetchColumn();
} catch (Throwable $e) {
    // The seller dashboard remains usable while the order module is still being built.
    $orderCount = 0;
}

$storeName = $store['business_name'] ?? 'Your Store';
$plan = SellerPlan::get($userId);
$storeReady = $store && !empty($store['business_name']) && !empty($store['store_slug']);
$success = flash('success');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0B5CFF"><title>Seller Dashboard · DogoFlow</title><link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>"><?php require __DIR__ . '/../partials/tailwind.php'; ?></head><body class="min-h-screen bg-slate-50 text-slate-900"><?php $sellerActive = 'dashboard'; require __DIR__ . '/../partials/seller_header.php'; ?>
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<?php if ($success): ?><div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"><?= e($success) ?></div><?php endif; ?>
<section class="mb-8 flex flex-col justify-between gap-5 rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-sky-950 p-6 text-white shadow-sm sm:p-8 md:flex-row md:items-center"><div><p class="mb-2 text-xs font-bold uppercase tracking-[.18em] text-blue-100">Seller workspace</p><h1 class="text-3xl font-black tracking-tight sm:text-4xl">Welcome, <?= e($user['name'] ?? 'Seller') ?>.</h1><p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">Run your DogoFlow store, keep products fresh and stay on top of customer orders.</p></div><a href="<?= url('/seller/add-product.php') ?>" class="inline-flex w-fit items-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-blue-700 shadow-sm hover:bg-blue-50">+ Add product</a></section>
<?php if (!$storeReady): ?><section class="mb-8 flex flex-col gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-bold text-amber-950">Your store is not ready yet.</p><p class="mt-1 text-sm text-amber-800">Add your business name, store link and category details before sending customers to your storefront.</p></div><a href="<?= url('/seller/store-profile.php') ?>" class="inline-flex w-fit rounded-xl bg-amber-950 px-4 py-2.5 text-sm font-bold text-white">Complete store setup</a></section><?php else: ?><section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Your store</p><h2 class="mt-1 text-xl font-extrabold"><?= e($storeName) ?></h2><p class="mt-1 text-sm text-slate-500"><?= e($store['store_location'] ?? 'Location not set') ?></p></div><div class="flex flex-wrap gap-2"><a class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" target="_blank" href="<?= url('/store.php?slug=' . urlencode((string) $store['store_slug'])) ?>">View store</a><a class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white" href="<?= url('/seller/store-profile.php') ?>">Edit store</a></div></div></section><?php endif; ?>
<section class="mb-8 grid gap-4 sm:grid-cols-3"><div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Products</p><p class="mt-2 text-3xl font-black"><?= (int)($productStats['total_products'] ?? 0) ?></p><p class="mt-1 text-xs text-emerald-600"><?= (int)($productStats['active_products'] ?? 0) ?> active</p></div><div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Stock units</p><p class="mt-2 text-3xl font-black"><?= (int)($productStats['total_stock'] ?? 0) ?></p><p class="mt-1 text-xs text-slate-500">Across your products</p></div><div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Orders</p><p class="mt-2 text-3xl font-black"><?= $orderCount ?></p><p class="mt-1 text-xs text-slate-500">Seller orders</p></div></section>
<section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Seller plan</p><h2 class="mt-1 text-xl font-extrabold"><?= e(strtoupper($plan['plan'])) ?> Plan <span class="font-medium text-slate-400">· <?= e($plan['seller_code'] ?: 'DF-PENDING') ?></span></h2><p class="mt-1 text-sm text-slate-500"><?= (int)$plan['slots_used'] ?> of <?= (int)$plan['slot_limit'] ?> product slots used.</p></div><?php if ($plan['is_pro']): ?><span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700">Pro · expires <?= e(date('j M Y', strtotime((string)$plan['subscription_expires_at']))) ?></span><?php else: ?><a href="<?= url('/seller/upgrade.php') ?>" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">Upgrade · GH₵30/year</a><?php endif; ?></div><div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-600" style="width: <?= (int)min(100,($plan['slots_used']/max(1,$plan['slot_limit']))*100) ?>%"></div></div><p class="mt-2 text-xs text-slate-500"><?= (int)$plan['remaining'] ?> product slot<?= $plan['remaining'] === 1 ? '' : 's' ?> remaining</p></section>
<section><div class="mb-4"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Quick actions</p><h2 class="mt-1 text-2xl font-black">Keep your store moving</h2></div><div class="grid gap-4 md:grid-cols-3"><a class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-blue-200" href="<?= url('/seller/add-product.php') ?>"><span class="text-2xl">📦</span><h3 class="mt-4 font-extrabold">Add a product</h3><p class="mt-1 text-sm leading-6 text-slate-500">Publish a new product with photos, pricing, stock and categories.</p></a><a class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-blue-200" href="<?= url('/seller/products.php') ?>"><span class="text-2xl">🛍️</span><h3 class="mt-4 font-extrabold">Manage products</h3><p class="mt-1 text-sm leading-6 text-slate-500">Update listings, stock and product visibility.</p></a><a class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-blue-200" href="<?= url('/seller/orders.php') ?>"><span class="text-2xl">🚚</span><h3 class="mt-4 font-extrabold">Manage orders</h3><p class="mt-1 text-sm leading-6 text-slate-500">Review customer purchases and fulfilment information.</p></a></div></section>
</main><?php require __DIR__ . '/../includes/footer.php'; ?></body></html>