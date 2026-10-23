-- DogoFlow hotfix: remove verification and password-reset links already stored as in-app notifications.
-- Run once on the live database. Safe to re-run.
DELETE FROM notifications
WHERE channel = 'in_app'
  AND (message LIKE '%/auth/verify.php%' OR message LIKE '%/auth/reset-password.php%');
