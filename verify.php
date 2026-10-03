<?php
/** Email verification landing page (?token=...). */
require __DIR__ . '/app/bootstrap.php';

$page_title = 'Verify email';
$ok = verify_email_token($_GET['token'] ?? '');

include __DIR__ . '/views/partials/header.php';
?>
<div class="form-card narrow center">
    <?php if ($ok): ?>
        <h1>Email verified 🎉</h1>
        <p class="muted">Your account is active. Happy trading!</p>
        <a class="btn btn-primary" href="<?= e(u('login.php')) ?>">Log in</a>
    <?php else: ?>
        <h1>Invalid link</h1>
        <p class="muted">This verification link is invalid or was already used.</p>
        <a class="btn btn-ghost" href="<?= e(u('index.php')) ?>">Home</a>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/views/partials/footer.php'; ?>
