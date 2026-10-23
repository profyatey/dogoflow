<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pdo = db();

$q = trim((string) ($_GET['q'] ?? ''));
$categorySlug = trim((string) ($_GET['category'] ?? ''));
$seller = trim((string) ($_GET['seller'] ?? ''));
$minPrice = trim((string) ($_GET['min_price'] ?? ''));
$maxPrice = trim((string) ($_GET['max_price'] ?? ''));
$inStock = isset($_GET['in_stock']) && $_GET['in_stock'] === '1';
$sort = (string) ($_GET['sort'] ?? 'newest');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$allowedSorts = [
    'newest' => 'p.created_at DESC, p.id DESC',
    'price_low' => 'p.price ASC, p.id DESC',
    'price_high' => 'p.price DESC, p.id DESC',
    'name' => 'p.name ASC, p.id DESC',
];
$orderBy = $allowedSorts[$sort] ?? $allowedSorts['newest'];

$categories = $pdo->query(
    "SELECT id, parent_id, name, slug
     FROM categories
     WHERE status = 'active'
     ORDER BY parent_id IS NOT NULL, sort_order, name"
)->fetchAll();

$categoryTree = [];
foreach ($categories as $category) {
    $parent = $category['parent_id'] === null ? 0 : (int) $category['parent_id'];
    $categoryTree[$parent][] = $category;
}

$sql = "
    SELECT
        p.id,
        p.name,
        p.slug,
        p.price,
        p.stock,
        p.image,
        p.description,
        p.created_at,
        sp.business_name,
        sp.store_slug,
        sp.seller_code
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
";
$params = [];

if ($q !== '') {
    $sql .= "
        AND (
            p.name LIKE :q_name
            OR p.description LIKE :q_description
            OR sp.business_name LIKE :q_store
            OR sp.seller_code LIKE :q_code
        )
    ";
    $needle = '%' . $q . '%';
    $params['q_name'] = $needle;
    $params['q_description'] = $needle;
    $params['q_store'] = $needle;
    $params['q_code'] = $needle;
}

if ($categorySlug !== '') {
    $sql .= "
        AND EXISTS (
            SELECT 1
            FROM product_categories pc_filter
            INNER JOIN categories c_filter
                ON c_filter.id = pc_filter.category_id
               AND c_filter.status = 'active'
            WHERE pc_filter.product_id = p.id
              AND c_filter.slug = :category_slug
        )
    ";
    $params['category_slug'] = $categorySlug;
}

if ($seller !== '') {
    $sql .= "
        AND (
            sp.store_slug = :seller_slug
            OR sp.seller_code = :seller_code
            OR sp.business_name LIKE :seller_name
        )
    ";
    $params['seller_slug'] = $seller;
    $params['seller_code'] = $seller;
    $params['seller_name'] = '%' . $seller . '%';
}

if ($minPrice !== '' && is_numeric($minPrice) && (float) $minPrice >= 0) {
    $sql .= ' AND p.price >= :min_price';
    $params['min_price'] = (float) $minPrice;
}

if ($maxPrice !== '' && is_numeric($maxPrice) && (float) $maxPrice >= 0) {
    $sql .= ' AND p.price <= :max_price';
    $params['max_price'] = (float) $maxPrice;
}

if ($inStock) {
    $sql .= ' AND p.stock > 0';
}

$countSql = "SELECT COUNT(*) FROM (" . $sql . ") filtered_products";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql .= " ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$selectedCategoryName = '';
foreach ($categories as $category) {
    if ($category['slug'] === $categorySlug) {
        $selectedCategoryName = (string) $category['name'];
        break;
    }
}

$pageTitle = $selectedCategoryName !== ''
    ? $selectedCategoryName . ' Products · DogoFlow'
    : 'Explore Products · DogoFlow';

$description = $selectedCategoryName !== ''
    ? 'Browse ' . $selectedCategoryName . ' products from approved DogoFlow stores.'
    : 'Search and discover products from approved DogoFlow stores.';

function products_query(array $changes = []): string
{
    $values = $_GET;
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') {
            unset($values[$key]);
        } else {
            $values[$key] = $value;
        }
    }
    return '?' . http_build_query($values);
}

