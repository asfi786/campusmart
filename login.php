<?php
/** Login page. */
require __DIR__ . '/app/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}
$page_title = 'Log in';
$error = '';

if (is_post()) {
    validate_csrf();
    $res = login_user($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($res['ok']) {
        $to = $_SESSION['after_login'] ?? u('index.php');
        unset($_SESSION['after_login']);
        // Only allow local redirects (open-redirect protection).
        if (!str_starts_with($to, '/') && !str_starts_with($to, u(''))) {
            $to = u('index.php');
        }
        flash('success', 'Welcome back, ' . $res['user']['name'] . '!');
        header('Location: ' . $to);
        exit;
    }
    $error = $res['error'];
}

include __DIR__ . '/views/partials/header.php';
?>

<div class="form-card narrow">
    <h1>Welcome back</h1>
    <p class="muted">Log in to buy, sell and chat.</p>
    <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" id="email" type="email" name="email" required autocomplete="email"
                   value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="field">
            <label class="label" for="password">Password</label>
            <input class="input" id="password" type="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Log in</button>
    </form>
    <p class="center muted">No account? <a href="<?= e(u('register.php')) ?>">Sign up</a> ·
        <a href="<?= e(u('forgot.php')) ?>">Forgot password?</a></p>
</div>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
