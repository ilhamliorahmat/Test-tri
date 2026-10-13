<?php
/**
 * Phortal API - Galleries Controller
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = get_db_connection();
$user = get_current_user_session();

if ($method === 'GET') {
    $gallery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $code_param = isset($_GET['code']) ? trim($_GET['code']) : '';

    if ($gallery_id > 0) {
        $stmt = $db->prepare("SELECT g.*, u.full_name as curator_name, w.text as watermark_text, w.opacity, w.font_size, w.position, w.color_hex 
                              FROM galleries g 
                              LEFT JOIN users u ON g.created_by = u.id 
                              LEFT JOIN watermarks w ON g.id = w.gallery_id 
                              WHERE g.id = ?");
        $stmt->execute([$gallery_id]);
        $gallery = $stmt->fetch();

        if (!$gallery) {
            http_response_code(404);
            echo json_encode(['error' => 'Gallery not found']);
            exit;
        }

        // Check code for private gallery
        $can_access = !$gallery['is_private'] || verify_gallery_access_code($gallery_id, $code_param);
        if (!$can_access) {
            http_response_code(403);
            echo json_encode(['error' => 'Gallery access code required', 'is_private' => true]);
            exit;
        }

        // Fetch photos
        $p_stmt = $db->prepare("SELECT id, title, caption, filename, file_size, width, height, uploaded_at FROM photos WHERE gallery_id = ? ORDER BY id DESC");
        $p_stmt->execute([$gallery_id]);
        $gallery['photos'] = $p_stmt->fetchAll();

        echo json_encode(['gallery' => $gallery]);
    } else {
        // List galleries
        if ($user && in_array($user['role'], ['admin', 'curator'])) {
            $stmt = $db->query("SELECT g.*, u.full_name as curator_name, (SELECT COUNT(*) FROM photos WHERE gallery_id = g.id) as photo_count 
                                FROM galleries g 
                                LEFT JOIN users u ON g.created_by = u.id 
                                ORDER BY g.created_at DESC");
            $galleries = $stmt->fetchAll();
        } else {
            // For public / guest view: list public galleries or unlocked private galleries
            $stmt = $db->query("SELECT g.id, g.title, g.description, g.is_private, g.created_at, u.full_name as curator_name, (SELECT COUNT(*) FROM photos WHERE gallery_id = g.id) as photo_count 
                                FROM galleries g 
                                LEFT JOIN users u ON g.created_by = u.id 
                                WHERE g.is_private = 0 
                                ORDER BY g.created_at DESC");
            $galleries = $stmt->fetchAll();

            // Append code if entered
            foreach ($galleries as &$g) {
                $g['unlocked'] = true;
            }
        }
        echo json_encode(['galleries' => $galleries]);
    }
    exit;
}

if ($method === 'POST') {
    // Verify guest gallery access code OR Create new gallery (Curator/Admin)
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;
    $action = $data['action'] ?? 'create';

    if ($action === 'verify_code') {
        $code = strtoupper(trim($data['code'] ?? ''));
        $stmt = $db->prepare("SELECT id, title, code, is_private FROM galleries WHERE UPPER(code) = UPPER(?)");
        $stmt->execute([$code]);
        $gallery = $stmt->fetch();

        if ($gallery) {
            $_SESSION['unlocked_galleries'][$gallery['id']] = true;
            log_audit('GUEST_ACCESS_GRANTED', 'GALLERY', $gallery['id'], "Guest unlocked gallery '{$gallery['title']}' with code.");
            echo json_encode([
                'success' => true,
                'gallery_id' => $gallery['id'],
                'title' => $gallery['title']
            ]);
        } else {
            log_audit('GUEST_ACCESS_DENIED', 'GALLERY', '0', "Invalid access code attempt: '{$code}'.");
            http_response_code(400);
            echo json_encode(['error' => 'Invalid gallery access code']);
        }
        exit;
    }

    // Require curator/admin to create gallery
    require_role(['admin', 'curator']);

    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $code = strtoupper(trim($data['code'] ?? ''));
    $is_private = isset($data['is_private']) ? (int)$data['is_private'] : 1;

    if (empty($title) || empty($code)) {
        http_response_code(400);
        echo json_encode(['error' => 'Title and unique access code are required']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO galleries (title, description, code, is_private, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $code, $is_private, $user['id']]);
        $gallery_id = $db->lastInsertId();

        // Create default watermark configuration for gallery
        $w_text = "© " . strtoupper($title) . " - PHORTAL";
        $w_stmt = $db->prepare("INSERT INTO watermarks (gallery_id, text, opacity, font_size, position) VALUES (?, ?, 50, 24, 'bottom-right')");
        $w_stmt->execute([$gallery_id, $w_text]);

        log_audit('CREATE_GALLERY', 'GALLERY', $gallery_id, "Created gallery '{$title}' with code '{$code}'.");

        echo json_encode(['success' => true, 'gallery_id' => $gallery_id]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(400);
            echo json_encode(['error' => 'Access code already exists. Please choose a unique code.']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create gallery']);
        }
    }
    exit;
}

if ($method === 'PUT') {
    require_role(['admin', 'curator']);
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $id = (int)($data['id'] ?? 0);
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $code = strtoupper(trim($data['code'] ?? ''));
    $is_private = (int)($data['is_private'] ?? 1);

    if (!$id || empty($title) || empty($code)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid parameters']);
        exit;
    }

    $stmt = $db->prepare("UPDATE galleries SET title = ?, description = ?, code = ?, is_private = ? WHERE id = ?");
    $stmt->execute([$title, $description, $code, $is_private, $id]);

    log_audit('UPDATE_GALLERY', 'GALLERY', $id, "Updated gallery '{$title}' details.");
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    require_role(['admin', 'curator']);
    $id = (int)($_GET['id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing gallery ID']);
        exit;
    }

    // Delete associated photo files from disk
    $p_stmt = $db->prepare("SELECT filename FROM photos WHERE gallery_id = ?");
    $p_stmt->execute([$id]);
    $photos = $p_stmt->fetchAll();
    foreach ($photos as $p) {
        $file = UPLOADS_DIR . $p['filename'];
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    $stmt = $db->prepare("DELETE FROM galleries WHERE id = ?");
    $stmt->execute([$id]);

    log_audit('DELETE_GALLERY', 'GALLERY', $id, "Deleted gallery ID {$id} and all contained photos.");
    echo json_encode(['success' => true]);
    exit;
}
