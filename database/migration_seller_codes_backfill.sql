-- DogoFlow hotfix: give every seller profile a Store ID (DF-00001 style).
-- New sellers get one at registration (core/catalog/SellerCodes.php). This script
-- fills in any older profiles that still have none. Safe to run more than once.

SET @next_df := (
    SELECT COALESCE(MAX(CAST(SUBSTRING(seller_code, 4) AS UNSIGNED)), 0)
    FROM seller_profiles
    WHERE seller_code REGEXP '^DF-[0-9]+$'
);

UPDATE seller_profiles
SET seller_code = CONCAT('DF-', LPAD((@next_df := @next_df + 1), 5, '0'))
WHERE seller_code IS NULL OR seller_code = ''
ORDER BY id;

-- Check: every seller should now have a code
SELECT id, user_id, business_name, seller_code FROM seller_profiles ORDER BY id;