function render_category_options(array $tree, int $parentId = 0, int $depth = 0, string $selected = ''): string
{
    $html = '';
    foreach ($tree[$parentId] ?? [] as $category) {
        $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
        $isSelected = $selected === (string) $category['slug'] ? ' selected' : '';
        $html .= '<option value="' . e((string) $category['slug']) . '"' . $isSelected . '>'
            . e($prefix . (string) $category['name'])
            . '</option>';
        $html .= render_category_options($tree, (int) $category['id'], $depth + 1, $selected);
    }
    return $html;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<?php render_og_meta($pageTitle, $description, APP_URL . '/products.php'); ?>
<link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>">
<?php require __DIR__ . '/partials/tailwind.php'; ?>
<style>
.df3{--blue:#0088ee;--navy:#0a1628;--muted:#64748b;--line:#e7edf5;--soft:#f6f9fd}.df3{background:#fff;color:var(--navy);min-height:100vh}.df3 *{box-sizing:border-box}.df3 .wrap{width:min(1180px,calc(100% - 32px));margin:auto}.df3-nav{border-bottom:1px solid var(--line);background:#fff;position:sticky;top:0;z-index:30}.df3-nav .wrap{height:68px;display:flex;align-items:center;justify-content:space-between}.df3-brand img{height:40px;width:auto}.df3-links{display:flex;gap:26px;align-items:center}.df3-links a{color:#475569;text-decoration:none;font-weight:750;font-size:.88rem}.df3-links a:hover{color:var(--blue)}.df3-join,.df3-login{display:inline-flex;align-items:center;justify-content:center;height:40px;padding:0 16px;border-radius:999px;font-weight:800;text-decoration:none!important}.df3-join{background:var(--blue)!important;color:#fff!important}.df3-login{border:1px solid var(--line);background:#fff;color:var(--navy)!important}.df3-hero{padding:42px 0 28px;background:linear-gradient(180deg,#f7fbff 0,#fff 100%)}.df3-hero-row{display:flex;justify-content:space-between;gap:24px;align-items:end}.df3-eyebrow{color:var(--blue);font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:.16em}.df3-hero h1{font-size:clamp(2.8rem,5vw,4.6rem);font-weight:950;line-height:.96;letter-spacing:-.055em;margin:10px 0}.df3-hero p{color:var(--muted);max-width:650px;line-height:1.7;margin:0}.df3-count{border:1px solid var(--line);background:#fff;border-radius:16px;padding:14px 18px;min-width:145px;box-shadow:0 8px 30px #0a16280b}.df3-count strong{font-size:1.5rem}.df3-count span{display:block;color:#94a3b8;font-size:.72rem;margin-top:2px}.df3-layout{display:grid;grid-template-columns:260px 1fr;gap:26px;padding:22px 0 70px}.df3-filter{border:1px solid var(--line);border-radius:20px;background:#fff;padding:20px;height:max-content;position:sticky;top:88px;box-shadow:0 10px 35px #0a16280a}.df3-filter h2{font-size:1.02rem}.df3-filter form{display:grid;gap:2px}.df3-filter h2{font-size:1rem;margin:0}.df3-filter-close{display:none}.df3-filter-title{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:6px}.df3-filter-title h2{margin:0}.df3-filter p{font-size:.76rem;color:#94a3b8;margin:4px 0 18px}.df3-filter label{display:block;font-size:.73rem;font-weight:800;color:#475569;margin-bottom:13px}.df3-filter input,.df3-filter select{width:100%;margin-top:6px;border:1px solid #dbe3ee;border-radius:10px;padding:10px 11px;background:#fff;color:var(--navy);outline:none}.df3-filter input:focus,.df3-filter select:focus{border-color:#80c9fa;box-shadow:0 0 0 3px #0088ee12}.df3-two{display:grid;grid-template-columns:1fr 1fr;gap:8px}.df3-check{display:flex!important;gap:8px;align-items:center}.df3-check input{width:auto;margin:0}.df3-apply{width:100%;border:0;border-radius:10px;background:var(--blue);color:#fff;padding:11px;font-weight:850;cursor:pointer}.df3-clear{float:right;color:var(--blue);font-size:.7rem;text-decoration:none}.df3-results{min-width:0}.df3-results-head{display:flex;justify-content:space-between;align-items:center;color:#64748b;font-size:.8rem;margin-bottom:12px}.df3-results-head strong{color:var(--navy)}.df3-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.df3-card{border:1px solid var(--line);border-radius:17px;background:#fff;overflow:hidden;transition:.2s;box-shadow:0 8px 28px #0a162808}.df3-card:hover{transform:translateY(-3px);box-shadow:0 15px 40px #0a162814;border-color:#cfe8fb}.df3-image{display:block;aspect-ratio:1/1;background:var(--soft);overflow:hidden}.df3-image img{width:100%;height:100%;object-fit:contain;transition:.25s}.df3-card:hover .df3-image img{transform:scale(1.035)}.df3-image{position:relative}.df3-image:after{content:"DogoFlow";position:absolute;left:10px;bottom:10px;background:rgba(255,255,255,.9);color:#64748b;padding:4px 7px;border-radius:7px;font-size:.58rem;font-weight:800;opacity:.9}.df3-body h2{min-height:2.5em;line-height:1.25}.df3-price{font-size:1.08rem;color:#0a1628}.df3-view{display:inline-flex;margin-top:10px;padding:8px 10px;border-radius:9px;background:#eff8ff;color:#0088ee!important;font-weight:850!important;text-decoration:none}.df3-view:hover{background:#e0f3ff}.df3-body{padding:14px}.df3-store{display:flex;justify-content:space-between;gap:8px;align-items:center;font-size:.67rem;color:#64748b}.df3-store a{color:#0088ee;font-weight:800;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.df3-stock{white-space:nowrap;color:#059669;font-weight:800}.df3-stock.out{color:#ef4444}.df3-body h2{font-size:.95rem;margin:9px 0 6px;line-height:1.3}.df3-body h2 a{color:var(--navy);text-decoration:none}.df3-price{color:var(--blue);font-size:1.05rem}.df3-view{display:block;text-align:center;text-decoration:none;margin-top:12px;padding:9px;border-radius:9px;background:#eef8ff;color:var(--blue);font-size:.76rem;font-weight:850}.df3-pagination{display:flex;justify-content:center;gap:14px;align-items:center;margin-top:24px;font-size:.8rem}.df3-pagination a{color:var(--blue);font-weight:800;text-decoration:none}.df3-empty{padding:40px;text-align:center;border:1px dashed #cbd5e1;border-radius:18px}.df3-empty p{color:#64748b}.df3-empty a{display:inline-block;margin-top:10px;background:var(--blue);color:#fff;text-decoration:none;padding:10px 15px;border-radius:10px;font-weight:800;font-size:.8rem}@media(max-width:900px){.df3-layout{grid-template-columns:1fr}.df3-filter{position:fixed;display:none;z-index:102;left:12px;right:12px;bottom:12px;top:auto;max-height:82dvh;overflow:auto;border-radius:22px;padding:20px;box-shadow:0 24px 80px #0a162855}.df3-filter.open{display:block}.df3-filter-close{display:inline-flex;align-items:center;justify-content:center;border:1px solid #dbe3ee;border-radius:9px;background:#fff;color:#475569;padding:7px 10px;font-size:.75rem;font-weight:800}.df3-filter-backdrop{display:none;position:fixed;inset:0;background:rgba(7,26,51,.42);backdrop-filter:blur(3px);z-index:101}.df3-filter-backdrop.open{display:block}.df3-filter-fab{display:flex!important;position:fixed;z-index:100;right:16px;bottom:18px;align-items:center;gap:8px;border:0;border-radius:999px;padding:14px 18px;background:#0088ee;color:#fff;font-weight:900;box-shadow:0 12px 32px #0088ee55}.df3-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(min-width:901px){.df3-filter-fab,.df3-filter-backdrop{display:none!important}}@media(max-width:640px){.df3-links .df3-join{display:none}.df3-hero{padding:30px 0 18px}.df3-hero-row{align-items:start;flex-direction:column;gap:12px}.df3-count{min-width:0;padding:8px 12px}.df3-count strong{font-size:1.05rem}.df3-count span{display:inline;margin-left:5px}.df3-grid{grid-template-columns:1fr 1fr;gap:10px}.df3-card .df3-view{font-size:.7rem}.df3-body{padding:10px}.df3 .wrap{width:min(100% - 22px,1180px)}}
</style><style>.df3-hero{background:linear-gradient(135deg,#020617 0%,#0F172A 55%,#075985 100%)!important;border-radius:0}.df3-hero h1{color:#fff!important}.df3-hero p{color:#CBD5E1!important}.df3-eyebrow{color:#7DD3FC!important}.df3-count{background:rgba(255,255,255,.08)!important;border-color:rgba(255,255,255,.14)!important;box-shadow:none!important}.df3-count strong{color:#fff!important}.df3-count span{color:#CBD5E1!important}</style></head>
<body class="df3">
<header class="df3-nav"><div class="wrap"><a class="df3-brand" href="<?= e(url('/')) ?>"><img src="<?= e(url('/assets/images/df-logo.png')) ?>" alt="DogoFlow"></a><nav class="df3-links"><a href="<?= e(url('/products.php')) ?>">Products</a><a href="<?= e(url('/stores.php')) ?>">Stores</a><a href="<?= e(url('/auth/login.php')) ?>" class="df3-login">Login</a><a class="df3-join" href="<?= e(url('/auth/register.php')) ?>">Create account</a></nav></div></header>
<div class="df3-filter-backdrop" id="dfFilterBackdrop"></div><button class="df3-filter-fab" id="dfFilterOpen" type="button" aria-controls="dfProductFilters" aria-expanded="false" style="display:none"><span>☷</span> Filters</button><section class="df3-hero"><div class="wrap df3-hero-row"><div><span class="df3-eyebrow">DogoFlow Marketplace</span><h1><?= e($selectedCategoryName !== '' ? $selectedCategoryName : 'Explore products') ?></h1><p>Find products from approved DogoFlow stores.</p></div><div class="df3-count"><strong><?= number_format($total) ?></strong><span>matching products</span></div></div></section>
<main class="wrap df3-layout">
<aside class="df3-filter" id="dfProductFilters"><button class="df3-filter-close" id="dfFilterClose" type="button">✕ Close</button><a class="df3-clear" href="<?= e(url('/products.php')) ?>">Clear</a><h2>Filter products</h2><p>Refine your marketplace search.</p><form method="get" action="<?= e(url('/products.php')) ?>">
<label>Search<input type="search" name="q" value="<?= e($q) ?>" placeholder="Product, store or seller ID"></label>
<label>Category<select name="category"><option value="">All categories</option><?= render_category_options($categoryTree,0,0,$categorySlug) ?></select></label>
<label>Store / Seller ID<input type="text" name="seller" value="<?= e($seller) ?>" placeholder="e.g. DF-00001"></label>
<div class="df3-two"><label>Min price<input type="number" name="min_price" min="0" step="0.01" value="<?= e($minPrice) ?>" placeholder="GH₵ 0"></label><label>Max price<input type="number" name="max_price" min="0" step="0.01" value="<?= e($maxPrice) ?>" placeholder="No limit"></label></div>
<label class="df3-check"><input type="checkbox" name="in_stock" value="1" <?= $inStock?'checked':'' ?>> Show in-stock only</label>
<label>Sort by<select name="sort"><option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest</option><option value="price_low" <?= $sort==='price_low'?'selected':'' ?>>Price: low to high</option><option value="price_high" <?= $sort==='price_high'?'selected':'' ?>>Price: high to low</option><option value="name" <?= $sort==='name'?'selected':'' ?>>Name A–Z</option></select></label>
<button class="df3-apply" type="submit">Apply filters</button></form></aside>
<section class="df3-results">
<?php if($products): ?><div class="df3-results-head"><span><strong><?= number_format($total) ?></strong> product<?= $total===1?'':'s' ?> found</span><span>Page <?= $page ?> of <?= $totalPages ?></span></div><div class="df3-grid">
<?php foreach($products as $product): $image=!empty($product['image'])?url('/'.ltrim((string)$product['image'],'/')):url('/assets/images/product-placeholder.png'); $storeSlug=(string)($product['store_slug']??''); ?>
<article class="df3-card"><a class="df3-image" href="<?= e(url('/product.php?id='.(int)$product['id'])) ?>"><img src="<?= e($image) ?>" alt="<?= e($product['name']) ?>" loading="lazy"></a><div class="df3-body"><div class="df3-store"><?php if($storeSlug): ?><a href="<?= e(url('/store.php?slug='.rawurlencode($storeSlug))) ?>"><?= e($product['business_name']) ?></a><?php else: ?><span><?= e($product['business_name']) ?></span><?php endif; ?><span class="df3-stock <?= (int)$product['stock']>0?'':'out' ?>"><?= (int)$product['stock']>0?'In stock':'Out' ?></span></div><h2><a href="<?= e(url('/product.php?id='.(int)$product['id'])) ?>"><?= e($product['name']) ?></a></h2><strong class="df3-price">GH₵ <?= number_format((float)$product['price'],2) ?></strong><a class="df3-view" href="<?= e(url('/product.php?id='.(int)$product['id'])) ?>">View product →</a></div></article>
<?php endforeach; ?></div>
<?php if($totalPages>1): ?><nav class="df3-pagination" aria-label="Product pages"><?php if($page>1): ?><a href="<?= e(url('/products.php'.products_query(['page'=>$page-1]))) ?>">← Previous</a><?php endif; ?><span>Page <?= $page ?> / <?= $totalPages ?></span><?php if($page<$totalPages): ?><a href="<?= e(url('/products.php'.products_query(['page'=>$page+1]))) ?>">Next →</a><?php endif; ?></nav><?php endif; ?>
<?php else: ?><div class="df3-empty"><strong>No products matched your filters.</strong><p>Try another search, widen the price range or clear the filters.</p><a href="<?= e(url('/products.php')) ?>">Browse all products</a></div><?php endif; ?></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?><script>(function(){const panel=document.getElementById('dfProductFilters'),backdrop=document.getElementById('dfFilterBackdrop'),open=document.getElementById('dfFilterOpen'),close=document.getElementById('dfFilterClose');if(!panel||!backdrop||!open)return;function setOpen(v){panel.classList.toggle('open',v);backdrop.classList.toggle('open',v);open.setAttribute('aria-expanded',v?'true':'false');document.body.style.overflow=v?'hidden':'';}open.addEventListener('click',()=>setOpen(!panel.classList.contains('open')));if(close)close.addEventListener('click',()=>setOpen(false));backdrop.addEventListener('click',()=>setOpen(false));document.addEventListener('keydown',e=>{if(e.key==='Escape')setOpen(false)});})();</script></body></html>