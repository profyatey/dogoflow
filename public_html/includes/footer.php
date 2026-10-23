<?php
// Shared public footer (Tailwind utilities). Replace # social URLs when official handles are confirmed.
$dfFooterEmail = 'info.dogoflowtechnologies.gh@gmail.com';

?>
<style>
.df-public-footer{margin-top:56px;background:#071A33!important;color:#F8FAFC!important;padding:42px 20px 18px!important;border-top:1px solid rgba(255,255,255,.08);box-sizing:border-box;font-family:inherit}
.df-public-footer *{box-sizing:border-box}
.df-public-footer .df-footer-wrap{width:min(1200px,100%);margin:0 auto}
.df-public-footer .df-footer-grid{display:grid;grid-template-columns:1.5fr 1fr 1fr 1.3fr;gap:30px}
.df-public-footer .df-footer-brand{display:block;margin:0 0 10px;max-height:48px;max-width:145px;width:auto;object-fit:contain}
.df-public-footer h3,.df-public-footer h4{margin:0 0 12px;color:#fff!important;font-size:14px;font-weight:800}
.df-public-footer p,.df-public-footer span{color:#C9D8E7!important;font-size:13px;line-height:1.8}
.df-public-footer .df-footer-col{display:flex;flex-direction:column;align-items:flex-start;gap:6px}
.df-public-footer a{color:#C9D8E7!important;text-decoration:none!important;font-size:13px;line-height:1.8;transition:color .2s ease,transform .2s ease}
.df-public-footer a:hover{color:#00A3FF!important;transform:translateX(1px)}
.df-public-footer .df-footer-email{overflow-wrap:anywhere}
.df-public-footer .df-footer-bottom{margin-top:30px;padding-top:14px;border-top:1px solid rgba(255,255,255,.13);display:flex;justify-content:space-between;gap:16px;align-items:center}
.df-public-footer .df-footer-bottom span{color:#AFC2D5!important;font-size:12px}
@media(max-width:800px){.df-public-footer .df-footer-grid{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.df-public-footer{padding:32px 18px 16px!important}.df-public-footer .df-footer-grid{grid-template-columns:1fr;gap:22px}.df-public-footer .df-footer-bottom{align-items:flex-start;flex-direction:column;gap:6px}}
</style>
<footer class="df-public-footer">
 <div class="df-footer-wrap">
  <div class="df-footer-grid">
   <div>
    <img class="df-footer-brand" src="<?= e(url('/assets/images/df-logo.png')) ?>" alt="DogoFlow">
    <h3>DogoFlow Technologies</h3>
    <p>Discover products, connect with sellers, and grow your business with DogoFlow.</p>
   </div>
   <div class="df-footer-col">
    <h4>Marketplace</h4>
    <a href="<?= e(url('/products.php')) ?>">Browse products</a>
    <a href="<?= e(url('/stores.php')) ?>">Explore stores</a>
    <a href="<?= e(url('/auth/register.php?account_type=seller')) ?>">Become a seller</a>
   </div>
   <div class="df-footer-col">
    <h4>Grow with us</h4>
    <a href="<?= e(url('/affiliate/')) ?>">Affiliate program</a>
    <a href="<?= e(url('/auth/register.php')) ?>">Create account</a>
    <a href="<?= e(url('/auth/login.php')) ?>">Sign in</a>
   </div>
   <div class="df-footer-col">
    <h4>Contact</h4>
    <a class="df-footer-email" href="mailto:info.dogoflowtechnologies.gh@gmail.com">info.dogoflowtechnologies.gh@gmail.com</a>
    <span>Accra, Ghana</span>
    <a href="#" aria-label="TikTok profile">TikTok</a>
    <a href="#" aria-label="Instagram profile">Instagram</a>
   </div>
  </div>
  <div class="df-footer-bottom"><span>&copy; <?= date('Y') ?> DogoFlow Technologies. All rights reserved.</span><span class="df-legal-links"><a href="<?= e(url('/privacy.php')) ?>">Privacy</a> · <a href="<?= e(url('/terms.php')) ?>">Terms</a> · <a href="<?= e(url('/contact.php')) ?>">Contact</a></span></div>
 </div>
</footer>
