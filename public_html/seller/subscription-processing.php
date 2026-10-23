<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/payments/SellerSubscriptionPayment.php';

Auth::requireApprovedSeller();

$reference = trim((string)($_GET['reference'] ?? ''));
if (!preg_match('/^DFPRO-\d+-[A-F0-9]{16}$/', $reference)) {
    flash('error', 'Invalid Paystack payment reference.');
    redirect('/seller/upgrade.php');
}

$payment = SellerSubscriptionPayment::findByReference($reference);
if (!$payment || (int)$payment['user_id'] !== (int)Auth::userId()) {
    flash('error', 'This payment does not belong to your seller account.');
    redirect('/seller/upgrade.php');
}

$statusUrl = url('/seller/subscription-status.php?reference=' . rawurlencode($reference));
$upgradeUrl = url('/seller/upgrade.php');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Confirming your payment · DogoFlow</title>
<link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>">
<style>
  [hidden]{display:none!important}
  .dfp{min-height:100vh;margin:0;display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box;background:radial-gradient(circle at 20% 10%,rgba(0,163,255,.25),transparent 40%),linear-gradient(160deg,#071A33,#0A2446 60%,#0B3A7A);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#fff}
  .dfp-card{width:min(440px,100%);text-align:center;padding:36px 28px;border-radius:24px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);box-shadow:0 30px 80px rgba(0,0,0,.35);box-sizing:border-box}
  .dfp-logo{width:56px;height:56px;margin:0 auto 22px;border-radius:16px;background:linear-gradient(135deg,#00A3FF,#0B5CFF);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:18px;letter-spacing:-.02em;box-shadow:0 12px 30px rgba(0,136,238,.45)}
  .dfp-loader{position:relative;width:96px;height:96px;margin:0 auto 22px}
  .dfp-loader::before{content:"";position:absolute;inset:0;border-radius:50%;background:conic-gradient(#00A3FF,#0B5CFF,transparent 70%);-webkit-mask:radial-gradient(farthest-side,transparent calc(100% - 7px),#000 calc(100% - 6px));mask:radial-gradient(farthest-side,transparent calc(100% - 7px),#000 calc(100% - 6px));animation:dfp-spin 1.1s linear infinite}
  .dfp-loader::after{content:"";position:absolute;inset:18px;border-radius:50%;background:rgba(0,163,255,.18);animation:dfp-pulse 1.6s ease-in-out infinite}
  .dfp-check{width:96px;height:96px;margin:0 auto 22px;border-radius:50%;background:linear-gradient(135deg,#00A3FF,#0B5CFF);display:flex;align-items:center;justify-content:center;font-size:44px;font-weight:800;animation:dfp-pop .5s cubic-bezier(.2,1.4,.4,1)}
  .dfp-warn{width:96px;height:96px;margin:0 auto 22px;border-radius:50%;border:2px solid #7DD3FC;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:800;color:#7DD3FC}
  .dfp h1{font-size:24px;letter-spacing:-.02em;margin:0 0 10px}
  .dfp p{color:#CBD5E1;line-height:1.6;font-size:15px;margin:0 0 14px}
  .dfp-dots{display:flex;justify-content:center;gap:8px;margin:6px 0 18px}
  .dfp-dots span{width:9px;height:9px;border-radius:50%;background:#7DD3FC;animation:dfp-bounce 1.2s infinite ease-in-out}
  .dfp-dots span:nth-child(2){animation-delay:.15s}
  .dfp-dots span:nth-child(3){animation-delay:.3s}
  .dfp-ref{font-size:12px;color:#94A3B8;font-family:ui-monospace,monospace;word-break:break-all;margin-top:10px}
  .dfp-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:12px}
  .dfp-btn{display:inline-flex;align-items:center;justify-content:center;height:46px;padding:0 22px;border-radius:12px;background:linear-gradient(135deg,#00A3FF,#0B5CFF);color:#fff;font-weight:700;font-size:15px;text-decoration:none;border:0;cursor:pointer;box-shadow:0 12px 26px rgba(0,136,238,.35)}
  .dfp-btn.ghost{background:transparent;box-shadow:none;border:1px solid rgba(255,255,255,.3)}
  @keyframes dfp-spin{to{transform:rotate(360deg)}}
  @keyframes dfp-pulse{0%,100%{transform:scale(.85);opacity:.5}50%{transform:scale(1);opacity:1}}
  @keyframes dfp-pop{from{transform:scale(.4);opacity:0}to{transform:scale(1);opacity:1}}
  @keyframes dfp-bounce{0%,80%,100%{transform:translateY(0);opacity:.4}40%{transform:translateY(-8px);opacity:1}}
  @media (prefers-reduced-motion: reduce){.dfp *{animation:none!important}}
</style>
</head>
<body class="dfp">
<main class="dfp-card" aria-live="polite">
  <div class="dfp-logo" aria-hidden="true">DF</div>

  <div id="loader" class="dfp-loader" aria-hidden="true"></div>
  <div id="ok" class="dfp-check" hidden>✓</div>
  <div id="warn" class="dfp-warn" hidden>!</div>

  <h1 id="title">Please wait</h1>
  <p id="msg">We are confirming your payment with Paystack. This usually takes a few seconds. Please keep this page open.</p>
  <div id="dots" class="dfp-dots" aria-hidden="true"><span></span><span></span><span></span></div>

  <div id="actions" class="dfp-actions" hidden></div>
  <p class="dfp-ref">Reference <?= e($reference) ?></p>
</main>

<script>
(function () {
  var statusUrl = <?= json_encode($statusUrl, JSON_UNESCAPED_SLASHES) ?>;
  var upgradeUrl = <?= json_encode($upgradeUrl, JSON_UNESCAPED_SLASHES) ?>;
  var maxAttempts = 24;   // about 1 minute at 2.5 seconds per check
  var delay = 2500;
  var attempts = 0;

  var $ = function (id) { return document.getElementById(id); };
  var el = { loader: $('loader'), ok: $('ok'), warn: $('warn'), title: $('title'), msg: $('msg'), dots: $('dots'), actions: $('actions') };

  function setView(opts) {
    el.loader.hidden = !opts.loading;
    el.dots.hidden = !opts.loading;
    el.ok.hidden = !opts.success;
    el.warn.hidden = !opts.warning;
    el.title.textContent = opts.title;
    el.msg.textContent = opts.message;
    el.actions.innerHTML = '';
    el.actions.hidden = !(opts.buttons && opts.buttons.length);
    (opts.buttons || []).forEach(function (b) {
      var node = document.createElement(b.href ? 'a' : 'button');
      node.className = 'dfp-btn' + (b.ghost ? ' ghost' : '');
      node.textContent = b.label;
      if (b.href) { node.href = b.href; } else { node.type = 'button'; node.addEventListener('click', b.onClick); }
      el.actions.appendChild(node);
    });
  }

  function succeed() {
    setView({ success: true, title: 'Payment confirmed', message: 'Your DogoFlow Pro plan is now active. Taking you back to your plans…' });
    setTimeout(function () { window.location.href = upgradeUrl; }, 1800);
  }

  function failed(message) {
    setView({ warning: true, title: 'Payment not completed', message: message || 'Pro was not activated.',
      buttons: [{ label: 'Back to plans', href: upgradeUrl }] });
  }

  function timedOut() {
    setView({ warning: true, title: 'Still confirming', message: 'Paystack has not confirmed this payment yet. Last check: ' + el.msg.textContent + ' If you were charged, keep this reference and contact support.',
      buttons: [{ label: 'Check again', onClick: restart }, { label: 'Back to plans', href: upgradeUrl, ghost: true }] });
  }

  function restart() {
    attempts = 0;
    setView({ loading: true, title: 'Please wait', message: 'Checking your payment with Paystack again…' });
    check();
  }

  function check() {
    attempts++;
    fetch(statusUrl, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (res) {
        return res.json().catch(function () { return { state: 'error', message: 'Unexpected response from the server.' }; })
          .then(function (body) { return { status: res.status, body: body }; });
      })
      .then(function (r) {
        var b = r.body || {};
        if (r.status === 400 || r.status === 401 || r.status === 403) { failed(b.message); return; }
        if (b.state === 'success') { succeed(); return; }
        if (b.state === 'failed') { failed(b.message); return; }
        if (b.message) { el.msg.textContent = b.message; }
        if (attempts >= maxAttempts) { timedOut(); return; }
        setTimeout(check, delay);
      })
      .catch(function () {
        if (attempts >= maxAttempts) { timedOut(); return; }
        setTimeout(check, delay);
      });
  }

  check();
})();
</script>
</body>
</html>
