<?php
/**
 * CampusMart end-to-end smoke test.
 * Runs real HTTP requests against the dev server with cookie jars,
 * exercising registration -> listing -> chat -> sold -> review ->
 * report -> lost&found -> admin flows, plus the JSON APIs.
 *
 * Usage: php tests/e2e.php [base_url]
 */
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$pass = 0;
$fail = 0;
$jarDir = sys_get_temp_dir() . '/cm_e2e_' . getmypid();
mkdir($jarDir);

function jar(string $name): string
{
    global $jarDir;
    return $jarDir . '/' . $name . '.txt';
}

function req(string $method, string $path, array $params = [], ?string $jarName = 'a',
             array $files = [], bool $follow = true): array
{
    global $base;
    $ch = curl_init();
    $url = $base . $path;
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    if ($jarName) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, jar($jarName));
        curl_setopt($ch, CURLOPT_COOKIEFILE, jar($jarName));
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($files) {
            $params = array_merge($params, $files);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    } elseif ($params) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['code' => $code,
            'headers' => substr($raw, 0, $headerSize),
            'body' => substr($raw, $headerSize)];
}

function csrf(string $html): ?string
{
    if (preg_match('/name="csrf"\s+value="([a-f0-9]{64})"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/name=\'csrf\'\s+value=\'([a-f0-9]{64})\'/', $html, $m)) {
        return $m[1];
    }
    // Footer exposes the token to JS as:  csrf: "abc..."
    if (preg_match('/csrf["\']?\s*:\s*["\']([a-f0-9]{64})["\']/', $html, $m)) {
        return $m[1];
    }
    return null;
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "PASS  $name\n";
    } else {
        $fail++;
        echo "FAIL  $name" . ($detail ? " — $detail" : '') . "\n";
    }
}

function json_api(array $res): ?array
{
    $d = json_decode($res['body'], true);
    return is_array($d) ? $d : null;
}

echo "== CampusMart E2E @ $base ==\n";

// --- 1. Register a brand-new user (buyer flow) ---
$r = req('GET', '/register.php', [], 'buyer');
$t = csrf($r['body']);
check('register form has CSRF', (bool) $t);
$buyerEmail = 'e2e_buyer_' . time() . '@campus.edu.pk';
$r = req('POST', '/register.php', [
    'csrf' => $t, 'name' => 'E2E Buyer', 'email' => $buyerEmail,
    'password' => 'secret123', 'password2' => 'secret123',
], 'buyer');
check('register redirects home logged-in',
    $r['code'] === 200 && str_contains($r['body'], 'E2E Buyer'));
check('header shows user menu after register',
    str_contains($r['body'], 'avatar-btn'));

// --- 2. Login rate limiting (fresh jar) ---
$r = req('GET', '/login.php', [], 'ratelimit');
$t = csrf($r['body']);
for ($i = 1; $i <= 5; $i++) {
    $r = req('POST', '/login.php', [
        'csrf' => $t, 'email' => 'nobody@campus.edu.pk', 'password' => 'wrong',
    ], 'ratelimit');
}
$r = req('POST', '/login.php', [
    'csrf' => $t, 'email' => 'nobody@campus.edu.pk', 'password' => 'wrong',
], 'ratelimit');
check('login rate-limited after 5 failures',
    str_contains($r['body'], 'Too many failed attempts'));

// --- 3. Wrong password, then correct admin login ---
$r = req('GET', '/login.php', [], 'admin');
$t = csrf($r['body']);
$r = req('POST', '/login.php', ['csrf' => $t, 'email' => 'admin@campusmart.local',
    'password' => 'nope'], 'admin');
check('wrong password rejected', str_contains($r['body'], 'Incorrect email or password'));
$r = req('POST', '/login.php', ['csrf' => $t, 'email' => 'admin@campusmart.local',
    'password' => 'admin123'], 'admin');
check('admin login ok', str_contains($r['body'], 'Campus Admin'));

// --- 4. Seller flow: register seller, create listing with image upload ---
$r = req('GET', '/register.php', [], 'seller');
$t = csrf($r['body']);
$sellerEmail = 'e2e_seller_' . time() . '@campus.edu.pk';
$r = req('POST', '/register.php', [
    'csrf' => $t, 'name' => 'E2E Seller', 'email' => $sellerEmail,
    'password' => 'secret123', 'password2' => 'secret123',
], 'seller');
check('seller registered', str_contains($r['body'], 'E2E Seller'));

$r = req('GET', '/create.php', [], 'seller');
$t = csrf($r['body']);
preg_match('/<option value="(\d+)"[^>]*>Books/', $r['body'], $m);
$booksCat = $m[1] ?? '1';
$img = sys_get_temp_dir() . '/cm_upload_test.jpg';
// Reuse a real generated placeholder as the uploaded file.
$sample = glob(__DIR__ . '/../uploads/*/*/*_thumb.jpg')[0] ?? null;
if (!$sample) {
    $sample = glob(__DIR__ . '/../uploads/*/*/*.jpg')[0];
}
$full = str_replace('_thumb.jpg', '.jpg', $sample);
copy($full, $img);
$r = req('POST', '/create.php', [
    'csrf' => $t, 'title' => 'E2E Test Physics Book', 'category_id' => $booksCat,
    'price' => '999', 'condition' => 'good', 'campus' => 'E2E Campus',
    'location' => 'Test Lab', 'description' => 'A book created by the automated E2E test.',
], 'seller', ['images[]' => new CURLFile($img, 'image/jpeg', 'book.jpg')]);
check('listing created + redirected', str_contains($res = $r['body'], 'E2E Test Physics Book')
    && $r['code'] === 200);
