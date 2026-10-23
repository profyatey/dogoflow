USE dogoflow;

-- Run after schema.sql.
-- Development admin. Replace password hash with your own generated hash.
INSERT INTO users (name,email,password_hash,role,status,email_verified_at)
VALUES ('DogoFlow Admin','admin@example.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6nQ4JfM0yQ7j1Q4h0u',
        'admin','active',NOW())
ON DUPLICATE KEY UPDATE role='admin', status='active';

-- Create an affiliate profile manually after creating an affiliate user:
-- INSERT INTO affiliate_profiles (user_id, referral_code) VALUES (USER_ID,'DF-ABC123');
