<?php
/**
 * Listing detail: gallery, price, seller card, chat CTA, wishlist,
 * mark-as-sold / edit / delete for the owner, report link, reviews.
 */
require __DIR__ . '/app/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$user = current_user();

$st = $db->prepare(
    'SELECT l.*, c.name AS category_name, c.color AS category_color,
            u.name AS seller_name, u.email AS seller_email, u.created_at AS seller_since,
            (SELECT COUNT(*) FROM reviews WHERE seller_id = u.id) AS review_count,
            (SELECT AVG(rating) FROM reviews WHERE seller_id = u.id) AS avg_rating
     FROM listings l
     JOIN categories c ON c.id = l.category_id
     JOIN users u ON u.id = l.user_id
     WHERE l.id = ?'
);
$st->execute([$id]);
$l = $st->fetch();
if (!$l || $l['status'] === 'deleted') {
    http_response_code(404);
    $page_title = 'Not found';
    include __DIR__ . '/views/partials/header.php';
    echo '<div class="empty-state"><div class="empty-art">?</div><h3>Listing not found</h3>'
       . '<p class="muted">It may have been removed.</p><a class="btn btn-primary" href="' . e(u('index.php')) . '">Back to browse</a></div>';
    include __DIR__ . '/views/partials/footer.php';
    exit;
}

/* ---- Owner actions (POST) ---- */
$isOwner = $user && (int) $user['id'] === (int) $l['user_id'];
if (is_post() && $isOwner) {
    validate_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'sold') {
        $db->prepare('UPDATE listings SET status = ? WHERE id = ?')->execute(['sold', $id]);
        flash('success', 'Marked as sold. Nice!');
        redirect('listing.php?id=' . $id);
    }
    if ($action === 'relist') {
        $db->prepare('UPDATE listings SET status = ? WHERE id = ?')->execute(['active', $id]);
        flash('success', 'Listing is active again.');
        redirect('listing.php?id=' . $id);
    }
    if ($action === 'delete') {
        $db->prepare('UPDATE listings SET status = ? WHERE id = ?')->execute(['deleted', $id]);
        flash('info', 'Listing deleted.');
        redirect('my-listings.php');
    }
}

/* Count the view (skip the owner's own views). */
if (!$isOwner) {
    $db->prepare('UPDATE listings SET views = views + 1 WHERE id = ?')->execute([$id]);
    $l['views']++;
}

$images = $db->prepare('SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id');
$images->execute([$id]);
$images = $images->fetchAll();

$wishlisted = false;
if ($user) {
    $st = $db->prepare('SELECT id FROM wishlists WHERE user_id = ? AND listing_id = ?');
    $st->execute([(int) $user['id'], $id]);
    $wishlisted = (bool) $st->fetch();
}

/* Can this viewer leave a review? (buyer, listing sold, not reviewed yet) */
$canReview = false;
if ($user && !$isOwner && $l['status'] === 'sold') {
    $st = $db->prepare('SELECT id FROM reviews WHERE listing_id = ? AND reviewer_id = ?');
    $st->execute([$id, (int) $user['id']]);
    $canReview = !$st->fetch();
}

/* Related listings in the same category. */
$rel = $db->prepare(
    "SELECT l.*, c.name AS category_name, c.color AS category_color,
            (SELECT filename FROM listing_images WHERE listing_id = l.id ORDER BY sort_order, id LIMIT 1) AS thumb
     FROM listings l JOIN categories c ON c.id = l.category_id
     WHERE l.status = 'active' AND l.category_id = ? AND l.id != ?
     ORDER BY l.created_at DESC LIMIT 4"
);
$rel->execute([$l['category_id'], $id]);
$related = $rel->fetchAll();

/* Latest reviews for this seller. */
$revSt = $db->prepare(
    'SELECT r.*, u.name AS reviewer_name FROM reviews r
     JOIN users u ON u.id = r.reviewer_id
     WHERE r.seller_id = ? ORDER BY r.created_at DESC LIMIT 5'
);
$revSt->execute([$l['user_id']]);
$sellerReviews = $revSt->fetchAll();

$page_title = $l['title'];
include __DIR__ . '/views/partials/header.php';
?>

<nav class="crumbs" aria-label="Breadcrumb">
    <a href="<?= e(u('index.php')) ?>">Browse</a> /
    <a href="<?= e(u('index.php') . '?' . http_build_query(['category' => $l['category_id']])) ?>"><?= e($l['category_name']) ?></a> /
    <span><?= e(mb_strimwidth($l['title'], 0, 40, '…')) ?></span>
</nav>