preg_match('/listing\.php\?id=(\d+)/', $r['headers'] . $r['body'], $lm);
$newListingId = $lm[1] ?? null;
if (!$newListingId && preg_match('/\/listing\.php\?id=(\d+)/', $r['body'], $lm2)) {
    $newListingId = $lm2[1];
}
check('new listing id found', (bool) $newListingId, "headers+body searched");

// --- 5. Listing page renders with image ---
if ($newListingId) {
    $r = req('GET', '/listing.php?id=' . $newListingId, [], 'seller');
    check('listing page 200 with gallery', $r['code'] === 200
        && str_contains($r['body'], 'gallery-main'));
}

// --- 6. Price suggestion API ---
$r = req('GET', '/api/price_suggest.php', [
    'category_id' => $booksCat, 'title' => 'calculus textbook', 'condition' => 'good',
], 'seller');
$d = json_api($r);
check('price_suggest returns estimate',
    $d && ($d['enough'] ?? false) && isset($d['median']) && isset($d['note']));

// --- 7. Search + filter + sort ---
$r = req('GET', '/', ['q' => 'calculator'], 'seller');
check('search finds calculator', str_contains($r['body'], 'calculator'));
$r = req('GET', '/', ['category' => $booksCat, 'sort' => 'price_asc'], 'seller');
check('category filter works', $r['code'] === 200 && !str_contains($r['body'], 'No items'));

// --- 8. Wishlist toggle API ---
$r = req('POST', '/api/wishlist_toggle.php', [
    'csrf' => csrf(req('GET', '/', [], 'buyer')['body']),
    'listing_id' => $newListingId,
], 'buyer');
$d = json_api($r);
check('wishlist add', $d && $d['ok'] && $d['wishlisted'] === true);
$r = req('GET', '/wishlist.php', [], 'buyer');
check('wishlist page lists item', str_contains($r['body'], 'E2E Test Physics Book'));

// --- 9. Chat: buyer starts conversation with seller ---
$r = req('GET', '/conversation.php?listing=' . $newListingId, [], 'buyer', [], false);
$loc = '';
if (preg_match('/Location:\s*(\S+)/i', $r['headers'], $mh)) {
    $loc = $mh[1];
}
check('conversation.php?listing= redirects to thread', $r['code'] === 302 && str_contains($loc, 'conversation.php?id='));
preg_match('/conversation\.php\?id=(\d+)/', $loc, $cm);
$convId = $cm[1] ?? null;
check('conversation id captured', (bool) $convId);

if ($convId) {
    $r = req('GET', '/conversation.php?id=' . $convId, [], 'buyer');
    $t = csrf($r['body']);
    check('conversation thread loads', $r['code'] === 200 && str_contains($r['body'], 'chat-messages'));

    $r = req('POST', '/api/chat_send.php', [
        'csrf' => $t, 'conversation_id' => $convId, 'body' => 'Hi! E2E test message here.',
    ], 'buyer');
    $d = json_api($r);
    check('chat_send ok', $d && $d['ok'] && $d['message']['body'] === 'Hi! E2E test message here.');

    // Seller polls and sees the message.
    $r = req('GET', '/api/chat_poll.php', ['conversation_id' => $convId, 'after_id' => 0], 'seller');
    $d = json_api($r);
    check('chat_poll delivers message to seller',
        $d && $d['ok'] && count($d['messages']) > 0
        && str_contains($d['messages'][count($d['messages']) - 1]['body'], 'E2E test message'));

    $r = req('POST', '/api/chat_mark_read.php', [
        'csrf' => csrf(req('GET', '/conversation.php?id=' . $convId, [], 'seller')['body']),
        'conversation_id' => $convId,
    ], 'seller');
    check('chat_mark_read ok', json_api($r)['ok'] ?? false);

    $r = req('GET', '/api/unread_count.php', [], 'seller');
    check('unread_count api', (json_api($r)['ok'] ?? false) === true);
}

