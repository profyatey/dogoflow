<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('affiliate');
$pdo = db();
$userId = (int) Auth::userId();
$stmt = $pdo->prepare('SELECT id, referral_code, status FROM affiliate_profiles WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$profile) { http_response_code(403); exit('Affiliate profile not found.'); }
$affiliateId = (int)$profile['id'];
$baseLink = url('/affiliate/track.php?ref=' . rawurlencode((string)$profile['referral_code']));
function aff_status(string $s): array { return match($s) { 'qualified','available','paid' => ['bg-emerald-50 text-emerald-700','Good'], 'verified' => ['bg-sky-50 text-sky-700','Verified'], 'registered','pending' => ['bg-amber-50 text-amber-700','Pending'],
 'approved','processing' => ['bg-sky-50 text-sky-700','Being paid'], 'rejected','reversed','failed' => ['bg-rose-50 text-rose-700','Attention'], default => ['bg-slate-100 text-slate-600','Info'] }; }
function aff_label(string $s): string { return ucfirst(str_replace('_',' ',$s)); }
function aff_nav(string $active): string {
    $items = ['dashboard'=>'Overview','links'=>'Referral Links','clicks'=>'Clicks','commissions'=>'Commissions','withdrawals'=>'Withdrawals','onboarding'=>'How it works'];
    $html=''; foreach($items as $key=>$label){$cls=$active===$key?'bg-sky-50 text-sky-700':'text-slate-600 hover:bg-slate-100';$html.='<a href="'.e(url('/affiliate/'.$key.'.php')).'" class="rounded-lg px-3 py-2 no-underline '.$cls.'">'.e($label).'</a>'; } return $html;
}
function aff_head(string $title,string $active): void { global $profile; ?>
<!doctype html><html lang="en"><head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#00A3FF"><title><?= e($title) ?> · DogoFlow</title><?php require __DIR__ . '/../partials/tailwind.php'; ?></head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900"><main class="mx-auto w-full max-w-7xl px-4 py-5 sm:px-6 lg:px-8 lg:py-8">
<header class="mb-6 rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:px-6"><div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"><div><a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-2 text-lg font-extrabold tracking-tight text-slate-950 no-underline"><img src="<?= e(url('/assets/images/df-logo-sm.png')) ?>" alt="DogoFlow" class="h-9 w-9 rounded-xl">DogoFlow <span class="font-medium text-slate-400">/ Affiliate</span></a><p class="mt-2 text-sm text-slate-500">Share. Refer. Earn GH₵10.</p></div><nav class="flex flex-wrap items-center gap-1 text-sm font-semibold"><?= aff_nav($active) ?><a href="<?= e(url('/auth/logout.php')) ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-600 no-underline hover:bg-slate-50">Sign out</a></nav></div></header>
<?php }
function aff_foot(): void { ?></main><?php require __DIR__ . '/../includes/footer.php'; ?></body></html><?php }
