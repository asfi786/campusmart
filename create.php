<?php
/**
 * Sell an item: validate input, store listing + images.
 */
require __DIR__ . '/app/bootstrap.php';

$user = require_login();
$page_title = 'Sell an item';

$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$errors = [];
$listing = null;

if (is_post()) {
    validate_csrf();

    $title       = trim($_POST['title'] ?? '');
    $categoryId  = (int) ($_POST['category_id'] ?? 0);
    $price       = $_POST['price'] ?? '';
    $condition   = $_POST['condition'] ?? '';
    $campus      = trim($_POST['campus'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (mb_strlen($title) < 4) {
        $errors['title'] = 'Give your item a clear title (at least 4 characters).';
    }
    $catIds = array_column($categories, 'id');
    if (!in_array($categoryId, $catIds, true)) {
        $errors['category_id'] = 'Please choose a category.';
    }
    if (!is_numeric($price) || (float) $price <= 0) {
        $errors['price'] = 'Enter a valid price greater than zero.';
    }
    if (!in_array($condition, ['new', 'like_new', 'good', 'fair'], true)) {
        $errors['condition'] = 'Please choose the condition.';
        $condition = 'good';
    }
    if (mb_strlen($description) < 10) {
        $errors['description'] = 'Describe the item a little (at least 10 characters).';
    }

    $upload = upload_listing_images($_FILES['images'] ?? [], (int) config('max_images', 5));
    if ($upload['errors']) {
        $errors['images'] = implode(' ', $upload['errors']);
    }

    if (!$errors) {
        $st = $db->prepare(
            'INSERT INTO listings (user_id, category_id, title, description, price, `condition`, campus, location)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            (int) $user['id'], $categoryId, $title, $description,
            (float) $price, $condition, $campus, $location,
        ]);
        $listingId = (int) $db->lastInsertId();

        $order = 0;
        $imgSt = $db->prepare(
            'INSERT INTO listing_images (listing_id, filename, sort_order) VALUES (?, ?, ?)'
        );
        foreach ($upload['files'] as $rel) {
            $imgSt->execute([$listingId, $rel, $order++]);
        }

        flash('success', 'Your listing is live!');
        redirect('listing.php?id=' . $listingId);
    }
}

include __DIR__ . '/views/partials/header.php';
?>

<div class="form-card">
    <h1>Sell an item</h1>
    <p class="muted">Good photos and an honest description sell twice as fast.</p>
    <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <?php include __DIR__ . '/views/partials/listing_form.php'; ?>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Publish listing</button>
    </form>
</div>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
