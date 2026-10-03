<?php
/**
 * Database connection factory.
 * One PDO instance per request, driven by config.php (sqlite | mysql).
 * Every query in the app uses prepared statements through this handle.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $config;
    $driver = $config['db_driver'] ?? 'sqlite';

    try {
        if ($driver === 'mysql') {
            $m = $config['mysql'];
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $m['host'], $m['dbname'], $m['charset'] ?? 'utf8mb4'
            );
            $pdo = new PDO($dsn, $m['user'], $m['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // real prepares
            ]);
        } else {
            $path = $config['sqlite_path'];
            $dir  = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            // SQLite ignores FK constraints unless this is set per connection.
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    } catch (PDOException $e) {
        // Friendly error instead of a stack trace leaking credentials.
        http_response_code(500);
        exit('<h1>Database connection failed</h1><p>Check your settings in '
            . '<code>config.php</code>, then run <code>install.php</code>.</p>');
    }

    return $pdo;
}

/**
 * Has the installer already created the tables?
 */
function db_is_installed(PDO $db): bool
{
    try {
        $db->query('SELECT setting_key FROM settings LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
