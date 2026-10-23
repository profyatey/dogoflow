<?php
declare(strict_types=1);

/** Assigns the public Store ID (DF-00001 style) to a seller profile. */
final class SellerCodes
{
    /** Returns the existing code, or creates the next free one. Call inside an open transaction. */
    public static function ensure(PDO $pdo, int $profileId): string
    {
        $current = $pdo->prepare('SELECT seller_code FROM seller_profiles WHERE id = ? FOR UPDATE');
        $current->execute([$profileId]);
        $existing = (string) $current->fetchColumn();
        if ($existing !== '') {
            return $existing;
        }

        // FOR UPDATE makes concurrent sign-ups wait here, so two sellers cannot receive the same number.
        $next = (int) $pdo->query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(seller_code, 4) AS UNSIGNED)), 0) + 1
             FROM seller_profiles WHERE seller_code REGEXP '^DF-[0-9]+$' FOR UPDATE"
        )->fetchColumn();

        $code = 'DF-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE seller_profiles SET seller_code = ? WHERE id = ? AND seller_code IS NULL')
            ->execute([$code, $profileId]);

        return $code;
    }
}
