-- DogoFlow 3.0 Product Management
-- Select the `dogo` database before running this migration.

USE dogo;

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

-- Backfill legacy product.image values so seller-wide photo quotas include them.
INSERT INTO product_images (product_id, image_path, is_primary, sort_order)
SELECT p.id, p.image, 1, 0
FROM products p
LEFT JOIN product_images pi ON pi.product_id = p.id
WHERE p.image IS NOT NULL
  AND p.image <> ''
  AND pi.id IS NULL;
