<?php
/**
 * Lost & Found: post lost/found items, browse, mark as resolved.
 */
require __DIR__ . '/app/bootstrap.php';

$user = current_user();
$page_title = 'Lost & Found';
$type = ($_GET['type'] ?? 'lost') === 'found' ? 'found' : 'lost';
$showResolved = isset($_GET['resolved']);

/* ---- Post a new item ---- */
if (is_post() && $user) {
    validate_csrf();
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $itemType = ($_POST['item_type'] ?? 'lost') === 'found' ? 'found' : 'lost';
        if (mb_strlen($title) >= 4 && mb_strlen($description) >= 10) {
            $upload = upload_listing_images(['name' => [$_FILES['image']['name'] ?? ''],
                'type' => [$_FILES['image']['type'] ?? ''], 'tmp_name' => [$_FILES['image']['tmp_name'] ?? ''],
                'error' => [$_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE],
                'size' => [$_FILES['image']['size'] ?? 0]], 1);
            $img = $upload['files'][0] ?? null;
            $db->prepare(
                'INSERT INTO lost_found (user_id, type, title, description, image, location)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([(int) $user['id'], $itemType, $title, $description, $img, $location]);
            flash('success', 'Posted! Hope it finds its owner.');
            redirect('lost-found.php?type=' . $itemType);
        } else {
            flash('error', 'Please add a title (4+ chars) and description (10+ chars).');
        }
    }

    if ($action === 'resolve') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = $db->prepare('SELECT user_id FROM lost_found WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row && ((int) $row['user_id'] === (int) $user['id'] || $user['role'] === 'admin')) {
            $db->prepare("UPDATE lost_found SET status = 'resolved' WHERE id = ?")->execute([$id]);
            flash('success', 'Marked as resolved. 🎉');
        }
        redirect('lost-found.php?type=' . $type);
    }
}

/* ---- List items ---- */
$where = 'lf.type = ?';
$params = [$type];
if (!$showResolved) {
    $where .= " AND lf.status = 'open'";
}
$st = $db->prepare(
    "SELECT lf.*, u.name AS user_name FROM lost_found lf
     JOIN users u ON u.id = lf.user_id
     WHERE $where ORDER BY lf.created_at DESC LIMIT 60"
);
$st->execute($params);
$items = $st->fetchAll();

$counts = [];
foreach (['lost', 'found'] as $t) {
    $st = $db->prepare("SELECT COUNT(*) FROM lost_found WHERE type = ? AND status = 'open'");
    $st->execute([$t]);
    $counts[$t] = (int) $st->fetchColumn();
}

include __DIR__ . '/views/partials/header.php';
?>

<div class="section-head">
    <h1>Lost &amp; Found</h1>
    <?php if ($user): ?>
        <button class="btn btn-primary" id="lf-toggle">+ Post an item</button>
    <?php else: ?>
        <a class="btn btn-primary" href="<?= e(u('login.php')) ?>">Log in to post</a>
    <?php endif; ?>
</div>

<?php if ($user): ?>
    <div class="form-card" id="lf-form" hidden>
        <h2>Post a <?= $type ?> item</h2>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="field">
                    <span class="label">This item is *</span>
                    <div class="radio-row">
                        <label><input type="radio" name="item_type" value="lost" <?= $type === 'lost' ? 'checked' : '' ?>> Lost</label>
                        <label><input type="radio" name="item_type" value="found" <?= $type === 'found' ? 'checked' : '' ?>> Found</label>
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="lf-title">Title *</label>
                    <input class="input" id="lf-title" name="title" required maxlength="150"
                           placeholder="e.g. Black wallet near library">
                </div>
            </div>
            <div class="field">
                <label class="label" for="lf-desc">Description *</label>
                <textarea class="textarea" id="lf-desc" name="description" rows="3" required
                          placeholder="Identifying details (keep one secret to verify the owner)…"></textarea>
            </div>
            <div class="form-row">
                <div class="field">
                    <label class="label" for="lf-loc">Last seen / found at</label>
                    <input class="input" id="lf-loc" name="location" maxlength="150" placeholder="e.g. Central Library, 2nd floor">
                </div>
                <div class="field">
                    <label class="label" for="lf-img">Photo (optional)</label>
                    <input class="input" id="lf-img" type="file" name="image" accept="image/*">
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Post item</button>
        </form>
    </div>
<?php endif; ?>

<div class="tabs" role="tablist">
    <a role="tab" class="tab-link<?= $type === 'lost' ? ' active' : '' ?>"
       href="<?= e(u('lost-found.php?type=lost')) ?>">Lost (<?= $counts['lost'] ?>)</a>
    <a role="tab" class="tab-link<?= $type === 'found' ? ' active' : '' ?>"
       href="<?= e(u('lost-found.php?type=found')) ?>">Found (<?= $counts['found'] ?>)</a>
    <a class="tab-link tab-link-right<?= $showResolved ? ' active' : '' ?>"
       href="<?= e(u('lost-found.php?type=' . $type . ($showResolved ? '' : '&resolved=1'))) ?>">
        <?= $showResolved ? 'Hide resolved' : 'Show resolved' ?>
    </a>
</div>

<?php if ($items): ?>
    <div class="grid-cards">
        <?php foreach ($items as $it): ?>
            <article class="card lf-card">
                <div class="card-img">
                    <?php if ($it['image']): ?>
                        <img src="<?= e(uploads_url(thumb_of($it['image']))) ?>" alt="<?= e($it['title']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="card-img-fallback lf-<?= e($it['type']) ?>"><?= $it['type'] === 'lost' ? '?' : '!' ?></span>
                    <?php endif; ?>
                    <span class="badge badge-lf-<?= e($it['type']) ?>"><?= ucfirst(e($it['type'])) ?></span>
                    <?php if ($it['status'] === 'resolved'): ?><span class="sold-ribbon">Resolved</span><?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="card-title"><?= e($it['title']) ?></div>
                    <p class="muted tiny"><?= nl2br(e($it['description'])) ?></p>
                    <div class="card-meta">
                        <?php if ($it['location']): ?><span>📍 <?= e($it['location']) ?></span> · <?php endif; ?>
                        <span><?= e($it['user_name']) ?> · <?= e(time_ago($it['created_at'])) ?></span>
                    </div>
                    <?php if ($user && $it['status'] === 'open'
                              && ((int) $it['user_id'] === (int) $user['id'] || $user['role'] === 'admin')): ?>
                        <form method="post" class="mt">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="resolve">
                            <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                            <button class="btn btn-ghost btn-sm" type="submit">Mark as resolved</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-art"><?= $type === 'lost' ? '🔍' : '🎒' ?></div>
        <h3>No <?= e($type) ?> items right now</h3>
        <p class="muted">Good news — nothing missing. Check back later.</p>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
