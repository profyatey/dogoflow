-- DogoFlow 3.0 ADMIN SEED (run AFTER schema.sql)
-- Database: dogo
-- Replace the email and PASSWORD_HASH below before importing.
-- Generate a hash with: php -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT), PHP_EOL;"
-- Never put a plain-text password in this file. Keep this seed private and delete it from hosting after use.

USE dogo;

INSERT INTO users (name, email, phone, password_hash, role, status, email_verified_at)
VALUES (
    'DogoFlow Administrator',
    'admin@your-domain.com',
    NULL,
    'REPLACE_WITH_PHP_PASSWORD_HASH',
    'admin',
    'active',
    NOW()
);
