<?php
/** API: unread badge counts (chat messages + notifications). */
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');
$user = current_user();
if (!$user) {
    echo json_encode(['ok' => false]);
    exit;
}
echo json_encode(['ok' => true] + unread_counts((int) $user['id']));
