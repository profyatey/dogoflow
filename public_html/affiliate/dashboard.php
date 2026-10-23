<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('affiliate');

$pdo = db();
$userId = (int) Auth::userId();

$stmt = $pdo->prepare('SELECT id, referral_code, status FROM affiliate_profiles WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    http_response_code(403);
    exit('Affiliate profile not found.');
}

$affiliateId = (int) $profile['id'];

$stmt = $pdo->prepare("SELECT
    COUNT(*) AS total,
    SUM(status = 'registered') AS registered,
    SUM(status = 'verified') AS verified,
    SUM(status = 'qualified') AS qualified
    FROM affiliate_referrals
    WHERE affiliate_id = ?");
$stmt->execute([$affiliateId]);
$referrals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("SELECT
    COUNT(*) AS clicks,
    COUNT(DISTINCT DATE(created_at)) AS active_days
    FROM affiliate_clicks
    WHERE affiliate_id = ?");
$stmt->execute([$affiliateId]);
$clickStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN status IN ('available','paid') THEN amount ELSE 0 END), 0) AS total_earned,
    COALESCE(SUM(CASE WHEN status = 'available' THEN amount ELSE 0 END), 0) AS available,
    COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid,
    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending
    FROM affiliate_rewards
    WHERE affiliate_id = ?");
