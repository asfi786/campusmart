<?php
/**
 * Shared page header: <head>, theme bootstrap, site header, toasts.
 * Every page sets $page_title before including this file.
 */
$user = current_user();
$unread = $user ? unread_counts((int) $user['id']) : ['messages' => 0, 'notifications' => 0];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(($page_title ?? '') ? $page_title . ' · ' : '') ?>CampusMart</title>
<meta name="description" content="CampusMart — the student marketplace. Buy and sell used items on campus.">
<script>
// Set the theme before first paint to avoid a flash.
(function () {
    try {
        var t = localStorage.getItem('cm-theme');
        if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.dataset.theme = t;
    } catch (e) { document.documentElement.dataset.theme = 'light'; }
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='24' fill='%236C4CF1'/><text x='50' y='68' font-size='52' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='bold'>M</text></svg>">
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?= e(u('index.php')) ?>" aria-label="CampusMart home">
            <span class="logo-mark">M</span>
            <span class="logo-text">Campus<em>Mart</em></span>
        </a>

        <nav class="nav-links" aria-label="Primary">
            <a class="nav-link" href="<?= e(u('index.php')) ?>">Browse</a>
            <a class="nav-link" href="<?= e(u('lost-found.php')) ?>">Lost &amp; Found</a>
            <?php if ($user): ?>
                <a class="nav-link" href="<?= e(u('chat.php')) ?>">Chats
                    <?php if ($unread['messages'] > 0): ?>
                        <span class="pill" id="nav-msg-pill"><?= (int) $unread['messages'] ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link" href="<?= e(u('wishlist.php')) ?>">Wishlist</a>
                <?php if ($user['role'] === 'admin'): ?>
                    <a class="nav-link" href="<?= e(u('admin.php')) ?>">Admin</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="header-actions">
            <button class="icon-btn" id="theme-toggle" aria-label="Toggle dark mode" title="Dark mode">
                <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
            </button>

            <?php if ($user): ?>
                <a class="icon-btn bell" href="<?= e(u('notifications.php')) ?>" aria-label="Notifications" title="Notifications">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                    <span class="badge-dot" id="notif-badge" <?= $unread['notifications'] > 0 ? '' : 'hidden' ?>><?= (int) $unread['notifications'] ?></span>
                </a>
                <div class="avatar-menu">
                    <button class="avatar" id="avatar-btn" aria-haspopup="true" aria-label="Account menu"><?= e(initials($user['name'])) ?></button>
                    <div class="menu-dropdown" id="avatar-menu" hidden>
                        <div class="menu-head"><?= e($user['name']) ?><small><?= e($user['email']) ?></small></div>
                        <a href="<?= e(u('profile.php?id=' . $user['id'])) ?>">My profile</a>
                        <a href="<?= e(u('my-listings.php')) ?>">My listings</a>
                        <a href="<?= e(u('wishlist.php')) ?>">Wishlist</a>
                        <a href="<?= e(u('logout.php')) ?>" class="danger">Log out</a>
                    </div>
                </div>
                <a class="btn btn-primary btn-sell" href="<?= e(u('create.php')) ?>">+ Sell</a>
            <?php else: ?>
                <a class="btn btn-ghost" href="<?= e(u('login.php')) ?>">Log in</a>
                <a class="btn btn-primary" href="<?= e(u('register.php')) ?>">Sign up</a>
            <?php endif; ?>

            <button class="icon-btn hamburger" id="nav-toggle" aria-label="Open menu" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>
        </div>
    </div>

    <nav class="mobile-nav" id="mobile-nav" hidden aria-label="Mobile">
        <a href="<?= e(u('index.php')) ?>">Browse</a>
        <a href="<?= e(u('lost-found.php')) ?>">Lost &amp; Found</a>
        <?php if ($user): ?>
            <a href="<?= e(u('create.php')) ?>">+ Sell an item</a>
            <a href="<?= e(u('chat.php')) ?>">My chats</a>
            <a href="<?= e(u('wishlist.php')) ?>">Wishlist</a>
            <a href="<?= e(u('my-listings.php')) ?>">My listings</a>
            <a href="<?= e(u('notifications.php')) ?>">Notifications</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="<?= e(u('admin.php')) ?>">Admin panel</a>
            <?php endif; ?>
            <a href="<?= e(u('logout.php')) ?>">Log out</a>
        <?php else: ?>
            <a href="<?= e(u('login.php')) ?>">Log in</a>
            <a href="<?= e(u('register.php')) ?>">Sign up</a>
        <?php endif; ?>
    </nav>
</header>

<div class="toast-wrap" id="toast-wrap" aria-live="polite">
    <?php foreach (flashes() as $f): ?>
        <div class="toast toast-<?= e($f['type']) ?>" role="status">
            <?= e($f['message']) ?>
            <button class="toast-close" aria-label="Dismiss">&times;</button>
        </div>
    <?php endforeach; ?>
</div>

<main class="container page">
