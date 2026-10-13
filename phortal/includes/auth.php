<?php
/**
 * Phortal - Authentication & Permission Middleware
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/audit.php';

function get_current_user_session() {
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'full_name' => $_SESSION['full_name']
        ];
    }
    return null;
}

function require_role($allowed_roles) {
    $user = get_current_user_session();
    if (!$user) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    if (!in_array($user['role'], (array)$allowed_roles)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    return $user;
}

function verify_gallery_access_code($gallery_id, $code) {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT id, title, code FROM galleries WHERE id = ?");
    $stmt->execute([$gallery_id]);
    $gallery = $stmt->fetch();

    if (!$gallery) {
        return false;
    }

    // Admins and curators have implicit access
    $user = get_current_user_session();
    if ($user && in_array($user['role'], ['admin', 'curator'])) {
        return true;
    }

    // Check code stored in guest session
    if (strcasecmp($gallery['code'], $code) === 0) {
        $_SESSION['unlocked_galleries'][$gallery_id] = true;
        return true;
    }

    if (isset($_SESSION['unlocked_galleries'][$gallery_id]) && $_SESSION['unlocked_galleries'][$gallery_id] === true) {
        return true;
    }

    return false;
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
