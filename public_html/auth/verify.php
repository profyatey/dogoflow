<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../core/affiliates/AffiliateRewards.php';

$token = trim((string) ($_GET['token'] ?? ''));
$message = 'Invalid or expired verification link.';

if ($token !== '') {
    $hash = hash('sha256', $token);
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id, user_id
         FROM email_verifications
         WHERE token_hash = ? AND verified_at IS NULL AND expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$hash]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($verification) {
        $pdo->beginTransaction();

        try {
            // Consume the verification token exactly once, and only while it is unexpired.
            $consume = $pdo->prepare(
                'UPDATE email_verifications
                 SET verified_at = NOW()
                 WHERE id = ? AND verified_at IS NULL AND expires_at > NOW()'
            );
            $consume->execute([(int) $verification['id']]);
            if ($consume->rowCount() !== 1) {
                throw new RuntimeException('Verification token was already used or expired.');
            }

            $userStmt = $pdo->prepare(
                'SELECT id, role, status
                 FROM users
                 WHERE id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $userStmt->execute([(int) $verification['user_id']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                throw new RuntimeException('Verification token refers to a missing user.');
            }

            $userId = (int) $user['id'];
            $role = strtolower((string) $user['role']);
            $currentUserStatus = strtolower((string) $user['status']);
            $isSeller = in_array($role, ['seller', 'vendor'], true);
            $sellerApproved = false;
            $sellerBlocked = false;
            $newUserStatus = $currentUserStatus;

            if ($isSeller) {
                $profileStmt = $pdo->prepare(
                    'SELECT id, status, approval_status
                     FROM seller_profiles
                     WHERE user_id = ?
                     LIMIT 1
                     FOR UPDATE'
                );
                $profileStmt->execute([$userId]);
                $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

                $profileStatus = strtolower((string) ($profile['status'] ?? ''));
                $approvalStatus = strtolower((string) ($profile['approval_status'] ?? ''));
                $userCanBeActivated = in_array($currentUserStatus, ['pending', 'active'], true);

                // New, verified sellers are automatically approved. Do not use email verification
                // to reinstate sellers already rejected, suspended, disabled, or missing a profile.
                if (
                    $profile
                    && $userCanBeActivated
                    && $profileStatus !== 'suspended'
                    && !in_array($approvalStatus, ['rejected', 'suspended'], true)
                    && in_array($approvalStatus, ['pending', 'approved'], true)
                ) {
                    $approve = $pdo->prepare(
                        "UPDATE seller_profiles
                         SET status = 'active', approval_status = 'approved', rejection_reason = NULL
                         WHERE id = ? AND status <> 'suspended'
                           AND approval_status IN ('pending', 'approved')"
                    );
                    $approve->execute([(int) $profile['id']]);

                    $sellerApproved = true;
                    $newUserStatus = 'active';
                } else {
                    $sellerBlocked = true;
                    if ($currentUserStatus === 'suspended' || $profileStatus === 'suspended' || $approvalStatus === 'suspended') {
                        $newUserStatus = 'suspended';
                    } elseif (!$userCanBeActivated || !$profile || $approvalStatus === 'rejected' || !in_array($approvalStatus, ['pending', 'approved'], true)) {
                        $newUserStatus = 'pending';
                    }
                }
            } elseif ($currentUserStatus === 'pending') {
                // Email verification activates ordinary customer/affiliate accounts,
                // but must not reactivate a suspended or otherwise restricted account.
                $newUserStatus = 'active';
            }

            $updateUser = $pdo->prepare(
                'UPDATE users
                 SET status = ?, email_verified_at = COALESCE(email_verified_at, NOW()), updated_at = NOW()
                 WHERE id = ?'
            );
            $updateUser->execute([$newUserStatus, $userId]);

            // Preserve the referral's verification milestone for compatibility with existing records.
            $pdo->prepare(
                "UPDATE affiliate_referrals
                 SET status = 'verified'
                 WHERE referred_user_id = ? AND status = 'registered'"
            )->execute([$userId]);

            // In the no-admin-approval flow, verified + auto-approved seller status is the
            // qualification event that previously occurred from the Admin approval screen.
            // Keep the existing GH₵10 reward and do not create a second reward for a referral.
            if ($isSeller && $sellerApproved) {
                $referralStmt = $pdo->prepare(
                    "SELECT id, affiliate_id
                     FROM affiliate_referrals
                     WHERE referred_user_id = ? AND status <> 'rejected'
                     ORDER BY id DESC
                     LIMIT 1
                     FOR UPDATE"
                );
                $referralStmt->execute([$userId]);
                $referral = $referralStmt->fetch(PDO::FETCH_ASSOC);

                if ($referral) {
                    $referralId = (int) $referral['id'];
                    $pdo->prepare(
                        "UPDATE affiliate_referrals
                         SET status = 'qualified', qualified_at = COALESCE(qualified_at, NOW())
                         WHERE id = ?"
                    )->execute([$referralId]);

                    // Held as pending until the seller's first Pro payment succeeds.
                    AffiliateRewards::holdForSeller($pdo, $referralId, (int) $referral['affiliate_id']);
                }
            }

            $pdo->commit();

            if ($isSeller && $sellerApproved) {
                $message = 'Email verified successfully. Your seller account is now approved, and you can log in.';
            } elseif ($isSeller && $sellerBlocked) {
                $message = 'Your email has been verified, but this seller account is not active. Please contact DogoFlow support.';
            } else {
                $message = 'Email verified successfully. You can now log in.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('DogoFlow email verification failed: ' . $e->getMessage());
            $message = 'Verification could not be completed. Please request a new verification link or contact support.';
        }
    }
}
?>
<?php
$tone = str_contains($message, 'verified successfully') && !str_contains($message, 'not active') ? 'success'
    : (str_contains($message, 'not active') ? 'warn' : 'error');
$icons = ['success' => '✓', 'warn' => '!', 'error' => '✕'];
$headings = ['success' => 'You are verified', 'warn' => 'Account not active', 'error' => 'Verification failed'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Email verification · DogoFlow</title>
    <link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>">
    <?php require __DIR__ . '/../partials/tailwind.php'; ?>
    <style>
        .verify-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px 16px;background:radial-gradient(circle at 10% 10%,rgba(0,163,255,.13),transparent 30%),linear-gradient(180deg,#f7fbff 0%,#eef5fb 100%);font-family:inherit}
        .verify-card{width:min(460px,100%);padding:34px;border-radius:26px;border:1px solid #dce7f1;background:#fff;box-shadow:0 24px 70px rgba(7,26,51,.12);text-align:center}
        .verify-card img{height:48px;width:auto;margin-bottom:22px}
        .verify-icon{width:64px;height:64px;margin:0 auto 18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:700}
        .verify-icon.success{background:#e7f8ef;color:#12834a}
        .verify-icon.warn{background:#fff6e0;color:#a86400}
        .verify-icon.error{background:#fdecec;color:#b42318}
        .verify-card h1{font-size:1.7rem;letter-spacing:-.04em;color:#071a33;margin:0 0 10px}
        .verify-card p{color:#47627b;line-height:1.6;font-size:.95rem;margin:0 0 24px}
        .verify-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
        .verify-btn{display:inline-flex;align-items:center;justify-content:center;height:46px;padding:0 22px;border-radius:12px;font-weight:600;font-size:.88rem;text-decoration:none;border:1px solid #d9e3ed;color:#071a33;background:#fff}
        .verify-btn.primary{background:linear-gradient(135deg,#00a3ff,#0088ee);color:#fff;border-color:transparent;box-shadow:0 12px 24px rgba(0,136,238,.2)}
        .verify-foot{margin-top:20px;font-size:.75rem;color:#94A3B8}
        @media(max-width:520px){.verify-card{padding:25px 19px;border-radius:21px}}
    </style>
</head>
<body>
<main class="verify-page">
    <section class="verify-card" role="status" aria-live="polite">
        <a href="<?= e(url('/')) ?>"><img src="<?= e(url('/assets/images/df-logo.png')) ?>" alt="DogoFlow"></a>
        <div class="verify-icon <?= e($tone) ?>" aria-hidden="true"><?= e($icons[$tone]) ?></div>
        <h1><?= e($headings[$tone]) ?></h1>
        <p><?= e($message) ?></p>
        <div class="verify-actions">
            <?php if ($tone === 'success'): ?>
                <a class="verify-btn primary" href="<?= e(url('/auth/login.php')) ?>">Sign in to DogoFlow</a>
            <?php elseif ($tone === 'warn'): ?>
                <a class="verify-btn primary" href="<?= e(url('/auth/login.php')) ?>">Try signing in</a>
                <a class="verify-btn" href="<?= e(url('/')) ?>">Return home</a>
            <?php else: ?>
                <a class="verify-btn primary" href="<?= e(url('/auth/login.php')) ?>">Go to sign in</a>
                <a class="verify-btn" href="<?= e(url('/auth/register.php')) ?>">Create account</a>
            <?php endif; ?>
        </div>
        <p class="verify-foot">DogoFlow · Shop • Sell • Earn</p>
    </section>
</main>
</body>
</html>
