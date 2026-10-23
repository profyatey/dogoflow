<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/catalog/ProductImages.php';
Auth::requireApprovedSeller();

$pdo = db();
$userId = (int) Auth::userId();
$sellerPlan = SellerPlan::get($userId);
$id = (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0);
$error = flash('error');

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND seller_id = ? LIMIT 1');
$stmt->execute([$id, $userId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}

$stmt = $pdo->query(
    'SELECT id, name
     FROM categories
     WHERE status = "active"
     ORDER BY parent_id IS NOT NULL, parent_id, sort_order, name'
);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare('SELECT category_id FROM product_categories WHERE product_id = ?');
$stmt->execute([$id]);
$selectedCategories = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
$gallery = ProductImages::gallery($pdo, $id, (string) ($product['image'] ?? ''));

function edit_category_ids_are_active(PDO $pdo, array $categoryIds): bool
{
    if ($categoryIds === []) {
        return false;
    }
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE status = "active" AND id IN (' . $placeholders . ')');
    $stmt->execute($categoryIds);
    return (int) $stmt->fetchColumn() === count($categoryIds);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);

    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
    $rawCategoryIds = $_POST['category_ids'] ?? [];
    $categoryIds = is_array($rawCategoryIds)
        ? array_values(array_unique(array_filter(array_map('intval', $rawCategoryIds), static fn (int $categoryId): bool => $categoryId > 0)))
        : [];

    if ($name === '' || mb_strlen($name) > 180 || $price === false || $price < 0 || $stock === false || $stock < 0) {
        $error = 'Enter valid product information.';
    } elseif (!$categories) {
        $error = 'Product updates are temporarily unavailable because no active categories exist. Please contact the DogoFlow administrator.';
    } elseif (!edit_category_ids_are_active($pdo, $categoryIds)) {
        $error = 'Select at least one valid active product category.';
    } else {
        $newlySavedPaths = [];
        $oldPathsToClean = [];
        try {
            $uploadsToAdd = ProductImages::multipleUploads($_FILES['images'] ?? null);
            $removeInput = $_POST['remove_image_ids'] ?? [];
            if (!is_array($removeInput)) {
                throw new RuntimeException('The photo removal selection was invalid. Please reload the page.');
            }
            $removeIds = array_values(array_unique(array_filter(array_map('intval', $removeInput), static fn (int $imageId): bool => $imageId > 0)));
            $legacyWasShown = (string) ($_POST['legacy_image_was_shown'] ?? '') === '1';

            $pdo->beginTransaction();
            // Serialize image-limit edits for this seller and lock the product
            // so parallel requests cannot exceed either photo cap.
            $profileLock = $pdo->prepare('SELECT id FROM seller_profiles WHERE user_id = ? FOR UPDATE');
            $profileLock->execute([$userId]);
            if (!$profileLock->fetchColumn()) {
                throw new RuntimeException('Your seller profile could not be found.');
            }
            $productLock = $pdo->prepare('SELECT id, image FROM products WHERE id = ? AND seller_id = ? FOR UPDATE');
            $productLock->execute([$id, $userId]);
            $lockedProduct = $productLock->fetch(PDO::FETCH_ASSOC);
            if (!$lockedProduct) {
                throw new RuntimeException('Product not found or you do not have permission to edit it.');
            }

            $imageStmt = $pdo->prepare(
                'SELECT id, image_path, is_primary, sort_order
                 FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC FOR UPDATE'
            );
            $imageStmt->execute([$id]);
            $rows = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
            $legacyRowId = null;
            $legacyImagePath = trim((string) ($lockedProduct['image'] ?? ''));
            if ($legacyImagePath !== '') {
                foreach ($rows as $existingRow) {
                    if ((string) $existingRow['image_path'] === $legacyImagePath) {
                        if ($legacyWasShown) {
                            $legacyRowId = (int) $existingRow['id'];
                        }
                        break;
                    }
                }
                $legacyImageRepresented = false;
                foreach ($rows as $existingRow) {
                    if ((string) $existingRow['image_path'] === $legacyImagePath) {
                        $legacyImageRepresented = true;
                        break;
                    }
                }
                // A legacy primary that is not represented by the gallery is
                // still a real store photo; migrate it without deleting it.
                if (!$legacyImageRepresented) {
                    $legacyInsert = $pdo->prepare(
                        'INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, 1, 0)'
                    );
                    $legacyInsert->execute([$id, $legacyImagePath]);
                    $legacyRowId = (int) $pdo->lastInsertId();
                    $rows[] = [
                        'id' => $legacyRowId,
                        'image_path' => $legacyImagePath,
                        'is_primary' => 1,
                        'sort_order' => 0,
                    ];
                }
            }

            if ($legacyWasShown && $legacyRowId === null && isset($_POST['remove_legacy_image'])) {
                throw new RuntimeException('This photo list changed. Reload the page and try again.');
            }
            if ($legacyWasShown && $legacyRowId === null && isset($_FILES['replace_legacy_image'])
                && (int) ($_FILES['replace_legacy_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('This photo list changed. Reload the page and try again.');
            }

            $availableIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
            foreach ($removeIds as $removeId) {
                if (!in_array($removeId, $availableIds, true)) {
                    throw new RuntimeException('One of the selected photos was not found. Reload the page and try again.');
                }
            }
            if ($legacyWasShown && isset($_POST['remove_legacy_image']) && $legacyRowId !== null) {
                $removeIds[] = $legacyRowId;
                $removeIds = array_values(array_unique($removeIds));
            }

            $replacementFiles = [];
            foreach ($rows as $row) {
                $imageId = (int) $row['id'];
                if ($legacyWasShown && $legacyRowId !== null && $imageId === $legacyRowId) {
                    $file = $_FILES['replace_legacy_image'] ?? null;
                    $file = is_array($file) ? $file : null;
                    if ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $replacementFiles[$imageId] = $file;
                    }
                } else {
                    $file = ProductImages::nestedUpload($_FILES['replace_images'] ?? null, $imageId);
                    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $replacementFiles[$imageId] = $file;
                    }
                }
            }

            if (array_intersect($removeIds, array_keys($replacementFiles))) {
                throw new RuntimeException('A photo cannot be removed and replaced in the same save. Choose one action for each photo.');
            }

            $currentImageCount = count($rows);
            $removeCount = count($removeIds);
            $addCount = count($uploadsToAdd);
            $finalProductImageCount = $currentImageCount - $removeCount + $addCount;
            if ($addCount > 0 && $finalProductImageCount > (int) SellerPlan::get((int) $userId)['photos_per_product']) {
                throw new RuntimeException('Your plan allows ' . (int) SellerPlan::get((int) $userId)['photos_per_product'] . ' photos per product. Remove a current photo before adding another.');
            }

            $freshPlan = SellerPlan::get($userId, $pdo);
            // Photos are limited to 5 per product; the plan limit applies to product slots only.

            $rowById = [];
            foreach ($rows as $row) {
                $rowById[(int) $row['id']] = $row;
            }
            foreach ($replacementFiles as $imageId => $file) {
                $newPath = ProductImages::saveUpload($file);
                $newlySavedPaths[] = $newPath;
                if (isset($rowById[(int) $imageId])) {
                    $oldPathsToClean[] = (string) $rowById[(int) $imageId]['image_path'];
                }
                $pdo->prepare('UPDATE product_images SET image_path = ? WHERE id = ? AND product_id = ?')
                    ->execute([$newPath, $imageId, $id]);
            }

            foreach ($removeIds as $removeId) {
                foreach ($rows as $row) {
                    if ((int) $row['id'] === $removeId) {
                        $oldPathsToClean[] = (string) $row['image_path'];
                        break;
                    }
                }
                $pdo->prepare('DELETE FROM product_images WHERE id = ? AND product_id = ?')->execute([$removeId, $id]);
            }

            $pdo->prepare(
                'UPDATE products SET name = ?, description = ?, price = ?, stock = ? WHERE id = ? AND seller_id = ?'
            )->execute([$name, $description !== '' ? $description : null, $price, $stock, $id, $userId]);

            $newRowIds = [];
            foreach ($uploadsToAdd as $file) {
                $newPath = ProductImages::saveUpload($file);
                $newlySavedPaths[] = $newPath;
                $addStmt = $pdo->prepare(
                    'INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, 0, 999)'
                );
                $addStmt->execute([$id, $newPath]);
                $newRowIds[] = (int) $pdo->lastInsertId();
            }

            $allStmt = $pdo->prepare(
                'SELECT id, image_path, is_primary, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC'
            );
            $allStmt->execute([$id]);
            $allRows = $allStmt->fetchAll(PDO::FETCH_ASSOC);
            $allIds = array_map(static fn (array $row): int => (int) $row['id'], $allRows);

            $primaryToken = (string) ($_POST['primary_image'] ?? '');
            $requestedPrimaryId = 0;
            if ($primaryToken === 'legacy' && $legacyRowId !== null) {
                $requestedPrimaryId = $legacyRowId;
            } elseif (ctype_digit($primaryToken)) {
                $requestedPrimaryId = (int) $primaryToken;
            }
            if (!in_array($requestedPrimaryId, $allIds, true)) {
                $requestedPrimaryId = 0;
                foreach ($allRows as $row) {
                    if ((int) $row['is_primary'] === 1) {
                        $requestedPrimaryId = (int) $row['id'];
                        break;
                    }
                }
            }
            if ($requestedPrimaryId === 0 && $allRows) {
                $requestedPrimaryId = (int) $allRows[0]['id'];
            }

            if ($requestedPrimaryId !== 0) {
                usort($allRows, static function (array $a, array $b) use ($requestedPrimaryId): int {
                    if ((int) $a['id'] === $requestedPrimaryId) return -1;
                    if ((int) $b['id'] === $requestedPrimaryId) return 1;
                    return ((int) $a['sort_order'] <=> (int) $b['sort_order']) ?: ((int) $a['id'] <=> (int) $b['id']);
                });
            }

            $setImage = $pdo->prepare('UPDATE product_images SET is_primary = ?, sort_order = ? WHERE id = ? AND product_id = ?');
            $primaryPath = null;
            foreach ($allRows as $sortOrder => $row) {
                $isPrimary = (int) $row['id'] === $requestedPrimaryId ? 1 : 0;
                $setImage->execute([$isPrimary, $sortOrder, (int) $row['id'], $id]);
                if ($isPrimary === 1) {
                    $primaryPath = (string) $row['image_path'];
                }
            }
            $pdo->prepare('UPDATE products SET image = ? WHERE id = ? AND seller_id = ?')->execute([$primaryPath, $id, $userId]);

            $pdo->prepare('DELETE FROM product_categories WHERE product_id = ?')->execute([$id]);
            $categoryStmt = $pdo->prepare('INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)');
            foreach ($categoryIds as $categoryId) {
                $categoryStmt->execute([$id, $categoryId]);
            }

            $pdo->commit();
            // Cleanup is deliberately best-effort after commit: a filesystem
            // cleanup issue must not make us remove newly committed uploads.
            try {
                ProductImages::cleanupUnreferenced($pdo, $oldPathsToClean);
            } catch (Throwable $cleanupError) {
                error_log('DogoFlow product image cleanup failed: ' . $cleanupError->getMessage());
            }
            flash('success', 'Product updated successfully.');
            redirect('/seller/products.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ProductImages::cleanupNewUploads($newlySavedPaths);
            error_log($e->getMessage());
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'The product could not be updated.';
        }
    }
}

// Refresh persisted gallery and photo allowance after a failed form submission.
$gallery = ProductImages::gallery($pdo, $id, (string) ($product['image'] ?? ''));
$sellerPlan = SellerPlan::get($userId);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#0B5CFF">
  <title>Edit Product · DogoFlow</title><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>"><?php require __DIR__ . '/../partials/tailwind.php'; ?>
<style>
@keyframes df-upload-progress-slide { from { transform: translateX(-110%); } to { transform: translateX(320%); } }
.df-upload-progress-bar { width: 32%; animation: df-upload-progress-slide 1.25s ease-in-out infinite; }
@media (prefers-reduced-motion: reduce) { .df-upload-progress-bar { animation: none; width: 100%; opacity: .65; } }
</style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<?php $sellerActive = 'products'; require __DIR__ . '/../partials/seller_header.php'; ?>
<main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
  <div class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600">Product management</p><h1 class="mt-2 text-3xl font-black">Edit product</h1><p class="mt-2 text-sm text-slate-500">Keep your listing accurate and manage up to three photos.</p></div><a href="<?= e(url('/seller/products.php')) ?>" class="text-sm font-bold text-slate-600 hover:text-blue-600">← My products</a></div>
  <?php if ($error): ?><div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"><?= e($error) ?></div><?php endif; ?>
  <form id="productForm" method="post" enctype="multipart/form-data" class="space-y-5">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= (int) $id ?>">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
      <div class="grid gap-5 md:grid-cols-2">
        <div class="md:col-span-2"><label class="mb-2 block text-sm font-bold" for="name">Product name</label><input id="name" name="name" maxlength="180" required value="<?= e((string) ($_POST['name'] ?? $product['name'])) ?>" class="h-12 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-500"></div>
        <div class="md:col-span-2"><label class="mb-2 block text-sm font-bold" for="description">Description</label><textarea id="description" name="description" rows="7" maxlength="5000" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-500"><?= e((string) ($_POST['description'] ?? $product['description'] ?? '')) ?></textarea></div>
        <div><label class="mb-2 block text-sm font-bold" for="price">Price (GHS)</label><div class="relative"><span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400">GH₵</span><input id="price" type="number" name="price" min="0" step="0.01" required value="<?= e((string) ($_POST['price'] ?? $product['price'])) ?>" class="h-12 w-full rounded-xl border border-slate-200 pl-14 pr-4 text-sm outline-none focus:border-blue-500"></div></div>
        <div><label class="mb-2 block text-sm font-bold" for="stock">Stock quantity</label><input id="stock" type="number" name="stock" min="0" step="1" required value="<?= e((string) ($_POST['stock'] ?? $product['stock'])) ?>" class="h-12 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-500"></div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
      <div class="mb-4"><h2 class="text-lg font-extrabold">Current product photos</h2><p class="mt-1 text-sm text-slate-500">Choose the main photo, replace an existing photo, or remove photos you no longer need.</p></div>
      <?php if ($gallery): ?>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <?php foreach ($gallery as $photo): $photoId = (int) $photo['id']; $legacyPhoto = $photoId === 0; $radioValue = $legacyPhoto ? 'legacy' : (string) $photoId; ?>
            <article class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
              <button class="photo-open block w-full cursor-zoom-in" type="button" data-full="<?= e(ProductImages::publicUrl((string) $photo['image_path'])) ?>" data-alt="Current product photo"><img class="h-44 w-full object-contain" src="<?= e(ProductImages::publicUrl((string) $photo['image_path'])) ?>" alt="Current product photo"></button>
              <div class="space-y-3 p-3">
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="radio" name="primary_image" value="<?= e($radioValue) ?>" <?= !empty($photo['is_primary']) ? 'checked' : '' ?>> Use as main photo</label>
                <?php if ($legacyPhoto): ?><input type="hidden" name="legacy_image_was_shown" value="1"><label class="flex items-center gap-2 text-sm text-rose-700"><input type="checkbox" name="remove_legacy_image" value="1"> Remove this photo</label><label class="block text-xs font-semibold text-slate-600">Replace this photo<input class="replace-preview mt-2 block w-full text-xs" type="file" name="replace_legacy_image" accept="image/jpeg,image/png,image/webp"></label>
                <?php else: ?><label class="flex items-center gap-2 text-sm text-rose-700"><input type="checkbox" name="remove_image_ids[]" value="<?= $photoId ?>"> Remove this photo</label><label class="block text-xs font-semibold text-slate-600">Replace this photo<input class="replace-preview mt-2 block w-full text-xs" type="file" name="replace_images[<?= $photoId ?>]" accept="image/jpeg,image/png,image/webp"></label><?php endif; ?>
                <img class="replacement-preview-image hidden mt-2 h-24 w-full rounded-lg bg-white object-contain" alt="Replacement photo preview"><p class="replacement-preview-text hidden text-xs text-blue-700"></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?><p class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500">No photos uploaded for this product yet.</p><?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
      <label class="mb-2 block text-sm font-bold" for="images">Add more photos (up to <?= (int) SellerPlan::get((int) $userId)['photos_per_product'] ?> per product)</label>
      <input id="images" type="file" name="images[]" data-current-count="<?= count($gallery) ?>" accept="image/jpeg,image/png,image/webp" multiple class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm">
      <p class="mt-2 text-xs leading-5 text-slate-500">Current plan: <?= e(ucfirst((string) $sellerPlan['plan'])) ?> · <?= (int) $sellerPlan['slots_used'] ?> of <?= (int) $sellerPlan['slot_limit'] ?> product slots used · <?= (int) $sellerPlan['remaining'] ?> product slots remaining. Replacing a photo does not consume an extra slot. JPG, PNG or WebP · maximum 5 MB per photo.</p>
      <div id="newImagePreview" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3" aria-live="polite"></div><p id="newImageError" class="mt-2 hidden text-sm font-semibold text-rose-700"></p>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-extrabold">Categories</h2><p class="mt-1 mb-5 text-sm text-slate-500">Choose at least one active category.</p>
      <?php if ($categories): ?><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($categories as $category): ?><label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-medium hover:border-blue-300 hover:bg-blue-50"><input class="h-4 w-4 accent-blue-600" type="checkbox" name="category_ids[]" value="<?= (int) $category['id'] ?>" <?= in_array((int) $category['id'], $selectedCategories, true) ? 'checked' : '' ?>><span><?= e($category['name']) ?></span></label><?php endforeach; ?></div><?php else: ?><div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>No active categories are available.</strong></div><?php endif; ?>
    </section>
    <div id="uploadProgress" class="hidden rounded-xl border border-blue-100 bg-blue-50 p-4" role="status" aria-live="polite" aria-atomic="true">
      <div class="flex items-center justify-between gap-3"><p id="uploadProgressText" class="text-sm font-semibold text-blue-950">Uploading product photos…</p><span class="shrink-0 text-xs font-medium text-blue-700">Please wait</span></div>
      <div class="mt-3 h-2 overflow-hidden rounded-full bg-blue-100" role="progressbar" aria-label="Product update in progress"><div class="df-upload-progress-bar h-full rounded-full bg-blue-600"></div></div>
      <p class="mt-2 text-xs text-blue-800">Keep this page open and do not press the submit button again.</p>
    </div>
    <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row"><a href="<?= e(url('/seller/products.php')) ?>" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-bold text-slate-700">Cancel</a><button class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50" type="submit" <?= !$categories ? 'disabled' : '' ?>>Save changes</button></div>
  </form>
</main>
<div id="photoLightbox" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/90 p-4" role="dialog" aria-modal="true" aria-label="Product photo preview"><button id="photoLightboxClose" class="absolute right-5 top-5 rounded-full bg-white px-4 py-2 font-bold text-slate-900" type="button">Close ×</button><img id="photoLightboxImage" class="max-h-[85vh] max-w-[95vw] object-contain" src="" alt="Product photo preview"></div>
<script>
(() => {
  const addInput = document.getElementById('images');
  const preview = document.getElementById('newImagePreview');
  const previewError = document.getElementById('newImageError');
  let objectUrls = [];
  if (addInput) addInput.addEventListener('change', () => {
    objectUrls.forEach(url => URL.revokeObjectURL(url)); objectUrls = []; preview.replaceChildren(); previewError.classList.add('hidden');
    const files = Array.from(addInput.files || []);
    const selectedRemovals = document.querySelectorAll('input[name="remove_image_ids[]"]:checked').length + (document.querySelector('input[name="remove_legacy_image"]:checked') ? 1 : 0);
    const allowedSlots = Math.max(0, 3 - Number(addInput.dataset.currentCount || 0) + selectedRemovals);
    if (files.length > allowedSlots) { previewError.textContent = `You can add ${allowedSlots} more photo(s) to this product after the selected removals.`; previewError.classList.remove('hidden'); addInput.value = ''; return; }
    files.forEach(file => { const card = document.createElement('div'); card.className='overflow-hidden rounded-xl border border-slate-200 bg-slate-50'; const img=document.createElement('img'); img.className='h-36 w-full object-contain'; img.alt=file.name; const objectUrl=URL.createObjectURL(file); objectUrls.push(objectUrl); img.src=objectUrl; const label=document.createElement('p'); label.className='truncate px-3 py-2 text-xs font-medium text-slate-600'; label.textContent=file.name; card.append(img,label); preview.append(card); });
  });
  document.querySelectorAll('input[name="remove_image_ids[]"], input[name="remove_legacy_image"]').forEach(input => input.addEventListener('change', () => { if (addInput && addInput.files?.length) addInput.dispatchEvent(new Event('change')); }));
  document.querySelectorAll('.replace-preview').forEach(input => input.addEventListener('change', () => {
    const article = input.closest('article');
    const text = article?.querySelector('.replacement-preview-text');
    const image = article?.querySelector('.replacement-preview-image');
    if (input.dataset.objectUrl) URL.revokeObjectURL(input.dataset.objectUrl);
    input.dataset.objectUrl = '';
    const file = input.files?.[0];
    if (text) { text.textContent = file ? 'Replacement selected: ' + file.name : ''; text.classList.toggle('hidden', !file); }
    if (image) {
      if (file) { input.dataset.objectUrl = URL.createObjectURL(file); image.src = input.dataset.objectUrl; image.classList.remove('hidden'); }
      else { image.removeAttribute('src'); image.classList.add('hidden'); }
    }
    if (file) { const remove = article?.querySelector('input[type="checkbox"]'); if (remove) remove.checked = false; }
  }));
  const lightbox=document.getElementById('photoLightbox'); const lightboxImage=document.getElementById('photoLightboxImage');
  function closeLightbox(){lightbox.classList.add('hidden');lightbox.classList.remove('flex');lightboxImage.src='';}
  document.querySelectorAll('.photo-open').forEach(button=>button.addEventListener('click',()=>{lightboxImage.src=button.dataset.full||'';lightboxImage.alt=button.dataset.alt||'Product photo';lightbox.classList.remove('hidden');lightbox.classList.add('flex');}));
  document.getElementById('photoLightboxClose').addEventListener('click',closeLightbox); lightbox.addEventListener('click',e=>{if(e.target===lightbox)closeLightbox();}); document.addEventListener('keydown',e=>{if(e.key==='Escape')closeLightbox();});
  const form = document.getElementById('productForm');
  const progress = document.getElementById('uploadProgress');
  const progressText = document.getElementById('uploadProgressText');
  let submissionStarted = false;

  form?.addEventListener('submit', (event) => {
    const categoryChecks = form.querySelectorAll('input[name="category_ids[]"]');
    if (categoryChecks.length && !Array.from(categoryChecks).some((checkbox) => checkbox.checked)) {
      event.preventDefault();
      alert('Please select at least one product category.');
      return;
    }

    if (submissionStarted) {
      event.preventDefault();
      return;
    }

    submissionStarted = true;
    const hasFiles = Array.from(form.querySelectorAll('input[type="file"]'))
      .some((fileInput) => fileInput.files && fileInput.files.length > 0);
    progressText.textContent = hasFiles
      ? 'Uploading product photos…'
      : 'Saving your product changes…';
    progress.classList.remove('hidden');
    form.setAttribute('aria-busy', 'true');

    form.querySelectorAll('button[type="submit"]').forEach((button) => {
      button.dataset.originalLabel = button.textContent.trim();
      button.dataset.originalDisabled = button.disabled ? '1' : '0';
      button.disabled = true;
      button.textContent = hasFiles ? 'Uploading…' : 'Saving…';
    });
  });

  // If the browser restores this page from its back/forward cache, unlock it.
  window.addEventListener('pageshow', () => {
    submissionStarted = false;
    progress.classList.add('hidden');
    form?.removeAttribute('aria-busy');
    form?.querySelectorAll('button[type="submit"]').forEach((button) => {
      if (button.dataset.originalLabel !== undefined) {
        button.textContent = button.dataset.originalLabel;
        button.disabled = button.dataset.originalDisabled === '1';
        delete button.dataset.originalLabel;
        delete button.dataset.originalDisabled;
      }
    });
  });
})();
</script>
</body></html>
