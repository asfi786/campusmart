<?php /** Shared page footer: closes <main>, mobile tab bar, scripts. */ ?>
</main>

<?php $user = current_user(); ?>
<?php if ($user): ?>
<nav class="tabbar" aria-label="Quick navigation">
    <a href="<?= e(u('index.php')) ?>" class="tab">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5 12 3l9 6.5V21H3z"/><path d="M9 21v-6h6v6"/></svg>
        <span>Home</span>
    </a>
    <a href="<?= e(u('lost-found.php')) ?>" class="tab">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <span>Lost&amp;Found</span>
    </a>
    <a href="<?= e(u('create.php')) ?>" class="tab tab-sell" aria-label="Sell an item">
        <span class="tab-sell-plus">+</span>
        <span>Sell</span>
    </a>
    <a href="<?= e(u('chat.php')) ?>" class="tab">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span>Chats</span>
        <span class="badge-dot tab-badge" id="tab-msg-badge" hidden></span>
    </a>
    <a href="<?= e(u('wishlist.php')) ?>" class="tab">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14c1.5-1.5 3-3.3 3-5.5A4.5 4.5 0 0 0 17.5 4c-1.8 0-3.4 1-4.2 2.4h-2.6C9.9 5 8.3 4 6.5 4A4.5 4.5 0 0 0 2 8.5c0 2.2 1.5 4 3 5.5l7 7z" transform="scale(.92) translate(1,1)"/></svg>
        <span>Saved</span>
    </a>
</nav>
<?php endif; ?>

<footer class="site-footer">
    <div class="container footer-inner">
        <div>
            <span class="logo-text">Campus<em>Mart</em></span>
            <p class="muted">The student marketplace — buy &amp; sell on campus, minus the WhatsApp chaos.</p>
        </div>
        <div class="footer-links">
            <a href="<?= e(u('index.php')) ?>">Browse</a>
            <a href="<?= e(u('lost-found.php')) ?>">Lost &amp; Found</a>
            <?php if ($user && $user['role'] === 'admin'): ?>
                <a href="<?= e(u('admin.php')) ?>">Admin</a>
            <?php endif; ?>
        </div>
    </div>
    <p class="container muted tiny">Built with plain PHP, MySQL/SQLite and vanilla JS · Final-year project</p>
</footer>

<script>
window.CM = {
    base: <?= json_encode(u('')) ?>,
    pollMs: <?= (int) config('chat_poll_ms', 3000) ?>,
    userId: <?= $user ? (int) $user['id'] : 0 ?>,
    csrf: <?= json_encode(csrf_token()) ?>
};
</script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
