<?php
/** Wishlist page: items the user hearted. */
require __DIR__ . '/app/bootstrap.php';

$user = require_login();
$page_title = 'Wishlist';
$uid = (int) $user['id'];

$st = $db->prepare(
    "SELECT l.*, c.name AS category_name, c.color AS category_color,
            (SELECT filename FROM listing_images WHERE listing_id = l.id
              ORDER BY sort_order, id LIMIT 1) AS thumb
     FROM wishlists w
     JOIN listings l ON l.id = w.listing_id
     JOIN categories c ON c.id = l.category_id
     WHERE w.user_id = ? AND l.status = 'active'
     ORDER BY w.created_at DESC"
);
$st->execute([$uid]);
$listings = $st->fetchAll();
foreach ($listings as &$l) {
    $l['wishlisted'] = true;
}
unset($l);

include __DIR__ . '/views/partials/header.php';
?>

<h1>Wishlist</h1>

<?php if ($listings): ?>
    <div class="grid-cards">
        <?php foreach ($listings as $l): ?>
            <?php include __DIR__ . '/views/partials/card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-art">♡</div>
        <h3>Nothing saved yet</h3>
        <p class="muted">Tap the heart on any listing to keep it here.</p>
        <a class="btn btn-primary" href="<?= e(u('index.php')) ?>">Browse listings</a>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
