<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
Auth::requireApprovedSeller();

$pdo = db();
$userId = Auth::userId();
$error = flash('error');

$stmt = $pdo->prepare('SELECT name, phone FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch() ?: [];

$stmt = $pdo->prepare('SELECT * FROM seller_profiles WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$store = $stmt->fetch() ?: [];

$businessName = (string) ($store['business_name'] ?? '');
$storeSlug = (string) ($store['store_slug'] ?? '');
$description = (string) ($store['store_description'] ?? '');
$location = (string) ($store['store_location'] ?? '');
$phone = (string) ($store['business_phone'] ?? ($user['phone'] ?? ''));
$address = (string) ($store['business_address'] ?? '');

$stmt = $pdo->query('SELECT id, name FROM categories WHERE status = "active" ORDER BY parent_id IS NOT NULL, parent_id, sort_order, name');
$categories = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT category_id FROM seller_categories WHERE seller_id = ?');
$stmt->execute([$userId]);
$selectedCategories = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

function seller_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'store';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);

    $businessName = trim((string) ($_POST['business_name'] ?? ''));
    $storeSlug = seller_slugify((string) ($_POST['store_slug'] ?? $businessName));
    $description = trim((string) ($_POST['store_description'] ?? ''));
    $location = trim((string) ($_POST['store_location'] ?? ''));
    $phone = trim((string) ($_POST['business_phone'] ?? ''));
    $address = trim((string) ($_POST['business_address'] ?? ''));
    $categoryIds = array_values(array_unique(array_map('intval', $_POST['category_ids'] ?? [])));

    if ($businessName === '') {
        $error = 'Enter your business or store name.';
    } elseif ($storeSlug === '') {
        $error = 'Enter a valid store URL name.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id FROM seller_profiles
             WHERE store_slug = ? AND user_id <> ?
             LIMIT 1'
        );
        $stmt->execute([$storeSlug, $userId]);

        if ($stmt->fetch()) {
            $error = 'That store URL is already in use. Choose another one.';
        } else {
            try {
                if ($store) {
                    $stmt = $pdo->prepare(
                        'UPDATE seller_profiles
                         SET business_name = ?, store_slug = ?, store_description = ?,
                             store_location = ?, business_phone = ?, business_address = ?
                         WHERE user_id = ?'
                    );
                    $stmt->execute([
                        $businessName,
                        $storeSlug,
                        $description !== '' ? $description : null,
                        $location !== '' ? $location : null,
                        $phone !== '' ? $phone : null,
                        $address !== '' ? $address : null,
                        $userId,
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO seller_profiles
                         (user_id, business_name, store_slug, store_description, store_location, business_phone, business_address, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
                    );
                    $stmt->execute([
                        $userId,
                        $businessName,
                        $storeSlug,
                        $description !== '' ? $description : null,
                        $location !== '' ? $location : null,
                        $phone !== '' ? $phone : null,
                        $address !== '' ? $address : null,
                    ]);
                }

                $pdo->prepare('DELETE FROM seller_categories WHERE seller_id = ?')->execute([$userId]);
                if ($categoryIds !== []) {
                    $categoryStmt = $pdo->prepare('INSERT INTO seller_categories (seller_id, category_id) VALUES (?, ?)');
                    foreach ($categoryIds as $categoryId) {
                        $categoryStmt->execute([$userId, $categoryId]);
                    }
                }

                flash('success', 'Your store profile has been saved.');
                redirect('/seller/dashboard.php');
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $error = 'Your store profile could not be saved.';
            }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0B5CFF"><title>Store Setup · DogoFlow</title><link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>"><?php require __DIR__ . '/../partials/tailwind.php'; ?></head><body class="min-h-screen bg-slate-50 text-slate-900"><?php $sellerActive = 'store-profile'; require __DIR__ . '/../partials/seller_header.php'; ?><main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8"><div class="mb-7"><p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600">Store profile</p><h1 class="mt-2 text-3xl font-black">Build your storefront</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Give customers a clear identity for your business. Your store name, description and contact details appear on your public storefront.</p></div><?php if($error): ?><div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"><?= e($error) ?></div><?php endif; ?><form method="post" class="space-y-5"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-extrabold">Business identity</h2><div class="mt-5 grid gap-5"><div><label class="mb-2 block text-sm font-bold" for="business_name">Business / store name</label><input id="business_name" name="business_name" maxlength="180" required value="<?= e($businessName) ?>" placeholder="Your business name" class="h-12 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-500"></div><div><label class="mb-2 block text-sm font-bold" for="store_slug">Store URL name</label><div class="flex overflow-hidden rounded-xl border border-slate-200 focus-within:border-blue-500"><span class="flex items-center bg-slate-50 px-3 text-xs font-semibold text-slate-400">/store/</span><input id="store_slug" name="store_slug" maxlength="200" value="<?= e($storeSlug) ?>" placeholder="your-store-name" class="h-12 min-w-0 flex-1 border-0 px-3 text-sm outline-none"></div><p class="mt-2 text-xs text-slate-500">Use a short, memorable name for your public store link.</p></div><div><label class="mb-2 block text-sm font-bold" for="store_description">Store description</label><textarea id="store_description" name="store_description" rows="5" maxlength="2000" placeholder="Tell customers what your business sells and what makes it useful." class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-500"><?= e($description) ?></textarea></div></div></section><section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-extrabold">Contact & location</h2><div class="mt-5 grid gap-5 md:grid-cols-2"><div><label class="mb-2 block text-sm font-bold" for="store_location">Location</label><input id="store_location" name="store_location" maxlength="180" value="<?= e($location) ?>" placeholder="e.g. Accra, Ghana" class="h-12 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-500"></div><div><label class="mb-2 block text-sm font-bold" for="business_phone">Business phone</label><input id="business_phone" name="business_phone" maxlength="30" value="<?= e($phone) ?>" placeholder="024 000 0000" class="h-12 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-500"></div><div class="md:col-span-2"><label class="mb-2 block text-sm font-bold" for="business_address">Business address <span class="font-normal text-slate-400">(optional)</span></label><textarea id="business_address" name="business_address" rows="3" maxlength="255" placeholder="Business address" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-500"><?= e($address) ?></textarea></div></div></section><section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-extrabold">What do you sell?</h2><p class="mt-1 mb-5 text-sm text-slate-500">Choose categories that best describe your business.</p><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"><?php foreach($categories as $category): ?><label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-medium hover:border-blue-300 hover:bg-blue-50"><input class="h-4 w-4 accent-blue-600" type="checkbox" name="category_ids[]" value="<?= (int)$category['id'] ?>" <?= in_array((int)$category['id'],$selectedCategories,true)?'checked':'' ?>><span><?= e($category['name']) ?></span></label><?php endforeach; ?></div></section><div class="flex flex-col-reverse justify-end gap-3 sm:flex-row"><a href="<?= url('/seller/dashboard.php') ?>" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-bold text-slate-700">Cancel</a><button class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white hover:bg-blue-700" type="submit">Save store profile</button></div></form></main></body></html>