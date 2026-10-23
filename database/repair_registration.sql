-- DogoFlow 3.0 registration repair
-- IMPORTANT: Select the `dogo` database before running this script.
-- This script is designed to preserve existing data.

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer','seller','affiliate','admin') NOT NULL DEFAULT 'customer',
    status ENUM('active','pending','suspended','blocked') NOT NULL DEFAULT 'pending',
    email_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role_status (role, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seller_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    seller_code VARCHAR(20) NULL UNIQUE,
    business_name VARCHAR(180) NOT NULL,
    store_slug VARCHAR(200) NULL UNIQUE,
    store_logo VARCHAR(255) NULL,
    store_banner VARCHAR(255) NULL,
    store_description TEXT NULL,
    store_location VARCHAR(180) NULL,
    business_phone VARCHAR(30) NULL,
    business_address VARCHAR(255) NULL,
    status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    plan ENUM('free','pro') NOT NULL DEFAULT 'free',
    photo_limit INT UNSIGNED NOT NULL DEFAULT 5,
    subscription_started_at DATETIME NULL,
    subscription_expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_repair_seller_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS email_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_repair_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email_verification_expiry (expires_at)
) ENGINE=InnoDB;

-- Add missing users columns when an older DogoFlow users table exists.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NOT NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS role ENUM('customer','seller','affiliate','admin') NOT NULL DEFAULT 'customer' AFTER password_hash,
    ADD COLUMN IF NOT EXISTS status ENUM('active','pending','suspended','blocked') NOT NULL DEFAULT 'pending' AFTER role,
    ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER email_verified_at,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Make sure the verification table has the expected fields on older installations.
ALTER TABLE email_verifications
    ADD COLUMN IF NOT EXISTS token_hash CHAR(64) NOT NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS expires_at DATETIME NOT NULL AFTER token_hash,
    ADD COLUMN IF NOT EXISTS verified_at DATETIME NULL AFTER expires_at,
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER verified_at;

-- Make seller registration compatible with older seller_profiles tables.
ALTER TABLE seller_profiles
    ADD COLUMN IF NOT EXISTS seller_code VARCHAR(20) NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS business_phone VARCHAR(30) NULL AFTER store_location,
    ADD COLUMN IF NOT EXISTS status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending' AFTER business_address,
    ADD COLUMN IF NOT EXISTS plan ENUM('free','pro') NOT NULL DEFAULT 'free' AFTER status,
    ADD COLUMN IF NOT EXISTS photo_limit INT UNSIGNED NOT NULL DEFAULT 5 AFTER plan,
    ADD COLUMN IF NOT EXISTS subscription_started_at DATETIME NULL AFTER photo_limit,
    ADD COLUMN IF NOT EXISTS subscription_expires_at DATETIME NULL AFTER subscription_started_at;

-- Verify that the application is using the intended database.
SELECT DATABASE() AS active_database;

-- Registration tables should return one row each.
SELECT TABLE_NAME
FROM information_schema.tables
WHERE table_schema = 'dogo'
  AND table_name IN ('users', 'seller_profiles', 'email_verifications')
ORDER BY TABLE_NAME;
