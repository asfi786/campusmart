<?php
/**
 * API: fetch messages newer than ?after_id for a conversation.
 * Polled every few seconds by assets/js/app.js (AJAX polling —
 * WebSockets are not available on free PHP hosting).
 */
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');
$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
$uid = (int) $user['id'];
$convId = (int) ($_GET['conversation_id'] ?? 0);
$afterId = (int) ($_GET['after_id'] ?? 0);

/* Must be a participant. */
$st = db()->prepare(
    'SELECT id FROM conversations WHERE id = ? AND (buyer_id = ? OR seller_id = ?)'
);
$st->execute([$convId, $uid, $uid]);
if (!$st->fetch()) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$st = db()->prepare(
    'SELECT id, sender_id, body, created_at FROM messages
     WHERE conversation_id = ? AND id > ? ORDER BY id ASC LIMIT 100'
);
$st->execute([$convId, $afterId]);
$messages = [];
foreach ($st->fetchAll() as $m) {
    $messages[] = [
        'id'        => (int) $m['id'],
        'mine'      => (int) $m['sender_id'] === $uid,
        'body'      => $m['body'],
        'created'   => $m['created_at'],
        'time_ago'  => time_ago($m['created_at']),
    ];
}

echo json_encode(['ok' => true, 'messages' => $messages]);
