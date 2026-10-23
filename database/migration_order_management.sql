-- DogoFlow 3.0 — Order Management
-- Database: dogo
-- Run after the Checkout + Paystack migration.

USE dogo;

ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS fulfillment_status ENUM('paid','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'paid' AFTER subtotal,
    ADD COLUMN IF NOT EXISTS seller_note VARCHAR(500) NULL AFTER fulfillment_status,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER seller_note;

CREATE INDEX IF NOT EXISTS idx_order_items_seller_status
    ON order_items (seller_id, fulfillment_status, order_id);

CREATE TABLE IF NOT EXISTS order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_history_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order_history_order_created (order_id, created_at),
    INDEX idx_order_history_actor (actor_user_id, created_at)
) ENGINE=InnoDB;

-- Backfill existing paid/processing/shipped/completed items.
UPDATE order_items oi
INNER JOIN orders o ON o.id = oi.order_id
SET oi.fulfillment_status = CASE
    WHEN o.status = 'completed' THEN 'completed'
    WHEN o.status = 'shipped' THEN 'shipped'
    WHEN o.status = 'processing' THEN 'processing'
    WHEN o.status = 'cancelled' OR o.status = 'refunded' THEN 'cancelled'
    ELSE 'paid'
END
WHERE oi.fulfillment_status = 'paid';

-- Seed a history row for existing orders only when no history exists.
INSERT INTO order_status_history (order_id, actor_user_id, from_status, to_status, note)
SELECT o.id, NULL, NULL, o.status, 'Initial order status recorded during Order Management migration.'
FROM orders o
LEFT JOIN order_status_history h ON h.order_id = o.id
WHERE h.id IS NULL;
