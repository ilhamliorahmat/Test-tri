<?php
/**
 * Phortal API - Users Management Controller (Admin Only)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

$user = require_role('admin');
$method = $_SERVER['REQUEST_METHOD'];
$db = get_db_connection();

if ($method === 'GET') {
    $stmt = $db->query("SELECT id, username, role, full_name, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
    echo json_encode(['users' => $users]);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    $full_name = trim($data['full_name'] ?? '');
    $role = trim($data['role'] ?? 'curator');

    if (empty($username) || empty($password) || empty($full_name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Username, password, and full name are required']);
        exit;
    }

    if (!in_array($role, ['admin', 'curator'])) {
        $role = 'curator';
    }

    try {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, full_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $hash, $role, $full_name]);
        $new_id = $db->lastInsertId();

        log_audit('CREATE_USER', 'USER', $new_id, "Admin created user '{$username}' as {$role}.");
        echo json_encode(['success' => true, 'id' => $new_id]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(400);
            echo json_encode(['error' => 'Username already exists']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create user']);
        }
    }
    exit;
}

if ($method === 'PUT') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $target_id = (int)($data['id'] ?? 0);
    $role = trim($data['role'] ?? '');
    $full_name = trim($data['full_name'] ?? '');

    if (!$target_id || !in_array($role, ['admin', 'curator'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user ID or role']);
        exit;
    }

    $stmt = $db->prepare("UPDATE users SET role = ?, full_name = ? WHERE id = ?");
    $stmt->execute([$role, $full_name, $target_id]);

    log_audit('UPDATE_USER_ROLE', 'USER', $target_id, "Updated user ID {$target_id} role to {$role}.");
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $target_id = (int)($_GET['id'] ?? 0);

    if (!$target_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user ID']);
        exit;
    }

    if ($target_id === $user['id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete your own admin account']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$target_id]);

    log_audit('DELETE_USER', 'USER', $target_id, "Admin deleted user ID {$target_id}.");
    echo json_encode(['success' => true]);
    exit;
}
