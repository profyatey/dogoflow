<?php
// Shared public footer. Replace # social URLs when official handles are confirmed.
$dfFooterEmail = 'info.dogoflowtechnologies.gh@gmail.com';
?>
<footer class="df-public-footer">
 <div class="df-footer-inner">
  <div class="df-footer-grid">
   <div><img class="df-footer-logo" src="<?= e(url('/assets/images/df-logo.png')) ?>" alt="DogoFlow"><h3>DogoFlow Technologies</h3><p>Discover products, connect with sellers, and grow your business with DogoFlow.</p></div>
   <div class="df-footer-col"><h4>Marketplace</h4><a href="<?= e(url('/products.php')) ?>">Browse products</a><a href="<?= e(url('/stores.php')) ?>">Explore stores</a><a href="<?= e(url('/auth/register.php?account_type=seller')) ?>">Become a seller</a></div>
   <div class="df-footer-col"><h4>Grow with us</h4><a href="<?= e(url('/affiliate/')) ?>">Affiliate program</a><a href="<?= e(url('/auth/register.php')) ?>">Create account</a><a href="<?= e(url('/auth/login.php')) ?>">Sign in</a></div>
   <div class="df-footer-col"><h4>Contact</h4><a href="mailto:<?= e($dfFooterEmail) ?>"><?= e($dfFooterEmail) ?></a><span>Accra, Ghana</span><a href="#" aria-label="TikTok profile">TikTok</a><a href="#" aria-label="Instagram profile">Instagram</a></div>
  </div><div class="df-footer-bottom"><p>&copy; <?= date('Y') ?> DogoFlow Technologies. All rights reserved.</p></div>
 </div>
</footer>
