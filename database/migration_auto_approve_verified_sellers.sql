-- DogoFlow hotfix: verified sellers no longer need a second Admin approval.
-- Run once on each database (XAMPP and AvesHost). Safe to re-run.
-- Rejected and suspended sellers are NOT touched.

UPDATE seller_profiles sp
JOIN users u ON u.id = sp.user_id
SET sp.approval_status = 'approved',
    sp.status = 'active',
    sp.rejection_reason = NULL,
    sp.reviewed_at = COALESCE(sp.reviewed_at, NOW())
WHERE u.role IN ('seller', 'vendor')
  AND u.email_verified_at IS NOT NULL
  AND sp.approval_status = 'pending'
  AND sp.status <> 'suspended';

UPDATE users u
JOIN seller_profiles sp ON sp.user_id = u.id
SET u.status = 'active',
    u.updated_at = NOW()
WHERE u.role IN ('seller', 'vendor')
  AND u.email_verified_at IS NOT NULL
  AND u.status = 'pending'
  AND sp.approval_status = 'approved'
  AND sp.status = 'active';
