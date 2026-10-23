<?php
declare(strict_types=1);

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

const CSRF_TTL_SECONDS = 28800; // 8 hours, matches session lifetime

/**
 * CSRF tokens are session-bound and expire after CSRF_TTL_SECONDS.
 * Older sessions without a creation timestamp receive a fresh token.
 */
function csrf_token(): string {
    $known = $_SESSION['_csrf'] ?? null;
    $createdAt = (int) ($_SESSION['_csrf_created_at'] ?? 0);
    $now = time();

    if (
        !is_string($known)
        || preg_match('/\A[a-f0-9]{64}\z/', $known) !== 1
        || $createdAt <= 0
        || $createdAt > $now
        || ($now - $createdAt) > CSRF_TTL_SECONDS
    ) {
        return csrf_regenerate();
    }

    return $known;
}

function csrf_regenerate(): string {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    $_SESSION['_csrf_created_at'] = time();

    return $_SESSION['_csrf'];
}

/** Reject invalid, malformed, or expired CSRF tokens before a handler mutates data. */
function verify_csrf(mixed $token): void {
    $known = $_SESSION['_csrf'] ?? null;
    $createdAt = (int) ($_SESSION['_csrf_created_at'] ?? 0);
    $now = time();

    $knownIsValid = is_string($known)
        && preg_match('/\A[a-f0-9]{64}\z/', $known) === 1;
    $tokenMatches = is_string($token)
        && $token !== ''
        && $knownIsValid
        && hash_equals($known, $token);
    $tokenIsFresh = $createdAt > 0
        && $createdAt <= $now
        && ($now - $createdAt) <= CSRF_TTL_SECONDS;

    if ($tokenMatches && $tokenIsFresh) {
        return;
    }

    // Fail closed and give browser users a safe way to reload the form.
    flash('error', 'Your security token expired or became invalid. Please submit the form again.');

    $returnTo = $_SERVER['REQUEST_URI'] ?? '/';
    if (
        !is_string($returnTo)
        || $returnTo === ''
        || !str_starts_with($returnTo, '/')
        || str_starts_with($returnTo, '//')
        || preg_match('/[\r\n]/', $returnTo) === 1
    ) {
        $returnTo = '/';
    }

    header('Location: ' . $returnTo, true, 303);
    exit;
}

function url(string $path = ''): string {
    if ($path === '') return APP_URL . '/';
    if (preg_match('#^https?://#i', $path)) return $path;
    return APP_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never {
    header('Location: ' . url($path));
    exit;
}

function flash(string $key, ?string $value = null): ?string {
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}


function login_throttle_key(string $email): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', strtolower(trim($email)) . '|' . $ip);
}

function login_is_rate_limited(string $email): bool {
    $file = STORAGE_DIR . '/cache/login-' . login_throttle_key($email) . '.json';
    if (!is_file($file)) return false;
    $data = json_decode((string)@file_get_contents($file), true);
    if (!is_array($data)) return false;
    if ((int)($data['reset_at'] ?? 0) <= time()) { @unlink($file); return false; }
    return (int)($data['failures'] ?? 0) >= 8;
}

function login_record_failure(string $email): void {
    $dir = STORAGE_DIR . '/cache';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $file = $dir . '/login-' . login_throttle_key($email) . '.json';
    $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
    if (!is_array($data) || (int)($data['reset_at'] ?? 0) <= time()) $data = ['failures'=>0,'reset_at'=>time()+900];
    $data['failures'] = (int)$data['failures'] + 1;
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function login_clear_failures(string $email): void {
    $file = STORAGE_DIR . '/cache/login-' . login_throttle_key($email) . '.json';
    if (is_file($file)) @unlink($file);
}
