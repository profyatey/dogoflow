<?php
declare(strict_types=1);

if (!defined('DOGOFLOW_ROOT')) {
    define('DOGOFLOW_ROOT', dirname(__DIR__, 1));
}

// Load the private project .env without exposing it through public_html.
// Existing server environment variables take precedence.
$dogoflowEnvFile = DOGOFLOW_ROOT . '/.env';
if (is_file($dogoflowEnvFile) && is_readable($dogoflowEnvFile)) {
    $lines = file($dogoflowEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) continue;
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[strlen($value)-1] === '"') || ($value[0] === "'" && $value[strlen($value)-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

require_once DOGOFLOW_ROOT . '/core/config/app.php';
require_once DOGOFLOW_ROOT . '/core/config/database.php';

// Production traffic should never carry credentials or sessions over plain HTTP.
if (APP_ENV === 'production' && !dogoflow_is_https() && PHP_SAPI !== 'cli') {
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if ($host !== '' && !str_starts_with($host, 'localhost') && !str_starts_with($host, '127.0.0.1')) {
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
}
if (APP_ENV === 'production' && dogoflow_is_https()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
require_once DOGOFLOW_ROOT . '/core/security/security.php';
require_once DOGOFLOW_ROOT . '/core/auth/Auth.php';
require_once DOGOFLOW_ROOT . '/core/seo/OpenGraph.php';
require_once DOGOFLOW_ROOT . '/core/orders/OrderManager.php';

require_once DOGOFLOW_ROOT . '/core/catalog/SellerPlan.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Keep sessions alive for the whole form-filling window so CSRF tokens do not
    // expire while a seller completes registration or product forms.
    ini_set('session.gc_maxlifetime', '28800');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => dogoflow_is_https(),
        'use_strict_mode' => true,
    ]);
}

Auth::init();

require_once DOGOFLOW_ROOT . '/core/notifications/SmtpMailer.php';
require_once DOGOFLOW_ROOT . '/core/notifications/NotificationService.php';
