USE dogo;

ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS read_at DATETIME NULL;

CREATE INDEX IF NOT EXISTS idx_notifications_user_read_created
  ON notifications (user_id, read_at, created_at);
