<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
Auth::requireApprovedSeller();

$pdo = db();
$userId = (int) Auth::userId();

$stmt = $pdo->prepare('SELECT business_name, store_slug, status, approval_status FROM seller_profiles WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$store = $stmt->fetch() ?: null;

$stmt = $pdo->prepare('SELECT id, name, slug, status FROM products WHERE seller_id = ? ORDER BY id DESC LIMIT 100');
$stmt->execute([$userId]);
$products = $stmt->fetchAll();

$storeUrl = $store && !empty($store['store_slug'])
    ? url('/store.php?slug=' . rawurlencode((string) $store['store_slug']))
    : '';

$approvalStatus = strtolower(trim((string) ($store['approval_status'] ?? '')));
$storeStatus = strtolower(trim((string) ($store['status'] ?? '')));
$storeApproved = $approvalStatus === 'approved' && $storeStatus === 'active';
?>
<!doctype html>
<html lang="en">
<head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A1628">
    <title>Marketing tools · DogoFlow</title>
    <?php require __DIR__ . '/../partials/tailwind.php'; ?>
</head>
<body class="seller-page">
<main class="container-wide seller-shell-df">
    <?php $sellerActive = 'marketing'; require __DIR__ . '/../partials/seller_header.php'; ?>

    <section class="seller-page-heading-df" style="padding-top:32px;margin-bottom:20px">
        <div>
            <span class="eyebrow-df">Growth tools</span>
            <h1>Marketing tools</h1>
            <p>Copy and share links to your public store and products.</p>
        </div>
    </section>

    <?php if (!$store || !$storeUrl): ?>
        <div class="marketing-note mb-4">Set up your store profile and add a store URL name before sharing your store.</div>
    <?php else: ?>
        <section class="card mb-[18px] p-[18px]">
            <h2 class="mt-0">Your store link</h2>
            <p class="share-url break-all" id="store-url"><?= e($storeUrl) ?></p>
            <div class="share-actions flex flex-wrap items-center gap-2">
                <button class="btn-df primary" type="button" data-copy="store-url">Copy store link</button>
                <button class="btn-df secondary" type="button" data-share="store-url" data-title="<?= e((string) ($store['business_name'] ?? 'DogoFlow store')) ?>">Share store</button>
                <a class="btn-df secondary" href="<?= e($storeUrl) ?>" target="_blank" rel="noopener">Preview store</a>
            </div>
            <?php if (!$storeApproved): ?>
                <p class="marketing-note mt-4">Your store is not yet approved for public visitors. You can prepare your link, but customers may not be able to view it.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card p-[18px]">
        <h2 class="mt-0">Product links</h2>
        <p class="muted">Share active products that are visible in the marketplace.</p>
        <div class="marketing-list">
            <?php foreach ($products as $product): ?>
                <?php $productUrl = url('/product.php?id=' . (int)$product['id']); ?>
                <article class="share-row">
                    <div class="min-w-0 flex-1">
                        <strong><?= e($product['name']) ?></strong>
                        <p>Status: <?= e($product['status']) ?></p>
                        <div class="share-url break-all" id="product-url-<?= (int) $product['id'] ?>"><?= e($productUrl) ?></div>
                    </div>
                    <div class="share-actions flex flex-wrap items-center gap-2">
                        <button type="button" class="btn-df primary" data-copy="product-url-<?= (int) $product['id'] ?>">Copy link</button>
                        <button type="button" class="btn-df secondary" data-share="product-url-<?= (int) $product['id'] ?>" data-title="<?= e((string) $product['name']) ?>">Share</button>
                        <a class="btn-df secondary" href="<?= e($productUrl) ?>" target="_blank" rel="noopener">Preview</a>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$products): ?>
                <p class="muted">You have not added any products yet.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
(() => {
    const readLink = (id) => {
        const node = document.getElementById(id);
        return node ? node.textContent.trim() : '';
    };

    async function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        const copied = document.execCommand('copy');
        field.remove();
        if (!copied) throw new Error('Copy failed');
    }

    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const link = readLink(button.dataset.copy);
            if (!link) return;
            const original = button.textContent;
            try {
                await copyText(link);
                button.textContent = 'Copied!';
            } catch (error) {
                button.textContent = 'Copy unavailable';
            }
            window.setTimeout(() => { button.textContent = original; }, 1800);
        });
    });

    document.querySelectorAll('[data-share]').forEach((button) => {
        button.addEventListener('click', async () => {
            const link = readLink(button.dataset.share);
            if (!link) return;
            const title = button.dataset.title || 'DogoFlow';
            try {
                if (navigator.share) {
                    await navigator.share({ title, text: 'Check this out on DogoFlow', url: link });
                } else {
                    await copyText(link);
                    const original = button.textContent;
                    button.textContent = 'Link copied!';
                    window.setTimeout(() => { button.textContent = original; }, 1800);
                }
            } catch (error) {
                if (error && error.name === 'AbortError') return;
                button.textContent = 'Unable to share';
                window.setTimeout(() => { button.textContent = 'Share'; }, 1800);
            }
        });
    });
})();
</script>
</body>
</html>
