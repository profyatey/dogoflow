-- DogoFlow 3.0
-- Admin Seller & Store Approval Migration
-- Database: dogo

USE dogo;

ALTER TABLE seller_profiles
    ADD COLUMN IF NOT EXISTS approval_status ENUM('pending','approved','rejected','suspended')
    NOT NULL DEFAULT 'pending';

ALTER TABLE seller_profiles
    ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL;

ALTER TABLE seller_profiles
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL;

ALTER TABLE seller_profiles
    ADD COLUMN IF NOT EXISTS reviewed_by BIGINT UNSIGNED NULL;

CREATE INDEX IF NOT EXISTS idx_seller_profiles_approval_status
    ON seller_profiles (approval_status);

-- Existing active sellers are treated as approved so this migration
-- does not unexpectedly hide sellers already in use.
UPDATE seller_profiles
SET approval_status = 'approved'
WHERE approval_status = 'pending'
  AND status = 'active';