<div class="detail-layout">
    <div class="detail-main">
        <div class="gallery">
            <?php if ($images): ?>
                <img class="gallery-main" id="gallery-main"
                     src="<?= e(uploads_url($images[0]['filename'])) ?>"
                     alt="<?= e($l['title']) ?>">
                <?php if (count($images) > 1): ?>
                    <div class="gallery-thumbs" role="list">
                        <?php foreach ($images as $i => $im): ?>
                            <button role="listitem" class="thumb<?= $i === 0 ? ' active' : '' ?>"
                                    data-full="<?= e(uploads_url($im['filename'])) ?>"
                                    aria-label="View image <?= $i + 1 ?>">
                                <img src="<?= e(uploads_url(thumb_of($im['filename']))) ?>" alt="" loading="lazy">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="gallery-empty" style="--cat: <?= e($l['category_color']) ?>">
                    <?= e(mb_substr($l['title'], 0, 1)) ?>
                </div>
            <?php endif; ?>
        </div>

        <h1 class="detail-title"><?= e($l['title']) ?></h1>
        <div class="detail-meta">
            <span class="badge badge-cond-<?= e($l['condition']) ?>"><?= e(cond_label($l['condition'])) ?></span>
            <span class="badge"><?= e($l['category_name']) ?></span>
            <?php if ($l['status'] === 'sold'): ?><span class="badge badge-sold">Sold</span><?php endif; ?>
            <span class="muted"><?= e($l['views']) ?> views · <?= e(time_ago($l['created_at'])) ?></span>
        </div>

        <div class="detail-price"><?= e(money($l['price'])) ?></div>

        <?php if ($l['campus'] || $l['location']): ?>
            <p class="detail-loc">📍 <?= e(trim($l['campus'] . ($l['campus'] && $l['location'] ? ' · ' : '') . $l['location'])) ?></p>
        <?php endif; ?>

        <h2 class="h3">Description</h2>
        <p class="detail-desc"><?= nl2br(e($l['description'])) ?></p>

        <?php if ($sellerReviews): ?>
            <h2 class="h3">Seller reviews</h2>
            <div class="review-list">
                <?php foreach ($sellerReviews as $r): ?>
                    <div class="review">
                        <div class="review-head">
                            <span class="avatar avatar-sm"><?= e(initials($r['reviewer_name'])) ?></span>
                            <strong><?= e($r['reviewer_name']) ?></strong>
                            <span class="stars" aria-label="<?= (int) $r['rating'] ?> out of 5 stars"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></span>
                            <span class="muted tiny"><?= e(time_ago($r['created_at'])) ?></span>
                        </div>
                        <?php if ($r['comment']): ?><p><?= e($r['comment']) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <aside class="detail-side">
        <div class="seller-card">
            <div class="seller-row">
                <span class="avatar"><?= e(initials($l['seller_name'])) ?></span>
                <div>
                    <strong><?= e($l['seller_name']) ?></strong>
                    <div class="muted tiny">
                        <?php if ($l['review_count'] > 0): ?>
                            <span class="stars"><?= str_repeat('★', (int) round($l['avg_rating'])) ?></span>
                            <?= number_format((float) $l['avg_rating'], 1) ?> (<?= (int) $l['review_count'] ?>)
                        <?php else: ?>
                            New seller
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <a class="btn btn-ghost btn-block btn-sm" href="<?= e(u('profile.php?id=' . $l['user_id'])) ?>">View profile</a>

            <?php if ($isOwner): ?>
                <div class="owner-actions">
                    <?php if ($l['status'] === 'active'): ?>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="sold">
                            <button class="btn btn-primary btn-block" type="submit">Mark as sold</button>
                        </form>
                    <?php elseif ($l['status'] === 'sold'): ?>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="relist">
                            <button class="btn btn-ghost btn-block" type="submit">Relist</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-ghost btn-block" href="<?= e(u('edit.php?id=' . $id)) ?>">Edit listing</a>
                    <form method="post" onsubmit="return confirm('Delete this listing?')"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-danger btn-block" type="submit">Delete</button>
                    </form>
                </div>
            <?php elseif ($user && $l['status'] === 'active'): ?>
                <a class="btn btn-primary btn-block" href="<?= e(u('conversation.php?listing=' . $id)) ?>">Chat with seller</a>
                <button class="btn btn-ghost btn-block heart-toggle<?= $wishlisted ? ' active' : '' ?>"
                        data-wishlist="<?= $id ?>" aria-pressed="<?= $wishlisted ? 'true' : 'false' ?>">
                    <?= $wishlisted ? '♥ Saved to wishlist' : '♡ Save to wishlist' ?>
                </button>
                <?php if ($canReview): ?>
                    <a class="btn btn-ghost btn-block" href="<?= e(u('review.php?listing=' . $id)) ?>">Rate this seller</a>
                <?php endif; ?>
                <a class="report-link" href="<?= e(u('report.php?target=listing&id=' . $id)) ?>">Report this listing</a>
            <?php elseif (!$user && $l['status'] === 'active'): ?>
                <a class="btn btn-primary btn-block" href="<?= e(u('login.php')) ?>">Log in to chat</a>
            <?php endif; ?>
        </div>

        <div class="safety-card">
            <strong>Stay safe</strong>
            <p class="muted tiny">Meet in a public campus spot, check the item before paying, and never share bank details in chat.</p>
        </div>
    </aside>
</div>

<?php if ($related): ?>
    <section>
        <h2 class="h3">You may also like</h2>
        <div class="grid-cards">
            <?php foreach ($related as $l): ?>
                <?php $l['wishlisted'] = false; include __DIR__ . '/views/partials/card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
