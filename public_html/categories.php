<?php
require_once __DIR__ . '/bootstrap.php';

$rows = db()->query("SELECT c.*, COUNT(pc.product_id) product_count
    FROM categories c
    LEFT JOIN product_categories pc ON pc.category_id = c.id
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.sort_order, c.name")->fetchAll();

function df_category_icon(string $name): string {
    $n = strtolower($name);
    if (str_contains($n, 'beauty') || str_contains($n, 'personal') || str_contains($n, 'care')) return '<path d="M9 3h6l1 4-2 2v9a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2V9L7 7l2-4Z"/><path d="M9 7h6M9 13h6"/>';
    if (str_contains($n, 'book') || str_contains($n, 'stationery')) return '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 1 4 16.5v-11Z"/><path d="M4 16.5A2.5 2.5 0 0 1 6.5 14H20M8 7h8M8 10h6"/>';
    if (str_contains($n, 'computer') || str_contains($n, 'accessor')) return '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8m-4-4v4M7 8h10"/>';
    if (str_contains($n, 'electronic') || str_contains($n, 'phone')) return '<rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M10 5h4m-3 13h2"/>';
    if (str_contains($n, 'fashion') || str_contains($n, 'cloth')) return '<path d="m8 4 4 2 4-2 5 4-3 4-2-1v10H8V11l-2 1-3-4 5-4Z"/><path d="m8 4 4 5 4-5"/>';
    if (str_contains($n, 'home') || str_contains($n, 'living') || str_contains($n, 'furniture')) return '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/>';
    if (str_contains($n, 'food') || str_contains($n, 'drink')) return '<path d="M4 3v7a3 3 0 0 0 6 0V3M7 3v18M16 3v18m0-18c3 3 3 7 0 10"/>';
    if (str_contains($n, 'sport') || str_contains($n, 'outdoor')) return '<circle cx="12" cy="12" r="9"/><path d="m12 3 3 6-3 3-3-3 3-6Zm-9 9 6-3m6 3 6 3M9 21l3-9 3 9"/>';
    return '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h8M8 17h4"/>';
}
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Shop by Category | DogoFlow</title>
<?php require __DIR__ . '/partials/tailwind.php'; ?>
<meta name="description" content="Explore DogoFlow categories and discover products from Ghanaian businesses.">
<meta property="og:title" content="Shop by Category | DogoFlow"><meta property="og:description" content="Find what you need from trusted DogoFlow sellers.">
<style>
.df-categories{max-width:1180px;margin:0 auto;padding:34px 22px 12px;color:#0b2545}
.df-cat-hero{position:relative;overflow:hidden;border:1px solid #e1edf8;background:linear-gradient(120deg,#f2f8ff 0%,#fff 72%);border-radius:26px;padding:34px 34px 30px;margin-bottom:28px}
.df-cat-hero:after{content:"";position:absolute;width:220px;height:220px;border-radius:50%;right:-70px;top:-95px;background:rgba(0,163,255,.08);pointer-events:none}
.df-cat-kicker{display:inline-flex;align-items:center;gap:8px;color:#087bc1;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;margin-bottom:12px}
.df-cat-hero h1{font-size:clamp(30px,4vw,46px);line-height:1.08;letter-spacing:-.045em;font-weight:800;margin:0;max-width:700px;color:#0b2545}
.df-cat-hero p{margin:12px 0 0;color:#61748a;font-size:15px;max-width:550px;line-height:1.7}
.df-cat-section-head{display:flex;justify-content:space-between;align-items:end;gap:14px;margin:0 2px 16px}.df-cat-section-head h2{font-size:20px;font-weight:800;letter-spacing:-.025em;margin:0;color:#0b2545}.df-cat-section-head p{font-size:13px;color:#718096;margin:5px 0 0}
.df-cat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.df-cat-card{display:flex;align-items:center;gap:14px;min-width:0;padding:18px;border:1px solid #e4edf6;border-radius:18px;background:#fff;text-decoration:none;color:#0b2545;box-shadow:0 2px 8px rgba(10,22,40,.035);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
.df-cat-card:hover{transform:translateY(-2px);border-color:#9edcff;box-shadow:0 9px 24px rgba(10,70,120,.09)}
.df-cat-icon{display:flex;align-items:center;justify-content:center;flex:0 0 50px;width:50px;height:50px;border-radius:15px;background:#edf7ff;color:#087fc4;border:1px solid #d9edff}.df-cat-icon svg{width:25px;height:25px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.df-cat-copy{min-width:0}.df-cat-copy h3{font-size:14px;line-height:1.35;font-weight:750;margin:0;overflow-wrap:anywhere}.df-cat-copy p{font-size:12px;color:#7b8b9d;margin:5px 0 0}.df-cat-arrow{margin-left:auto;flex:0 0 auto;color:#8da2b7;font-size:18px}
.df-cat-empty{grid-column:1/-1;border:1px dashed #cbd9e8;background:#f8fbff;padding:28px;border-radius:18px;text-align:center;color:#60758a}
@media(max-width:900px){.df-cat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:540px){.df-categories{padding:18px 15px 6px}.df-cat-hero{padding:25px 21px 24px;border-radius:21px;margin-bottom:23px}.df-cat-hero h1{font-size:31px}.df-cat-hero p{font-size:14px}.df-cat-grid{grid-template-columns:1fr;gap:10px}.df-cat-card{padding:13px 14px;border-radius:15px;gap:12px}.df-cat-icon{width:45px;height:45px;flex-basis:45px;border-radius:13px}.df-cat-section-head h2{font-size:18px}}
@media(prefers-reduced-motion:reduce){.df-cat-card{transition:none}}
</style></head><body class="bg-white min-h-screen">
<?php if (is_file(__DIR__ . '/partials/header.php')) require __DIR__ . '/partials/header.php'; ?>
<main class="df-categories">
 <section class="df-cat-hero"><span class="df-cat-kicker"><span aria-hidden="true">✦</span> DogoFlow Marketplace</span><h1>Find your next favourite.</h1><p>Shop by category and discover products from trusted businesses across Ghana.</p></section>
 <section aria-labelledby="df-category-heading"><div class="df-cat-section-head"><div><h2 id="df-category-heading">Shop by category</h2><p>Choose a category to explore its products.</p></div><span class="text-sm text-slate-500"><?= count($rows) ?> <?= count($rows) === 1 ? 'category' : 'categories' ?></span></div>
 <div class="df-cat-grid">
 <?php foreach ($rows as $r): ?><a class="df-cat-card" href="<?= e(url('/products.php?category=' . urlencode($r['slug']))) ?>"><span class="df-cat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= df_category_icon((string)$r['name']) ?></svg></span><span class="df-cat-copy"><h3><?= e($r['name']) ?></h3><p><?= (int)$r['product_count'] ?> <?= (int)$r['product_count'] === 1 ? 'product' : 'products' ?></p></span><span class="df-cat-arrow" aria-hidden="true">→</span></a><?php endforeach; ?>
 <?php if (!$rows): ?><div class="df-cat-empty"><strong>No categories available yet.</strong><p class="mt-2">Please check back soon as more categories are added.</p></div><?php endif; ?>
 </div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body></html>
