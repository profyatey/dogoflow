<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$q = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$sql = "SELECT sp.*, COUNT(DISTINCT p.id) AS product_count FROM seller_profiles sp JOIN users u ON u.id=sp.user_id AND u.role='seller' AND u.status='active' LEFT JOIN products p ON p.seller_id=sp.user_id AND p.status='active' LEFT JOIN seller_categories sc ON sc.seller_id=sp.user_id LEFT JOIN categories c ON c.id=sc.category_id AND c.status='active' WHERE sp.status='active'";
$params=[];
if ($q !== '') { $sql .= " AND (sp.business_name LIKE :q OR sp.store_description LIKE :q OR sp.store_location LIKE :q)"; $params['q']='%'.$q.'%'; }
if ($category !== '') { $sql .= " AND c.slug=:category"; $params['category']=$category; }
$sql .= " GROUP BY sp.id ORDER BY sp.business_name LIMIT 48";
$stmt=db()->prepare($sql); $stmt->execute($params); $stores=$stmt->fetchAll(PDO::FETCH_ASSOC);
$categories=db()->query("SELECT DISTINCT c.name,c.slug FROM categories c JOIN seller_categories sc ON sc.category_id=c.id WHERE c.status='active' ORDER BY c.name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Explore Stores | DogoFlow</title><meta name="description" content="Discover approved stores and vendors on DogoFlow."><meta property="og:title" content="Explore Stores | DogoFlow"><meta property="og:description" content="Discover approved stores and vendors on DogoFlow."><meta property="og:image" content="<?= e(url('/assets/images/df-logo.png')) ?>"><link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>"></head><body>
<main class="df-stores-page"><header class="page-head"><h1>Explore Stores</h1><p>Discover sellers and find products from stores across DogoFlow.</p></header>
<form method="get" class="card df-store-search"><div><label for="store-q">Search stores</label><input id="store-q" name="q" value="<?= e($q) ?>" placeholder="Store name or location"></div><div><label for="store-category">Category</label><select id="store-category" name="category"><option value="">All categories</option><?php foreach($categories as $c): ?><option value="<?= e($c['slug']) ?>" <?= $category===$c['slug']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div><button class="btn btn-primary" type="submit">Search stores</button></form>
<div class="grid grid-3"><?php foreach($stores as $s): $slug=(string)($s['store_slug'] ?: strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',(string)$s['business_name']),'-'))); $logo=trim((string)($s['store_logo']??'')); $banner=trim((string)($s['store_banner']??'')); ?>
<article class="card df-store-card"><?php if($banner!==''): ?><img class="df-store-cover" src="<?= e(url('/'.ltrim($banner,'/'))) ?>" alt="<?= e($s['business_name']) ?> cover" loading="lazy"><?php else: ?><div class="df-store-cover"></div><?php endif; ?><?php if($logo!==''): ?><img class="df-store-logo" src="<?= e(url('/'.ltrim($logo,'/'))) ?>" alt="<?= e($s['business_name']) ?> logo" loading="lazy"><?php endif; ?><h2><?= e($s['business_name']) ?></h2><p><?= e((string)($s['store_location']??'Location not provided')) ?></p><p><?= (int)$s['product_count'] ?> products</p><div class="df-store-actions"><a class="btn btn-outline" href="<?= e(url('/store.php?slug='.urlencode($slug))) ?>">Visit store</a></div></article><?php endforeach; ?><?php if(!$stores): ?><div class="card"><p>No stores matched your search. Try another name or category.</p></div><?php endif; ?></div></main>
<?php require __DIR__ . '/includes/footer.php'; ?></body></html>
