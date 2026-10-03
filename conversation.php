<?php
/**
 * One conversation thread.
 *  - ?listing=ID  → buyer starts (or resumes) a chat about that listing
 *  - ?id=ID       → open an existing conversation
 * Messages load via AJAX polling (see assets/js/app.js + api/chat_poll.php)
 * because free PHP hosts cannot run WebSockets.
 */
require __DIR__ . '/app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];

/* ---- Start / resume a conversation about a listing ---- */
if (isset($_GET['listing'])) {
    $listingId = (int) $_GET['listing'];
    $st = $db->prepare('SELECT * FROM listings WHERE id = ? AND status = ?');
    $st->execute([$listingId, 'active']);
    $listing = $st->fetch();
    if (!$listing) {
        flash('error', 'That listing is not available.');
        redirect('index.php');
    }
    if ((int) $listing['user_id'] === $uid) {
        flash('info', 'That is your own listing.');
        redirect('listing.php?id=' . $listingId);
    }
    // One conversation per (listing, buyer) pair — resume if it exists.
    $st = $db->prepare('SELECT id FROM conversations WHERE listing_id = ? AND buyer_id = ?');
    $st->execute([$listingId, $uid]);
    $existing = $st->fetch();
    if ($existing) {
        redirect('conversation.php?id=' . $existing['id']);
    }
    $db->prepare(
        'INSERT INTO conversations (listing_id, buyer_id, seller_id) VALUES (?, ?, ?)'
    )->execute([$listingId, $uid, (int) $listing['user_id']]);
    redirect('conversation.php?id=' . (int) $db->lastInsertId());
}

/* ---- Open an existing conversation ---- */
$id = (int) ($_GET['id'] ?? 0);
$st = $db->prepare(
    'SELECT c.*, l.title AS listing_title, l.price AS listing_price, l.status AS listing_status,
            u_seller.name AS seller_name, u_buyer.name AS buyer_name
     FROM conversations c
     JOIN listings l ON l.id = c.listing_id
     JOIN users u_seller ON u_seller.id = c.seller_id
     JOIN users u_buyer ON u_buyer.id = c.buyer_id
     WHERE c.id = ? AND (c.buyer_id = ? OR c.seller_id = ?)'
);
$st->execute([$id, $uid, $uid]);
$convo = $st->fetch();
if (!$convo) {
    http_response_code(404);
    exit('Conversation not found.');
}

$otherName = ((int) $convo['buyer_id'] === $uid) ? $convo['seller_name'] : $convo['buyer_name'];

/* First page load renders recent history; newer messages arrive via polling. */
$msgSt = $db->prepare(
    'SELECT * FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 50'
);
$msgSt->execute([$id]);
$history = array_reverse($msgSt->fetchAll());

/* Mark the other party's messages as read on open. */
$db->prepare(
    'UPDATE messages SET is_read = 1
     WHERE conversation_id = ? AND sender_id != ? AND is_read = 0'
)->execute([$id, $uid]);

$page_title = 'Chat with ' . $otherName;
include __DIR__ . '/views/partials/header.php';
?>

<div class="chat-thread" id="chat-thread" data-conversation="<?= $id ?>">
    <div class="chat-thread-head">
        <a class="icon-btn" href="<?= e(u('chat.php')) ?>" aria-label="Back to chats">←</a>
        <div>
            <strong><?= e($otherName) ?></strong>
            <div class="muted tiny">
                <a href="<?= e(u('listing.php?id=' . $convo['listing_id'])) ?>"><?= e($convo['listing_title']) ?></a>
                · <?= e(money($convo['listing_price'])) ?>
                <?php if ($convo['listing_status'] === 'sold'): ?><span class="badge badge-sold">Sold</span><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="chat-messages" id="chat-messages" aria-live="polite">
        <?php foreach ($history as $m): ?>
            <div class="msg<?= (int) $m['sender_id'] === $uid ? ' me' : ' they' ?>" data-msg-id="<?= (int) $m['id'] ?>">
                <div class="msg-bubble"><?= nl2br(e($m['body'])) ?></div>
                <div class="msg-time muted tiny"><?= e(time_ago($m['created_at'])) ?></div>
            </div>
        <?php endforeach; ?>
        <div id="chat-loading" class="muted tiny center" hidden>Loading…</div>
    </div>

    <form class="chat-input-bar" id="chat-form" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="conversation_id" value="<?= $id ?>">
        <input class="input" id="chat-input" name="body" maxlength="2000"
               placeholder="Write a message…" aria-label="Write a message" required>
        <button class="btn btn-primary" type="submit" aria-label="Send">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>
        </button>
    </form>
</div>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
