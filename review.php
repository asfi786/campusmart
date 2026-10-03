<?php
/**
 * Leave a rating + review for a seller after a sale.
 * Rules: listing must be sold, reviewer != seller, one review per listing.
 */
require __DIR__ . '/app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$listingId = (int) ($_GET['listing'] ?? 0);

$st = $db->prepare(
    'SELECT l.*, u.name AS seller_name FROM listings l
     JOIN users u ON u.id = l.user_id WHERE l.id = ?'
);
$st->execute([$listingId]);
$listing = $st->fetch();
if (!$listing) {
    http_response_code(404);
    exit('Listing not found.');
}

$error = '';
if ((int) $listing['user_id'] === $uid) {
    $error = 'You cannot review your own listing.';
} elseif ($listing['status'] !== 'sold') {
    $error = 'You can review the seller once the item is marked as sold.';
} else {
    $st = $db->prepare('SELECT id FROM reviews WHERE listing_id = ? AND reviewer_id = ?');
    $st->execute([$listingId, $uid]);
    if ($st->fetch()) {
        $error = 'You have already reviewed this sale.';
    }
}

if (is_post() && !$error) {
    validate_csrf();
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
        $error = 'Please choose a star rating.';
    } else {
        $db->prepare(
            'INSERT INTO reviews (listing_id, reviewer_id, seller_id, rating, comment)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$listingId, $uid, (int) $listing['user_id'], $rating, $comment ?: null]);
        notify((int) $listing['user_id'], 'review',
            $user['name'] . ' rated you ' . $rating . '★',
            'For: ' . $listing['title'], 'profile.php?id=' . $listing['user_id']);
        flash('success', 'Thanks! Your review was posted.');
        redirect('listing.php?id=' . $listingId);
    }
}

$page_title = 'Rate seller';
include __DIR__ . '/views/partials/header.php';
?>

<div class="form-card narrow">
    <h1>Rate <?= e($listing['seller_name']) ?></h1>
    <p class="muted">For “<?= e($listing['title']) ?>”. Be fair — this helps other students trust them.</p>
    <?php if ($error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
        <a class="btn btn-ghost" href="<?= e(u('listing.php?id=' . $listingId)) ?>">Back to listing</a>
    <?php else: ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <span class="label">Your rating *</span>
                <div class="star-input" role="radiogroup" aria-label="Star rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
                        <label for="star<?= $i ?>" title="<?= $i ?> stars">★</label>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="field">
                <label class="label" for="comment">Comment (optional)</label>
                <textarea class="textarea" id="comment" name="comment" rows="3"
                          placeholder="Was the item as described? Smooth handover?"><?= old('comment') ?></textarea>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Post review</button>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
