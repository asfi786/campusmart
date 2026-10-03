<?php
/**
 * Admin panel: dashboard stats + charts, users, listings, reports,
 * categories, allowed email domains, and site settings.
 */
require __DIR__ . '/app/bootstrap.php';

$admin = require_admin();
$page_title = 'Admin panel';
$tab = $_GET['tab'] ?? 'dashboard';

/* ---------------- POST actions ---------------- */
if (is_post()) {
    validate_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    switch ($action) {
        case 'ban_user':
            $db->prepare('UPDATE users SET is_banned = 1 WHERE id = ? AND role != ?')
               ->execute([$id, 'admin']);
            flash('success', 'User banned.');
            break;
        case 'unban_user':
            $db->prepare('UPDATE users SET is_banned = 0 WHERE id = ?')->execute([$id]);
            flash('success', 'User unbanned.');
            break;
        case 'delete_listing':
            $db->prepare("UPDATE listings SET status = 'deleted' WHERE id = ?")->execute([$id]);
            flash('success', 'Listing removed.');
            break;
        case 'report_status':
            $status = in_array($_POST['status'] ?? '', ['reviewed', 'dismissed'], true)
                ? $_POST['status'] : 'reviewed';
            $db->prepare('UPDATE reports SET status = ? WHERE id = ?')->execute([$status, $id]);
            // Tell the reporter what happened.
            $r = $db->prepare('SELECT reporter_id FROM reports WHERE id = ?');
            $r->execute([$id]);
            if ($row = $r->fetch()) {
                notify((int) $row['reporter_id'], 'report',
                    'Your report was ' . $status, '', 'index.php');
            }
            flash('success', 'Report marked as ' . $status . '.');
            break;
        case 'category_add':
            $name = trim($_POST['name'] ?? '');
            $color = trim($_POST['color'] ?? '#6C4CF1');
            if ($name !== '') {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
                try {
                    $db->prepare('INSERT INTO categories (name, slug, color) VALUES (?, ?, ?)')
                       ->execute([$name, trim($slug, '-'), $color]);
                    flash('success', 'Category added.');
                } catch (PDOException $e) {
                    flash('error', 'That category already exists.');
                }
            }
            break;
        case 'category_delete':
            $st = $db->prepare('SELECT COUNT(*) FROM listings WHERE category_id = ?');
            $st->execute([$id]);
            if ((int) $st->fetchColumn() === 0) {
                $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
                flash('success', 'Category deleted.');
            } else {
                flash('error', 'Cannot delete a category that has listings.');
            }
            break;
        case 'domain_add':
            $domain = strtolower(trim($_POST['domain'] ?? ''));
            if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
                try {
                    $db->prepare('INSERT INTO allowed_domains (domain) VALUES (?)')->execute([$domain]);
                    flash('success', 'Domain allowed.');
                } catch (PDOException $e) {
                    flash('error', 'That domain is already in the list.');
                }
            } else {
                flash('error', 'Enter a valid domain, e.g. campus.edu.pk');
            }
            break;
        case 'domain_delete':
            $db->prepare('DELETE FROM allowed_domains WHERE id = ?')->execute([$id]);
            flash('success', 'Domain removed.');
            break;
        case 'save_settings':
            save_setting('email_verification', isset($_POST['email_verification']) ? '1' : '0');
            save_setting('allowed_domains_only', isset($_POST['allowed_domains_only']) ? '1' : '0');
            save_setting('items_per_page', max(4, min(48, (int) ($_POST['items_per_page'] ?? 12))));
            flash('success', 'Settings saved.');
            break;
    }
    redirect('admin.php?tab=' . $tab);
}

