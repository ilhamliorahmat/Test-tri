<?php
/**
 * Phortal API - Photos Upload & Delete Controller
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = get_db_connection();

if ($method === 'POST') {
    require_role(['admin', 'curator']);

    $gallery_id = (int)($_POST['gallery_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $caption = trim($_POST['caption'] ?? '');

    if (!$gallery_id || !isset($_FILES['photo'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Gallery ID and photo file are required']);
        exit;
    }

    $file = $_FILES['photo'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'File upload error code: ' . $file['error']]);
        exit;
    }

    // Verify MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($mime, $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file format. Only JPG, PNG, and WEBP allowed.']);
        exit;
    }

    // Generate secure randomized filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
    $destination = UPLOADS_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file to server directory']);
        exit;
    }

    // Read image dimensions
    $size_info = getimagesize($destination);
    $width = $size_info[0] ?? 0;
    $height = $size_info[1] ?? 0;
    $file_size = filesize($destination);

    if (empty($title)) {
        $title = pathinfo($file['name'], PATHINFO_FILENAME);
    }

    $stmt = $db->prepare("INSERT INTO photos (gallery_id, filename, original_name, title, caption, file_size, width, height) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$gallery_id, $filename, $file['name'], $title, $caption, $file_size, $width, $height]);
    $photo_id = $db->lastInsertId();

    log_audit('UPLOAD_PHOTO', 'PHOTO', $photo_id, "Uploaded photo '{$title}' ({$width}x{$height}) to gallery ID {$gallery_id}.");

    echo json_encode([
        'success' => true,
        'photo' => [
            'id' => $photo_id,
            'filename' => $filename,
            'title' => $title,
            'width' => $width,
            'height' => $height
        ]
    ]);
    exit;
}

if ($method === 'DELETE') {
    require_role(['admin', 'curator']);
    $id = (int)($_GET['id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing photo ID']);
        exit;
    }

    $stmt = $db->prepare("SELECT filename, gallery_id, title FROM photos WHERE id = ?");
    $stmt->execute([$id]);
    $photo = $stmt->fetch();

    if ($photo) {
        $file = UPLOADS_DIR . $photo['filename'];
        if (file_exists($file)) {
            @unlink($file);
        }

        $del_stmt = $db->prepare("DELETE FROM photos WHERE id = ?");
        $del_stmt->execute([$id]);

        log_audit('DELETE_PHOTO', 'PHOTO', $id, "Deleted photo '{$photo['title']}' from gallery ID {$photo['gallery_id']}.");
    }

    echo json_encode(['success' => true]);
    exit;
}
