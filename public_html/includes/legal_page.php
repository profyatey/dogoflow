<?php
declare(strict_types=1);

/** Shared layout for the Terms, Privacy and Contact pages. */
function legal_head(string $title, string $intro): void
{
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>"><title><?= e($title) ?> · DogoFlow</title><meta name="description" content="<?= e($intro) ?>"><style>
.lg{background:#F6F9FC;color:#0A1628;min-height:100vh;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;margin:0}
.lg-nav{background:#fff;border-bottom:1px solid #E2E8F0;height:68px;display:flex;align-items:center}
.lg-nav-inner{width:min(1040px,calc(100% - 30px));margin:auto;display:flex;justify-content:space-between;align-items:center}
.lg-nav img{height:40px}.lg-nav nav{display:flex;gap:22px}.lg-nav nav a{font-size:14px;font-weight:700;color:#475569;text-decoration:none}
.lg-wrap{width:min(860px,calc(100% - 30px));margin:40px auto 10px}
.lg-eyebrow{font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#0088EE;margin:0 0 8px}
.lg h1{font-size:clamp(28px,4vw,38px);letter-spacing:-.04em;margin:0 0 10px}
.lg-intro{color:#64748B;font-size:15px;line-height:1.7;margin:0 0 28px}
.lg-card{background:#fff;border:1px solid #E2E8F0;border-radius:18px;padding:28px 30px;margin-bottom:22px}
.lg-card h2{font-size:18px;letter-spacing:-.02em;margin:0 0 10px}
.lg-card p,.lg-card li{font-size:15px;line-height:1.75;color:#334155;margin:0 0 12px}
.lg-card ul{padding-left:20px;margin:0 0 12px}
.lg-card a{color:#0088EE;font-weight:700}
.lg-muted{font-size:13px;color:#64748B}
@media(max-width:600px){.lg-card{padding:22px 20px}.lg-nav nav{display:none}}
</style><style>header nav{display:flex;align-items:center;gap:14px}a[data-df-pill]{display:inline-flex!important;align-items:center;justify-content:center;align-self:center;vertical-align:middle;height:40px;min-width:84px;padding:0 20px!important;margin:0!important;line-height:1;white-space:nowrap;box-sizing:border-box}</style></head><body class="lg"><header class="lg-nav"><div class="lg-nav-inner"><a href="<?= e(url('/')) ?>"><img src="<?= e(url('/assets/images/df-logo.png')) ?>" alt="DogoFlow"></a><nav><a href="<?= e(url('/products.php')) ?>">Products</a><a href="<?= e(url('/stores.php')) ?>">Stores</a><a href="<?= e(url('/auth/login.php')) ?>" data-df-pill="1" style="display:inline-flex;align-items:center;justify-content:center;height:40px;padding:0 20px;border-radius:999px;background:#0B5CFF;color:#fff !important;font-weight:700;text-decoration:none;box-shadow:0 8px 18px rgba(11,92,255,.22)">Sign in</a></nav></div></header><main class="lg-wrap"><?php
}

function legal_foot(): void
{
    ?></main><?php require __DIR__ . '/footer.php'; ?></body></html><?php
}
