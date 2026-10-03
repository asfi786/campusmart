<?php
/** Reset password landing page (?token=...). */
require __DIR__ . '/app/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}
$page_title = 'Reset password';
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = '';

if (is_post()) {
    validate_csrf();
    $res = reset_password_with_token($token, $_POST['password'] ?? '');
    if ($res['ok']) {
        flash('success', 'Password changed. Please log in.');
        redirect('login.php');
    }
    $error = $res['error'];
}

include __DIR__ . '/views/partials/header.php';
?>
<div class="form-card narrow">
    <h1>Choose a new password</h1>
    <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
            <label class="label" for="password">New password</label>
            <input class="input" id="password" type="password" name="password" required
                   autocomplete="new-password" minlength="8">
            <p class="hint">At least 8 characters.</p>
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Reset password</button>
    </form>
</div>
<?php include __DIR__ . '/views/partials/footer.php'; ?>
