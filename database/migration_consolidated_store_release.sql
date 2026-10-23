-- DogoFlow 3.0 consolidated storefront release migration
-- Run once with the DogoFlow database selected. Back up the database first.
-- Existing non-empty seller codes are preserved.

SET @next_df_code := (
    SELECT COALESCE(MAX(CAST(SUBSTRING(seller_code, 4) AS UNSIGNED)), 0)
    FROM seller_profiles
    WHERE seller_code REGEXP '^DF-[0-9]+$'
);

UPDATE seller_profiles
SET seller_code = CONCAT('DF-', LPAD((@next_df_code := @next_df_code + 1), 5, '0'))
WHERE seller_code IS NULL OR seller_code = '';

CREATE TABLE IF NOT EXISTS marketplace_view_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_profile_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    event_type ENUM('store_view', 'product_view') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_marketplace_view_seller_profile
        FOREIGN KEY (seller_profile_id) REFERENCES seller_profiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_marketplace_view_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_marketplace_view_seller_type_date (seller_profile_id, event_type, created_at),
    INDEX idx_marketplace_view_product_type_date (product_id, event_type, created_at)
) ENGINE=InnoDB;
