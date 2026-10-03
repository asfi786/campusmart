<?php
/**
 * Home page: hero search, category chips, filters, listing grid, pagination.
 */
require __DIR__ . '/app/bootstrap.php';

$page_title = 'Buy & sell on campus';
$user = current_user();

/* ---------------- Read & sanitise filters ---------------- */
$q         = trim($_GET['q'] ?? '');
$cat       = (int) ($_GET['category'] ?? 0);
$minPrice  = $_GET['min_price'] ?? '';
$maxPrice  = $_GET['max_price'] ?? '';
$condition = $_GET['condition'] ?? '';
$campus    = trim($_GET['campus'] ?? '');
$sort      = $_GET['sort'] ?? 'newest';
$page      = max(1, (int) ($_GET['page'] ?? 1));
$per       = (int) site_setting('items_per_page', config('items_per_page', 12));

$sortMap = [
    'newest'    => 'l.created_at DESC',
    'price_asc' => 'l.price ASC',
    'price_low' => 'l.price ASC',
    'price_desc'=> 'l.price DESC',
    'price_high'=> 'l.price DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['newest'];

/* ---------------- Build the search query (all params bound) ---------------- */
$where = ['l.status = ?'];
$params = ['active'];
if ($q !== '') {
    $where[] = '(l.title LIKE ? OR l.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($cat > 0) {
    $where[] = 'l.category_id = ?';
    $params[] = $cat;
}
if ($minPrice !== '' && is_numeric($minPrice)) {
    $where[] = 'l.price >= ?';
    $params[] = (float) $minPrice;
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $where[] = 'l.price <= ?';
    $params[] = (float) $maxPrice;
}
if (in_array($condition, ['new', 'like_new', 'good', 'fair'], true)) {
    $where[] = 'l.`condition` = ?';
    $params[] = $condition;
}
if ($campus !== '') {
    $where[] = 'l.campus LIKE ?';
    $params[] = "%$campus%";
}
$whereSql = implode(' AND ', $where);

$countSt = $db->prepare("SELECT COUNT(*) FROM listings l WHERE $whereSql");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();

$listSt = $db->prepare(
    "SELECT l.*, c.name AS category_name, c.color AS category_color,
            (SELECT filename FROM listing_images
              WHERE listing_id = l.id ORDER BY sort_order, id LIMIT 1) AS thumb
     FROM listings l
     JOIN categories c ON c.id = l.category_id
     WHERE $whereSql
     ORDER BY $orderBy LIMIT ? OFFSET ?"
);
$listSt->execute([...$params, $per, ($page - 1) * $per]);
$listings = $listSt->fetchAll();

/* Wishlist state for the hearts (one query, not N). */
$wished = [];
if ($user && $listings) {
    $ids = array_column($listings, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("SELECT listing_id FROM wishlists WHERE user_id = ? AND listing_id IN ($in)");
    $st->execute([(int) $user['id'], ...$ids]);
    $wished = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
}
foreach ($listings as &$l) {
    $l['wishlisted'] = isset($wished[$l['id']]);
}
unset($l);

$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$campuses = $db->query(
    "SELECT DISTINCT campus FROM listings WHERE campus != '' ORDER BY campus LIMIT 20"
)->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/views/partials/header.php';
?>

<section class="hero">
    <div class="hero-glow" aria-hidden="true"></div>
    <h1 class="hero-title">Everything on campus,<br><span class="grad">one marketplace.</span></h1>
    <p class="hero-sub">Buy and sell books, gadgets and hostel essentials with fellow students — no more lost WhatsApp messages.</p>

    <form class="search-bar" action="<?= e(u('index.php')) ?>" method="get" role="search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input class="search-input" type="search" name="q" value="<?= e($q) ?>"
               placeholder="Search books, laptops, calculators…" aria-label="Search listings" autocomplete="off">
        <button class="btn btn-primary" type="submit">Search</button>
    </form>

    <div class="chip-row" role="list" aria-label="Categories">
        <a role="listitem" class="chip<?= $cat === 0 ? ' active' : '' ?>"
           href="<?= e(u('index.php') . '?' . build_query(['category' => null, 'page' => null])) ?>">All</a>
        <?php foreach ($categories as $c): ?>
            <a role="listitem" class="chip<?= $cat === (int) $c['id'] ? ' active' : '' ?>"
               href="<?= e(u('index.php') . '?' . build_query(['category' => $c['id'], 'page' => null])) ?>">
                <span class="chip-dot" style="background: <?= e($c['color']) ?>"></span><?= e($c['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<details class="filters" <?= ($minPrice !== '' || $maxPrice !== '' || $condition !== '' || $campus !== '' || $sort !== 'newest') ? 'open' : '' ?>>
    <summary class="filters-toggle">Filters &amp; sorting</summary>
    <form class="filter-grid" action="<?= e(u('index.php')) ?>" method="get">
        <input type="hidden" name="q" value="<?= e($q) ?>">
        <input type="hidden" name="category" value="<?= $cat ?: '' ?>">
        <div class="field">
            <label class="label" for="f-min">Min price (<?= e(config('currency', 'Rs')) ?>)</label>
            <input class="input" id="f-min" type="number" name="min_price" min="0" value="<?= e($minPrice) ?>">
        </div>
        <div class="field">
            <label class="label" for="f-max">Max price</label>
            <input class="input" id="f-max" type="number" name="max_price" min="0" value="<?= e($maxPrice) ?>">
        </div>
        <div class="field">
            <label class="label" for="f-cond">Condition</label>
            <select class="select" id="f-cond" name="condition">
                <option value="">Any</option>
                <?php foreach (['new' => 'New', 'like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $condition === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="f-campus">Campus</label>
            <input class="input" id="f-campus" name="campus" list="campus-list" value="<?= e($campus) ?>" placeholder="e.g. Main Campus">
            <datalist id="campus-list">
                <?php foreach ($campuses as $cp): ?><option value="<?= e($cp) ?>"><?php endforeach; ?>
            </datalist>
        </div>
        <div class="field">
            <label class="label" for="f-sort">Sort by</label>
            <select class="select" id="f-sort" name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
            </select>
        </div>
        <div class="field field-actions">
            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-ghost" href="<?= e(u('index.php')) ?>">Clear</a>
        </div>
    </form>
</details>

<section aria-label="Listings">
    <div class="section-head">
        <h2><?= $q !== '' ? 'Results for “' . e($q) . '”' : 'Fresh listings' ?></h2>
        <span class="muted"><?= $total ?> item<?= $total === 1 ? '' : 's' ?></span>
    </div>

    <?php if ($listings): ?>
        <div class="grid-cards">
            <?php foreach ($listings as $l): ?>
                <?php include __DIR__ . '/views/partials/card.php'; ?>
            <?php endforeach; ?>
        </div>
        <?= paginate($total, $per, $page) ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-art" aria-hidden="true">∅</div>
            <h3>Nothing found</h3>
            <p class="muted">Try a different keyword, or be the first to sell something in this category.</p>
            <a class="btn btn-primary" href="<?= e(u('create.php')) ?>">+ Sell an item</a>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/views/partials/footer.php'; ?>
