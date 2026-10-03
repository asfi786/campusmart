<?php
/** API: Smart Price Suggestion for the listing form. */
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');

$categoryId = (int) ($_GET['category_id'] ?? 0);
$title      = trim($_GET['title'] ?? '');
$condition  = $_GET['condition'] ?? 'good';
$exclude    = isset($_GET['exclude']) ? (int) $_GET['exclude'] : null;

if ($categoryId <= 0 || $title === '') {
    echo json_encode(['ok' => true, 'enough' => false, 'count' => 0,
                      'note' => 'Choose a category and type a title to get a suggestion.']);
    exit;
}

$s = suggest_price($categoryId, $title, $condition, $exclude);
$s['ok'] = true;
if (!empty($s['enough'])) {
    $s['min_fmt']    = money($s['min']);
    $s['max_fmt']    = money($s['max']);
    $s['median_fmt'] = money($s['median']);
}
echo json_encode($s);
