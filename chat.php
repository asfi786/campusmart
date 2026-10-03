<?php
/**
 * Chat inbox: all conversations for the current user, with unread counts.
 */
require __DIR__ . '/app/bootstrap.php';

$user = require_login();
$page_title = 'My chats';
$uid = (int) $user['id'];

$st = $db->prepare(
    "SELECT c.*, l.title AS listing_title, l.price AS listing_price,
            (SELECT filename FROM listing_images WHERE listing_id = l.id
              ORDER BY sort_order, id LIMIT 1) AS thumb,
            CASE WHEN c.buyer_id = ? THEN u_seller.name ELSE u_buyer.name END AS other_name,
            (SELECT body FROM messages WHERE conversation_id = c.id
              ORDER BY id DESC LIMIT 1) AS last_body,
            (SELECT created_at FROM messages WHERE conversation_id = c.id
              ORDER BY id DESC LIMIT 1) AS last_at,
            (SELECT COUNT(*) FROM messages
              WHERE conversation_id = c.id AND is_read = 0 AND sender_id != ?) AS unread
     FROM conversations c
     JOIN listings l ON l.id = c.listing_id
     JOIN users u_seller ON u_seller.id = c.seller_id
     JOIN users u_buyer ON u_buyer.id = c.buyer_id
     WHERE c.buyer_id = ? OR c.seller_id = ?
     ORDER BY COALESCE(c.last_message_at, c.created_at) DESC"
);
$st->execute([$uid, $uid, $uid, $uid]);
$convos = $st->fetchAll();

include __DIR__ . '/views/partials/header.php';
?>

<h1>My chats</h1>

<?php if ($convos): ?>
    <div class="chat-list">
        <?php foreach ($convos as $c): ?>
            <a class="chat-item<?= (int) $c['unread'] > 0 ? ' has-unread' : '' ?>"
               href="<?= e(u('conversation.php?id=' . $c['id'])) ?>">
                <?php if ($c['thumb']): ?>
                    <img class="chat-thumb" src="<?= e(uploads_url(thumb_of($c['thumb']))) ?>" alt="">
                <?php else: ?>
                    <span class="chat-thumb chat-thumb-fallback"><?= e(mb_substr($c['listing_title'], 0, 1)) ?></span>
                <?php endif; ?>
                <div class="chat-item-body">
                    <div class="chat-item-top">
                        <strong><?= e($c['other_name']) ?></strong>
                        <?php if ($c['last_at']): ?>
                            <span class="muted tiny"><?= e(time_ago($c['last_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="muted tiny"><?= e($c['listing_title']) ?> · <?= e(money($c['listing_price'])) ?></div>
                    <div class="chat-preview<?= (int) $c['unread'] > 0 ? ' unread' : '' ?>">
                        <?= e(mb_strimwidth($c['last_body'] ?? 'Say hello 👋', 0, 70, '…')) ?>
                    </div>
                </div>
                <?php if ((int) $c['unread'] > 0): ?>
                    <span class="pill"><?= (int) $c['unread'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-art">💬</div>
        <h3>No chats yet</h3>
        <p class="muted">Found something you like? Tap “Chat with seller” on any listing.</p>
        <a class="btn btn-primary" href="<?= e(u('index.php')) ?>">Browse listings</a>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
