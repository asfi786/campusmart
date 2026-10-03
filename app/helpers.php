<?php
/**
 * Shared helpers: escaping, URLs, flashes, CSRF, formatting, settings.
 * (Auth helpers live in auth.php.)
 */

/* ---------------- Config ---------------- */
function config(string $key, $default = null)
{
    global $config;
    return $config[$key] ?? $default;
}

/* ---------------- Output escaping (XSS protection) ---------------- */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/* ---------------- URLs ---------------- */
/** Site-relative URL, e.g. u('listing.php?id=3') */
function u(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . '/' . $path;
}

/** Public asset URL with a cache-busting mtime query string. */
function asset(string $path): string
{
    $full = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($full) ? filemtime($full) : 1;
    return u($path) . '?v=' . $v;
}

/** Public URL for an uploaded file stored as a path relative to /uploads. */
function uploads_url(?string $rel): string
{
    if (!$rel) {
        return '';
    }
    return u('uploads/' . ltrim($rel, '/'));
}

/** Thumbnail sibling of an uploaded image: photo.jpg -> photo_thumb.jpg */
function thumb_of(string $rel): string
{
    $pos = strrpos($rel, '.');
    if ($pos === false) {
        return $rel . '_thumb';
    }
    return substr($rel, 0, $pos) . '_thumb' . substr($rel, $pos);
}

function redirect(string $path): void
{
    header('Location: ' . u($path));
    exit;
}

/* ---------------- Flash messages (rendered as toasts) ---------------- */
function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

/** Returns queued flashes and clears them. */
function flashes(): array
{
    $f = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $f;
}

/* ---------------- CSRF protection ---------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. Dies with 419 on mismatch.
 *  Pass $json=true in JSON APIs so the client gets parseable output. */
function validate_csrf(bool $json = false): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) $sent)) {
        http_response_code(419);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Session expired. Please refresh the page and try again.']);
        } else {
            echo '<h1>419 — Session expired</h1><p>Please go back and try again.</p>';
        }
        exit;
    }
}

/* ---------------- Request helpers ---------------- */
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Repopulate a form field after a validation error. */
function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/* ---------------- Formatting ---------------- */
function money($n): string
{
    return config('currency', 'Rs') . ' ' . number_format((float) $n, 0);
}

/** "2026-09-24 18:01:00" -> "3 hours ago" */
function time_ago(string $dt): string
{
    $ts = strtotime($dt);
    if (!$ts) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    $units = [[31536000, 'year'], [2592000, 'month'], [86400, 'day'],
              [3600, 'hour'], [60, 'minute']];
    foreach ($units as [$secs, $name]) {
        if ($diff >= $secs) {
            $n = (int) floor($diff / $secs);
            return $n . ' ' . $name . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function cond_label(string $c): string
{
    return ['new' => 'New', 'like_new' => 'Like new',
            'good' => 'Good', 'fair' => 'Fair'][$c] ?? ucfirst($c);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '?';
}

/* ---------------- Query-string / pagination ---------------- */
/** Current query string with overrides applied (used for filter links). */
function build_query(array $overrides = []): string
{
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    return http_build_query($q);
}

function page_url(int $page): string
{
    return u('index.php') . '?' . build_query(['page' => $page > 1 ? $page : null]);
}

/** Simple numbered pagination HTML. */
function paginate(int $total, int $per, int $page): string
{
    $pages = (int) ceil($total / max(1, $per));
    if ($pages <= 1) {
        return '';
    }
    $page = max(1, min($page, $pages));
    $html = '<nav class="pagination" aria-label="Pages">';
    if ($page > 1) {
        $html .= '<a class="page-btn" href="' . e(page_url($page - 1)) . '">&larr; Prev</a>';
    }
    $from = max(1, $page - 2);
    $to   = min($pages, $page + 2);
    for ($i = $from; $i <= $to; $i++) {
        $html .= $i === $page
            ? '<span class="page-btn current" aria-current="page">' . $i . '</span>'
            : '<a class="page-btn" href="' . e(page_url($i)) . '">' . $i . '</a>';
    }
    if ($page < $pages) {
        $html .= '<a class="page-btn" href="' . e(page_url($page + 1)) . '">Next &rarr;</a>';
    }
    return $html . '</nav>';
}

/* ---------------- Settings (admin-editable, stored in DB) ---------------- */
function site_setting(string $key, $default = null)
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $st = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $st->execute([$key]);
        $val = $st->fetchColumn();
    } catch (PDOException $e) {
        $val = false;
    }
    $cache[$key] = ($val === false) ? $default : $val;
    return $cache[$key];
}

function save_setting(string $key, $value): void
{
    $db = db();
    // Portable upsert: works on both MySQL and SQLite.
    $db->prepare('DELETE FROM settings WHERE setting_key = ?')->execute([$key]);
    $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)')
       ->execute([$key, (string) $value]);
}

/* ---------------- Notifications ---------------- */
function notify(int $userId, string $type, string $title, string $body = '', string $link = ''): void
{
    db()->prepare(
        'INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)'
    )->execute([$userId, $type, $title, $body, $link]);
}

function unread_counts(int $userId): array
{
    $db = db();
    $st = $db->prepare(
        'SELECT COUNT(*) FROM messages m
         JOIN conversations c ON c.id = m.conversation_id
         WHERE m.is_read = 0 AND m.sender_id != ?
           AND (c.buyer_id = ? OR c.seller_id = ?)'
    );
    $st->execute([$userId, $userId, $userId]);
    $messages = (int) $st->fetchColumn();

    $st = $db->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
    );
    $st->execute([$userId]);
    $notifications = (int) $st->fetchColumn();

    return ['messages' => $messages, 'notifications' => $notifications];
}
