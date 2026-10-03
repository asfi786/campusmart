<?php
/**
 * Edit a listing (owner or admin).
 */
require __DIR__ . '/app/bootstrap.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);

$st = $db->prepare('SELECT * FROM listings WHERE id = ?');
$st->execute([$id]);
$listing = $st->fetch();
if (!$listing || $listing['status'] === 'deleted') {
    http_response_code(404);
    exit('Listing not found.');
}
if ((int) $listing['user_id'] !== (int) $user['id'] && $user['role'] !== 'admin') {
    http_response_code(403);
    exit('You cannot edit this listing.');
}

$page_title = 'Edit listing';
$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$errors = [];

$imgSt = $db->prepare('SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id');
$imgSt->execute([$id]);
$listing['images'] = $imgSt->fetchAll();

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
    if (!in_array($categoryId, array_column($categories, 'id'), true)) {
        $errors['category_id'] = 'Please choose a category.';
    }
    if (!is_numeric($price) || (float) $price <= 0) {
        $errors['price'] = 'Enter a valid price greater than zero.';
    }
    if (!in_array($condition, ['new', 'like_new', 'good', 'fair'], true)) {
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
        $db->prepare(
            'UPDATE listings SET category_id = ?, title = ?, description = ?, price = ?,
                              `condition` = ?, campus = ?, location = ?, updated_at = ?
             WHERE id = ?'
        )->execute([
            $categoryId, $title, $description, (float) $price,
            $condition, $campus, $location, date('Y-m-d H:i:s'), $id,
        ]);

        // Remove images the user ticked.
        $delIds = array_map('intval', $_POST['delete_images'] ?? []);
        if ($delIds) {
            $in = implode(',', array_fill(0, count($delIds), '?'));
            $rows = $db->prepare(
                "SELECT filename FROM listing_images WHERE id IN ($in) AND listing_id = ?"
            );
            $rows->execute([...$delIds, $id]);
            foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $rel) {
                @unlink(APP_ROOT . '/uploads/' . $rel);
                @unlink(APP_ROOT . '/uploads/' . thumb_of($rel));
            }
            $db->prepare("DELETE FROM listing_images WHERE id IN ($in) AND listing_id = ?")
               ->execute([...$delIds, $id]);
        }

        // Append newly uploaded images.
        $maxOrder = $db->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM listing_images WHERE listing_id = ?');
        $maxOrder->execute([$id]);
        $order = (int) $maxOrder->fetchColumn() + 1;
        $imgIns = $db->prepare(
            'INSERT INTO listing_images (listing_id, filename, sort_order) VALUES (?, ?, ?)'
        );
        foreach ($upload['files'] as $rel) {
            $imgIns->execute([$id, $rel, $order++]);
        }

        flash('success', 'Listing updated.');
        redirect('listing.php?id=' . $id);
    }
}

$is_edit = true;
include __DIR__ . '/views/partials/header.php';
?>

<div class="form-card">
    <h1>Edit listing</h1>
    <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <?php include __DIR__ . '/views/partials/listing_form.php'; ?>
        <div class="form-row">
            <button class="btn btn-primary btn-lg" type="submit">Save changes</button>
            <a class="btn btn-ghost btn-lg" href="<?= e(u('listing.php?id=' . $id)) ?>">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
