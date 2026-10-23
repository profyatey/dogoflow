-- Admin-controlled cart switch. Safe to re-run.
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
    ('signup_customer', '1'),
    ('signup_seller', '1'),
    ('signup_affiliate', '1'),
    ('cart_enabled', '0');
