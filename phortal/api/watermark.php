<?php
/**
 * Phortal API - Watermark Configuration Controller
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'curator']);

$db = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' || $method === 'PUT') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $gallery_id = (int)($data['gallery_id'] ?? 0);
    $text = trim($data['text'] ?? 'PHORTAL COPYRIGHT');
    $opacity = max(5, min(100, (int)($data['opacity'] ?? 50)));
    $font_size = max(12, min(72, (int)($data['font_size'] ?? 24)));
    $position = trim($data['position'] ?? 'bottom-right');
    $color_hex = trim($data['color_hex'] ?? '#FFFFFF');

    if (!$gallery_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Gallery ID is required']);
        exit;
    }

    $valid_positions = ['center', 'bottom-right', 'bottom-left', 'top-right', 'top-left', 'tile'];
    if (!in_array($position, $valid_positions)) {
        $position = 'bottom-right';
    }

    $stmt = $db->prepare("INSERT INTO watermarks (gallery_id, text, opacity, font_size, position, color_hex, updated_at) 
                          VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                          ON CONFLICT(gallery_id) DO UPDATE SET 
                          text = excluded.text,
                          opacity = excluded.opacity,
                          font_size = excluded.font_size,
                          position = excluded.position,
                          color_hex = excluded.color_hex,
                          updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([$gallery_id, $text, $opacity, $font_size, $position, $color_hex]);

    log_audit('UPDATE_WATERMARK', 'WATERMARK', $gallery_id, "Updated watermark configuration for gallery ID {$gallery_id}: '{$text}' at {$opacity}% opacity.");

    echo json_encode(['success' => true]);
    exit;
}
