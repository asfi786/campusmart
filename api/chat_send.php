<?php
/** API: send a chat message. */
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
$body = trim($_POST['body'] ?? '');
if ($body === '' || mb_strlen($body) > 2000) {
    echo json_encode(['ok' => false, 'error' => 'Message is empty or too long.']);
    exit;
}

$db = db();
$st = $db->prepare(
    'SELECT c.*, l.title AS listing_title FROM conversations c
     JOIN listings l ON l.id = c.listing_id
     WHERE c.id = ? AND (c.buyer_id = ? OR c.seller_id = ?)'
);
$st->execute([$convId, $uid, $uid]);
$convo = $st->fetch();
if (!$convo) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$db->prepare('INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)')
   ->execute([$convId, $uid, $body]);
$msgId = (int) $db->lastInsertId();
$db->prepare('UPDATE conversations SET last_message_at = ? WHERE id = ?')
   ->execute([date('Y-m-d H:i:s'), $convId]);

/* Notify the other party. */
$otherId = ((int) $convo['buyer_id'] === $uid) ? (int) $convo['seller_id'] : (int) $convo['buyer_id'];
notify($otherId, 'message', 'New message from ' . $user['name'],
    mb_strimwidth($body, 0, 80, '…'), 'conversation.php?id=' . $convId);

echo json_encode([
    'ok' => true,
    'message' => [
        'id'       => $msgId,
        'mine'     => true,
        'body'     => $body,
        'time_ago' => 'just now',
    ],
]);
