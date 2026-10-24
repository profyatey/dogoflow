<?php
declare(strict_types=1);

/*
 * Prefer environment variables in production:
 * DOGOFLOW_DB_HOST, DOGOFLOW_DB_NAME, DOGOFLOW_DB_USER, DOGOFLOW_DB_PASS
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DOGOFLOW_DB_HOST') ?: 'hostname';
    $name = getenv('DOGOFLOW_DB_NAME') ?: (APP_ENV === 'production' ? '' : 'DB_NAAME');
    $user = getenv('DOGOFLOW_DB_USER') ?: (APP_ENV === 'production' ? '' : 'DB_USERNAME');
    $passEnv = getenv('DOGOFLOW_DB_PASS');
    $pass = $passEnv === false ? '' : $passEnv;

    if (APP_ENV === 'production' && ($name === '' || $user === '' || $passEnv === false)) {
        throw new RuntimeException('Production database configuration is incomplete. Set DOGOFLOW_DB_NAME, DOGOFLOW_DB_USER and DOGOFLOW_DB_PASS.');
    }

    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
