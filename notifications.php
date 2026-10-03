<?php
/** Notifications inbox. */
require __DIR__ . '/app/bootstrap.php';

$user = require_login();
$page_title = 'Notifications';
$uid = (int) $user['id'];

if (is_post()) {
    validate_csrf();
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
    redirect('notifications.php');
}

$st = $db->prepare(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50'
);
$st->execute([$uid]);
$items = $st->fetchAll();

include __DIR__ . '/views/partials/header.php';
?>

<div class="section-head">
    <h1>Notifications</h1>
    <form method="post"><?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" type="submit">Mark all read</button>
    </form>
</div>

<?php if ($items): ?>
    <div class="notif-list">
        <?php foreach ($items as $n): ?>
            <a class="notif<?= (int) $n['is_read'] === 0 ? ' unread' : '' ?>"
               href="<?= e(u($n['link'] ?: 'notifications.php')) ?>">
                <span class="notif-dot" aria-hidden="true"></span>
                <div>
                    <strong><?= e($n['title']) ?></strong>
                    <?php if ($n['body']): ?><div class="muted"><?= e($n['body']) ?></div><?php endif; ?>
                    <div class="muted tiny"><?= e(time_ago($n['created_at'])) ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-art">🔔</div>
        <h3>All quiet</h3>
        <p class="muted">You'll see messages, reviews and updates here.</p>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
