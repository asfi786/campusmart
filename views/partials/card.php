<?php
/**
 * Listing card partial. Expects $l with keys:
 * id, title, price, `condition`, campus, created_at, thumb (rel path or ''),
 * category_name, category_color, wishlisted (bool).
 */
$thumb = !empty($l['thumb']) ? uploads_url(thumb_of($l['thumb'])) : '';
?>
<article class="card">
    <a class="card-img" href="<?= e(u('listing.php?id=' . $l['id'])) ?>" aria-label="<?= e($l['title']) ?>">
        <?php if ($thumb): ?>
            <img src="<?= e($thumb) ?>" alt="<?= e($l['title']) ?>" loading="lazy">
        <?php else: ?>
            <span class="card-img-fallback" style="--cat: <?= e($l['category_color'] ?? '#6C4CF1') ?>">
                <?= e(mb_substr($l['title'], 0, 1)) ?>
            </span>
        <?php endif; ?>
        <span class="badge badge-cond-<?= e($l['condition']) ?>"><?= e(cond_label($l['condition'])) ?></span>
        <?php if (($l['status'] ?? 'active') === 'sold'): ?>
            <span class="sold-ribbon">Sold</span>
        <?php endif; ?>
    </a>
    <?php if (current_user()): ?>
        <button class="heart-btn<?= !empty($l['wishlisted']) ? ' active' : '' ?>"
                data-wishlist="<?= (int) $l['id'] ?>"
                aria-pressed="<?= !empty($l['wishlisted']) ? 'true' : 'false' ?>"
                aria-label="Save to wishlist" title="Save to wishlist">
            <svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21.2l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
        </button>
    <?php endif; ?>
    <div class="card-body">
        <div class="card-price"><?= e(money($l['price'])) ?></div>
        <a class="card-title" href="<?= e(u('listing.php?id=' . $l['id'])) ?>"><?= e($l['title']) ?></a>
        <div class="card-meta">
            <?php if (!empty($l['campus'])): ?><span><?= e($l['campus']) ?></span> · <?php endif; ?>
            <span><?= e(time_ago($l['created_at'])) ?></span>
        </div>
    </div>
</article>
