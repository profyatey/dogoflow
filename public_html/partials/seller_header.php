<?php
/**
 * Shared seller header. Pages set $sellerActive (e.g. 'products') before including this.
 * Mobile: brand on the left, hamburger on the right, menu opens as a panel below.
 * Desktop (1024px and up): the same links sit in one row, no hamburger.
 */
$sellerActive = $sellerActive ?? '';
$sellerMenu = [
    'dashboard' => ['Dashboard', '/seller/dashboard.php'],
    'products' => ['Products', '/seller/products.php'],
    'marketing' => ['Marketing', '/seller/marketing.php'],
    'store-profile' => ['Store profile', '/seller/store-profile.php'],
    'upgrade' => ['Upgrade plan', '/seller/upgrade.php'],
];
?>
<style>
.df-sh{position:sticky;top:0;z-index:40;background:rgba(255,255,255,.97);backdrop-filter:blur(8px);border-bottom:1px solid #E2E8F0;font-family:inherit}
.df-sh-bar{position:relative;display:flex;align-items:center;justify-content:space-between;gap:12px;height:64px;padding:0 16px;max-width:1200px;margin:0 auto}
.df-sh-brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:#0F172A;font-weight:800;font-size:16px;min-width:0}
.df-sh-brand img{width:36px;height:36px;border-radius:10px;flex:0 0 auto}
.df-sh-brand small{display:block;font-size:11px;font-weight:700;color:#0B5CFF;letter-spacing:.06em;text-transform:uppercase;line-height:1}
.df-sh-burger{width:44px;height:44px;border:1px solid #CBD5E1;border-radius:12px;background:#fff;display:inline-flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;cursor:pointer;padding:0;flex:0 0 auto}
.df-sh-burger span{display:block;width:20px;height:2px;background:#0F172A;border-radius:2px;transition:transform .2s,opacity .2s}
.df-sh-burger[aria-expanded="true"] span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.df-sh-burger[aria-expanded="true"] span:nth-child(2){opacity:0}
.df-sh-burger[aria-expanded="true"] span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
.df-sh-panel{position:absolute;top:100%;left:0;right:0;display:flex;flex-direction:column;gap:4px;padding:10px 16px 16px;background:#fff;border-top:1px solid #E2E8F0;box-shadow:0 18px 40px rgba(15,23,42,.12)}
.df-sh-panel[hidden]{display:none}
.df-sh-link{display:flex;align-items:center;min-height:48px;padding:0 14px;border-radius:12px;color:#334155;font-weight:700;font-size:15px;text-decoration:none;white-space:nowrap}
.df-sh-link:hover{background:#F1F5F9}
.df-sh-link.is-active{background:#EFF6FF;color:#0B5CFF}
.df-sh-link.df-sh-logout{margin-top:8px;border-radius:0;border-top:1px solid #E2E8F0;padding-top:14px;color:#B42318}
@media (min-width:1024px){
  .df-sh-burger{display:none}
  .df-sh-panel{position:static;flex-direction:row;align-items:center;gap:2px;padding:0;border:0;box-shadow:none;background:transparent;margin-left:auto}
  .df-sh-panel[hidden]{display:flex}
  .df-sh-link{min-height:38px;padding:0 10px;font-size:14px}
  .df-sh-link.df-sh-logout{margin:0 0 0 8px;border:0;padding:0 12px;min-height:38px;background:#FEF3F2}
}
</style>
<header class="df-sh">
  <div class="df-sh-bar">
    <a class="df-sh-brand" href="<?= e(url('/seller/dashboard.php')) ?>">
      <img src="<?= e(url('/assets/images/df-logo-sm.png')) ?>" alt="">
      <span>DogoFlow<small>Seller</small></span>
    </a>
    <button type="button" class="df-sh-burger" aria-label="Open seller menu" aria-expanded="false" aria-controls="dfSellerMenu">
      <span></span><span></span><span></span>
    </button>
    <nav id="dfSellerMenu" class="df-sh-panel" aria-label="Seller navigation" hidden>
      <?php foreach ($sellerMenu as $key => [$label, $path]): ?>
        <a class="df-sh-link<?= $sellerActive === $key ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>"<?= $sellerActive === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="df-sh-link df-sh-logout" href="<?= e(url('/auth/logout.php')) ?>">Log out</a>
    </nav>
  </div>
</header>
<script>
(function () {
  var btn = document.querySelector('.df-sh-burger');
  var panel = document.getElementById('dfSellerMenu');
  if (!btn || !panel) return;
  function setOpen(open) {
    panel.hidden = !open;
    btn.setAttribute('aria-expanded', String(open));
    btn.setAttribute('aria-label', open ? 'Close seller menu' : 'Open seller menu');
  }
  setOpen(false);
  btn.addEventListener('click', function () { setOpen(panel.hidden); });
  panel.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { setOpen(false); }); });
  document.addEventListener('click', function (e) {
    if (!panel.hidden && !panel.contains(e.target) && !btn.contains(e.target)) setOpen(false);
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
})();
</script>