/* ---------------- Dashboard data ---------------- */
$stats = [];
if ($tab === 'dashboard') {
    $q = fn($sql) => (int) $db->query($sql)->fetchColumn();
    $stats = [
        'Users'            => $q('SELECT COUNT(*) FROM users'),
        'Active listings'  => $q("SELECT COUNT(*) FROM listings WHERE status = 'active'"),
        'Items sold'       => $q("SELECT COUNT(*) FROM listings WHERE status = 'sold'"),
        'Messages sent'    => $q('SELECT COUNT(*) FROM messages'),
        'Pending reports'  => $q("SELECT COUNT(*) FROM reports WHERE status = 'pending'"),
        'Lost & Found'     => $q("SELECT COUNT(*) FROM lost_found WHERE status = 'open'"),
    ];
    // Sign-ups per day, last 7 days (portable: date() works in both DBs).
    $signups = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $st = $db->prepare('SELECT COUNT(*) FROM users WHERE date(created_at) = ?');
        $st->execute([$day]);
        $signups[$day] = (int) $st->fetchColumn();
    }
    $perCat = $db->query(
        "SELECT c.name, c.color, COUNT(l.id) AS n FROM categories c
         LEFT JOIN listings l ON l.category_id = c.id AND l.status = 'active'
         GROUP BY c.id ORDER BY n DESC"
    )->fetchAll();
    $latest = $db->query(
        'SELECT l.id, l.title, l.price, l.created_at, u.name AS seller
         FROM listings l JOIN users u ON u.id = l.user_id
         ORDER BY l.created_at DESC LIMIT 5'
    )->fetchAll();
}

/* ---------------- Tab data ---------------- */
$users = $reports = $allListings = $categories = $domains = [];
if ($tab === 'users') {
    $users = $db->query(
        'SELECT u.*, (SELECT COUNT(*) FROM listings l WHERE l.user_id = u.id) AS listings,
                (SELECT COUNT(*) FROM reviews r WHERE r.seller_id = u.id) AS reviews
         FROM users u ORDER BY u.created_at DESC LIMIT 100'
    )->fetchAll();
}
if ($tab === 'listings') {
    $allListings = $db->query(
        "SELECT l.*, c.name AS category_name, u.name AS seller
         FROM listings l JOIN categories c ON c.id = l.category_id
         JOIN users u ON u.id = l.user_id
         WHERE l.status != 'deleted' ORDER BY l.created_at DESC LIMIT 100"
    )->fetchAll();
}
if ($tab === 'reports') {
    $status = $_GET['status'] ?? 'pending';
    $st = $db->prepare(
        'SELECT r.*, u.name AS reporter_name, l.title AS listing_title,
                ru.name AS reported_name
         FROM reports r
         JOIN users u ON u.id = r.reporter_id
         LEFT JOIN listings l ON l.id = r.listing_id
         LEFT JOIN users ru ON ru.id = r.reported_user_id
         WHERE r.status = ? ORDER BY r.created_at DESC'
    );
    $st->execute([in_array($status, ['pending', 'reviewed', 'dismissed'], true) ? $status : 'pending']);
    $reports = $st->fetchAll();
}
if ($tab === 'categories') {
    $categories = $db->query(
        'SELECT c.*, (SELECT COUNT(*) FROM listings l WHERE l.category_id = c.id) AS n
         FROM categories c ORDER BY c.name'
    )->fetchAll();
}
if ($tab === 'domains') {
    $domains = $db->query('SELECT * FROM allowed_domains ORDER BY domain')->fetchAll();
}

$tabs = ['dashboard' => 'Dashboard', 'users' => 'Users', 'listings' => 'Listings',
         'reports' => 'Reports', 'categories' => 'Categories', 'domains' => 'Email domains',
         'settings' => 'Settings'];

include __DIR__ . '/views/partials/header.php';
?>

<h1>Admin panel</h1>

