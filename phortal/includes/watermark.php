<?php
/**
 * Phortal - Native GD Dynamic Resizing & Automated Watermark Generator
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

$photo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$req_w = isset($_GET['w']) ? min((int)$_GET['w'], 3840) : 0;
$req_h = isset($_GET['h']) ? min((int)$_GET['h'], 2160) : 0;
$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if (!$photo_id) {
    header('HTTP/1.1 400 Bad Request');
    die('Missing photo ID');
}

$db = get_db_connection();
$stmt = $db->prepare("SELECT p.*, g.id as gallery_id, g.is_private, g.code as gallery_code 
                      FROM photos p 
                      JOIN galleries g ON p.gallery_id = g.id 
                      WHERE p.id = ?");
$stmt->execute([$photo_id]);
$photo = $stmt->fetch();

if (!$photo) {
    header('HTTP/1.1 404 Not Found');
    die('Photo not found');
}

// Check authorization if gallery is private
if ($photo['is_private']) {
    if (!verify_gallery_access_code($photo['gallery_id'], $code)) {
        header('HTTP/1.1 403 Forbidden');
        die('Access denied: Gallery code required');
    }
}

$file_path = UPLOADS_DIR . $photo['filename'];
if (!file_exists($file_path)) {
    header('HTTP/1.1 404 Not Found');
    die('Image file missing on disk');
}

// Fetch watermark configuration for gallery
$w_stmt = $db->prepare("SELECT * FROM watermarks WHERE gallery_id = ?");
$w_stmt->execute([$photo['gallery_id']]);
$watermark_cfg = $w_stmt->fetch();

if (!$watermark_cfg) {
    $watermark_cfg = [
        'text' => 'PHORTAL COPYRIGHT',
        'opacity' => 50,
        'font_size' => 24,
        'position' => 'bottom-right',
        'color_hex' => '#FFFFFF'
    ];
}

// Load Image using GD
$image_info = getimagesize($file_path);
$mime_type = $image_info['mime'] ?? 'image/jpeg';

switch ($mime_type) {
    case 'image/jpeg':
        $src_img = imagecreatefromjpeg($file_path);
        break;
    case 'image/png':
        $src_img = imagecreatefrompng($file_path);
        break;
    case 'image/webp':
        $src_img = imagecreatefromwebp($file_path);
        break;
    default:
        header('HTTP/1.1 415 Unsupported Media Type');
        die('Unsupported image format');
}

if (!$src_img) {
    header('HTTP/1.1 500 Internal Server Error');
    die('Failed to load image');
}

$orig_w = imagesx($src_img);
$orig_h = imagesy($src_img);

// Calculate dynamic target dimensions
if ($req_w > 0 && $req_h > 0) {
    $target_w = $req_w;
    $target_h = $req_h;
} else if ($req_w > 0) {
    $target_w = $req_w;
    $target_h = (int)round(($orig_h / $orig_w) * $req_w);
} else if ($req_h > 0) {
    $target_h = $req_h;
    $target_w = (int)round(($orig_w / $orig_h) * $req_h);
} else {
    $target_w = $orig_w;
    $target_h = $orig_h;
}

// Perform Resizing if needed
if ($target_w !== $orig_w || $target_h !== $orig_h) {
    $resized_img = imagecreatetruecolor($target_w, $target_h);
    // preserve alpha for PNG
    imagealphablending($resized_img, false);
    imagesavealpha($resized_img, true);
    imagecopyresampled($resized_img, $src_img, 0, 0, 0, 0, $target_w, $target_h, $orig_w, $orig_h);
    imagedestroy($src_img);
    $canvas = $resized_img;
} else {
    $canvas = $src_img;
}

// Render Watermark Text using GD
$text = $watermark_cfg['text'];
$opacity = max(5, min(100, (int)$watermark_cfg['opacity'])); // 5 - 100%
$font_size = max(12, min(72, (int)$watermark_cfg['font_size']));
$position = $watermark_cfg['position'];

// Convert hex color to RGB
$hex = ltrim($watermark_cfg['color_hex'], '#');
if (strlen($hex) == 6) {
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
} else {
    $r = 255; $g = 255; $b = 255;
}

// GD alpha is 0 (opaque) to 127 (transparent)
$gd_alpha = (int)round((100 - $opacity) * 1.27);
$watermark_color = imagecolorallocatealpha($canvas, $r, $g, $b, $gd_alpha);

// Use built-in or TTF font rendering
$use_ttf = function_exists('imagettftext') && file_exists(__DIR__ . '/arial.ttf');

if ($use_ttf) {
    $bbox = imagettfbbox($font_size, 0, __DIR__ . '/arial.ttf', $text);
    $text_w = abs($bbox[2] - $bbox[0]);
    $text_h = abs($bbox[7] - $bbox[1]);
} else {
    // GD Builtin Font
    $font = 5; // built in font index 1-5
    $text_w = imagefontwidth($font) * strlen($text);
    $text_h = imagefontheight($font);
}

if ($position === 'center') {
    $x = (int)(($target_w - $text_w) / 2);
    $y = (int)(($target_h - $text_h) / 2);
} else if ($position === 'top-left') {
    $x = 20; $y = 20;
} else if ($position === 'top-right') {
    $x = $target_w - $text_w - 20; $y = 20;
} else if ($position === 'bottom-left') {
    $x = 20; $y = $target_h - $text_h - 20;
} else if ($position === 'tile') {
    // Tile watermark across canvas
    for ($tx = 30; $tx < $target_w; $tx += ($text_w + 120)) {
        for ($ty = 40; $ty < $target_h; $ty += ($text_h + 80)) {
            if ($use_ttf) {
                imagettftext($canvas, $font_size, 0, $tx, $ty + $text_h, $watermark_color, __DIR__ . '/arial.ttf', $text);
            } else {
                imagestring($canvas, 5, $tx, $ty, $text, $watermark_color);
            }
        }
    }
} else {
    // Default bottom-right
    $x = max(10, $target_w - $text_w - 20);
    $y = max(10, $target_h - $text_h - 20);
}

if ($position !== 'tile') {
    if ($use_ttf) {
        imagettftext($canvas, $font_size, 0, $x, $y + $text_h, $watermark_color, __DIR__ . '/arial.ttf', $text);
    } else {
        imagestring($canvas, 5, $x, $y, $text, $watermark_color);
    }
}

// Output headers and image stream
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');

imagejpeg($canvas, null, 90);
imagedestroy($canvas);
exit;
