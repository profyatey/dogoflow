<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/catalog/ProductImages.php';
Auth::requireApprovedSeller();

$pdo = db();
$error = flash('error');
$sellerId = (int) Auth::userId();
$sellerPlan = SellerPlan::get($sellerId);

function product_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'product';
}

$stmt = $pdo->query(
    'SELECT id, name, parent_id
     FROM categories
     WHERE status = "active"
     ORDER BY parent_id IS NOT NULL, parent_id, sort_order, name'
);
$categories = $stmt->fetchAll();

function category_ids_are_active(PDO $pdo, array $categoryIds): bool
{
    if ($categoryIds === []) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM categories WHERE status = "active" AND id IN (' . $placeholders . ')'
    );
    $stmt->execute($categoryIds);

    return (int) $stmt->fetchColumn() === count($categoryIds);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);

    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);
    $categoryIds = array_values(array_unique(array_map('intval', $_POST['category_ids'] ?? [])));

    if ($name === '' || $price < 0 || $stock < 0) {
        $error = 'Enter valid product information.';
    } elseif (!$categories) {
        $error = 'Product upload is temporarily unavailable because no active categories exist. Please contact the DogoFlow administrator.';
    } elseif (!category_ids_are_active($pdo, $categoryIds)) {
        $error = 'Select at least one valid active product category.';
    } else {
        $uploadedImages = [];
        try {
            $base = product_slugify($name);
            $slug = $base;
            $suffix = 2;
            $check = $pdo->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');

            while (true) {
                $check->execute([$slug]);
                if (!$check->fetch()) {
                    break;
                }
                $slug = $base . '-' . $suffix++;
            }

            $uploads = ProductImages::multipleUploads($_FILES['images'] ?? null);
            $uploadCount = count($uploads);

            if ($uploadCount > (int) SellerPlan::get((int) $sellerId)['photos_per_product']) {
                throw new RuntimeException('Your plan allows ' . (int) SellerPlan::get((int) $sellerId)['photos_per_product'] . ' photos per product (main photo plus extra photos).');
            }

            // Serialize store-wide photo allowance changes for this seller.
            $pdo->beginTransaction();
            $profileLock = $pdo->prepare('SELECT id FROM seller_profiles WHERE user_id = ? FOR UPDATE');
            $profileLock->execute([$sellerId]);
            if (!$profileLock->fetchColumn()) {
                throw new RuntimeException('Your seller profile could not be found.');
            }
            $sellerPlan = SellerPlan::get($sellerId, $pdo);
            if ($sellerPlan['remaining'] < 1) {
                throw new RuntimeException(
                    'Your ' . ucfirst($sellerPlan['plan']) . ' plan allows ' . $sellerPlan['slot_limit'] . ' products and all your slots are in use. '
                    . ($sellerPlan['plan'] === 'free' ? 'Upgrade to Pro for 10 product slots, or remove a product first.' : 'Remove a product to add a new one.')
                );
            }

            foreach ($uploads as $file) {
                $uploadedImages[] = ProductImages::saveUpload($file);
            }

            $image = $uploadedImages[0] ?? null;

            $stmt = $pdo->prepare(
                'INSERT INTO products (seller_id, name, slug, description, price, stock, image, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "active")'
            );
            $stmt->execute([
                Auth::userId(),
                $name,
                $slug,
                $description !== '' ? $description : null,
                $price,
                $stock,
                $image,
            ]);

            $productId = (int) $pdo->lastInsertId();
            if ($uploadedImages) {
                $imageStmt = $pdo->prepare(
                    'INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)'
                );

                foreach ($uploadedImages as $index => $imagePath) {
                    $imageStmt->execute([$productId, $imagePath, $index === 0 ? 1 : 0, $index]);
                }
            }

            $categoryStmt = $pdo->prepare(
                'INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)'
            );

            foreach ($categoryIds as $categoryId) {
                $categoryStmt->execute([$productId, $categoryId]);
            }

            $pdo->commit();

            flash('success', 'Product published successfully and is now available in the marketplace.');
            redirect('/seller/products.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            ProductImages::cleanupNewUploads($uploadedImages);

            error_log($e->getMessage());
            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'The product could not be created.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0B5CFF"><title>Add Product · DogoFlow</title><link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>"><?php require __DIR__ . '/../partials/tailwind.php'; ?><style>
@keyframes df-upload-progress-slide { from { transform: translateX(-110%); } to { transform: translateX(320%); } }
.df-upload-progress-bar { width: 32%; animation: df-upload-progress-slide 1.25s ease-in-out infinite; }
@media (prefers-reduced-motion: reduce) { .df-upload-progress-bar { animation: none; width: 100%; opacity: .65; } }
</style>
</head><body class="min-h-screen bg-slate-50 text-slate-900"><?php $sellerActive = 'products'; require __DIR__ . '/../partials/seller_header.php'; ?><main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8"><div class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600">Product management</p><h1 class="mt-2 text-3xl font-black">Add a product</h1><p class="mt-2 text-sm text-slate-500">Publish a clear, well-priced listing to your DogoFlow storefront.</p></div><a href="<?= url('/seller/products.php') ?>" class="text-sm font-bold text-slate-600 hover:text-blue-600">← My products</a></div><?php if($error): ?><div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"><?= e($error) ?></div><?php endif; ?><form id="productForm" method="post" enctype="multipart/form-data" class="space-y-5"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="mb-5"><h2 class="text-lg font-extrabold">Product information</h2><p class="mt-1 text-sm text-slate-500">Give customers the details they need to make a confident purchase.</p></div><div class="grid gap-5 md:grid-cols-2"><div class="md:col-span-2"><label class="mb-2 block text-sm font-bold" for="name">Product name</label><input id="name" name="name" maxlength="180" required value="<?= e((string)($_POST['name']??'')) ?>" placeholder="e.g. Wireless Bluetooth Headphones" class="h-12 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-500"></div><div class="md:col-span-2"><label class="mb-2 block text-sm font-bold" for="description">Description</label><textarea id="description" name="description" rows="7" maxlength="5000" placeholder="Describe the product, features and important details." class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-500"><?= e((string)($_POST['description']??'')) ?></textarea></div><div><label class="mb-2 block text-sm font-bold" for="price">Price (GHS)</label><div class="relative"><span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400">GH₵</span><input id="price" type="number" name="price" min="0" step="0.01" required value="<?= e((string)($_POST['price']??'')) ?>" placeholder="0.00" class="h-12 w-full rounded-xl border border-slate-200 pl-14 pr-4 text-sm outline-none focus:border-blue-500"></div></div><div><label class="mb-2 block text-sm font-bold" for="stock">Stock quantity</label><input id="stock" type="number" name="stock" min="0" step="1" required value="<?= e((string)($_POST['stock']??'0')) ?>" class="h-12 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-500"></div><div class="md:col-span-2"><label class="mb-2 block text-sm font-bold" for="images">Product photos (main photo plus up to <?= (int) SellerPlan::get((int) Auth::userId(), db())['extra_photos'] ?> extra)</label><input id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm"><p class="mt-2 text-xs leading-5 text-slate-500">Maximum 5 photos per product. Your <?= e(ucfirst($sellerPlan['plan'])) ?> plan allows <?= (int)$sellerPlan['slot_limit'] ?> products, each with up to <?= (int) SellerPlan::get((int) Auth::userId(), db())['photos_per_product'] ?> photos; <?= (int)$sellerPlan['remaining'] ?> remaining. JPG, PNG or WebP · maximum 5 MB each.</p><?php if($sellerPlan['remaining']===0): ?><a class="mt-1 inline-block text-xs font-bold text-blue-600" href="<?= url('/seller/upgrade.php') ?>">Upgrade to Pro for 10 products →</a><?php endif; ?><div id="imagePreview" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3" aria-live="polite"></div><p id="imagePreviewError" class="mt-2 hidden text-sm font-semibold text-rose-700"></p></div></div></section><section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="mb-5"><h2 class="text-lg font-extrabold">Categories</h2><p class="mt-1 text-sm text-slate-500">Select at least one active category so customers can find your product.</p></div><?php if($categories): ?><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"><?php foreach($categories as $category): ?><label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-medium hover:border-blue-300 hover:bg-blue-50"><input class="h-4 w-4 accent-blue-600" type="checkbox" name="category_ids[]" value="<?= (int)$category['id'] ?>" <?= in_array((int)$category['id'],array_map('intval',$_POST['category_ids']??[]),true)?'checked':'' ?>><span><?= e($category['name']) ?></span></label><?php endforeach; ?></div><?php else: ?><div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>No active categories are available.</strong><p class="mt-1">Ask the DogoFlow administrator to create or activate a category.</p></div><?php endif; ?></section><div id="uploadProgress" class="hidden rounded-xl border border-blue-100 bg-blue-50 p-4" role="status" aria-live="polite" aria-atomic="true">
  <div class="flex items-center justify-between gap-3"><p id="uploadProgressText" class="text-sm font-semibold text-blue-950">Uploading product photos…</p><span class="shrink-0 text-xs font-medium text-blue-700">Please wait</span></div>
  <div class="mt-3 h-2 overflow-hidden rounded-full bg-blue-100" role="progressbar" aria-label="Product upload in progress"><div class="df-upload-progress-bar h-full rounded-full bg-blue-600"></div></div>
  <p class="mt-2 text-xs text-blue-800">Keep this page open and do not press the submit button again.</p>
</div><div class="flex flex-col-reverse justify-end gap-3 sm:flex-row"><a href="<?= url('/seller/products.php') ?>" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-bold text-slate-700">Cancel</a><button class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50" type="submit" <?= !$categories?'disabled':'' ?>>Publish product</button></div></form></main><script>
(() => {
  const input = document.getElementById('images');
  const preview = document.getElementById('imagePreview');
  const error = document.getElementById('imagePreviewError');
  const MAX = <?= (int) SellerPlan::get((int) Auth::userId(), db())['photos_per_product'] ?>;
  const slotNames = ['Main photo'];
  let picked = new DataTransfer();
  let objectUrls = [];

  const syncInput = () => { if (input) input.files = picked.files; };
  const render = () => {
    objectUrls.forEach(u => URL.revokeObjectURL(u));
    objectUrls = [];
    preview.replaceChildren();
    Array.from(picked.files).forEach((file, index) => {
      const card = document.createElement('div');
      card.className = 'relative overflow-hidden rounded-xl border border-slate-200 bg-slate-50';
      const img = document.createElement('img');
      img.className = 'h-36 w-full object-contain';
      img.alt = file.name;
      const url = URL.createObjectURL(file);
      objectUrls.push(url);
      img.src = url;
      const tag = document.createElement('span');
      tag.className = 'absolute left-2 top-2 rounded-md bg-blue-600 px-2 py-1 text-[11px] font-bold text-white';
      tag.textContent = slotNames[index] || ('Extra photo ' + index);
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'absolute right-2 top-2 rounded-md bg-white px-2 py-1 text-xs font-bold text-rose-700 shadow hover:bg-rose-50';
      remove.textContent = 'Remove';
      remove.addEventListener('click', () => {
        const next = new DataTransfer();
        Array.from(picked.files).forEach((f, i) => { if (i !== index) next.items.add(f); });
        picked = next;
        syncInput();
        error.classList.add('hidden');
        render();
      });
      const label = document.createElement('p');
      label.className = 'truncate px-3 py-2 text-xs font-medium text-slate-600';
      label.textContent = file.name;
      card.append(img, tag, remove, label);
      preview.append(card);
    });
    const left = MAX - picked.files.length;
    const hint = document.createElement('p');
    hint.className = 'col-span-full text-sm font-semibold text-blue-900';
    hint.textContent = picked.files.length === 0
      ? 'Add up to ' + MAX + ' photos for this product: 1 main photo and ' + (MAX - 1) + ' extra. The first photo is the main photo.'
      : (left > 0 ? 'You can add ' + left + ' more photo' + (left === 1 ? '' : 's') + '. The first photo is the main photo.' : 'Maximum of ' + MAX + ' photos reached. Remove one to change it.');
    preview.append(hint);
  };

  if (input && preview && error) {
    input.addEventListener('change', () => {
      const next = new DataTransfer();
      Array.from(picked.files).forEach(f => next.items.add(f));
      let dropped = 0;
      Array.from(input.files || []).forEach(f => {
        if (next.files.length < MAX) next.items.add(f); else dropped++;
      });
      picked = next;
      syncInput();
      if (dropped) {
        error.textContent = 'Each product can have up to ' + MAX + ' photos. Remove one to add another.';
        error.classList.remove('hidden');
      } else {
        error.classList.add('hidden');
      }
      render();
    });
    render();
  }
  const form = document.getElementById('productForm');
  const progress = document.getElementById('uploadProgress');
  const progressText = document.getElementById('uploadProgressText');
  let submissionStarted = false;

  if (form) {
    form.addEventListener('submit', (event) => {
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
        : 'Saving your product…';
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
      form.removeAttribute('aria-busy');
      form.querySelectorAll('button[type="submit"]').forEach((button) => {
        if (button.dataset.originalLabel !== undefined) {
          button.textContent = button.dataset.originalLabel;
          button.disabled = button.dataset.originalDisabled === '1';
          delete button.dataset.originalLabel;
          delete button.dataset.originalDisabled;
        }
      });
    });
  }
})();
</script></body></html>