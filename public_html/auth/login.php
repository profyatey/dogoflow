<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

if (Auth::check() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $loggedInRole = Auth::role();
} else {
    $loggedInRole = null;
}

$error = flash('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = $_POST['password'] ?? '';

    if ($email === '' || login_is_rate_limited($email)) {
        $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    } else {
        $stmt = db()->prepare(
            'SELECT u.id, u.password_hash, u.role, u.status, u.email_verified_at,
                    sp.status AS seller_profile_status, sp.approval_status
             FROM users u
             LEFT JOIN seller_profiles sp ON sp.user_id = u.id
             WHERE u.email = ?
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $isSellerAccount = $user && in_array(strtolower((string) $user['role']), ['seller', 'vendor'], true);
        $canSignIn = false;

        if ($user && password_verify($password, $user['password_hash']) && !empty($user['email_verified_at'])) {
            if ($isSellerAccount) {
                // Admin approval is the gate. Email verification auto-approves, so an
                // approved, verified seller whose users.status lagged behind is healed here.
                $canSignIn = $user['seller_profile_status'] === 'active'
                    && $user['approval_status'] === 'approved'
                    && in_array($user['status'], ['active', 'pending'], true);

                if ($canSignIn && $user['status'] !== 'active') {
                    db()->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ? AND status = 'pending'")
                        ->execute([(int) $user['id']]);
                }
            } else {
                $canSignIn = $user['status'] === 'active';
            }
        }

        if (!$canSignIn) {
            login_record_failure($email);
            $error = 'Invalid email or password.';
        } else {
            login_clear_failures($email);
            Auth::login((int) $user['id'], (string) $user['role']);
            Auth::redirectToDashboard();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0A1628"><title>Sign in · DogoFlow</title>
<link rel="icon" href="<?=url('/assets/images/favicon-32.png')?>"><?php require __DIR__ . '/../partials/tailwind.php'; ?>
<style>
.auth-page{background:radial-gradient(circle at 10% 10%,rgba(0,163,255,.13),transparent 30%),linear-gradient(180deg,#f7fbff 0%,#eef5fb 100%);padding:28px 16px}.auth-box-df{width:min(460px,100%);padding:34px;border-radius:26px;border:1px solid #dce7f1;box-shadow:0 24px 70px rgba(7,26,51,.12)}.auth-brand-df{display:flex;justify-content:center;margin-bottom:22px}.auth-brand-df img{height:48px;width:auto}.auth-box-df h1{font-size:2rem;letter-spacing:-.055em}.auth-box-df .sub{font-size:.9rem;line-height:1.6;margin-bottom:25px}.form-field-df{gap:8px}.form-field-df input{height:50px;border:1px solid #d9e3ed;border-radius:12px;padding:0 14px;background:#fbfdff;transition:.2s}.form-field-df input:focus{border-color:#6bc8fa;box-shadow:0 0 0 4px rgba(0,163,255,.09);outline:0;background:#fff}.auth-box-df .btn-df.primary{height:50px;border-radius:12px;font-size:.86rem;background:linear-gradient(135deg,#00a3ff,#0088ee);box-shadow:0 12px 24px rgba(0,136,238,.2)}.auth-box-df .btn-df.primary:hover{transform:translateY(-1px)}.auth-links-df{margin-top:19px}.auth-links-df a:hover{text-decoration:underline}.auth-notice-df{border-radius:13px;background:#eff8ff;border:1px solid #cdeaff;padding:12px 14px;font-size:.76rem;color:#47627b}.alert.danger{border-radius:12px!important;margin-bottom:15px!important}.auth-trust{display:flex;justify-content:center;gap:15px;flex-wrap:wrap;color:#8a9aad;font-size:.68rem;margin-top:20px}.auth-trust span{display:inline-flex;align-items:center;gap:5px}@media(max-width:520px){.auth-box-df{padding:25px 19px;border-radius:21px}.auth-box-df h1{font-size:1.7rem}}</style>
</head>
<body class="auth-page">
<main class="auth-box-df">
  <a class="auth-brand-df" href="<?=url('/')?>"><img src="<?=url('/assets/images/df-logo.png')?>" alt="DogoFlow"></a>
  <h1>Welcome back</h1>
  <p class="sub">Sign in to continue to your DogoFlow account.</p>
  <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-field-df"><label for="email">Email address</label><input id="email" type="email" name="email" autocomplete="email" required placeholder="you@example.com"></div>
    <div class="form-field-df"><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Enter your password"></div>
    <button class="btn-df primary" type="submit">Sign in securely →</button>
  </form>
  <div class="auth-links-df"><a href="<?=url('/auth/forgot-password.php')?>">Forgot password?</a><a href="<?=url('/auth/register.php')?>">Create account</a></div>
  <div class="auth-trust"><span>✓ Secure sign in</span><span>✓ Ghana marketplace</span><span>✓ Protected account</span></div><p style="text-align:center;color:#94A3B8;font-size:.7rem;margin:16px 0 0">DogoFlow · Shop • Sell • Earn</p>
</main>
</body>
</html>