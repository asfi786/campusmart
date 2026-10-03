<?php
/** API: mark a conversation's incoming messages as read. */
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');
$user = current_user();
if (!$user || !is_post()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
validate_csrf(true);

$uid = (int) $user['id'];
$convId = (int) ($_POST['conversation_id'] ?? 0);

$st = db()->prepare(
    'SELECT id FROM conversations WHERE id = ? AND (buyer_id = ? OR seller_id = ?)'
);
$st->execute([$convId, $uid, $uid]);
if (!$st->fetch()) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

db()->prepare(
    'UPDATE messages SET is_read = 1
     WHERE conversation_id = ? AND sender_id != ? AND is_read = 0'
)->execute([$convId, $uid]);

echo json_encode(['ok' => true] + unread_counts($uid));
