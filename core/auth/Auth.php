<?php
declare(strict_types=1);

final class Auth
{
    public static function init(): void
    {
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function check(): bool
    {
        return self::userId() !== null;
    }

    public static function role(): ?string
    {
        return isset($_SESSION['role']) ? self::canonicalRole((string) $_SESSION['role']) : null;
    }

    public static function login(int $userId, string $role): void
    {
        session_regenerate_id(true);
        csrf_regenerate();

        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = self::canonicalRole($role);
    }

    public static function canonicalRole(string $role): string
    {
        $role = strtolower(trim($role));

        return match ($role) {
            'vendor' => 'seller',
            default => $role,
        };
    }

    public static function dashboardPath(?string $role = null): string
    {
        $role = self::canonicalRole($role ?? (string) self::role());

        return match ($role) {
            'admin' => '/admin/dashboard.php',
            'seller' => '/seller/dashboard.php',
            'affiliate' => '/affiliate/dashboard.php',
            default => '/customer/dashboard.php',
        };
    }

    public static function redirectToDashboard(): never
    {
        redirect(self::dashboardPath());
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/auth/login.php');
        }
    }

    public static function requireApprovedSeller(): void
    {
        self::requireRole('seller');

        $stmt = db()->prepare(
            'SELECT
                u.status AS user_status,
                u.email_verified_at,
                sp.approval_status,
                sp.status AS seller_status
             FROM users u
             LEFT JOIN seller_profiles sp ON sp.user_id = u.id
             WHERE u.id = ?
             LIMIT 1'
        );
        $stmt->execute([self::userId()]);
        $seller = $stmt->fetch();

        if (
            !$seller
            || $seller['user_status'] !== 'active'
            || empty($seller['email_verified_at'])
            || $seller['approval_status'] !== 'approved'
            || $seller['seller_status'] !== 'active'
        ) {
            // This is a redirect to an explanatory page, so keep a 3xx response.
            redirect('/seller/access-status.php');
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();

        // Re-check the database on protected requests so a stale session cannot
        // retain a revoked role or continue after the account is suspended.
        $stmt = db()->prepare('SELECT role, status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([self::userId()]);
        $account = $stmt->fetch();

        $sessionRole = self::canonicalRole((string) self::role());
        $databaseRole = $account
            ? self::canonicalRole((string) ($account['role'] ?? ''))
            : '';

        if (
            !$account
            || ($account['status'] ?? null) !== 'active'
            || $databaseRole === ''
            || $databaseRole !== $sessionRole
        ) {
            self::logout();
            redirect('/auth/login.php');
        }

        $allowedRoles = array_map(
            static fn (string $role): string => self::canonicalRole($role),
            $roles
        );

        if (!in_array($databaseRole, $allowedRoles, true)) {
            // Let the destination render the 403 response instead of combining
            // a 403 status with a Location header.
            redirect('/403.php');
        }
    }
}
