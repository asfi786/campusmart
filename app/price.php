<?php
/**
 * Smart Price Suggestion — the "wow" feature.
 *
 * When a student creates a listing, we suggest a fair price range by looking
 * at SIMILAR listings already on CampusMart:
 *
 *   1. Candidate pool: active listings in the SAME category.
 *   2. Similarity: the listing title is tokenised (lowercased, split on
 *      non-letters, stop-words removed). A candidate counts as "similar"
 *      if it shares at least one keyword with the new title.
 *      If fewer than 3 similar listings exist, we fall back to the whole
 *      category pool so the student still gets guidance.
 *   3. Condition adjustment: a "fair" phone should not be compared raw with
 *      a "new" one, so every comparable price is normalised to the target
 *      condition using fixed multipliers:
 *          new=1.20, like_new=1.10, good=1.00, fair=0.85
 *      normalised = price * (target_mult / comparable_mult)
 *   4. Statistics: from the normalised prices we take the MEDIAN and the
 *      25th / 75th PERCENTILES (the interquartile range). The median is
 *      robust against one absurdly-priced outlier; the IQR gives a range
 *      where "most fair prices" fall.
 *   5. Confidence: High (>=10 listings), Medium (>=5), Low (>=3).
 *      Below 3 we refuse to guess — a bad suggestion is worse than none.
 *
 * Returns: ['enough'=>bool, 'count'=>int, 'min'=>float, 'max'=>float,
 *           'median'=>float, 'confidence'=>string, 'note'=>string]
 */

const PRICE_CONDITION_MULT = [
    'new'      => 1.20,
    'like_new' => 1.10,
    'good'     => 1.00,
    'fair'     => 0.85,
];

function price_stopwords(): array
{
    return ['the','a','an','and','or','for','with','in','on','of','to','is',
            'at','by','from','urgent','sale','selling','brand','original'];
}

/** "HP Pavilion i5 Laptop!" -> ['hp','pavilion','laptop'] */
function tokenize_title(string $title): array
{
    $title = mb_strtolower($title);
    $words = preg_split('/[^a-z0-9]+/', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stop  = array_flip(price_stopwords());
    $tokens = [];
    foreach ($words as $w) {
        if (strlen($w) >= 3 && !isset($stop[$w])) {
            $tokens[] = $w;
        }
    }
    return array_values(array_unique($tokens));
}

/** Percentile with linear interpolation over a sorted array. */
function percentile_sorted(array $sorted, float $p): float
{
    $n = count($sorted);
    if ($n === 0) {
        return 0.0;
    }
    if ($n === 1) {
        return (float) $sorted[0];
    }
    $rank = ($p / 100) * ($n - 1);
    $low  = (int) floor($rank);
    $high = (int) ceil($rank);
    if ($low === $high) {
        return (float) $sorted[$low];
    }
    $frac = $rank - $low;
    return (float) ($sorted[$low] * (1 - $frac) + $sorted[$high] * $frac);
}

/** Round to a "human" price: nearest 50 above 1000, else nearest 10. */
function nice_round(float $v): float
{
    if ($v >= 1000) {
        return round($v / 50) * 50;
    }
    return max(10, round($v / 10) * 10);
}

function suggest_price(int $categoryId, string $title, string $condition, ?int $excludeId = null): array
{
    $db = db();
    $targetMult = PRICE_CONDITION_MULT[$condition] ?? 1.0;

    // 1. Candidate pool: active listings in the same category.
    $sql = 'SELECT id, title, price, `condition` FROM listings
            WHERE status = ? AND category_id = ?';
    $params = ['active', $categoryId];
    if ($excludeId) {
        $sql .= ' AND id != ?';
        $params[] = $excludeId;
    }
    $sql .= ' ORDER BY created_at DESC LIMIT 300';
    $st = $db->prepare($sql);
    $st->execute($params);
    $pool = $st->fetchAll();

    // 2. Keyword similarity on titles.
    $tokens = tokenize_title($title);
    $similar = [];
    $fallback = false;
    if ($tokens) {
        foreach ($pool as $row) {
            $rowTokens = tokenize_title($row['title']);
            if (count(array_intersect($tokens, $rowTokens)) >= 1) {
                $similar[] = $row;
            }
        }
    }
    if (count($similar) < 3) {
        // Not enough keyword matches — fall back to the category average
        // behaviour rather than saying nothing.
        $similar = $pool;
        $fallback = true;
    }

    // 3. Normalise every comparable price to the target condition.
    $prices = [];
    foreach ($similar as $row) {
        $compMult = PRICE_CONDITION_MULT[$row['condition']] ?? 1.0;
        $prices[] = (float) $row['price'] * ($targetMult / $compMult);
    }
    sort($prices, SORT_NUMERIC);
    $n = count($prices);

    if ($n < 3) {
        return [
            'enough' => false,
            'count'  => $n,
            'note'   => 'Not enough similar listings yet — check what others charge for similar items.',
        ];
    }

    // 4. Robust statistics: median + interquartile range.
    $median = percentile_sorted($prices, 50);
    $p25    = percentile_sorted($prices, 25);
    $p75    = percentile_sorted($prices, 75);

    $confidence = $n >= 10 ? 'High' : ($n >= 5 ? 'Medium' : 'Low');

    return [
        'enough'     => true,
        'count'      => $n,
        'min'        => nice_round($p25),
        'max'        => nice_round($p75),
        'median'     => nice_round($median),
        'confidence' => $confidence,
        'fallback'   => $fallback,
        'note'       => $fallback
            ? "Based on {$n} listings in this category (few close title matches)."
            : "Based on {$n} similar listings.",
    ];
}
