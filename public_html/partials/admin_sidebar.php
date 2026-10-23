<?php
// Isolated admin UI stylesheet. This affects only pages that render the admin shell.
?>
<link rel="stylesheet" href="<?=url('/assets/css/admin-dashboard.css')?>">
<?php
$active = $active ?? 'dashboard';
$adminName = 'Administrator';
$adminEmail = '';
if (Auth::userId()) {
    $stmt = db()->prepare('SELECT name,email FROM users WHERE id=? LIMIT 1');
    $stmt->execute([Auth::userId()]);
    $me = $stmt->fetch();
    if ($me) { $adminName = $me['name'] ?: $adminName; $adminEmail = $me['email'] ?? ''; }
}
$initial = strtoupper(substr(trim($adminName), 0, 1));
$items = [
    'dashboard' => ['📊','Dashboard','/admin/dashboard.php'],
    'users' => ['👥','Users','/admin/users.php'],
    'signup' => ['🔓','Sign-up','/admin/signup-settings.php'],
    'sellers' => ['🏪','Sellers','/admin/sellers.php'],
    'products' => ['📦','Products','/admin/products.php'],
    'categories' => ['🏷️','Categories','/admin/categories.php'],
    'orders' => ['🛍️','Orders','/admin/orders.php'],
    'payments' => ['💳','Payments','/admin/payments.php'],
    'subscriptions' => ['🧾','Subscriptions','/admin/subscription-payments.php'],
    'affiliates' => ['🔗','Affiliates','/admin/affiliates.php'],
    'withdrawals' => ['💸','Withdrawals','/admin/withdrawals.php'],
    'sms' => ['✉️','SMS Center','/admin/sms.php'],
    'audit' => ['🛡️','Audit Logs','/admin/audit-logs.php'],
    'reports' => ['📈','Reports','/admin/reports.php'],
    'system' => ['⚙️','System Checks','/admin/system-check.php'],
];
$navBase   = 'flex items-center gap-3 rounded-xl px-3 py-[11px] text-[.88rem] font-bold no-underline transition duration-200';
$navIdle   = 'text-[#C5D6E5] hover:translate-x-0.5 hover:bg-white/[.08] hover:text-white';
$navActive = 'bg-brand-gradient text-white shadow-[0_8px_20px_rgba(0,163,255,.24)]';
$navLabel  = 'mt-[5px] px-3 pb-[7px] text-[.67rem] font-extrabold uppercase tracking-[.12em] text-[#7FA1BF]';
?>
<aside id="adminSidebar" class="fixed inset-y-0 left-0 z-[1100] flex w-[248px] flex-col border-r border-white/[.08] bg-sidebar-gradient px-4 py-[22px] text-white shadow-[8px_0_28px_rgba(7,26,51,.10)] transition-transform duration-[250ms] max-[1100px]:w-[235px] max-[820px]:w-[min(285px,88vw)] max-[820px]:-translate-x-[105%] max-[820px]:[&.open]:translate-x-0">
    <a class="flex items-center gap-2.5 border-b border-white/10 px-2.5 pb-[22px] pt-1.5 text-white no-underline" href="<?=url('/admin/dashboard.php')?>">
        <img class="h-[38px] w-auto" src="<?=url('/assets/images/df-logo-sm.png')?>" alt="DogoFlow">
        <span class="text-[1.05rem] font-extrabold tracking-[-.02em] text-white">DogoFlow<small class="mt-0.5 block text-[.68rem] font-semibold uppercase tracking-[.05em] text-[#A9C2D9]">Administration</small></span>
    </a>
    <nav class="mt-5 flex min-h-0 flex-1 flex-col gap-[5px] overflow-y-auto overflow-x-hidden [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden" aria-label="Admin navigation">
        <div class="<?= $navLabel ?>">Management</div>
        <?php foreach ($items as $key => [$icon,$label,$href]): ?>
            <a href="<?= e($href) ?>" class="<?= $navBase ?> <?= $active === $key ? $navActive : $navIdle ?>">
                <span class="w-[21px] flex-none text-center text-base" aria-hidden="true"><?= $icon ?></span><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
        <div class="<?= $navLabel ?> mt-3.5">Platform</div>
        <a class="<?= $navBase ?> <?= $navIdle ?>" href="<?=url('/')?>" target="_blank"><span class="w-[21px] flex-none text-center text-base">🌐</span><span>View Storefront</span></a>
    </nav>
    <div class="mt-auto border-t border-white/10 pt-3.5">
        <div class="flex items-center gap-2.5 rounded-xl border border-white/5 bg-white/[.07] p-2.5">
            <div class="grid h-[38px] w-[38px] flex-none place-items-center rounded-xl bg-avatar-gradient font-extrabold"><?= e($initial) ?></div>
            <div class="min-w-0">
                <div class="truncate text-[.82rem] font-extrabold"><?= e($adminName) ?></div>
                <div class="text-[.68rem] uppercase tracking-[.06em] text-[#9DB8D0]">Administrator</div>
            </div>
        </div>
        <?php if ($adminEmail): ?><div class="truncate px-2.5 pt-[7px] text-[.68rem] text-slate-500"><?= e($adminEmail) ?></div><?php endif; ?>
        <a href="<?=url('/auth/logout.php')?>" class="mt-2 block w-full rounded-[10px] border border-white/[.13] bg-transparent p-[9px] text-center text-[.8rem] font-bold text-[#D7E4EF] no-underline transition hover:border-danger/25 hover:bg-danger/[.12] hover:text-[#FDA4AF]">↪ Log out</a>
    </div>
</aside>
<div id="adminBackdrop" class="fixed inset-0 z-[1050] hidden bg-brand-deep/[.48] max-[820px]:block" hidden></div>