$stmt->execute([$affiliateId]);
$rewards = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("SELECT
    r.id,
    r.status,
    r.created_at,
    r.qualified_at,
    u.name
    FROM affiliate_referrals r
    INNER JOIN users u ON u.id = r.referred_user_id
    WHERE r.affiliate_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5");
$stmt->execute([$affiliateId]);
$recentReferrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT amount, status, reference, created_at
    FROM affiliate_rewards
    WHERE affiliate_id = ?
    ORDER BY created_at DESC
    LIMIT 5");
$stmt->execute([$affiliateId]);
$recentRewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

$link = url('/affiliate/track.php?ref=' . rawurlencode((string) $profile['referral_code']));

$totalReferrals = (int) ($referrals['total'] ?? 0);
$qualified = (int) ($referrals['qualified'] ?? 0);
$clicks = (int) ($clickStats['clicks'] ?? 0);
$totalEarned = (float) ($rewards['total_earned'] ?? 0);
$available = (float) ($rewards['available'] ?? 0);
$paid = (float) ($rewards['paid'] ?? 0);
$pending = (float) ($rewards['pending'] ?? 0);
$conversion = $clicks > 0 ? ($qualified / $clicks) * 100 : 0;

function affiliateStatusClasses(string $status): string
{
    return match ($status) {
        'qualified', 'available', 'paid' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'verified' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'registered', 'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'rejected', 'reversed' => 'bg-rose-50 text-rose-700 ring-rose-200',
        default => 'bg-slate-50 text-slate-600 ring-slate-200',
    };
}

function affiliateStatusLabel(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}
?>
<!doctype html>
<html lang="en">
<head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#00A3FF">
    <title>Affiliate Dashboard · DogoFlow</title>
    <?php require __DIR__ . '/../partials/tailwind.php'; ?>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900">
    <main class="mx-auto w-full max-w-7xl px-4 py-5 sm:px-6 lg:px-8 lg:py-8">
        <header class="mb-6 rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:px-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-2 text-lg font-extrabold tracking-tight text-slate-950 no-underline">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-sky-500 text-sm font-black text-white">DF</span>
                        DogoFlow <span class="font-medium text-slate-400">/ Affiliate</span>
                    </a>
                    <p class="mt-2 text-sm text-slate-500">Turn seller referrals into GH₵10 rewards.</p>
                </div>
                <nav class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                    <a href="<?= e(url('/affiliate/dashboard.php')) ?>" class="rounded-lg bg-sky-50 px-3 py-2 text-sky-700 no-underline">Overview</a>
                    <a href="<?= e(url('/affiliate/links.php')) ?>" class="rounded-lg px-3 py-2 text-slate-600 no-underline hover:bg-slate-100">Referral Links</a>
                    <a href="<?= e(url('/affiliate/clicks.php')) ?>" class="rounded-lg px-3 py-2 text-slate-600 no-underline hover:bg-slate-100">Clicks</a>
                    <a href="<?= e(url('/affiliate/commissions.php')) ?>" class="rounded-lg px-3 py-2 text-slate-600 no-underline hover:bg-slate-100">Commissions</a>
                    <a href="<?= e(url('/affiliate/withdrawals.php')) ?>" class="rounded-lg px-3 py-2 text-slate-600 no-underline hover:bg-slate-100">Withdrawals</a>
                    <a href="<?= e(url('/auth/logout.php')) ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-600 no-underline hover:bg-slate-50">Sign out</a>
                </nav>
            </div>
        </header>

        <section class="mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-sky-950 px-5 py-7 text-white shadow-lg sm:px-8 sm:py-9">
            <div class="grid gap-8 lg:grid-cols-[1.35fr_.65fr] lg:items-center">
                <div>
                    <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold text-sky-100 ring-1 ring-white/10">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        Affiliate account <?= e(affiliateStatusLabel((string) $profile['status'])) ?>
                    </div>
                    <h1 class="max-w-2xl text-3xl font-black tracking-tight sm:text-4xl">Refer sellers. Earn GH₵10.</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">Share your personal referral link. When a referred seller is approved, the GH₵10 reward is recorded against your affiliate account.</p>
                    <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                        <a href="<?= e(url('/affiliate/links.php')) ?>" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-sky-500 px-5 py-3 text-sm font-extrabold text-white no-underline shadow-sm hover:bg-sky-400">Manage referral link</a>
                        <a href="<?= e(url('/affiliate/withdrawals.php')) ?>" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-sm font-bold text-white no-underline hover:bg-white/15">View withdrawals</a>
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                    <p class="text-xs font-bold uppercase tracking-[.16em] text-sky-200">Available balance</p>
                    <p class="mt-2 text-3xl font-black">GH₵ <?= number_format($available, 2) ?></p>
                    <p class="mt-1 text-sm text-slate-300">Ready for your next withdrawal request.</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-500">Referral clicks</p>
                <p class="mt-2 text-2xl font-black text-slate-950"><?= number_format($clicks) ?></p>
                <p class="mt-1 text-xs text-slate-400">Tracked visits to your referral links</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-500">Referrals</p>
                <p class="mt-2 text-2xl font-black text-slate-950"><?= number_format($totalReferrals) ?></p>
                <p class="mt-1 text-xs text-slate-400"><?= number_format($qualified) ?> qualified</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-500">Total earned</p>
                <p class="mt-2 text-2xl font-black text-slate-950">GH₵ <?= number_format($totalEarned, 2) ?></p>
                <p class="mt-1 text-xs text-slate-400">Available + paid rewards</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-500">Conversion</p>
                <p class="mt-2 text-2xl font-black text-slate-950"><?= number_format($conversion, 1) ?>%</p>
                <p class="mt-1 text-xs text-slate-400">Qualified referrals ÷ clicks</p>
            </div>
        </section>

        <section class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[.16em] text-sky-600">Share &amp; grow</p>
                        <h2 class="mt-1 text-xl font-extrabold tracking-tight text-slate-950">Your referral link</h2>
                        <p class="mt-1 text-sm text-slate-500">Your code is <span class="font-bold tracking-wider text-slate-700"><?= e((string) $profile['referral_code']) ?></span>.</p>
                    </div>
                    <a href="<?= e(url('/affiliate/links.php')) ?>" class="text-sm font-bold text-sky-600 no-underline hover:text-sky-700">Open referral tools →</a>
                </div>
                <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                    <input id="affiliateReferralLink" type="text" readonly value="<?= e($link) ?>" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100" onclick="this.select()">
                    <button type="button" data-copy-link="affiliateReferralLink" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-sky-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-sky-600">Copy link</button>
                </div>
                <p id="copyStatus" class="mt-2 min-h-5 text-sm text-emerald-700" aria-live="polite"></p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-xs font-extrabold uppercase tracking-[.16em] text-sky-600">Earnings</p>
                <h2 class="mt-1 text-xl font-extrabold tracking-tight text-slate-950">Reward summary</h2>
                <div class="mt-5 space-y-4">
                    <div class="flex items-center justify-between gap-4"><span class="text-sm text-slate-500">Available</span><strong class="text-sm text-emerald-700">GH₵ <?= number_format($available, 2) ?></strong></div>
                    <div class="flex items-center justify-between gap-4"><span class="text-sm text-slate-500">Paid out</span><strong class="text-sm text-slate-900">GH₵ <?= number_format($paid, 2) ?></strong></div>
                    <div class="flex items-center justify-between gap-4"><span class="text-sm text-slate-500">Pending</span><strong class="text-sm text-amber-700">GH₵ <?= number_format($pending, 2) ?></strong></div>
                </div>
            </div>
        </section>

        <section class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div><h2 class="font-extrabold text-slate-950">Recent referrals</h2><p class="mt-1 text-xs text-slate-500">Your latest referred users</p></div>
                    <a href="<?= e(url('/affiliate/clicks.php')) ?>" class="text-sm font-bold text-sky-600 no-underline">View tracking</a>
                </div>
                <?php if (!$recentReferrals): ?>
                    <div class="px-5 py-10 text-center text-sm text-slate-500">No referrals yet. Share your link to get started.</div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($recentReferrals as $row): ?>
                            <div class="flex items-center justify-between gap-4 px-5 py-4 sm:px-6">
                                <div class="min-w-0"><p class="truncate text-sm font-bold text-slate-900"><?= e((string) $row['name']) ?></p><p class="mt-1 text-xs text-slate-500"><?= e(date('d M Y', strtotime((string) $row['created_at']))) ?></p></div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold ring-1 <?= affiliateStatusClasses((string) $row['status']) ?>"><?= e(affiliateStatusLabel((string) $row['status'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div><h2 class="font-extrabold text-slate-950">Recent rewards</h2><p class="mt-1 text-xs text-slate-500">GH₵10 affiliate reward activity</p></div>
                    <a href="<?= e(url('/affiliate/commissions.php')) ?>" class="text-sm font-bold text-sky-600 no-underline">View all</a>
                </div>
                <?php if (!$recentRewards): ?>
                    <div class="px-5 py-10 text-center text-sm text-slate-500">No rewards have been recorded yet.</div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($recentRewards as $row): ?>
                            <div class="flex items-center justify-between gap-4 px-5 py-4 sm:px-6">
                                <div class="min-w-0"><p class="text-sm font-bold text-slate-900">GH₵ <?= number_format((float) $row['amount'], 2) ?></p><p class="mt-1 truncate text-xs text-slate-500"><?= e(date('d M Y', strtotime((string) $row['created_at']))) ?> · <?= e((string) $row['reference']) ?></p></div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold ring-1 <?= affiliateStatusClasses((string) $row['status']) ?>"><?= e(affiliateStatusLabel((string) $row['status'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <footer class="mt-8 pb-4 text-center text-xs text-slate-400">DogoFlow Affiliate Program · Share. Refer. Earn.</footer>
    </main>

    <script>
    (() => {
        const button = document.querySelector('[data-copy-link]');
        if (!button) return;
        button.addEventListener('click', async () => {
            const input = document.getElementById(button.dataset.copyLink);
            const status = document.getElementById('copyStatus');
            if (!input || !input.value) return;
            let copied = false;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(input.value);
                    copied = true;
                } else {
                    input.focus(); input.select(); input.setSelectionRange(0, input.value.length);
                    copied = document.execCommand('copy');
                }
            } catch (error) {
                input.focus(); input.select();
                try { copied = document.execCommand('copy'); } catch (_) { copied = false; }
            }
            status.textContent = copied ? 'Referral link copied. You can now share it.' : 'Copy was blocked by your browser. Select the link and copy it manually.';
            button.textContent = copied ? 'Copied!' : 'Try again';
            window.setTimeout(() => { button.textContent = 'Copy link'; }, 2500);
        });
    })();
    </script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
