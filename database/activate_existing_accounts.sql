-- DogoFlow 3.0 immediate-login migration
-- Database: dogo
-- This activates existing pending accounts because email verification is not
-- being sent yet. Run only if you want already-created pending accounts
-- to be able to sign in immediately.

USE dogo;

UPDATE users
SET status = 'active',
    email_verified_at = COALESCE(email_verified_at, NOW()),
    updated_at = NOW()
WHERE status = 'pending';
