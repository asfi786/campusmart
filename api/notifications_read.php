<?php
/** API: mark notifications as read (one id, or all=1). */
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
$db = db();
if (!empty($_POST['all'])) {
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
} else {
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
       ->execute([(int) ($_POST['id'] ?? 0), $uid]);
}
echo json_encode(['ok' => true] + unread_counts($uid));
