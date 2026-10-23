-- DogoFlow 3.0 production hardening / canonical runtime additions
-- Run after schema.sql and the existing phase migrations.
USE dogo;

CREATE TABLE IF NOT EXISTS affiliate_referrals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 affiliate_id BIGINT UNSIGNED NOT NULL,
 referred_user_id BIGINT UNSIGNED NOT NULL,
 referral_code VARCHAR(40) NOT NULL,
 status ENUM('registered','verified','qualified','rejected') NOT NULL DEFAULT 'registered',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 qualified_at DATETIME NULL,
 UNIQUE KEY uq_affiliate_referred_user (referred_user_id),
 INDEX idx_affiliate_referrals_affiliate_status (affiliate_id,status),
 CONSTRAINT fk_referral_affiliate FOREIGN KEY (affiliate_id) REFERENCES affiliate_profiles(id) ON DELETE CASCADE,
 CONSTRAINT fk_referral_user FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS affiliate_rewards (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 affiliate_id BIGINT UNSIGNED NOT NULL,
 referral_id BIGINT UNSIGNED NOT NULL,
 amount DECIMAL(12,2) NOT NULL DEFAULT 10.00,
 status ENUM('pending','available','paid','reversed') NOT NULL DEFAULT 'available',
 reference VARCHAR(100) NOT NULL UNIQUE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 paid_at DATETIME NULL,
 UNIQUE KEY uq_affiliate_reward_referral (referral_id),
 INDEX idx_affiliate_rewards_owner_status (affiliate_id,status),
 CONSTRAINT fk_reward_affiliate FOREIGN KEY (affiliate_id) REFERENCES affiliate_profiles(id) ON DELETE CASCADE,
 CONSTRAINT fk_reward_referral FOREIGN KEY (referral_id) REFERENCES affiliate_referrals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seller_subscription_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 reference VARCHAR(100) NOT NULL UNIQUE,
 amount DECIMAL(12,2) NOT NULL DEFAULT 30.00,
 currency CHAR(3) NOT NULL DEFAULT 'GHS',
 status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
 gateway_transaction_id VARCHAR(100) NULL,
 gateway_response LONGTEXT NULL,
 paid_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_ssp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_ssp_user_status (user_id,status), INDEX idx_ssp_user_created (user_id,created_at)
) ENGINE=InnoDB;

INSERT INTO categories (name, slug, description, status, sort_order) VALUES
 ('Fashion & Beauty','fashion-beauty','Clothing, accessories and beauty products','active',10),
 ('Electronics','electronics','Phones, computers and electronic accessories','active',20),
 ('Home & Living','home-living','Home, kitchen and household products','active',30),
 ('Food & Groceries','food-groceries','Food, drinks and grocery products','active',40),
 ('Health & Wellness','health-wellness','Health, fitness and wellness products','active',50),
 ('Services','services','Professional and local services','active',60)
ON DUPLICATE KEY UPDATE name=VALUES(name), status=VALUES(status);

ALTER TABLE withdrawals ADD COLUMN IF NOT EXISTS payout_note VARCHAR(500) NULL AFTER processed_at;
