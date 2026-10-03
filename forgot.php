<?php
/** Forgot password: request a reset link. */
require __DIR__ . '/app/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}
$page_title = 'Forgot password';
$sent = false;

if (is_post()) {
    validate_csrf();
    request_password_reset($_POST['email'] ?? '');
    $sent = true; // always "sent" — see auth.php for why
}

include __DIR__ . '/views/partials/header.php';
?>
<div class="form-card narrow">
    <h1>Forgot password?</h1>
    <?php if ($sent): ?>
        <p class="alert alert-success">If an account exists for that email, a reset link is on its way (valid for 1 hour).</p>
    <?php else: ?>
        <p class="muted">Enter your email and we'll send you a reset link.</p>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label class="label" for="email">Email</label>
                <input class="input" id="email" type="email" name="email" required autocomplete="email">
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Send reset link</button>
        </form>
    <?php endif; ?>
    <p class="center muted"><a href="<?= e(u('login.php')) ?>">Back to login</a></p>
</div>
<?php include __DIR__ . '/views/partials/footer.php'; ?>
