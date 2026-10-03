<?php
/**
 * Report a listing or a user. Admins review reports in the admin panel.
 * ?target=listing&id=X  or  ?target=user&id=X
 */
require __DIR__ . '/app/bootstrap.php';

$user = require_login();
$page_title = 'Report';

$target = $_GET['target'] ?? '';
$targetId = (int) ($_GET['id'] ?? 0);
$reasons = ['Spam or scam', 'Inappropriate content', 'Wrong category / misleading',
            'Item already sold elsewhere', 'Harassment', 'Other'];

$subject = null;
if ($target === 'listing' && $targetId > 0) {
    $st = $db->prepare('SELECT id, title FROM listings WHERE id = ? AND status != ?');
    $st->execute([$targetId, 'deleted']);
    $subject = $st->fetch();
    $subjectLabel = $subject ? 'listing “' . $subject['title'] . '”' : null;
} elseif ($target === 'user' && $targetId > 0) {
    $st = $db->prepare('SELECT id, name FROM users WHERE id = ?');
    $st->execute([$targetId]);
    $subject = $st->fetch();
    $subjectLabel = $subject ? 'user ' . $subject['name'] : null;
}
if (!$subject) {
    flash('error', 'Nothing to report.');
    redirect('index.php');
}

$error = '';
if (is_post()) {
    validate_csrf();
    $reason = $_POST['reason'] ?? '';
    $details = trim($_POST['details'] ?? '');
    if (!in_array($reason, $reasons, true)) {
        $error = 'Please choose a reason.';
    } else {
        $db->prepare(
            'INSERT INTO reports (reporter_id, listing_id, reported_user_id, reason, details)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            (int) $user['id'],
            $target === 'listing' ? $targetId : null,
            $target === 'user' ? $targetId : null,
            $reason, $details ?: null,
        ]);
        flash('success', 'Thanks — an admin will review your report.');
        redirect($target === 'listing' ? 'listing.php?id=' . $targetId : 'index.php');
    }
}

include __DIR__ . '/views/partials/header.php';
?>
<div class="form-card narrow">
    <h1>Report <?= e($subjectLabel) ?></h1>
    <p class="muted">Reports are private — only admins see them.</p>
    <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="reason">Reason *</label>
            <select class="select" id="reason" name="reason" required>
                <option value="">Choose…</option>
                <?php foreach ($reasons as $r): ?>
                    <option <?= old('reason') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="details">Details (optional)</label>
            <textarea class="textarea" id="details" name="details" rows="4"
                      placeholder="What exactly is wrong?"><?= old('details') ?></textarea>
        </div>
        <button class="btn btn-danger btn-block btn-lg" type="submit">Submit report</button>
    </form>
</div>
<?php include __DIR__ . '/views/partials/footer.php'; ?>
