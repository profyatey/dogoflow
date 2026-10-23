-- DogoFlow 3.0 Seller Plans & Photo Storage
-- Run this while the `dogo` database is selected.

ALTER TABLE seller_profiles
    ADD COLUMN seller_code VARCHAR(20) NULL UNIQUE AFTER user_id,
    ADD COLUMN plan ENUM('free','pro') NOT NULL DEFAULT 'free' AFTER status,
    ADD COLUMN photo_limit INT UNSIGNED NOT NULL DEFAULT 5 AFTER plan,
    ADD COLUMN subscription_started_at DATETIME NULL AFTER photo_limit,
    ADD COLUMN subscription_expires_at DATETIME NULL AFTER subscription_started_at;

CREATE TABLE IF NOT EXISTS product_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_images_product (product_id, sort_order),
    INDEX idx_product_images_primary (product_id, is_primary)
) ENGINE=InnoDB;

-- Give existing sellers stable DogoFlow IDs where possible.
SET @next_df := (SELECT COALESCE(MAX(CAST(SUBSTRING(seller_code, 4) AS UNSIGNED)), 0) FROM seller_profiles WHERE seller_code REGEXP '^DF-[0-9]+$');
UPDATE seller_profiles
SET seller_code = CONCAT('DF-', LPAD((@next_df := @next_df + 1), 5, '0'))
WHERE seller_code IS NULL OR seller_code = '';

-- Existing products with a legacy image get one product_images record.
INSERT INTO product_images (product_id, image_path, is_primary, sort_order)
SELECT p.id, p.image, 1, 0
FROM products p
LEFT JOIN product_images pi ON pi.product_id = p.id
WHERE p.image IS NOT NULL
  AND p.image <> ''
  AND pi.id IS NULL;

UPDATE seller_profiles
SET photo_limit = CASE WHEN plan = 'pro' THEN 30 ELSE 5 END;
