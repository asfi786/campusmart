<?php
/**
 * CampusMart installer — run once after uploading the files.
 *
 *   Web:  https://your-site/install.php   (form: admin account + demo data)
 *   CLI:  php install.php --cli --admin-email=a@b.c --admin-pass=secret --demo
 *
 * Creates all tables, the admin account and (optionally) demo data.
 * ⚠ DELETE this file after installing — it can recreate the database.
 */
declare(strict_types=1);

define('APP_ROOT', __DIR__);
$config = require APP_ROOT . '/config.php';

$isCli = PHP_SAPI === 'cli';

/* ---------------- Requirement checks ---------------- */
$problems = [];
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    $problems[] = 'PHP 8.0+ is required (found ' . PHP_VERSION . ').';
}
foreach (['pdo', 'json', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) {
        $problems[] = "PHP extension missing: $ext";
    }
}
$driver = $config['db_driver'] ?? 'sqlite';
if ($driver === 'sqlite' && !extension_loaded('pdo_sqlite')) {
    $problems[] = 'PHP extension missing: pdo_sqlite (needed for SQLite mode).';
}
if ($driver === 'mysql' && !extension_loaded('pdo_mysql')) {
    $problems[] = 'PHP extension missing: pdo_mysql (needed for MySQL mode).';
}
if (!is_writable(APP_ROOT . '/uploads') && !@mkdir(APP_ROOT . '/uploads', 0775, true)) {
    $problems[] = 'The /uploads folder is not writable.';
}

