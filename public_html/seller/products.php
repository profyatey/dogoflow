<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
Auth::requireApprovedSeller();
require_once DOGOFLOW_ROOT . '/core/catalog/SellerPlan.php';
$slotPlan = SellerPlan::get((int) Auth::userId(), db());

$pdo = db();
$userId = (int) Auth::userId();
$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$allowedStatuses = ['draft', 'active', 'inactive', 'rejected'];

$where = ['p.seller_id = ?'];
$params = [$userId];

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.slug LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if (in_array($status, $allowedStatuses, true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}

$stmt = $pdo->prepare(
    'SELECT
        p.id,
        p.name,
        p.price,
        p.stock,
        p.status,
        COALESCE(
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1),
            p.image
        ) AS image,
        p.created_at,
        GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ", ") AS categories,
        (SELECT COUNT(*) FROM product_images pi2 WHERE pi2.product_id = p.id) AS photo_count
     FROM products p
     LEFT JOIN product_categories pc ON pc.product_id = p.id
     LEFT JOIN categories c ON c.id = pc.category_id
     WHERE ' . implode(' AND ', $where) . '
     GROUP BY p.id
     ORDER BY p.id DESC'
);
$stmt->execute($params);
$products = $stmt->fetchAll();
$success = flash('success');
$error = flash('error');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0B5CFF"><title>My Products · DogoFlow</title><link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>"><?php require __DIR__ . '/../partials/tailwind.php'; ?></head><body class="min-h-screen bg-slate-50 text-slate-900"><?php $sellerActive = 'products'; require __DIR__ . '/../partials/seller_header.php'; ?>
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8"><div class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600">Seller workspace</p><h1 class="mt-2 text-3xl font-black tracking-tight">My products</h1><p class="mt-2 text-sm text-slate-500">Manage everything you are selling on DogoFlow.</p><p class="mt-2 text-sm font-bold text-blue-700">Product slots: <?= (int) $slotPlan['slots_used'] ?> of <?= (int) $slotPlan['slot_limit'] ?> used · <?= e(ucfirst((string) $slotPlan['plan'])) ?> plan</p></div><a href="<?= url('/seller/add-product.php') ?>" class="inline-flex w-fit rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">+ Add product</a></div>
<?php if ($success): ?><div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"><?= e($success) ?></div><?php endif; ?><?php if ($error): ?><div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"><?= e($error) ?></div><?php endif; ?>
<form method="get" class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_180px_auto]"><input name="q" value="<?= e($search) ?>" placeholder="Search your products..." class="h-11 rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-500"><select name="status" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500"><option value="">All statuses</option><?php foreach ($allowedStatuses as $item): ?><option value="<?= e($item) ?>" <?= $status===$item?'selected':'' ?>><?= e(ucfirst($item)) ?></option><?php endforeach; ?></select><button class="h-11 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white">Filter</button></form>
<?php if (!$products): ?><div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-2xl">📦</div><h2 class="mt-5 text-xl font-extrabold">No products found</h2><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Try another search or add your first product to start building your storefront.</p><a class="mt-5 inline-flex rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white" href="<?= url('/seller/add-product.php') ?>">Add your first product</a></div><?php else: ?><div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="hidden grid-cols-[72px_1fr_120px_100px_110px] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wider text-slate-400 md:grid"><span>Image</span><span>Product</span><span>Price</span><span>Stock</span><span>Status</span></div><div class="divide-y divide-slate-100"><?php foreach ($products as $product): ?><div class="grid gap-4 px-5 py-4 md:grid-cols-[72px_1fr_120px_100px_110px] md:items-center"><div class="h-16 w-16 overflow-hidden rounded-xl bg-slate-100"><?php if (!empty($product['image'])): ?><img src="<?= e(url('/' . ltrim((string)$product['image'],'/'))) ?>" alt="<?= e($product['name']) ?>" class="h-full w-full object-cover"><?php else: ?><div class="flex h-full items-center justify-center text-xl text-slate-300">□</div><?php endif; ?></div><div class="min-w-0"><h3 class="font-extrabold text-slate-950"><?= e($product['name']) ?></h3><p class="mt-1 truncate text-xs text-slate-500"><?= e($product['categories'] ?: 'Uncategorized') ?> · <?= (int)$product['photo_count'] ?> photo<?= (int)$product['photo_count']===1?'':'s' ?></p><div class="mt-2 flex flex-wrap items-center gap-3 md:hidden df-card-actions"><a class="text-xs font-bold text-blue-600" href="<?= url('/seller/edit-product.php?id='.(int)$product['id']) ?>">Edit</a><a class="text-xs font-bold text-slate-500" href="<?= url('/product.php?id='.(int)$product['id']) ?>" target="_blank">View</a><?php if (in_array((string) $product['status'], ['active', 'inactive'], true)): ?><form method="post" action="<?= e(url('/seller/toggle-product.php')) ?>" style="display:inline"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button type="submit" class="text-xs font-bold text-blue-700" style="background:none;border:0;cursor:pointer;padding:0"><?= $product['status'] === 'active' ? 'Hide' : 'Show' ?></button></form><?php endif; ?><form method="post" action="<?= e(url('/seller/delete-product.php')) ?>" style="display:inline" onsubmit="return confirm('Delete this product? This cannot be undone.')"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button type="submit" class="text-xs font-bold text-rose-700" style="background:none;border:0;cursor:pointer;padding:0">Delete</button></form></div></div><div><span class="text-xs text-slate-400 md:hidden">Price</span><p class="font-black">GH₵<?= number_format((float)$product['price'],2) ?></p></div><div><span class="text-xs text-slate-400 md:hidden">Stock</span><p class="font-semibold <?= (int)$product['stock']===0?'text-rose-600':'text-slate-800' ?>"><?= (int)$product['stock'] ?></p></div><div class="flex items-center justify-between gap-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold <?= $product['status']==='active'?'bg-emerald-50 text-emerald-700':($product['status']==='rejected'?'bg-rose-50 text-rose-700':'bg-slate-100 text-slate-600') ?>"><?= e(ucfirst($product['status'])) ?></span><div class="hidden items-center gap-3 md:flex"><a class="text-xs font-bold text-blue-600" href="<?= url('/seller/edit-product.php?id='.(int)$product['id']) ?>">Edit</a><?php if (in_array((string) $product['status'], ['active', 'inactive'], true)): ?><form method="post" action="<?= e(url('/seller/toggle-product.php')) ?>" style="display:inline"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button type="submit" class="text-xs font-bold text-blue-700" style="background:none;border:0;cursor:pointer;padding:0"><?= $product['status'] === 'active' ? 'Hide' : 'Show' ?></button></form><?php endif; ?><form method="post" action="<?= e(url('/seller/delete-product.php')) ?>" style="display:inline" onsubmit="return confirm('Delete this product? This cannot be undone.')"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button type="submit" class="text-xs font-bold text-rose-700" style="background:none;border:0;cursor:pointer;padding:0">Delete</button></form></div></div></div><?php endforeach; ?></div></div><?php endif; ?></main></body></html>