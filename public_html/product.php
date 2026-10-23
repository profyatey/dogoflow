<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/catalog/ProductImages.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$productId = max(0, (int) ($_GET['id'] ?? 0));
if ($slug === '' && $productId < 1) {
    http_response_code(404);
    exit('Product not found.');
}
$where = $slug !== '' ? 'p.slug = :slug' : 'p.id = :product_id';
$params = $slug !== '' ? ['slug' => $slug] : ['product_id' => $productId];
$stmt = db()->prepare(
    "SELECT
        p.*,
        sp.business_name,
        sp.store_slug,
        sp.seller_code,
        sp.business_phone
     FROM products p
     INNER JOIN users u
        ON u.id = p.seller_id
       AND u.role = 'seller'
       AND u.status = 'active'
     INNER JOIN seller_profiles sp
        ON sp.user_id = p.seller_id
       AND sp.status = 'active'
       AND sp.approval_status = 'approved'
     WHERE p.status = 'active'
       AND {$where}
     LIMIT 1"
);
$stmt->execute($params);
$product = $stmt->fetch();

/** Builds a WhatsApp click-to-chat link. Accepts 0XXXXXXXXX (Ghana), 233XXXXXXXXX or 00-prefixed numbers. */
if (!function_exists('dogoflow_whatsapp_link')) {
    function dogoflow_whatsapp_link(?string $phone, string $message): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (preg_match('/^0\d{9}$/', $digits)) {
            $digits = '233' . substr($digits, 1);
        }
        if (!preg_match('/^\d{8,15}$/', $digits)) {
            return null;
        }
        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }
}

$whatsappHref = null;
if (!function_exists('dogoflow_tel_link')) {
    function dogoflow_tel_link(?string $phone): ?string
    {
        $digits = preg_replace('/\\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (preg_match('/^0\\d{9}$/', $digits)) {
            $digits = '233' . substr($digits, 1);
        }
        return preg_match('/^\\d{8,15}$/', $digits) ? 'tel:+' . $digits : null;
    }
}
if ($product) {
    $productPath = url('/product.php?id=' . (int) $product['id']);
    $productUrl = preg_match('#^https?://#i', $productPath)
        ? $productPath
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'dogoflow.com') . $productPath;
    $whatsappMessage = 'Hello ' . (string) $product['business_name'] . ', I am interested in "' . (string) $product['name']
        . '" (GH₵ ' . number_format((float) $product['price'], 2) . ') I found on DogoFlow. Is it available, and what is your best price?'
        . "\n" . $productUrl;
    $whatsappHref = dogoflow_whatsapp_link($product['business_phone'] ?? null, $whatsappMessage);
}

if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}

$categoryStmt = db()->prepare(
    "SELECT c.name, c.slug
     FROM product_categories pc
     INNER JOIN categories c ON c.id = pc.category_id
     WHERE pc.product_id = ?
       AND c.status = 'active'
     ORDER BY c.parent_id IS NOT NULL, c.sort_order, c.name"
);
$categoryStmt->execute([(int) $product['id']]);
$productCategories = $categoryStmt->fetchAll();