<nav class="admin-tabs" aria-label="Admin sections">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="admin-tab<?= $tab === $k ? ' active' : '' ?>" href="<?= e(u('admin.php?tab=' . $k)) ?>">
            <?= e($label) ?>
            <?php if ($k === 'reports' && ($stats['Pending reports'] ?? 0) === 0): ?>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'dashboard'): ?>
    <div class="stat-grid">
        <?php foreach ($stats as $label => $n): ?>
            <div class="stat-card">
                <div class="stat-num"><?= $n ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="admin-cols">
        <div class="panel">
            <h2 class="h3">Sign-ups — last 7 days</h2>
            <div class="bar-chart">
                <?php $mx = max(1, max($signups)); foreach ($signups as $day => $n): ?>
                    <div class="bar-row">
                        <span><?= e(date('D', strtotime($day))) ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width: <?= (int) round(100 * $n / $mx) ?>%"></div></div>
                        <span class="muted"><?= $n ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="panel">
            <h2 class="h3">Active listings per category</h2>
            <div class="bar-chart">
                <?php $mx = 1; foreach ($perCat as $c) { $mx = max($mx, (int) $c['n']); } ?>
                <?php foreach ($perCat as $c): ?>
                    <div class="bar-row">
                        <span><?= e(mb_strimwidth($c['name'], 0, 14, '…')) ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width: <?= (int) round(100 * $c['n'] / $mx) ?>%; background: <?= e($c['color']) ?>"></div></div>
                        <span class="muted"><?= (int) $c['n'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="panel">
        <h2 class="h3">Latest listings</h2>
        <div class="table-wrap"><table class="table">
            <tr><th>Title</th><th>Seller</th><th>Price</th><th>Posted</th></tr>
            <?php foreach ($latest as $l): ?>
                <tr>
                    <td><a href="<?= e(u('listing.php?id=' . $l['id'])) ?>"><?= e($l['title']) ?></a></td>
                    <td><?= e($l['seller']) ?></td><td><?= e(money($l['price'])) ?></td>
                    <td class="muted"><?= e(time_ago($l['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </table></div>
    </div>
<?php endif; ?>

<?php if ($tab === 'users'): ?>
    <div class="panel"><div class="table-wrap"><table class="table">
        <tr><th>User</th><th>Email</th><th>Role</th><th>Listings</th><th>Joined</th><th></th></tr>
        <?php foreach ($users as $x): ?>
            <tr class="<?= (int) $x['is_banned'] ? 'row-banned' : '' ?>">
                <td><strong><?= e($x['name']) ?></strong>
                    <?php if ((int) $x['is_banned']): ?><span class="badge badge-banned">Banned</span><?php endif; ?></td>
                <td class="muted"><?= e($x['email']) ?></td>
                <td><?= e($x['role']) ?></td>
                <td><?= (int) $x['listings'] ?></td>
                <td class="muted"><?= e(time_ago($x['created_at'])) ?></td>
                <td>
                    <?php if ($x['role'] !== 'admin'): ?>
                        <form method="post" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
                            <?php if ((int) $x['is_banned']): ?>
                                <input type="hidden" name="action" value="unban_user">
                                <button class="btn btn-ghost btn-sm" type="submit">Unban</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="ban_user">
                                <button class="btn btn-danger btn-sm" type="submit"
                                        onclick="return confirm('Ban <?= e($x['name']) ?>?')">Ban</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table></div></div>
<?php endif; ?>

<?php if ($tab === 'listings'): ?>
    <div class="panel"><div class="table-wrap"><table class="table">
        <tr><th>Title</th><th>Category</th><th>Seller</th><th>Price</th><th>Status</th><th></th></tr>
        <?php foreach ($allListings as $l): ?>
            <tr>
                <td><a href="<?= e(u('listing.php?id=' . $l['id'])) ?>"><?= e(mb_strimwidth($l['title'], 0, 45, '…')) ?></a></td>
                <td class="muted"><?= e($l['category_name']) ?></td>
                <td class="muted"><?= e($l['seller']) ?></td>
                <td><?= e(money($l['price'])) ?></td>
                <td><span class="badge badge-<?= e($l['status']) ?>"><?= e($l['status']) ?></span></td>
                <td>
                    <form method="post" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_listing">
                        <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit"
                                onclick="return confirm('Remove this listing?')">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table></div></div>
<?php endif; ?>

<?php if ($tab === 'reports'): ?>
    <div class="tabs">
        <?php foreach (['pending', 'reviewed', 'dismissed'] as $s): ?>
            <a class="tab-link<?= ($_GET['status'] ?? 'pending') === $s ? ' active' : '' ?>"
               href="<?= e(u('admin.php?tab=reports&status=' . $s)) ?>"><?= ucfirst($s) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if ($reports): ?>
        <?php foreach ($reports as $r): ?>
            <div class="panel report">
                <div class="report-head">
                    <strong><?= e($r['reason']) ?></strong>
                    <span class="muted tiny"><?= e(time_ago($r['created_at'])) ?> · by <?= e($r['reporter_name']) ?></span>
                </div>
                <?php if ($r['listing_title']): ?>
                    <p>Listing: <a href="<?= e(u('listing.php?id=' . $r['listing_id'])) ?>"><?= e($r['listing_title']) ?></a></p>
                <?php endif; ?>
                <?php if ($r['reported_name']): ?><p>Reported user: <?= e($r['reported_name']) ?></p><?php endif; ?>
                <?php if ($r['details']): ?><p class="muted">“<?= e($r['details']) ?>”</p><?php endif; ?>
                <?php if (($_GET['status'] ?? 'pending') === 'pending'): ?>
                    <form method="post" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="report_status">
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="btn btn-ghost btn-sm" name="status" value="reviewed" type="submit">Mark reviewed</button>
                        <button class="btn btn-ghost btn-sm" name="status" value="dismissed" type="submit">Dismiss</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state"><div class="empty-art">✓</div><h3>No reports here</h3></div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($tab === 'categories'): ?>
    <div class="panel">
        <h2 class="h3">Add category</h2>
        <form method="post" class="form-row">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="category_add">
            <div class="field"><label class="label">Name</label>
                <input class="input" name="name" required maxlength="80"></div>
            <div class="field"><label class="label">Color</label>
                <input class="input input-color" type="color" name="color" value="#6C4CF1"></div>
            <div class="field field-actions"><button class="btn btn-primary" type="submit">Add</button></div>
        </form>
    </div>
    <div class="panel"><div class="table-wrap"><table class="table">
        <tr><th>Category</th><th>Listings</th><th></th></tr>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td><span class="chip-dot" style="background: <?= e($c['color']) ?>"></span> <?= e($c['name']) ?></td>
                <td><?= (int) $c['n'] ?></td>
                <td>
                    <form method="post" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="category_delete">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit"
                                onclick="return confirm('Delete this category?')">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table></div></div>
<?php endif; ?>

<?php if ($tab === 'domains'): ?>
    <div class="panel">
        <p class="muted">When “restrict registration to university domains” is ON (Settings tab),
            only these domains may register.</p>
        <form method="post" class="form-row">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="domain_add">
            <div class="field"><label class="label">Domain</label>
                <input class="input" name="domain" required placeholder="campus.edu.pk"></div>
            <div class="field field-actions"><button class="btn btn-primary" type="submit">Allow</button></div>
        </form>
    </div>
    <div class="panel"><div class="table-wrap"><table class="table">
        <tr><th>Domain</th><th></th></tr>
        <?php foreach ($domains as $d): ?>
            <tr><td><?= e($d['domain']) ?></td>
                <td><form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="domain_delete">
                    <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Remove</button>
                </form></td></tr>
        <?php endforeach; ?>
    </table></div></div>
<?php endif; ?>

<?php if ($tab === 'settings'): ?>
    <div class="panel form-card" style="margin:0">
        <h2 class="h3">Site settings</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_settings">
            <label class="check">
                <input type="checkbox" name="email_verification" value="1"
                    <?= site_setting('email_verification', config('email_verification') ? '1' : '0') === '1' ? 'checked' : '' ?>>
                <span><strong>Require email verification</strong><br>
                <span class="muted tiny">New accounts must click a link in their email. Only enable if mail() works on your host.</span></span>
            </label>
            <label class="check">
                <input type="checkbox" name="allowed_domains_only" value="1"
                    <?= site_setting('allowed_domains_only', config('allowed_domains_only') ? '1' : '0') === '1' ? 'checked' : '' ?>>
                <span><strong>Restrict registration to university email domains</strong><br>
                <span class="muted tiny">Only addresses from the Email domains tab may sign up.</span></span>
            </label>
            <div class="field">
                <label class="label" for="ipp">Listings per page</label>
                <input class="input" id="ipp" type="number" name="items_per_page" min="4" max="48"
                       value="<?= (int) site_setting('items_per_page', config('items_per_page', 12)) ?>">
            </div>
            <button class="btn btn-primary" type="submit">Save settings</button>
        </form>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
