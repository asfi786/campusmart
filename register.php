<?php
/** Registration page. */
require __DIR__ . '/app/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}
$page_title = 'Sign up';
$errors = [];

if (is_post()) {
    validate_csrf();
    $res = register_user($_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($res['ok']) {
        if (!empty($res['needs_verify'])) {
            flash('info', 'Account created! Check your email to verify it, then log in.');
            redirect('login.php');
        }
        // Log straight in when verification is off.
        $_SESSION['user_id'] = $res['user_id'];
        session_regenerate_id(true);
        flash('success', 'Welcome to CampusMart!');
        redirect('index.php');
    }
    $errors = $res['errors'];
}

include __DIR__ . '/views/partials/header.php';
?>

<div class="form-card narrow">
    <h1>Create your account</h1>
    <p class="muted">Free for students. One account per email.</p>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="name">Full name</label>
            <input class="input" id="name" name="name" required autocomplete="name" value="<?= old('name') ?>">
            <?php if (!empty($errors['name'])): ?><p class="form-error"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" id="email" type="email" name="email" required autocomplete="email" value="<?= old('email') ?>">
            <?php if (!empty($errors['email'])): ?><p class="form-error"><?= e($errors['email']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label class="label" for="password">Password</label>
            <input class="input" id="password" type="password" name="password" required
                   autocomplete="new-password" minlength="8">
            <?php if (!empty($errors['password'])): ?><p class="form-error"><?= e($errors['password']) ?></p><?php endif; ?>
            <p class="hint">At least 8 characters.</p>
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Sign up</button>
    </form>
    <p class="center muted">Already have an account? <a href="<?= e(u('login.php')) ?>">Log in</a></p>
</div>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
