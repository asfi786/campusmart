<?php
/**
 * Image uploads: validation, GD resizing, thumbnails, demo placeholders.
 * Files live under /uploads/YYYY/MM/ so one folder never gets too full.
 */

function upload_dir_for(string $relDir): string
{
    $full = APP_ROOT . '/uploads/' . trim($relDir, '/');
    if (!is_dir($full)) {
        mkdir($full, 0775, true);
    }
    // Block PHP execution if this ever lands on an Apache host.
    $ht = APP_ROOT . '/uploads/.htaccess';
    if (!is_file($ht)) {
        file_put_contents($ht, "php_flag engine off\n<FilesMatch \"\\.php$\">\n  Require all denied\n</FilesMatch>\n");
    }
    return $full;
}

/**
 * Handle up to N uploaded listing images.
 * Returns ['files' => [rel paths...], 'errors' => [...]].
 * Each image is resized (max 1200px) and a 480px thumbnail is generated.
 */
function upload_listing_images(array $files, int $max = 5): array
{
    $errors = [];
    $saved  = [];

    // Normalise the $_FILES structure into a flat list.
    $items = [];
    if (isset($files['name']) && is_array($files['name'])) {
        $n = count($files['name']);
        for ($i = 0; $i < $n; $i++) {
            $items[] = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
        }
    }

    $items = array_values(array_filter($items, fn($f) => ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
    if (count($items) > $max) {
        $errors[] = "You can upload at most {$max} images.";
        $items = array_slice($items, 0, $max);
    }

    $maxBytes = (int) (config('upload_max_mb', 5) * 1024 * 1024);
    $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png',
                 'image/webp' => 'webp', 'image/gif' => 'gif'];

    $relDir  = date('Y') . '/' . date('m');
    $fullDir = upload_dir_for($relDir);

    foreach ($items as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed for ' . $f['name'] . '.';
            continue;
        }
        if ($f['size'] > $maxBytes) {
            $errors[] = $f['name'] . ' is larger than ' . config('upload_max_mb', 5) . ' MB.';
            continue;
        }
        // Never trust the browser-supplied MIME type — sniff the real one.
        $mime = mime_content_type($f['tmp_name']);
        if (!isset($allowed[$mime])) {
            $errors[] = $f['name'] . ' is not a supported image (JPG, PNG, WebP, GIF).';
            continue;
        }
        $info = @getimagesize($f['tmp_name']);
        if ($info === false) {
            $errors[] = $f['name'] . ' is not a readable image.';
            continue;
        }

        $base = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        $dest = $fullDir . '/' . $base;

        // Resize the original down to a sane max dimension (GD library).
        if (!gd_resize($f['tmp_name'], $dest, 1200, $mime)) {
            $errors[] = 'Could not process ' . $f['name'] . '.';
            continue;
        }
        // Thumbnail for cards/search results (fast page loads on phones).
        gd_resize($dest, thumb_path($dest), 480, $mime);

        $saved[] = $relDir . '/' . $base;
    }

    return ['files' => $saved, 'errors' => $errors];
}

/** Sibling thumbnail path: /a/b/photo.jpg -> /a/b/photo_thumb.jpg */
function thumb_path(string $full): string
{
    $pos = strrpos($full, '.');
    return substr($full, 0, $pos) . '_thumb' . substr($full, $pos);
}

/**
 * Resize $src into $dst so the longest side is $maxDim px (never upscale).
 * Returns true on success. Requires the GD extension.
 */
function gd_resize(string $src, string $dst, int $maxDim, string $mime): bool
{
    if (!extension_loaded('gd')) {
        return copy($src, $dst); // graceful fallback: store as-is
    }
    $create = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng',
               'image/webp' => 'imagecreatefromwebp', 'image/gif' => 'imagecreatefromgif'][$mime] ?? null;
    if (!$create || !function_exists($create)) {
        return false;
    }
    $in = @$create($src);
    if (!$in) {
        return false;
    }
    $w = imagesx($in);
    $h = imagesy($in);
    $scale = min(1, $maxDim / max($w, $h));
    $nw = (int) round($w * $scale);
    $nh = (int) round($h * $scale);

    $out = imagecreatetruecolor($nw, $nh);
    // Preserve transparency for PNG/GIF/WebP.
    if ($mime !== 'image/jpeg') {
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefilledrectangle($out, 0, 0, $nw, $nh, $transparent);
    }
    imagecopyresampled($out, $in, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $ok = match ($mime) {
        'image/jpeg' => imagejpeg($out, $dst, 82),
        'image/png'  => imagepng($out, $dst, 6),
        'image/webp' => imagewebp($out, $dst, 82),
        'image/gif'  => imagegif($out, $dst),
        default      => false,
    };
    imagedestroy($in);
    imagedestroy($out);
    return (bool) $ok;
}

/**
 * Generate a nice placeholder image with GD (used by the demo seeder so the
 * app looks alive without downloading anything from the internet).
 */
function make_placeholder(string $relPath, string $title, string $bgHex): string
{
    $fullDir = upload_dir_for(dirname($relPath));
    $full = $fullDir . '/' . basename($relPath);

    $w = 800;
    $h = 600;
    $img = imagecreatetruecolor($w, $h);

    // Vertical gradient from $bgHex to a darker shade.
    [$r, $g, $b] = sscanf($bgHex, '#%02x%02x%02x');
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $c = imagecolorallocate($img,
            (int) ($r * (1 - $t * 0.45)), (int) ($g * (1 - $t * 0.45)), (int) ($b * (1 - $t * 0.45)));
        imageline($img, 0, $y, $w, $y, $c);
    }

    // Soft white card in the middle with the item name.
    $card = imagecolorallocatealpha($img, 255, 255, 255, 70);
    imagefilledrectangle($img, 60, 200, $w - 60, $h - 200, $card);
    $ink = imagecolorallocate($img, 25, 20, 40);

    // Word-wrap the title with the built-in GD font.
    $words = explode(' ', $title);
    $lines = [''];
    foreach ($words as $word) {
        $last = count($lines) - 1;
        if (strlen($lines[$last] . ' ' . $word) > 26) {
            $lines[] = $word;
        } else {
            $lines[$last] = trim($lines[$last] . ' ' . $word);
        }
    }
    $lines = array_slice($lines, 0, 3);
    $y = 300 - count($lines) * 14;
    foreach ($lines as $line) {
        $tw = imagefontwidth(5) * strlen($line);
        imagestring($img, 5, (int) (($w - $tw) / 2), $y, $line, $ink);
        $y += 34;
    }
    $tag = imagecolorallocate($img, 255, 255, 255);
    $tw = imagefontwidth(3) * strlen('CampusMart');
    imagestring($img, 3, (int) (($w - $tw) / 2), $h - 60, 'CampusMart', $tag);

    imagejpeg($img, $full, 80);
    imagedestroy($img);

    gd_resize($full, thumb_path($full), 480, 'image/jpeg');
    return $relPath;
}
