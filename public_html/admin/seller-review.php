<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';
require_once dirname(__DIR__, 2) . '/core/affiliates/AffiliateRewards.php';
Auth::requireRole('admin');

$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('/admin/seller-approvals.php');
}

$stmt = $pdo->prepare("
    SELECT
        sp.*,
        u.name,
        u.email,
        u.phone,
        u.status AS user_status,
        u.email_verified_at
    FROM seller_profiles sp
    INNER JOIN users u ON u.id = sp.user_id
    WHERE sp.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seller) {
    http_response_code(404);
    exit('Seller application not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);

    $action = $_POST['action'] ?? '';
    $reason = trim((string)($_POST['rejection_reason'] ?? ''));

    if (!in_array($action, ['approve', 'reject', 'suspend'], true)) {
        flash('error', 'Invalid review action.');
        redirect('/admin/seller-review.php?id=' . $id);
    }

    if ($action === 'reject' && $reason === '') {
        flash('error', 'Please provide a rejection reason.');
        redirect('/admin/seller-review.php?id=' . $id);
    }

    $pdo->beginTransaction();

    try {
        $adminId = Auth::userId();
        $approvalStatus = match ($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            default => 'suspended',
        };

        // Approval and email verification are separate states. An approved
        // but unverified seller must not gain login access yet.
        $profileStatus = match ($action) {
            'approve' => 'active',
            'reject' => 'pending',
            default => 'suspended',
        };

        $update = $pdo->prepare("
            UPDATE seller_profiles
            SET
                approval_status = ?,
                status = ?,
                rejection_reason = ?,
                reviewed_at = NOW(),
                reviewed_by = ?
            WHERE id = ?
        ");
        $update->execute([$approvalStatus, $profileStatus, $action === 'reject' ? $reason : null, $adminId, $id]);

        // Use the database's current verification value to avoid activating an
        // unverified account based on stale page/session state.
        $user = $pdo->prepare("
            UPDATE users
            SET status = CASE
                WHEN ? = 'approve' AND email_verified_at IS NOT NULL THEN 'active'
                WHEN ? = 'suspend' THEN 'suspended'
                ELSE 'pending'
            END, updated_at = NOW()
            WHERE id = ?
        ");
        $user->execute([$action, $action, (int)$seller['user_id']]);

        // Rejected or suspended sellers lose unpaid affiliate rewards.
        if (in_array($action, ['reject', 'suspend'], true)) {
            AffiliateRewards::reverseForSeller($pdo, (int)$seller['user_id']);
        }

        // Pay the configured GH₵10 onboarding reward once, only after approval.
        if ($action === 'approve') {
            $referral = $pdo->prepare("SELECT id, affiliate_id FROM affiliate_referrals WHERE referred_user_id = ? AND status <> 'rejected' ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $referral->execute([(int)$seller['user_id']]);
            $attribution = $referral->fetch(PDO::FETCH_ASSOC);
            if ($attribution) {
                $referralId = (int) $attribution['id'];
                $mark = $pdo->prepare("UPDATE affiliate_referrals SET status='qualified', qualified_at=COALESCE(qualified_at,NOW()) WHERE id=?");
                $mark->execute([$referralId]);

                // The reward is held as pending until the seller's first Pro payment succeeds.
                AffiliateRewards::holdForSeller($pdo, $referralId, (int) $attribution['affiliate_id']);
            }
        }

        $pdo->commit();

        flash('success', 'Seller application updated successfully.');
        redirect('/admin/seller-approvals.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Seller review could not be saved.');
        redirect('/admin/seller-review.php?id=' . $id);
    }
}

$active = 'sellers';
$pageTitle = 'Review Seller';
$pageDescription = 'Approve, reject or suspend a seller application';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Review Seller · DogoFlow Admin</title><link rel="icon" href="<?= url('/assets/images/favicon-32.png') ?>"><link rel="stylesheet" href="<?= url('/assets/css/admin.css') ?>"><link rel="stylesheet" href="<?= url('/assets/css/admin-ui-consistency.css') ?>"><style>.rv-card h2{font-size:1.05rem;font-weight:800;color:#102b4a;margin:0 0 14px}.rv-card{padding:22px}.rv-dl>div{display:grid;grid-template-columns:150px 1fr;gap:12px;padding:10px 0;border-bottom:1px solid #eef3f8}.rv-dl dt{color:#64748b;font-size:.82rem;font-weight:700}.rv-dl dd{margin:0;color:#102b4a;font-size:.9rem;word-break:break-word}@media(max-width:640px){.rv-dl>div{grid-template-columns:1fr}}</style><?php require __DIR__ . '/../partials/tailwind.php'; ?></head><body class="admin-shell"><?php require __DIR__ . '/../partials/admin_sidebar.php'; ?><section class="admin-main"><?php require __DIR__ . '/../partials/admin_topbar.php'; ?><main class="admin-content">
<div>
    <div class="page-heading">
        <div>
            <p style="font-size:.7rem;font-weight:800;letter-spacing:.16em;color:#0088ee;text-transform:uppercase;margin:0 0 6px">SELLER APPLICATION</p>
            <h1><?= e($seller['business_name'] ?: $seller['name']) ?></h1>
            <p>Review the seller account and store information before approving public marketplace access.</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;margin-bottom:18px">
        <section class="panel-df rv-card">
            <h2>Seller Details</h2>
            <dl class="rv-dl" style="margin:0">
                <div><dt>Seller ID</dt><dd><?= e($seller['seller_code'] ?: 'Not assigned') ?></dd></div>
                <div><dt>Name</dt><dd><?= e($seller['name']) ?></dd></div>
                <div><dt>Email</dt><dd><?= e($seller['email']) ?></dd></div>
                <div><dt>Phone</dt><dd><?= e($seller['phone'] ?: $seller['business_phone'] ?: 'Not provided') ?></dd></div>
                <div><dt>Account Status</dt><dd><?= e(ucfirst($seller['user_status'])) ?></dd></div>
                <div><dt>Email Verification</dt><dd><?= !empty($seller['email_verified_at']) ? 'Verified' : 'Not verified' ?></dd></div>
                <div><dt>Approval Status</dt><dd><?= e(ucfirst($seller['approval_status'])) ?></dd></div>
            </dl>
        </section>

        <section class="panel-df rv-card">
            <h2>Store Details</h2>
            <dl class="rv-dl" style="margin:0">
                <div><dt>Business Name</dt><dd><?= e($seller['business_name'] ?: 'Not provided') ?></dd></div>
                <div><dt>Store Slug</dt><dd><?= e($seller['store_slug'] ?: 'Not provided') ?></dd></div>
                <div><dt>Description</dt><dd><?= nl2br(e($seller['store_description'] ?? 'Not provided')) ?></dd></div>
                <div><dt>Location</dt><dd><?= e($seller['store_location'] ?? 'Not provided') ?></dd></div>
                <div><dt>Address</dt><dd><?= e($seller['business_address'] ?? 'Not provided') ?></dd></div>
                <div><dt>Phone</dt><dd><?= e($seller['business_phone'] ?? 'Not provided') ?></dd></div>
            </dl>
        </section>
    </div>

    <section class="panel-df rv-card">
        <h2>Decision</h2>
        <?php if (!empty($seller['rejection_reason'])): ?>
            <div style="margin-bottom:14px;padding:12px 14px;border-radius:10px;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa">Previous rejection: <?= e($seller['rejection_reason']) ?></div>
        <?php endif; ?>

        <form method="post" class="admin-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label for="rejection_reason">Rejection reason <span>(required when rejecting)</span></label>
            <textarea id="rejection_reason" name="rejection_reason" rows="4" placeholder="Explain what the seller needs to correct..."></textarea>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px">
                <button class="btn-df primary" type="submit" name="action" value="approve">Approve Seller & Store</button>
                <button class="btn-df danger" type="submit" name="action" value="reject">Reject Application</button>
                <button class="btn-df secondary" type="submit" name="action" value="suspend">Suspend Seller</button>
                <a class="btn-df secondary" href="<?= e(url('/admin/seller-approvals.php')) ?>">Cancel</a>
            </div>
        </form>
    </section>
</div>
</main></section></body></html>
