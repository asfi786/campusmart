<?php
/**
 * CampusMart bootstrap — included at the top of every page / API script.
 * Loads config, starts a secure session, and wires up the app libraries.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$config = require APP_ROOT . '/config.php';

/* Show errors on a dev machine, hide them on a live host. */
error_reporting(E_ALL);
ini_set('display_errors', !empty($config['debug']) ? '1' : '0');

/* ---------------- Secure session ---------------- */
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* -------- Base URL auto-detect (works in a sub-folder too) -------- */
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$base = rtrim($scriptDir, '/');
define('BASE_URL', ($base === '' || $base === '.') ? '' : $base);

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/image.php';
require __DIR__ . '/price.php';

/* Shared PDO connection (lazy — connects on first real query). */
$db = db();
