-- DogoFlow 3.0 Phase 13: affiliate onboarding rewards
-- Import into the existing `dogo` database. This is an additive migration only.
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
    INDEX idx_affiliate_referrals_affiliate_status (affiliate_id, status),
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
    INDEX idx_affiliate_rewards_owner_status (affiliate_id, status),
    CONSTRAINT fk_reward_affiliate FOREIGN KEY (affiliate_id) REFERENCES affiliate_profiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_reward_referral FOREIGN KEY (referral_id) REFERENCES affiliate_referrals(id) ON DELETE CASCADE
) ENGINE=InnoDB;
