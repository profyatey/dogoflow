USE dogo;

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS shipping_address JSON NULL AFTER total,
  ADD COLUMN IF NOT EXISTS customer_note VARCHAR(500) NULL AFTER shipping_address,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER status;

ALTER TABLE payments
  ADD COLUMN IF NOT EXISTS access_code VARCHAR(120) NULL AFTER reference,
  ADD COLUMN IF NOT EXISTS gateway_transaction_id BIGINT UNSIGNED NULL AFTER access_code;

CREATE INDEX IF NOT EXISTS idx_orders_status_created ON orders (status, created_at);
CREATE INDEX IF NOT EXISTS idx_payments_reference_status ON payments (reference, status);
