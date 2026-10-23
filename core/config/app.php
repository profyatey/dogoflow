<?php
declare(strict_types=1);

/**
 * DogoFlow environment-aware application configuration.
 * Works when the project is served from XAMPP /DogoFlow/public
 * or when public/ is deployed as AvesHost public_html.
 */

/**
 * Load private settings from a .env file kept OUTSIDE the public web root.
 * Real server environment variables always win; the file only fills in what is missing.
 * Looked up in the project root, then one level above it (useful on shared hosting).
 */
function dogoflow_load_env(): void {
    $root = defined('DOGOFLOW_ROOT') ? DOGOFLOW_ROOT : dirname(__DIR__, 2);
    foreach ([$root . '/.env', dirname($root) . '/.env'] as $file) {
        if (!is_file($file) || !is_readable($file)) continue;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (stripos($line, 'export ') === 0) $line = ltrim(substr($line, 7));
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) continue;
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                $end = strrpos($value, $quote);
                $value = $end > 0 ? substr($value, 1, $end - 1) : substr($value, 1);
            } else {
                $value = trim(preg_replace('/\s+#.*$/', '', $value) ?? $value);
            }
            $existing = getenv($key);
            if ($existing !== false && $existing !== '') continue;
            if ($value === '' ) continue;
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}
dogoflow_load_env();

define('APP_NAME', 'DogoFlow');
define('APP_ENV', getenv('DOGOFLOW_ENV') ?: 'development');
// Legacy constant stays fail-closed; runtime code should use dogoflow_cart_enabled().
define('CART_ENABLED', false);

function dogoflow_cart_enabled(): bool {
    if (!class_exists('SiteSettings', false)) {
        require_once DOGOFLOW_ROOT . '/core/config/SiteSettings.php';
    }
    try {
        return SiteSettings::isCartEnabled();
    } catch (Throwable $e) {
        // If the setting table has not been migrated, keep product checkout disabled.
        return false;
    }
}

function dogoflow_is_https(): bool {
    $forwarded = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443)
        || $forwarded === 'https';
}

function detect_app_url(): string {
    $configured = trim((string)(getenv('DOGOFLOW_URL') ?: ''));
    if ($configured !== '') {
        // A bare host such as "www.dogoflow.com" would turn every url() into a relative link
        // (and break CSS, images and redirects inside /admin, /seller, ...). Always keep a scheme.
        if (!preg_match('#^https?://#i', $configured)) {
            $isLocal = (bool) preg_match('#^(localhost|127\.0\.0\.1)(:\d+)?(/|$)#i', $configured);
            $configured = ($isLocal ? 'http://' : 'https://') . ltrim($configured, '/');
        }
        return rtrim($configured, '/');
    }

    $https = dogoflow_is_https();
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));

    // In XAMPP the public web root is /DogoFlow/public.
    $marker = '/public/';
    $pos = strpos($script, $marker);
    if ($pos !== false) {
        $base = substr($script, 0, $pos + strlen('/public'));
    } else {
        // On AvesHost, public/ contents are copied into public_html, so the base is /.
        $base = '';
    }
    return $scheme . '://' . $host . rtrim($base, '/');
}

define('APP_URL', detect_app_url());
define('PUBLIC_DIR', DOGOFLOW_ROOT . '/public_html');
define('STORAGE_DIR', DOGOFLOW_ROOT . '/storage');
define('UPLOAD_DIR', PUBLIC_DIR . '/uploads/products');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

date_default_timezone_set(getenv('DOGOFLOW_TIMEZONE') ?: 'Africa/Accra');

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', STORAGE_DIR . '/logs/php-error.log');
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
