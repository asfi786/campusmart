<?php
/** The current user's own listings. */
require __DIR__ . '/app/bootstrap.php';

$user = require_login();
$page_title = 'My listings';
$uid = (int) $user['id'];

$st = $db->prepare(
    "SELECT l.*, c.name AS category_name, c.color AS category_color,
            (SELECT filename FROM listing_images WHERE listing_id = l.id
              ORDER BY sort_order, id LIMIT 1) AS thumb,
            (SELECT COUNT(*) FROM wishlists WHERE listing_id = l.id) AS saves
     FROM listings l JOIN categories c ON c.id = l.category_id
     WHERE l.user_id = ? AND l.status != 'deleted'
     ORDER BY l.created_at DESC"
);
$st->execute([$uid]);
$listings = $st->fetchAll();
foreach ($listings as &$l) {
    $l['wishlisted'] = false;
}
unset($l);

include __DIR__ . '/views/partials/header.php';
?>

<div class="section-head">
    <h1>My listings</h1>
    <a class="btn btn-primary" href="<?= e(u('create.php')) ?>">+ Sell an item</a>
</div>

<?php if ($listings): ?>
    <div class="grid-cards">
        <?php foreach ($listings as $l): ?>
            <?php include __DIR__ . '/views/partials/card.php'; ?>
        <?php endforeach; ?>
    </div>
    <p class="muted tiny">♥ = how many students saved each listing.</p>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-art">📦</div>
        <h3>You haven't listed anything yet</h3>
        <p class="muted">Turn your unused stuff into cash.</p>
        <a class="btn btn-primary" href="<?= e(u('create.php')) ?>">+ Sell your first item</a>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