// --- 10. Seller marks sold; buyer leaves review ---
if ($newListingId && $convId) {
    $r = req('GET', '/listing.php?id=' . $newListingId, [], 'seller');
    $t = csrf($r['body']);
    $r = req('POST', '/listing.php?id=' . $newListingId, [
        'csrf' => $t, 'action' => 'sold',
    ], 'seller');
    check('mark as sold', str_contains($r['body'], 'Marked as sold'));

    $r = req('GET', '/review.php?listing=' . $newListingId, [], 'buyer');
    $t = csrf($r['body']);
    $r = req('POST', '/review.php?listing=' . $newListingId, [
        'csrf' => $t, 'rating' => '5', 'comment' => 'Great E2E seller!',
    ], 'buyer');
    check('review submitted', str_contains($r['body'], 'Thanks! Your review was posted.'));

    // Duplicate review blocked.
    $r = req('GET', '/review.php?listing=' . $newListingId, [], 'buyer');
    $t = csrf($r['body']);
    $r = req('POST', '/review.php?listing=' . $newListingId, [
        'csrf' => $t, 'rating' => '4', 'comment' => 'second',
    ], 'buyer');
    check('duplicate review blocked', str_contains($r['body'], 'already reviewed'));

    // Self-review blocked (seller tries to review own listing).
    $r = req('GET', '/review.php?listing=' . $newListingId, [], 'seller');
    check('self-review blocked', str_contains($r['body'], 'own listing'));
}

// --- 11. Report listing ---
if ($newListingId) {
    $r = req('GET', '/report.php?target=listing&id=' . $newListingId, [], 'buyer');
    $t = csrf($r['body']);
    $r = req('POST', '/report.php?target=listing&id=' . $newListingId, [
        'csrf' => $t, 'reason' => 'Spam or scam', 'details' => 'E2E automated report.',
    ], 'buyer');
    check('report filed', str_contains($r['body'], 'an admin will review your report'));
}

// --- 12. Lost & Found post ---
$r = req('GET', '/lost-found.php', [], 'buyer');
$t = csrf($r['body']);
$r = req('POST', '/lost-found.php', [
    'csrf' => $t, 'type' => 'lost', 'title' => 'E2E lost keys',
    'description' => 'Lost during the automated test.', 'location' => 'E2E Lab',
], 'buyer');
check('lost&found posted', str_contains($r['body'], 'E2E lost keys'));

// --- 13. Notifications ---
$r = req('GET', '/notifications.php', [], 'seller');
check('notifications page loads', $r['code'] === 200);
$r = req('POST', '/api/notifications_read.php', [
    'csrf' => csrf($r['body']),
], 'seller');
check('notifications mark-read', (json_api($r)['ok'] ?? false) === true);

// --- 14. Admin panel ---
$r = req('GET', '/admin.php', [], 'admin');
check('admin dashboard loads', $r['code'] === 200 && str_contains($r['body'], 'Admin'));
$r = req('GET', '/admin.php?tab=reports', [], 'admin');
check('admin reports tab shows test report', str_contains($r['body'], 'E2E automated report'));
$t = csrf($r['body']);
preg_match('/name="id"\s+value="(\d+)"/', $r['body'], $rpm);
$reportId = $rpm[1] ?? null;
if ($reportId) {
    $r = req('POST', '/admin.php?tab=reports', [
        'csrf' => $t, 'action' => 'report_status', 'id' => $reportId, 'status' => 'dismissed',
    ], 'admin');
    check('admin dismisses report', str_contains($r['body'], 'Report marked as dismissed'));
}

// Non-admin blocked from admin.
$r = req('GET', '/admin.php', [], 'buyer');
check('buyer blocked from admin (403)', $r['code'] === 403);

// --- 15. CSRF protection: POST without token should fail ---
$r = req('POST', '/api/wishlist_toggle.php', ['listing_id' => $newListingId], 'buyer');
check('CSRF enforced on API', (json_api($r)['ok'] ?? true) === false);

// --- 16. Forgot/reset password flow ---
$r = req('GET', '/forgot.php', [], 'guest');
$t = csrf($r['body']);
$r = req('POST', '/forgot.php', ['csrf' => $t, 'email' => $buyerEmail], 'guest');
check('forgot posts (no enumeration)', str_contains($r['body'], 'If an account exists for that email'));
// Grab the token straight from the DB (email is off in dev).
$dbPath = __DIR__ . '/../database/campusmart.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$tok = $db->query("SELECT reset_token FROM users WHERE email = " . $db->quote($buyerEmail))
          ->fetchColumn();
check('reset token stored', (bool) $tok);
$r = req('GET', '/reset.php?token=' . $tok, [], 'guest');
$t = csrf($r['body']);
$r = req('POST', '/reset.php?token=' . $tok, [
    'csrf' => $t, 'password' => 'newsecret123', 'password2' => 'newsecret123',
], 'guest');
check('password reset works', str_contains($r['body'], 'Password changed'));
$r = req('GET', '/login.php', [], 'guest2');
$t = csrf($r['body']);
$r = req('POST', '/login.php', [
    'csrf' => $t, 'email' => $buyerEmail, 'password' => 'newsecret123',
], 'guest2');
check('login with new password', str_contains($r['body'], 'E2E Buyer'));

// --- 17. Logout ---
$r = req('GET', '/logout.php', [], 'guest2');
check('logout redirects home', $r['code'] === 200 && !str_contains($r['body'], 'E2E Buyer'));

echo "\n== $pass passed, $fail failed ==\n";
exit($fail > 0 ? 1 : 0);