/* ---------------- Connect ---------------- */
try {
    if ($driver === 'mysql') {
        $m = $config['mysql'];
        // Connect WITHOUT dbname first — the database may not exist yet.
        $pdo = new PDO(
            "mysql:host={$m['host']};charset=" . ($m['charset'] ?? 'utf8mb4'),
            $m['user'], $m['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $m['dbname']) . '` '
                 . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . str_replace('`', '', $m['dbname']) . '`');
    } else {
        $path = $config['sqlite_path'];
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    fail('Database connection failed: ' . $e->getMessage());
}

/* ---------------- Already installed? ---------------- */
$installed = false;
try {
    $installed = $pdo->query('SELECT setting_value FROM settings WHERE setting_key = \'installed\'')
                     ->fetchColumn() === '1';
} catch (PDOException $e) {
    $installed = false; // tables do not exist yet
}

/**
 * Run the schema file. Our schema has no semicolons inside string
 * literals, so a simple split on ";" is safe here.
 */
function run_schema(PDO $pdo, string $driver): void
{
    $file = APP_ROOT . '/schema/' . ($driver === 'mysql' ? 'mysql.sql' : 'sqlite.sql');
    $sql = file_get_contents($file);
    // Strip full-line comments.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (explode(';', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
    }
}

function do_install(PDO $pdo, string $driver, string $name, string $email, string $pass, bool $demo): array
{
    run_schema($pdo, $driver);

    // Admin account.
    $pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
    $pdo->prepare(
        "INSERT INTO users (name, email, password_hash, role, is_verified)
         VALUES (?, ?, ?, 'admin', 1)"
    )->execute([$name, strtolower(trim($email)), password_hash($pass, PASSWORD_DEFAULT)]);

    // Default settings.
    foreach (['installed' => '1', 'email_verification' => '0',
              'allowed_domains_only' => '0', 'items_per_page' => '12'] as $k => $v) {
        $pdo->prepare('DELETE FROM settings WHERE setting_key = ?')->execute([$k]);
        $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)')
            ->execute([$k, $v]);
    }

    if ($demo) {
        require APP_ROOT . '/app/image.php';
        // make_placeholder needs APP_ROOT + config(); config() lives in helpers.
        if (!function_exists('config')) {
            $GLOBALS['config'] = require APP_ROOT . '/config.php';
            function config($key, $default = null)
            {
                return $GLOBALS['config'][$key] ?? $default;
            }
        }
        require APP_ROOT . '/app/seed.php';
        seed_demo_data($pdo);
    }
    return ['email' => $email];
}

function fail(string $msg): void
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "INSTALL FAILED: $msg\n");
        exit(1);
    }
    http_response_code(500);
    exit('<h1>Installation failed</h1><p>' . htmlspecialchars($msg) . '</p>');
}

/* ================= CLI mode ================= */
if ($isCli) {
    $opts = getopt('', ['cli', 'admin-email:', 'admin-pass:', 'admin-name::', 'demo', 'force']);
    if ($installed && empty($opts['force'])) {
        fwrite(STDERR, "Already installed. Use --force to reinstall (wipes data!).\n");
        exit(1);
    }
    if (empty($opts['admin-email']) || empty($opts['admin-pass'])) {
        fwrite(STDERR, "Usage: php install.php --cli --admin-email=you@x.com --admin-pass=secret [--admin-name=Name] [--demo] [--force]\n");
        exit(1);
    }
    if ($problems) {
        fwrite(STDERR, "Pre-checks failed:\n - " . implode("\n - ", $problems) . "\n");
        exit(1);
    }
    do_install($pdo, $driver, $opts['admin-name'] ?? 'Admin',
        $opts['admin-email'], $opts['admin-pass'], isset($opts['demo']));
    echo "CampusMart installed. Admin: {$opts['admin-email']}\n";
    echo "Delete install.php before going live.\n";
    exit(0);
}

/* ================= Web mode ================= */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install CampusMart</title>
<style>
body{font-family:system-ui,sans-serif;background:#FAF8F4;color:#1C1533;max-width:560px;margin:2rem auto;padding:0 1rem}
.card{background:#fff;border:1px solid #E8E0D3;border-radius:18px;padding:1.6rem;box-shadow:0 12px 32px rgba(46,24,120,.1)}
h1{font-size:1.5rem}.field{margin-bottom:.9rem}label{font-weight:600;font-size:.88rem;display:block;margin-bottom:.3rem}
input[type=text],input[type=email],input[type=password]{width:100%;padding:.7rem .9rem;border:1.5px solid #E8E0D3;border-radius:12px;font:inherit;box-sizing:border-box}
button{background:linear-gradient(135deg,#6C4CF1,#4A2FD6);color:#fff;border:none;border-radius:14px;padding:.85rem 1.4rem;font-weight:700;font-size:1rem;cursor:pointer;width:100%}
.warn{background:#FEF3C7;border:1px solid #F59E0B;border-radius:12px;padding:.9rem 1rem;margin:1rem 0}
.err{background:#FDECEC;border:1px solid #F5C2C2;border-radius:12px;padding:.9rem 1rem;margin:1rem 0;color:#7F1D1D}
.ok{background:#E7F6EC;border:1px solid #BFE6CC;border-radius:12px;padding:.9rem 1rem;margin:1rem 0;color:#14532D}
.check{display:flex;gap:.6rem;align-items:flex-start;margin:.9rem 0}
</style>
</head>
<body>
<div class="card">
<h1>🛠 Install CampusMart</h1>

<?php if ($problems): ?>
    <div class="err"><strong>Please fix these first:</strong><ul>
        <?php foreach ($problems as $p): ?><li><?= htmlspecialchars($p) ?></li><?php endforeach; ?>
    </ul></div>
<?php endif; ?>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$problems && !$installed) {
    $name  = trim($_POST['name'] ?? 'Admin');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo '<div class="err">Enter a valid admin email.</div>';
    } elseif (strlen($pass) < 8) {
        echo '<div class="err">Admin password must be at least 8 characters.</div>';
    } else {
        do_install($pdo, $driver, $name, $email, $pass, isset($_POST['demo']));
        echo '<div class="ok"><strong>Installed!</strong><br>Admin login: '
           . htmlspecialchars($email) . '</div>';
        echo '<div class="warn"><strong>Important:</strong> delete <code>install.php</code> from the server now, '
           . 'then <a href="index.php">open CampusMart →</a></div>';
        exit;
    }
}
?>

<?php if ($installed): ?>
    <div class="warn"><strong>Already installed.</strong> If you just deployed, delete
    <code>install.php</code> now for security. <a href="index.php">Open CampusMart →</a></div>
<?php elseif (!$problems): ?>
    <p>This creates the database tables, your admin account
       <?= $driver === 'sqlite' ? '(SQLite file)' : '(MySQL)' ?>, and optional demo data.</p>
    <form method="post">
        <div class="field"><label>Admin name</label>
            <input type="text" name="name" value="Admin" required></div>
        <div class="field"><label>Admin email</label>
            <input type="email" name="email" required placeholder="you@university.edu"></div>
        <div class="field"><label>Admin password (min 8 chars)</label>
            <input type="password" name="password" required minlength="8"></div>
        <label class="check"><input type="checkbox" name="demo" checked>
            <span>Include demo data (20 listings, users, chats, reviews) — recommended for the viva demo</span></label>
        <button type="submit">Install now</button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
