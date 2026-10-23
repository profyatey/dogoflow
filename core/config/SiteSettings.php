<?php
declare(strict_types=1);

/** Admin-controlled switches. Stored in the site_settings table. */
final class SiteSettings
{
    private const SWITCHES = [
        'customer' => 'signup_customer',
        'seller' => 'signup_seller',
        'affiliate' => 'signup_affiliate',
    ];

    private const CART_SETTING = 'cart_enabled';

    private static ?array $values = null;

    public static function accountTypes(): array
    {
        return array_keys(self::SWITCHES);
    }

    /** Cart is disabled by default; product ordering can use direct seller contact. */
    public static function isCartEnabled(): bool
    {
        return (self::values()[self::CART_SETTING] ?? '0') === '1';
    }

    public static function setCartEnabled(bool $enabled): void
    {
        db()->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([self::CART_SETTING, $enabled ? '1' : '0']);
        self::$values = null;
    }

    public static function isSignupOpen(string $accountType): bool
    {
        $key = self::SWITCHES[$accountType] ?? null;
        if ($key === null) {
            return true;
        }
        return (self::values()[$key] ?? '1') === '1';
    }

    /** Account types whose sign-up is currently paused. */
    public static function closedAccountTypes(): array
    {
        return array_values(array_filter(self::accountTypes(), fn (string $type) => !self::isSignupOpen($type)));
    }

    public static function closedMessage(string $accountType): string
    {
        return 'Sign-up for ' . $accountType . ' accounts is paused right now. Please check back later.';
    }

    public static function setSignupOpen(string $accountType, bool $open): void
    {
        $key = self::SWITCHES[$accountType] ?? null;
        if ($key === null) {
            throw new InvalidArgumentException('Unknown account type.');
        }
        db()->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([$key, $open ? '1' : '0']);
        self::$values = null;
    }

    private static function values(): array
    {
        if (self::$values !== null) {
            return self::$values;
        }
        $defaults = array_fill_keys(array_values(self::SWITCHES), '1');
        $defaults[self::CART_SETTING] = '0';
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable $e) {
            $rows = [];
        }
        self::$values = array_merge($defaults, array_intersect_key($rows, $defaults));
        return self::$values;
    }
}
