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
-- Existing installations: if seller_subscription_payments already exists with user_id, no destructive migration is required.
