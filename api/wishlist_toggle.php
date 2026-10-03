<?php
/** API: toggle wishlist heart (no page reload). */
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');
$user = current_user();
if (!$user || !is_post()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in.']);
    exit;
}
validate_csrf(true);

$uid = (int) $user['id'];
$listingId = (int) ($_POST['listing_id'] ?? 0);

$st = db()->prepare('SELECT id FROM listings WHERE id = ? AND status != ?');
$st->execute([$listingId, 'deleted']);
if (!$st->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Listing not found.']);
    exit;
}

$db = db();
$st = $db->prepare('SELECT id FROM wishlists WHERE user_id = ? AND listing_id = ?');
$st->execute([$uid, $listingId]);
if ($row = $st->fetch()) {
    $db->prepare('DELETE FROM wishlists WHERE id = ?')->execute([$row['id']]);
    $wishlisted = false;
} else {
    $db->prepare('INSERT INTO wishlists (user_id, listing_id) VALUES (?, ?)')
       ->execute([$uid, $listingId]);
    $wishlisted = true;
}

$st = $db->prepare('SELECT COUNT(*) FROM wishlists WHERE listing_id = ?');
$st->execute([$listingId]);

echo json_encode([
    'ok' => true,
    'wishlisted' => $wishlisted,
    'count' => (int) $st->fetchColumn(),
]);
