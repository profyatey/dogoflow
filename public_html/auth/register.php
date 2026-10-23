<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once DOGOFLOW_ROOT . '/core/config/SiteSettings.php';
$closedAccountTypes = SiteSettings::closedAccountTypes();
require_once DOGOFLOW_ROOT . '/core/catalog/SellerCodes.php';

$error = flash('error');
$success = flash('success');
$loggedInRole = Auth::check() ? Auth::canonicalRole((string) Auth::role()) : null;

$name = '';
$email = '';
$phone = '';
$accountType = in_array((string)($_GET['account_type'] ?? 'customer'), ['customer','seller','affiliate'], true) ? (string)($_GET['account_type'] ?? 'customer') : 'customer';
$businessName = '';
$affiliatePrefix = '';
$referralCode = strtoupper(trim((string)($_GET['ref'] ?? $_POST['referral_code'] ?? $_SESSION['_affiliate_referral_code'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
    $requestedType = (string) ($_POST['account_type'] ?? 'customer');
    $accountType = in_array($requestedType, ['customer', 'seller', 'affiliate'], true) ? $requestedType : 'customer';
    $businessName = trim((string) ($_POST['business_name'] ?? ''));
    $affiliatePrefix = strtoupper(trim((string) ($_POST['affiliate_prefix'] ?? '')));
    // The visible referral field wins over the URL parameter so a typed code can be corrected.
    $referralCode = strtoupper(trim((string) ($_POST['referral_code'] ?? $referralCode)));

    if (in_array($accountType, $closedAccountTypes, true)) {
        $error = SiteSettings::closedMessage($accountType);
    } elseif ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid name and email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Your password must contain at least 8 characters.';
    } elseif ($password !== $passwordConfirmation) {
        $error = 'The password confirmation does not match.';
    } elseif ($accountType === 'seller' && ($businessName === '' || mb_strlen($businessName) > 180)) {
        $error = 'Enter a valid business or store name for your seller account.';
    } elseif ($accountType === 'seller' && $referralCode !== '' && !preg_match('/^[A-Z]{3}[0-9]{3}$/', $referralCode)) {
        $error = 'Affiliate referral codes look like SIM000. Check the code or leave it blank.';
    } elseif ($accountType === 'affiliate' && $affiliatePrefix !== '' && !preg_match('/^[A-Z]{3}$/', $affiliatePrefix)) {
        $error = 'Your affiliate code prefix must contain exactly 3 letters.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, phone, password_hash, role, status)
                     VALUES (?, ?, ?, ?, ?, "pending")'
                );
                $stmt->execute([
                    $name,
                    $email,
                    $phone !== '' ? $phone : null,
                    password_hash($password, PASSWORD_DEFAULT),
                    $accountType,
                ]);

                $userId = (int) $pdo->lastInsertId();

                if ($accountType === 'affiliate') {
                    // Use the submitted 3-letter prefix, or derive it from the affiliate's name.
                    if ($affiliatePrefix === '') {
                        $letters = strtoupper((string) preg_replace('/[^A-Z]/', '', strtoupper($name)));
                        $affiliatePrefix = substr($letters . 'DFG', 0, 3);
                    }

                    // Generate a unique 3-letter + 3-digit referral code.
                    $codeExists = $pdo->prepare('SELECT 1 FROM affiliate_profiles WHERE referral_code = ? LIMIT 1');
                    $referralCode = null;
                    for ($attempt = 0; $attempt < 100; $attempt++) {
                        $candidate = $affiliatePrefix . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
                        $codeExists->execute([$candidate]);
                        if (!$codeExists->fetchColumn()) {
                            $referralCode = $candidate;
                            break;
                        }
                    }
                    if ($referralCode === null) {
                        throw new RuntimeException('Could not generate a unique affiliate referral code.');
                    }

                    $stmt = $pdo->prepare("INSERT INTO affiliate_profiles (user_id, referral_code, status) VALUES (?, ?, 'active')");
                    $stmt->execute([$userId, $referralCode]);
                }

                if ($accountType === 'seller') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO seller_profiles (user_id, business_name, business_phone, status, approval_status)
                         VALUES (?, ?, ?, "pending", "pending")'
                    );
                    $stmt->execute([
                        $userId,
                        $businessName,
                        $phone !== '' ? $phone : null,
                    ]);
                    SellerCodes::ensure($pdo, (int) $pdo->lastInsertId());
                }

                // Preserve affiliate attribution for seller referrals. The reward is created after email verification auto-approves the seller.
                if ($accountType === 'seller' && $referralCode !== '') {
                    $refStmt = $pdo->prepare(
                        "SELECT id FROM affiliate_profiles WHERE referral_code = ? AND status = 'active' LIMIT 1"
                    );
                    $refStmt->execute([$referralCode]);
                    $affiliateId = $refStmt->fetchColumn();
                    if ($affiliateId) {
                        $pdo->prepare(
                            "INSERT IGNORE INTO affiliate_referrals (affiliate_id, referred_user_id, referral_code, status) VALUES (?, ?, ?, 'registered')"
                        )->execute([(int)$affiliateId, $userId, $referralCode]);
                    }
                }

                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);

                $stmt = $pdo->prepare(
                    'INSERT INTO email_verifications (user_id, token_hash, expires_at)
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
                );
                $stmt->execute([$userId, $tokenHash]);

                $pdo->commit();
                unset($_SESSION['_affiliate_referral_code']);

                $verificationUrl = url('/auth/verify.php?token=' . urlencode($rawToken));
                try {
                    NotificationService::queue(
                        $pdo,
                        $userId,
                        'Verify your DogoFlow account',
                        "Hello {$name},\n\nPlease verify your DogoFlow account using this link:\n{$verificationUrl}\n\nThis verification link expires in 24 hours.\n\nDogoFlow"
                    , true, false);
                } catch (Throwable $mailError) {
                    error_log('DogoFlow verification email failed: ' . $mailError->getMessage());
                }

                $_SESSION['_registered_account_type'] = $accountType;

                redirect('/auth/registration-success.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($e->getMessage());
                $error = 'Registration could not be completed. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A1628">
    <title>Create account · DogoFlow</title>
    <link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>">
    <?php require __DIR__ . '/../partials/tailwind.php'; ?>
<style>
.auth-page{background:radial-gradient(circle at 90% 5%,rgba(0,163,255,.13),transparent 28%),linear-gradient(180deg,#f7fbff 0%,#eef5fb 100%);padding:28px 16px}.register-box-df{width:min(650px,100%);padding:34px;border-radius:26px;border:1px solid #dce7f1;box-shadow:0 24px 70px rgba(7,26,51,.12)}.auth-brand-df{display:flex;justify-content:center;margin-bottom:20px}.auth-brand-df img{height:48px}.register-box-df h1{font-size:2rem;letter-spacing:-.055em}.register-box-df .sub{font-size:.9rem;line-height:1.6;margin-bottom:23px}.account-type-df{border:0;padding:0;margin:0 0 21px;display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.account-type-df legend{grid-column:1/-1;font-size:.74rem;font-weight:900;color:#334155;margin-bottom:1px}.account-option-df{min-height:125px!important;border:1px solid #dce5ef!important;border-radius:15px!important;background:#fff!important;padding:14px!important;display:flex!important;align-items:flex-start!important;gap:9px!important;transition:.18s!important}.account-option-df:hover{border-color:#8ed5fb!important;transform:translateY(-1px)}.account-option-df.selected{border-color:#49baf5!important;background:#eff9ff!important;box-shadow:0 8px 22px rgba(0,163,255,.08)}.account-option-df input{margin-top:3px;accent-color:#0088ee}.account-option-icon{font-size:1.2rem}.account-option-df strong{display:block;font-size:.78rem;color:#10233e}.account-option-df small{display:block;color:#708196;font-size:.66rem;line-height:1.45;margin-top:4px}.register-box-df .form-field-df{gap:7px;margin-bottom:14px}.register-box-df .form-field-df input{height:49px;border:1px solid #d9e3ed;border-radius:12px;padding:0 13px;background:#fbfdff}.register-box-df .form-field-df input:focus{outline:0;border-color:#6bc8fa;box-shadow:0 0 0 4px rgba(0,163,255,.09);background:#fff}.seller-fields-df{border:1px solid #dcebf5!important;border-radius:15px!important;background:#f7fbff!important;padding:15px!important;margin-bottom:15px}.seller-heading-df{margin-bottom:11px}.seller-heading-df strong{display:block;color:#0b2545;font-size:.8rem}.seller-heading-df span{display:block;color:#718096;font-size:.68rem;line-height:1.5;margin-top:3px}.register-box-df .btn-df.primary{height:50px;width:100%;border-radius:12px;background:linear-gradient(135deg,#00a3ff,#0088ee);box-shadow:0 12px 24px rgba(0,136,238,.2)}.auth-footer-df{text-align:center!important;margin-top:18px!important}.auth-tagline-df{text-align:center!important;color:#94a3b8!important;font-size:.68rem!important}@media(max-width:700px){.account-type-df{grid-template-columns:1fr}.account-option-df{min-height:auto!important}.register-box-df{padding:25px 19px;border-radius:21px}.register-box-df h1{font-size:1.7rem}}</style>
</head>
<body class="auth-page">
    <main class="auth-box-df register-box-df register-choice-df">
        <a class="auth-brand-df" href="<?= url('/') ?>">
            <img src="<?= url('/assets/images/df-logo.png') ?>" alt="DogoFlow">
        </a>

        <h1>Create your account</h1>
        <p class="sub">Choose how you want to use DogoFlow.</p>

        <?php if ($loggedInRole): ?>
            <div class="auth-notice-df">
                <strong>You are currently signed in.</strong>
                <span>
                    You are signed in as <?= e(ucfirst($loggedInRole)) ?>.
                    Sign out before testing a different account.
                </span>
                <a href="<?= url('/auth/logout.php') ?>">Sign out</a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="auth-form-df" id="registerForm">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

            <fieldset class="account-type-df">
                <legend>I want to:</legend>

                <?php if ($closedAccountTypes): ?><p class="sub" style="margin:0 0 12px;font-size:.9rem">Sign-up is paused for some account types right now.</p><?php endif; ?>
                <label class="account-option-df <?= $accountType === 'customer' ? 'selected' : '' ?>">
                    <input type="radio" name="account_type" value="customer" <?= in_array('customer', $closedAccountTypes, true) ? 'disabled' : '' ?> <?= $accountType === 'customer' ? 'checked' : '' ?>>
                    <span class="account-option-icon">🛍️</span>
                    <span>
                        <strong>Shop on DogoFlow</strong>
                        <small>Browse products, place orders and manage your purchases.</small>
                    </span>
                </label>

                <label class="account-option-df <?= $accountType === 'seller' ? 'selected' : '' ?>">
                    <input type="radio" name="account_type" value="seller" <?= in_array('seller', $closedAccountTypes, true) ? 'disabled' : '' ?> <?= $accountType === 'seller' ? 'checked' : '' ?>>
                    <span class="account-option-icon">🏪</span>
                    <span>
                        <strong>Sell on DogoFlow</strong>
                        <small>Create a store, upload products and manage orders.</small>
                    </span>
                </label>

                <label class="account-option-df <?= $accountType === 'affiliate' ? 'selected' : '' ?>">
                    <input type="radio" name="account_type" value="affiliate" <?= in_array('affiliate', $closedAccountTypes, true) ? 'disabled' : '' ?> <?= $accountType === 'affiliate' ? 'checked' : '' ?>>
                    <span class="account-option-icon">🔗</span>
                    <span><strong>Become an affiliate</strong><small>Share DogoFlow and earn rewards for approved seller referrals.</small></span>
                </label>
            </fieldset>

            <div class="form-field-df">
                <label for="name">Full name</label>
                <input id="name" name="name" required maxlength="120" autocomplete="name" placeholder="Your full name" value="<?= e($name) ?>">
            </div>

            <div class="seller-fields-df" id="affiliateFields" <?= $accountType === 'affiliate' ? '' : 'hidden' ?>>
                <div class="seller-heading-df">
                    <strong>Personalize your referral code</strong>
                    <span>We suggest the first 3 letters of your name. You can change them before creating your account.</span>
                </div>
                <div class="form-field-df">
                    <label for="affiliate_prefix">Your 3-letter code</label>
                    <input id="affiliate_prefix" name="affiliate_prefix" type="text" maxlength="3" minlength="3"
                           pattern="[A-Za-z]{3}" autocapitalize="characters" autocomplete="off"
                           placeholder="e.g. SIM" value="<?= e($affiliatePrefix) ?>"
                           aria-describedby="affiliateCodeHint">
                    <small id="affiliateCodeHint">Your final code will look like <strong id="affiliateCodePreview">SIM000</strong>. The last 3 digits are generated automatically.</small>
                </div>
            </div>

            <div class="form-field-df">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" required maxlength="190" autocomplete="email" placeholder="you@example.com" value="<?= e($email) ?>">
            </div>

            <div class="form-field-df">
                <label for="phone">Phone number <span class="optional-df">Optional</span></label>
                <input id="phone" name="phone" maxlength="30" autocomplete="tel" placeholder="024 000 0000" value="<?= e($phone) ?>">
            </div>

            <div class="seller-fields-df" id="sellerFields" <?= $accountType === 'seller' ? '' : 'hidden' ?>>
                <div class="seller-heading-df">
                    <strong>Set up your seller account</strong>
                    <span>Your store can be completed after email verification.</span>
                </div>

                <div class="form-field-df">
                    <label for="business_name">Business / store name</label>
                    <input id="business_name" name="business_name" maxlength="180" placeholder="e.g. Simon Electronics" value="<?= e($businessName) ?>">
                </div>

                <div class="form-field-df">
                    <label for="referral_code">Referral code <span class="optional-df">Optional</span></label>
                    <input id="referral_code" name="referral_code" maxlength="6" autocapitalize="characters" autocomplete="off"
                           placeholder="e.g. SIM000" value="<?= e($referralCode) ?>"
                           pattern="[A-Za-z]{3}[0-9]{3}" title="Affiliate codes look like SIM000">
                    <small>Entered a referral from an affiliate? Add their code so they get credit once you are approved.</small>
                </div>
            </div>

            <div class="form-field-df">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="At least 8 characters">
            </div>

            <div class="form-field-df">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password" placeholder="Repeat your password">
            </div>

            <button class="btn-df primary" type="submit">Create account →</button>
        </form>

        <p class="auth-footer-df">
            Already registered? <a href="<?= url('/auth/login.php') ?>">Sign in</a>
        </p>

        <p class="auth-tagline-df">DogoFlow · Shop • Sell • Earn</p>
    </main>

    <script>
        const sellerFields = document.getElementById('sellerFields');
        const businessName = document.getElementById('business_name');
        const affiliateFields = document.getElementById('affiliateFields');
        const affiliatePrefix = document.getElementById('affiliate_prefix');
        const affiliateCodePreview = document.getElementById('affiliateCodePreview');
        const nameInput = document.getElementById('name');
        let affiliatePrefixTouched = Boolean(affiliatePrefix?.value);
        const options = document.querySelectorAll('input[name="account_type"]');

        function updateAccountType() {
            const selected = document.querySelector('input[name="account_type"]:checked')?.value;
            const isSeller = selected === 'seller';

            sellerFields.hidden = !isSeller;
            businessName.required = isSeller;

            const isAffiliate = selected === 'affiliate';
            affiliateFields.hidden = !isAffiliate;
            if (isAffiliate && affiliatePrefix && !affiliatePrefixTouched) {
                affiliatePrefix.value = suggestedAffiliatePrefix();
            }
            updateAffiliatePreview();

            document.querySelectorAll('.account-option-df').forEach((option) => {
                option.classList.toggle('selected', option.querySelector('input').checked);
            });
        }

        function suggestedAffiliatePrefix() {
            const letters = (nameInput?.value || '').toUpperCase().replace(/[^A-Z]/g, '');
            return (letters + 'DFG').slice(0, 3);
        }

        function updateAffiliatePreview() {
            if (!affiliateCodePreview || !affiliatePrefix) return;
            const prefix = affiliatePrefix.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3).padEnd(3, 'X');
            affiliateCodePreview.textContent = prefix + '000';
        }

        affiliatePrefix?.addEventListener('input', () => {
            affiliatePrefixTouched = true;
            affiliatePrefix.value = affiliatePrefix.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3);
            updateAffiliatePreview();
        });
        nameInput?.addEventListener('input', () => {
            if (!affiliatePrefixTouched && affiliatePrefix) {
                affiliatePrefix.value = suggestedAffiliatePrefix();
                updateAffiliatePreview();
            }
        });

        options.forEach((option) => option.addEventListener('change', updateAccountType));
        updateAccountType();
    </script>
</body>
</html>