$pageTitle = (string) $product['name'] . ' · DogoFlow';
$description = trim((string) ($product['description'] ?? ''));
if ($description === '') {
    $description = 'Discover ' . $product['name'] . ' on DogoFlow.';
}
$productImages = ProductImages::gallery(db(), (int) $product['id'], (string) ($product['image'] ?? ''));
$galleryItems = [];
foreach ($productImages as $productImage) {
    $path = trim((string) ($productImage['image_path'] ?? ''));
    if ($path !== '') {
        $galleryItems[] = [
            'id' => (int) ($productImage['id'] ?? 0),
            'url' => ProductImages::publicUrl($path),
            'path' => $path,
            'is_primary' => !empty($productImage['is_primary']),
        ];
    }
}
$image = $galleryItems[0]['url'] ?? null;
$canonical = APP_URL . '/product.php?slug=' . rawurlencode((string) $product['slug']);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($pageTitle) ?></title><?php render_og_meta($pageTitle,mb_substr($description,0,180),$canonical,$image,'product'); ?><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>"><?php require __DIR__ . '/partials/tailwind.php'; ?><style>
.pd3{--blue:#0088ee;--navy:#0a1628;--muted:#64748b;--line:#e6edf5;--soft:#f6f9fd;background:#fff;color:var(--navy);min-height:100vh}.pd3 .wrap{width:min(1100px,calc(100% - 30px));margin:auto}.pd3-nav{border-bottom:1px solid var(--line);height:68px;display:flex;align-items:center}.pd3-nav .wrap{display:flex;align-items:center;justify-content:space-between}.pd3-nav img{height:40px}.pd3-links{display:flex;gap:25px}.pd3-links a{font-size:.86rem;font-weight:750;color:#475569;text-decoration:none}.pd3-main{padding:30px 0 80px}.pd3-back{font-size:.78rem;color:var(--blue);font-weight:850;text-decoration:none}.pd3-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:42px;margin-top:20px;align-items:start}.pd3-gallery{border:1px solid var(--line);border-radius:22px;overflow:hidden;background:var(--soft);aspect-ratio:1/1;display:flex;align-items:center;justify-content:center}.pd3-gallery img{width:100%;height:100%;object-fit:contain}.pd3-info{padding:8px 0}.pd3-badges{display:flex;gap:7px;flex-wrap:wrap}.pd3-badge{background:#eef7ff;color:var(--blue);border-radius:999px;padding:6px 10px;font-size:.68rem;font-weight:850;text-decoration:none}.pd3-badge.gray{background:#f1f5f9;color:#475569}.pd3-info h1{font-size:clamp(2rem,4vw,3rem);line-height:1.02;letter-spacing:-.05em;margin:18px 0 8px}.pd3-price{font-size:1.65rem;color:var(--blue);font-weight:900}.pd3-seller{margin:9px 0 24px;color:var(--muted);font-size:.82rem}.pd3-seller a{color:var(--blue);font-weight:850;text-decoration:none}.pd3-description{color:#475569;line-height:1.8;font-size:.92rem;border-top:1px solid var(--line);padding-top:20px}.pd3-buy{margin-top:24px;border:1px solid var(--line);border-radius:16px;padding:15px;background:#fafcff}.pd3-buy form{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.pd3-buy label{font-size:.78rem;font-weight:850;color:#475569}.pd3-buy input{width:70px;margin-left:6px;border:1px solid #cbd5e1;border-radius:9px;padding:9px}.pd3-btn{border:0;border-radius:10px;padding:11px 17px;font-weight:850;text-decoration:none;display:inline-block;cursor:pointer}.pd3-primary{background:var(--blue);color:#fff}.pd3-outline{border:1px solid #cbd5e1;color:var(--navy);background:#fff}.pd3-out{background:#f1f5f9;color:#64748b;display:inline-block;padding:11px 16px;border-radius:10px;font-weight:850}.pd3-gallery-area{min-width:0}.pd3-main-image{width:100%;height:100%;border:0;padding:0;background:transparent;cursor:zoom-in}.pd3-thumbnails{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.pd3-thumb{width:76px;height:76px;border:2px solid transparent;border-radius:12px;overflow:hidden;background:#f6f9fd;padding:0;cursor:pointer}.pd3-thumb[aria-pressed="true"]{border-color:var(--blue)}.pd3-thumb img{width:100%;height:100%;object-fit:contain}.pd3-lightbox{position:fixed;inset:0;z-index:1000;background:rgba(3,12,27,.92);display:none;align-items:center;justify-content:center;padding:24px}.pd3-lightbox.is-open{display:flex}.pd3-lightbox img{max-width:95vw;max-height:88vh;object-fit:contain}.pd3-lightbox-close{position:absolute;top:18px;right:18px;border:0;border-radius:999px;padding:10px 15px;font-weight:850;background:#fff;color:#0a1628;cursor:pointer}@media(max-width:800px){.pd3-grid{grid-template-columns:1fr;gap:25px}.pd3-links{gap:12px}.pd3-links a:not(:last-child){display:none}}
</style><style>header nav{display:flex;align-items:center;gap:14px}a[data-df-pill]{display:inline-flex!important;align-items:center;justify-content:center;align-self:center;vertical-align:middle;height:40px;min-width:84px;padding:0 20px!important;margin:0!important;line-height:1;white-space:nowrap;box-sizing:border-box}</style></head><body class="pd3">
<header class="pd3-nav"><div class="wrap"><a href="<?= e(url('/')) ?>"><img src="<?= e(url('/assets/images/df-logo.png')) ?>" alt="DogoFlow"></a><nav class="pd3-links"><a href="<?= e(url('/products.php')) ?>">Products</a><a href="<?= e(url('/stores.php')) ?>">Stores</a><a href="<?= e(url('/auth/login.php')) ?>" data-df-pill="1" style="display:inline-flex;align-items:center;justify-content:center;height:40px;padding:0 20px;border-radius:999px;background:#0B5CFF;color:#fff !important;font-weight:700;text-decoration:none;box-shadow:0 8px 18px rgba(11,92,255,.22)">Login</a></nav></div></header>
<main class="wrap pd3-main"><a class="pd3-back" href="<?= e(url('/products.php')) ?>">← Back to products</a>
<div class="pd3-grid">
  <section class="pd3-gallery-area" aria-label="Product photo gallery">
    <div class="pd3-gallery">
      <?php if ($galleryItems): ?>
        <button class="pd3-main-image" id="pd3MainImageButton" type="button" aria-label="Open larger product photo"><img id="pd3MainImage" src="<?= e($galleryItems[0]['url']) ?>" alt="<?= e($product['name']) ?>"></button>
      <?php else: ?><img src="<?= e(url('/assets/images/product-placeholder.png')) ?>" alt="Product placeholder" style="width:60%;height:60%;opacity:.7"><?php endif; ?>
    </div>
    <?php if (count($galleryItems) > 1): ?><div class="pd3-thumbnails" aria-label="Choose a product photo">
      <?php foreach ($galleryItems as $galleryIndex => $galleryItem): ?><button class="pd3-thumb" type="button" data-gallery-index="<?= (int) $galleryIndex ?>" data-gallery-src="<?= e($galleryItem['url']) ?>" aria-pressed="<?= $galleryIndex === 0 ? 'true' : 'false' ?>" aria-label="Show product photo <?= (int) ($galleryIndex + 1) ?>"><img src="<?= e($galleryItem['url']) ?>" alt=""></button><?php endforeach; ?>
    </div><?php endif; ?>
  </section>
  <article class="pd3-info"><div class="pd3-badges"><span class="pd3-badge">Available on DogoFlow</span><?php foreach ($productCategories as $category): ?><a class="pd3-badge gray" href="<?= e(url('/products.php?category=' . rawurlencode((string) $category['slug']))) ?>"><?= e($category['name']) ?></a><?php endforeach; ?></div><h1><?= e($product['name']) ?></h1><div class="pd3-price">GH₵ <?= number_format((float) $product['price'], 2) ?></div><p class="pd3-seller">Sold by <?php if ($product['store_slug']): ?><a href="<?= e(url('/store.php?slug=' . rawurlencode((string) $product['store_slug']))) ?>"><?= e($product['business_name']) ?></a><?php else: ?><strong><?= e($product['business_name']) ?></strong><?php endif; ?><?php if ($product['seller_code']): ?> · <?= e($product['seller_code']) ?><?php endif; ?></p><div class="pd3-description"><?= nl2br(e((string) ($product['description'] ?? ''))) ?></div><?php if (dogoflow_cart_enabled() || (int) $product['stock'] <= 0): ?><div class="pd3-buy"><?php if (dogoflow_cart_enabled() && (int) $product['stock'] > 0): ?><form method="post" action="<?= e(url('/cart/add.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="return_to" value="/cart.php"><label>Qty<input name="quantity" type="number" value="1" min="1" max="<?= (int) $product['stock'] ?>"></label><button class="pd3-btn pd3-primary" type="submit">Add to cart</button><?php if (dogoflow_cart_enabled()): ?><a class="pd3-btn pd3-outline" href="<?= e(url('/cart.php')) ?>">View cart</a><?php endif; ?></form><?php elseif ((int) $product['stock'] <= 0): ?><span class="pd3-out">Out of stock</span><?php endif; ?></div><?php endif; ?><?php $callHref = dogoflow_tel_link($product['business_phone'] ?? null); if ($callHref !== null || $whatsappHref !== null): ?><div class="df-contact-row"><?php if ($callHref !== null): ?><a class="df-contact-btn df-call" href="<?= e($callHref) ?>">Call to Order</a><?php endif; ?><?php if ($whatsappHref !== null): ?><a class="df-contact-btn df-whatsapp" href="<?= e($whatsappHref) ?>" target="_blank" rel="noopener noreferrer">WhatsApp Order</a><?php endif; ?></div><style>.df-contact-row{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.df-contact-btn{display:inline-flex;align-items:center;justify-content:center;height:44px;padding:0 24px;border-radius:999px;font-weight:700;font-size:14px;text-decoration:none;color:#fff;transition:filter .15s}.df-contact-btn:hover{filter:brightness(.94)}.df-call{background:#0B5CFF;box-shadow:0 8px 18px rgba(11,92,255,.2)}.df-whatsapp{background:#25D366;box-shadow:0 8px 18px rgba(37,211,102,.22)}</style><?php endif; ?></article>
</div>
<div id="pd3Lightbox" class="pd3-lightbox" role="dialog" aria-modal="true" aria-label="Larger product photo"><button class="pd3-lightbox-close" id="pd3LightboxClose" type="button">Close ×</button><img id="pd3LightboxImage" src="" alt="Larger product photo"></div>
</main>
<script>
(() => {
  const mainButton = document.getElementById('pd3MainImageButton');
  const mainImage = document.getElementById('pd3MainImage');
  const thumbs = Array.from(document.querySelectorAll('.pd3-thumb'));
  const lightbox = document.getElementById('pd3Lightbox');
  const lightboxImage = document.getElementById('pd3LightboxImage');
  function selectImage(button) {
    if (!mainImage || !button) return;
    mainImage.src = button.dataset.gallerySrc || '';
    thumbs.forEach(thumb => thumb.setAttribute('aria-pressed', thumb === button ? 'true' : 'false'));
  }
  thumbs.forEach(button => button.addEventListener('click', () => selectImage(button)));
  function openLightbox() { if (!mainImage || !lightbox) return; lightboxImage.src = mainImage.src; lightbox.classList.add('is-open'); document.getElementById('pd3LightboxClose').focus(); }
  function closeLightbox() { lightbox.classList.remove('is-open'); lightboxImage.src = ''; }
  mainButton?.addEventListener('click', openLightbox);
  document.getElementById('pd3LightboxClose')?.addEventListener('click', closeLightbox);
  lightbox?.addEventListener('click', event => { if (event.target === lightbox) closeLightbox(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeLightbox(); });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?></body></html>