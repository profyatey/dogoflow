<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pageTitle = 'DogoFlow — Your business deserves its own store.';
$description = 'Create your online store, reach customers and grow your business with DogoFlow.';

require_once DOGOFLOW_ROOT . '/core/catalog/StoreArt.php';
$hpProducts = [];
$hpCategories = [];
try {
    $hpPdo = db();
    $hpProducts = $hpPdo->query("
        SELECT p.id, p.name, p.price, p.image, sp.business_name, sp.seller_code
        FROM products p
        JOIN seller_profiles sp ON sp.user_id = p.seller_id
        JOIN users u ON u.id = sp.user_id
        WHERE p.status = 'active' AND p.stock > 0
          AND sp.approval_status = 'approved' AND sp.status = 'active' AND u.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 8")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $hpCategories = $hpPdo->query("
        SELECT name, slug FROM categories
        WHERE status = 'active' AND (parent_id IS NULL OR parent_id = 0)
        ORDER BY sort_order, name
        LIMIT 6")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('DogoFlow front page data: ' . $e->getMessage());
}
function hp_category_icon(string $name): string {
    $n = strtolower($name);
    return match (true) {
        str_contains($n, 'fashion'), str_contains($n, 'cloth') => '👗',
        str_contains($n, 'electron'), str_contains($n, 'phone'), str_contains($n, 'gadget') => '📱',
        str_contains($n, 'home'), str_contains($n, 'furnit') => '🏠',
        str_contains($n, 'beauty'), str_contains($n, 'cosmetic') => '💄',
        str_contains($n, 'food'), str_contains($n, 'grocer') => '🍲',
        str_contains($n, 'book') => '📚',
        str_contains($n, 'shoe'), str_contains($n, 'foot') => '👟',
        default => '🛍️',
    };
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#071A2F">

    <title><?= e($pageTitle) ?></title>

    <?php render_og_meta($pageTitle, $description, APP_URL . '/'); ?>

    <link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>">
    <?php require __DIR__ . '/partials/tailwind.php'; ?>

    <style>
        html { scroll-behavior: smooth; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        .df-hero-grid {
            background-image:
                linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
            background-size: 42px 42px;
        }
        .df-hero-gradient {
            background:
                radial-gradient(circle at 76% 18%, rgba(25,168,255,.30), transparent 30%),
                linear-gradient(115deg, #071A2F 0%, #0A3155 48%, #087FCF 100%);
        }
        .df-text-gradient {
            background: linear-gradient(90deg, #00A3FF, #35B9FF);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .df-float-card { animation: dfFloat 5s ease-in-out infinite; }
        .df-float-card:nth-child(2) { animation-delay: .7s; }
        .df-float-card:nth-child(3) { animation-delay: 1.4s; }
        @keyframes dfFloat { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-7px)} }
        .df-ticker { animation: dfTicker 22s linear infinite; }
        .df-payout-pill {
            display:inline-flex;
            align-items:center;
            gap:.45rem;
            white-space:nowrap;
            border:1px solid #bae6fd;
            background:linear-gradient(180deg,#f8fcff 0%,#eff8ff 100%);
            color:#172033;
            border-radius:9999px;
            padding:.62rem .95rem;
            font-size:.72rem;
            font-weight:800;
            box-shadow:0 4px 14px rgba(7,142,247,.07);
        }
        .df-payout-dot {
            width:7px;
            height:7px;
            flex:0 0 7px;
            border-radius:9999px;
            background:#10b981;
            box-shadow:0 0 0 4px rgba(16,185,129,.10);
        }
        .df-payout-paid { color:#172033; }
        .df-payout-note { color:#94a3b8; }
        @keyframes dfTicker { from{transform:translateX(0)} to{transform:translateX(-50%)} }
        @media (prefers-reduced-motion: reduce) {
            .df-float-card, .df-ticker { animation: none; }
        }

        /* Local theme fallbacks keep custom DogoFlow colors visible even if the CDN theme is unavailable. */
        .df-brand-bg { background:#00A3FF !important; color:#fff !important; }
        .df-brand-bg:hover { background:#0088EE !important; }
        .df-brand-text { color:#00A3FF !important; }
        .df-navy-bg { background:#0A1628 !important; color:#fff !important; }
        .df-gold-bg { background:#F59E0B !important; color:#fff !important; }
        .df-gold-bg:hover { background:#D97706 !important; }
        .df-home-footer { background:#071A2F !important; color:#fff !important; }
        .df-home-footer a { color:#B8C6D8; }
        .df-home-footer a:hover { color:#fff; }
        .df-store-preview { border:1px solid #dbe8f3; background:linear-gradient(180deg,#fff 0%,#f8fbff 100%); box-shadow:0 24px 60px rgba(7,26,47,.20); }
        .df-product-visual { position:relative; overflow:hidden; height:112px; border-radius:12px; background:linear-gradient(135deg,#eaf3fb 0%,#cbdced 100%); }
        .df-product-visual::after { content:""; position:absolute; width:76px; height:76px; border-radius:20px; left:50%; top:50%; transform:translate(-50%,-50%) rotate(8deg); background:linear-gradient(135deg,#d7e6f4,#8ecff1); box-shadow:0 12px 25px rgba(20,100,150,.12); }
        .df-product-visual.alt { background:linear-gradient(135deg,#edf8ff 0%,#b7e3f8 100%); }
        .df-product-visual.alt::after { background:linear-gradient(135deg,#7dd3fc,#0284c7); border-radius:50%; width:70px; height:70px; transform:translate(-50%,-50%); }
        .df-preview-button { background:#00A3FF !important; color:#fff !important; transition:.2s ease; }
        .df-preview-button:hover { background:#0088EE !important; }
    </style>
<style>header nav{display:flex;align-items:center;gap:14px}a[data-df-pill]{display:inline-flex!important;align-items:center;justify-content:center;align-self:center;vertical-align:middle;height:40px;min-width:84px;padding:0 20px!important;margin:0!important;line-height:1;white-space:nowrap;box-sizing:border-box}</style><style>
.hp-wrap{width:min(1100px,calc(100% - 32px));margin:0 auto}
.hp-section{padding:36px 0}
.hp-soft{background:#F6F9FC}
.hp-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:16px}
.hp-head h2{font-size:22px;letter-spacing:-.02em;margin:0;color:#0F172A}
.hp-head p{margin:4px 0 0;color:#64748B;font-size:14px}
.hp-head a{font-size:14px;font-weight:700;color:#0B5CFF;white-space:nowrap;text-decoration:none}
.hp-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;padding:2px 0 12px}
.hp-row>*{min-width:0}.hp-row>:nth-child(n+5){display:none}
.hp-product{display:block;width:auto;background:#fff;border:1px solid #E2E8F0;border-radius:16px;overflow:hidden;color:inherit;text-decoration:none;box-shadow:0 6px 18px rgba(15,23,42,.05)}
.hp-product-img{height:132px;background:linear-gradient(135deg,#E0F2FE,#BFDBFE) center/cover no-repeat}
.hp-product-body{padding:10px 12px 12px}
.hp-product-name{font-size:14px;font-weight:700;line-height:1.3;margin:0 0 4px;min-height:36px;color:#0F172A}
.hp-price{font-size:15px;font-weight:800;color:#0B5CFF}
.hp-muted{font-size:12px;color:#64748B;margin-top:4px}
.hp-cats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:2px 0 6px}
.hp-cat{display:block;min-width:0;width:auto;text-align:center;text-decoration:none;color:inherit}
.hp-cat-ico{width:64px;height:64px;margin:0 auto 8px;border-radius:18px;background:#EFF6FF;display:flex;align-items:center;justify-content:center;font-size:26px;border:1px solid #DBEAFE}
.hp-cat-name{font-size:12px;font-weight:700;color:#334155;line-height:1.25;display:block}
@media (min-width:720px){.hp-section{padding:48px 0}}
</style>
<style>@media (min-width:1024px){.hp-row{grid-template-columns:repeat(5,minmax(0,1fr))}.hp-row>:nth-child(5){display:block}.hp-cats{grid-template-columns:repeat(6,minmax(0,1fr))}}</style></head>
<body class="bg-white text-slate-900 antialiased">

<!-- NAVIGATION -->
<header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur">
    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 lg:px-8">
        <a href="<?= url('/') ?>" class="flex items-center" aria-label="DogoFlow home">
            <img src="<?= url('/assets/images/df-logo.png') ?>" alt="DogoFlow" class="h-9 w-auto object-contain">
        </a>

        <div class="hidden items-center gap-7 text-sm font-semibold text-slate-600 md:flex">
            <a href="<?= url('/products.php') ?>" class="transition hover:text-brand">Products</a>
            <a href="<?= url('/stores.php') ?>" class="transition hover:text-brand">Stores</a>
            <a href="<?= url('/auth/login.php') ?>" class="transition hover:text-brand" data-df-pill="1" style="display:inline-flex;align-items:center;justify-content:center;height:40px;padding:0 20px;border-radius:999px;background:#fff;color:#0B2545 !important;border:1px solid #D8E3EF;font-weight:700;text-decoration:none;box-shadow:none">Login</a>
            <a href="<?= url('/auth/register.php') ?>" class="df-brand-bg rounded-lg px-4 py-2.5 text-xs font-extrabold shadow-sm transition hover:-translate-y-0.5">
                Create store
            </a>
        </div>

        <button id="dfMenuBtn" type="button" class="rounded-lg border border-slate-200 p-2 text-navy md:hidden" aria-label="Open navigation" aria-expanded="false">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </nav>

    <div id="dfMobileMenu" class="hidden border-t border-slate-100 bg-white px-5 py-4 md:hidden">
        <div class="flex flex-col gap-3 text-sm font-semibold">
            <a href="<?= url('/products.php') ?>">Products</a>
            <a href="<?= url('/stores.php') ?>">Stores</a>
            <a href="<?= url('/auth/login.php') ?>" data-df-pill="1" style="display:inline-flex;align-items:center;justify-content:center;height:40px;padding:0 20px;border-radius:999px;background:#fff;color:#0B2545 !important;border:1px solid #D8E3EF;font-weight:700;text-decoration:none;box-shadow:none">Login</a>
            <a href="<?= url('/auth/register.php') ?>" class="df-brand-bg rounded-lg px-4 py-3 text-center">Create store</a>
        </div>
    </div>
</header>

<main class="w-full overflow-hidden">
    <!-- HERO -->
    <section id="start" class="df-hero-gradient df-hero-grid relative overflow-hidden text-white">
        <div class="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-sky-400/20 blur-3xl"></div>

        <div class="relative mx-auto grid min-h-[590px] max-w-7xl items-center gap-14 px-5 py-20 lg:grid-cols-2 lg:px-8 lg:py-24">
            <div class="max-w-2xl">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-[.16em] text-sky-200 backdrop-blur">
                    DogoFlow Marketplace
                </div>

                <h1 class="max-w-3xl text-5xl font-black leading-[.94] tracking-[-.045em] sm:text-6xl lg:text-7xl">
                    Your business deserves
                    <span class="block df-text-gradient">its own store.</span>
                </h1>

                <p class="mt-7 max-w-xl text-base leading-7 text-slate-200 sm:text-lg">
                    Create a beautiful online store, showcase your products and start reaching customers — without the complexity.
                </p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <a href="<?= url('/auth/register.php') ?>" class="df-brand-bg rounded-xl px-6 py-3.5 text-center text-sm font-extrabold shadow-lg shadow-sky-950/20 transition hover:-translate-y-1">
                        Start Selling Free
                    </a>
                    <a href="<?= url('/stores.php') ?>" class="rounded-xl bg-white px-6 py-3.5 text-center text-sm font-extrabold text-navy transition hover:-translate-y-1 hover:bg-slate-100">
                        Explore Stores
                    </a>
                </div>

                <div class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-xs font-semibold text-slate-300">
                    <span>✓ No coding required</span>
                    <span>✓ Free to start</span>
                    <span>✓ Built for Ghanaian businesses</span>
                </div>
            </div>

            <!-- Store preview -->
            <div class="relative mx-auto w-full max-w-xl">
                <div class="absolute inset-8 rounded-[2rem] bg-sky-400/20 blur-3xl"></div>
                <div class="relative rounded-[2rem] border border-white/15 bg-white/10 p-4 shadow-2xl backdrop-blur-md sm:p-6">
                    <div class="df-store-preview rounded-[1.5rem] p-5 text-slate-900 sm:p-7">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-5">
                            <div>
                                <p class="df-brand-text text-[10px] font-bold uppercase tracking-widest">ONLINE STORE</p>
                                <h3 class="mt-1 text-xl font-black">Example store</h3>
                                <p class="mt-1 text-xs text-slate-500">Fashion &amp; lifestyle · Accra</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700">DF Store</div>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="df-float-card rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                <div class="df-product-visual"></div>
                                <p class="mt-3 text-xs font-extrabold text-slate-900">Premium Collection</p>
                                <div class="mt-1 flex items-center justify-between gap-2">
                                    <p class="text-sm font-black text-slate-900">GH₵120</p>
                                    <span class="rounded-md bg-sky-50 px-2 py-1 text-[9px] font-bold text-sky-700">In stock</span>
                                </div>
                            </div>
                            <div class="df-float-card rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                <div class="df-product-visual alt"></div>
                                <p class="mt-3 text-xs font-extrabold text-slate-900">Classic Essentials</p>
                                <div class="mt-1 flex items-center justify-between gap-2">
                                    <p class="text-sm font-black text-slate-900">GH₵85</p>
                                    <span class="rounded-md bg-sky-50 px-2 py-1 text-[9px] font-bold text-sky-700">In stock</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center justify-between rounded-xl bg-[#0A1628] px-4 py-3 text-white">
                            <div>
                                <span class="block text-xs font-extrabold">Your store is live</span>
                                <span class="mt-0.5 block text-[10px] text-slate-400">Ready to share with customers</span>
                            </div>
                            <span class="df-preview-button rounded-lg px-3 py-2 text-[10px] font-extrabold">View store →</span>
                        </div>
                    </div>
                </div>
            </div>            </div>
        </div>
    </section>
<section class="hp-section">
  <div class="hp-wrap">
    <div class="hp-head"><div><h2>Featured products</h2><p>From approved sellers on DogoFlow</p></div><a href="<?= e(url('/products.php')) ?>">View all</a></div>
    <?php if ($hpProducts): ?>
    <div class="hp-row">
      <?php foreach ($hpProducts as $p): $img = !empty($p['image']) ? url('/' . ltrim((string) $p['image'], '/')) : null; ?>
      <a class="hp-product" href="<?= e(url('/product.php?id=' . (int) $p['id'])) ?>">
        <div class="hp-product-img"<?= $img ? ' style="background-image:url(\'' . e($img) . '\')"' : '' ?>></div>
        <div class="hp-product-body">
          <p class="hp-product-name"><?= e((string) $p['name']) ?></p>
          <div class="hp-price">GH₵ <?= e(number_format((float) $p['price'], 2)) ?></div>
          <div class="hp-muted"><?= e((string) $p['business_name']) ?><?= !empty($p['seller_code']) ? ' · ' . e((string) $p['seller_code']) : '' ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="hp-muted">Products from approved sellers will appear here soon.</p>
    <?php endif; ?>
  </div>
</section>
<?php if ($hpCategories): ?>
<section class="hp-section hp-soft">
  <div class="hp-wrap">
    <div class="hp-head"><div><h2>Shop by category</h2><p>Find what you need</p></div><a href="<?= e(url('/categories.php')) ?>">All categories</a></div>
    <div class="hp-cats">
      <?php foreach ($hpCategories as $c): ?>
      <a class="hp-cat" href="<?= e(url('/products.php?category=' . urlencode((string) $c['slug']))) ?>">
        <span class="hp-cat-ico" aria-hidden="true"><?= hp_category_icon((string) $c['name']) ?></span>
        <span class="hp-cat-name"><?= e((string) $c['name']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>


    <!-- HOW IT WORKS -->
    <section id="products" class="bg-slate-50 py-24">
        <div class="mx-auto max-w-7xl px-5 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-xs font-extrabold uppercase tracking-[.18em] text-brand">Simple to start</p>
                <h2 class="mt-3 text-4xl font-black tracking-tight text-navy sm:text-5xl">From idea to online store in minutes.</h2>
                <p class="mt-5 text-base leading-7 text-slate-600">
                    DogoFlow gives you the tools to put your business online, add your products and share your store with customers.
                </p>
            </div>

            <div class="mt-14 grid gap-5 md:grid-cols-3">
                <article class="group rounded-2xl border border-slate-200 bg-white p-7 shadow-soft transition duration-300 hover:-translate-y-2 hover:border-sky-200 hover:shadow-lift">
                    <div class="flex items-center justify-between">
                        <span class="text-4xl font-black tracking-tight text-sky-100 group-hover:text-sky-200">01</span>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-brand">+</div>
                    </div>
                    <h3 class="mt-8 text-xl font-black text-navy">Start your store</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Create your seller account and get your own DogoFlow storefront.</p>
                </article>

                <article class="group rounded-2xl border border-slate-200 bg-white p-7 shadow-soft transition duration-300 hover:-translate-y-2 hover:border-sky-200 hover:shadow-lift">
                    <div class="flex items-center justify-between">
                        <span class="text-4xl font-black tracking-tight text-sky-100 group-hover:text-sky-200">02</span>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-brand">▤</div>
                    </div>
                    <h3 class="mt-8 text-xl font-black text-navy">Add your products</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Upload products, add prices and descriptions, then organize your catalog.</p>
                </article>

                <article class="group rounded-2xl border border-slate-200 bg-white p-7 shadow-soft transition duration-300 hover:-translate-y-2 hover:border-sky-200 hover:shadow-lift">
                    <div class="flex items-center justify-between">
                        <span class="text-4xl font-black tracking-tight text-sky-100 group-hover:text-sky-200">03</span>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-brand">↗</div>
                    </div>
                    <h3 class="mt-8 text-xl font-black text-navy">Share your link</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Share your store on WhatsApp, TikTok, Instagram and anywhere your customers are.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- AFFILIATE CTA -->
    <section id="affiliate" class="df-navy-bg py-20 text-white">
        <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-8 px-5 lg:flex-row lg:items-center lg:px-8">
            <div class="max-w-2xl">
                <p class="text-xs font-extrabold uppercase tracking-[.18em] text-sky-400">Grow with DogoFlow</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Know businesses that need an online store?</h2>
                <p class="mt-4 max-w-xl text-sm leading-6 text-slate-300">
                    Refer businesses to DogoFlow and earn when they successfully join the platform. Turn your network into an opportunity.
                </p>
            </div>
            <a href="<?= url('/affiliate/') ?>" class="df-gold-bg shrink-0 rounded-xl px-6 py-3.5 text-sm font-extrabold shadow-gold transition hover:-translate-y-1">Become an Affiliate →</a>
        </div>
    </section>

    <!-- PAYOUT ACTIVITY -->
    <section id="stores" class="overflow-hidden bg-white py-20">
        <div class="mx-auto max-w-7xl px-5 text-center lg:px-8">
            <p class="text-xs font-extrabold uppercase tracking-[.18em] text-brand">Real opportunity</p>
            <h2 class="mt-3 text-3xl font-black tracking-tight text-navy sm:text-4xl">Affiliates are getting paid.</h2>
            <p class="mx-auto mt-4 max-w-xl text-sm leading-6 text-slate-500">
                Verified affiliate commissions are sent to Mobile Money. <strong class="font-extrabold text-slate-700">Payouts are processed within 48–72 hours once verified.</strong>
            </p>

            <div class="relative mx-auto mt-8 max-w-5xl overflow-hidden">
                <div class="pointer-events-none absolute inset-y-0 left-0 z-10 w-16 bg-gradient-to-r from-white to-transparent"></div>
                <div class="pointer-events-none absolute inset-y-0 right-0 z-10 w-16 bg-gradient-to-l from-white to-transparent"></div>

                <div class="df-ticker flex w-max gap-3">
                    <?php for ($copy = 0; $copy < 2; $copy++): ?>
                        <div class="flex gap-3" aria-hidden="<?= $copy === 1 ? 'true' : 'false' ?>">
                            <?php foreach (['050********','024********','020********','024********','024********','024********'] as $phone): ?>
                                <div class="df-payout-pill">
                                    <span class="df-payout-dot" aria-hidden="true"></span>
                                    <span><?= e($phone) ?></span>
                                    <span class="df-payout-paid">· Paid</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <p class="df-payout-note mt-5 text-xs">Numbers are partially masked for privacy. Status updates after admin verification.</p>
        </div>
    </section>
</main>

<!-- FOOTER -->
<footer class="df-home-footer">
    <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
        <div class="sm:col-span-2">
            <a href="<?= url('/') ?>" class="inline-flex items-center">
                <img src="<?= url('/assets/images/df-logo.png') ?>" alt="DogoFlow" class="h-10 w-auto object-contain brightness-0 invert">
            </a>
            <p class="mt-5 max-w-sm text-sm leading-6 text-slate-300">Give your business a digital home. Create your store, reach customers and grow with DogoFlow.</p>
            <p class="mt-4 text-xs font-semibold uppercase tracking-[.14em] text-sky-300">DogoFlow Technologies · Accra, Ghana</p>
        </div>

        <div>
            <h3 class="text-sm font-bold text-white">Marketplace</h3>
            <div class="mt-4 space-y-3 text-sm">
                <a href="<?= url('/products.php') ?>" class="block">Browse products</a>
                <a href="<?= url('/stores.php') ?>" class="block">Explore stores</a>
                <a href="<?= url('/auth/register.php') ?>" class="block">Become a seller</a>
            </div>
        </div>

        <div>
            <h3 class="text-sm font-bold text-white">Grow with us</h3>
            <div class="mt-4 space-y-3 text-sm">
                <a href="<?= url('/affiliate/') ?>" class="block">Affiliate program</a>
                <a href="<?= url('/auth/register.php') ?>" class="block">Create account</a>
                <a href="<?= url('/auth/login.php') ?>" class="block">Sign in</a>
            </div>
        </div>

        <div>
            <h3 class="text-sm font-bold text-white">Contact</h3>
            <div class="mt-4 space-y-3 text-sm">
                <a href="mailto:info.dogoflowtechnologies.gh@gmail.com" class="block break-all">info.dogoflowtechnologies.gh@gmail.com</a>
                <span class="block text-slate-300">Mon–Sat · 6:00 AM–6:00 PM</span>
                <div class="flex gap-4 pt-1">
                    <a href="#" class="font-semibold">TikTok</a>
                    <a href="#" class="font-semibold">Instagram</a>
                    <a href="#" class="font-semibold">LinkedIn</a>
                </div>
            </div>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col justify-between gap-3 px-5 py-5 text-xs text-slate-500 sm:flex-row lg:px-8">
            <p>© <?= date('Y') ?> DogoFlow Technologies. All rights reserved.</p>
            <div class="flex gap-5">
                <a href="<?= e(url('/privacy.php')) ?>" class="hover:text-white">Privacy</a>
                <a href="<?= e(url('/terms.php')) ?>" class="hover:text-white">Terms</a>
                <a href="<?= e(url('/contact.php')) ?>" class="hover:text-white">Contact</a>
            </div>
        </div>
    </div>
</footer>

<script>
(() => {
    const button = document.getElementById('dfMenuBtn');
    const menu = document.getElementById('dfMobileMenu');
    if (!button || !menu) return;

    button.addEventListener('click', () => {
        const isOpen = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden');
        button.setAttribute('aria-expanded', String(!isOpen));
    });

    menu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            menu.classList.add('hidden');
            button.setAttribute('aria-expanded', 'false');
        });
    });
})();
</script>

</body>
</html>
