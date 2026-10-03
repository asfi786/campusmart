<?php
/**
 * Listing form partial — used by create.php and edit.php.
 * Expects: $listing (array|null), $errors (array), $categories (array),
 *          $is_edit (bool).
 */
$val = function (string $k, string $d = '') use ($listing): string {
    if (isset($_POST[$k])) {
        return (string) $_POST[$k];
    }
    return (string) ($listing[$k] ?? $d);
};
$err = fn(string $k) => $errors[$k] ?? '';
?>
<?php if ($err('images')): ?><p class="form-error"><?= e($err('images')) ?></p><?php endif; ?>

<div class="field">
    <label class="label" for="f-title">Title *</label>
    <input class="input" id="f-title" name="title" maxlength="150" required
           value="<?= e($val('title')) ?>" placeholder="e.g. HP Pavilion 15, i5 11th gen, 8GB RAM">
    <?php if ($err('title')): ?><p class="form-error"><?= e($err('title')) ?></p><?php endif; ?>
</div>

<div class="form-row">
    <div class="field">
        <label class="label" for="f-category">Category *</label>
        <select class="select" id="f-category" name="category_id" required>
            <option value="">Choose…</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (string) $val('category_id') === (string) $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($err('category_id')): ?><p class="form-error"><?= e($err('category_id')) ?></p><?php endif; ?>
    </div>
    <div class="field">
        <label class="label" for="f-condition">Condition *</label>
        <select class="select" id="f-condition" name="condition" required>
            <?php foreach (['new' => 'New (unused)', 'like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= $val('condition', 'good') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="field">
    <label class="label" for="f-price">Price (<?= e(config('currency', 'Rs')) ?>) *</label>
    <input class="input" id="f-price" name="price" type="number" min="1" step="1" required
           value="<?= e($val('price')) ?>" placeholder="e.g. 45000">
    <?php if ($err('price')): ?><p class="form-error"><?= e($err('price')) ?></p><?php endif; ?>

    <!-- Smart Price Suggestion: filled by assets/js/app.js via api/price_suggest.php -->
    <div class="price-suggest" id="price-suggest" hidden aria-live="polite">
        <div class="ps-head">
            <span class="ps-spark">✦</span>
            <strong>Smart price suggestion</strong>
            <span class="ps-conf" id="ps-conf"></span>
        </div>
        <div class="ps-range" id="ps-range"></div>
        <p class="ps-note muted tiny" id="ps-note"></p>
        <button type="button" class="btn btn-ghost btn-sm" id="ps-apply" hidden>Use median price</button>
    </div>
</div>

<div class="form-row">
    <div class="field">
        <label class="label" for="f-campus">Campus</label>
        <input class="input" id="f-campus" name="campus" maxlength="100" list="campus-list"
               value="<?= e($val('campus')) ?>" placeholder="e.g. Main Campus">
    </div>
    <div class="field">
        <label class="label" for="f-location">Pickup location</label>
        <input class="input" id="f-location" name="location" maxlength="150"
               value="<?= e($val('location')) ?>" placeholder="e.g. Hostel Block C, Room 12">
    </div>
</div>

<div class="field">
    <label class="label" for="f-desc">Description *</label>
    <textarea class="textarea" id="f-desc" name="description" rows="5" required
              placeholder="Condition details, what's included, reason for selling…"><?= e($val('description')) ?></textarea>
    <?php if ($err('description')): ?><p class="form-error"><?= e($err('description')) ?></p><?php endif; ?>
</div>

<div class="field">
    <label class="label" for="f-images">Photos (up to <?= (int) config('max_images', 5) ?>)</label>
    <input class="input" id="f-images" type="file" name="images[]" accept="image/*" multiple data-preview>
    <p class="hint">JPG, PNG, WebP or GIF · max <?= e(config('upload_max_mb', 5)) ?> MB each · first photo is the cover.</p>
    <div class="img-preview" id="img-preview" aria-hidden="true"></div>
    <?php if ($is_edit && !empty($listing['images'])): ?>
        <div class="existing-images">
            <?php foreach ($listing['images'] as $im): ?>
                <label class="existing-img">
                    <img src="<?= e(uploads_url(thumb_of($im['filename']))) ?>" alt="">
                    <span><input type="checkbox" name="delete_images[]" value="<?= (int) $im['id'] ?>"> remove</span>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
