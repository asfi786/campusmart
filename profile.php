<?php
/** Public seller profile with rating summary and reviews. */
require __DIR__ . '/app/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $db->prepare('SELECT id, name, created_at, role FROM users WHERE id = ? AND is_banned = 0');
$st->execute([$id]);
$profile = $st->fetch();
if (!$profile) {
    http_response_code(404);
    exit('User not found.');
}

$stats = $db->prepare(
    'SELECT COUNT(*) AS c, AVG(rating) AS avg FROM reviews WHERE seller_id = ?'
);
$stats->execute([$id]);
$stats = $stats->fetch();

$dist = $db->prepare(
    'SELECT rating, COUNT(*) AS c FROM reviews WHERE seller_id = ? GROUP BY rating ORDER BY rating DESC'
);
$dist->execute([$id]);
$dist = $dist->fetchAll();

$revSt = $db->prepare(
    'SELECT r.*, u.name AS reviewer_name, l.title AS listing_title FROM reviews r
     JOIN users u ON u.id = r.reviewer_id
     JOIN listings l ON l.id = r.listing_id
     WHERE r.seller_id = ? ORDER BY r.created_at DESC LIMIT 20'
);
$revSt->execute([$id]);
$reviews = $revSt->fetchAll();

$active = $db->prepare(
    "SELECT l.*, c.name AS category_name, c.color AS category_color,
            (SELECT filename FROM listing_images WHERE listing_id = l.id
              ORDER BY sort_order, id LIMIT 1) AS thumb
     FROM listings l JOIN categories c ON c.id = l.category_id
     WHERE l.user_id = ? AND l.status = 'active' ORDER BY l.created_at DESC LIMIT 8"
);
$active->execute([$id]);
$activeListings = $active->fetchAll();
foreach ($activeListings as &$l) {
    $l['wishlisted'] = false;
}
unset($l);

$page_title = $profile['name'];
include __DIR__ . '/views/partials/header.php';
?>

<div class="profile-head">
    <span class="avatar avatar-lg"><?= e(initials($profile['name'])) ?></span>
    <div>
        <h1><?= e($profile['name']) ?></h1>
        <p class="muted">Member since <?= e(date('M Y', strtotime($profile['created_at']))) ?>
            <?php if ($profile['role'] === 'admin'): ?><span class="badge">Admin</span><?php endif; ?></p>
        <?php if ((int) $stats['c'] > 0): ?>
            <p><span class="stars big"><?= str_repeat('★', (int) round($stats['avg'])) ?></span>
                <strong><?= number_format((float) $stats['avg'], 1) ?></strong>
                <span class="muted">· <?= (int) $stats['c'] ?> review<?= (int) $stats['c'] === 1 ? '' : 's' ?></span></p>
        <?php else: ?>
            <p class="muted">No reviews yet.</p>
        <?php endif; ?>
    </div>
    <?php if (current_user() && (int) current_user()['id'] !== $id): ?>
        <a class="report-link" href="<?= e(u('report.php?target=user&id=' . $id)) ?>">Report user</a>
    <?php endif; ?>
</div>

<?php if ($dist): ?>
    <h2 class="h3">Rating breakdown</h2>
    <div class="bar-chart">
        <?php foreach ($dist as $d): ?>
            <div class="bar-row">
                <span><?= (int) $d['rating'] ?>★</span>
                <div class="bar-track"><div class="bar-fill" style="width: <?= (int) round(100 * $d['c'] / max(1, $stats['c'])) ?>%"></div></div>
                <span class="muted"><?= (int) $d['c'] ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($reviews): ?>
    <h2 class="h3">Reviews</h2>
    <div class="review-list">
        <?php foreach ($reviews as $r): ?>
            <div class="review">
                <div class="review-head">
                    <span class="avatar avatar-sm"><?= e(initials($r['reviewer_name'])) ?></span>
                    <strong><?= e($r['reviewer_name']) ?></strong>
                    <span class="stars"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></span>
                    <span class="muted tiny">on “<?= e(mb_strimwidth($r['listing_title'], 0, 40, '…')) ?>”</span>
                </div>
                <?php if ($r['comment']): ?><p><?= e($r['comment']) ?></p><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($activeListings): ?>
    <h2 class="h3">Active listings</h2>
    <div class="grid-cards">
        <?php foreach ($activeListings as $l): ?>
            <?php include __DIR__ . '/views/partials/card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
