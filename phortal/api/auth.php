<?php
/**
 * Phortal API - Auth Controller
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

if ($action === 'login') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required']);
        exit;
    }

    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        log_audit('USER_LOGIN', 'USER', $user['id'], "User '{$user['username']}' logged in successfully as {$user['role']}.");

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'full_name' => $user['full_name']
            ]
        ]);
    } else {
        log_audit('LOGIN_FAILED', 'USER', '0', "Failed login attempt for username '{$username}'.");
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password']);
    }
    exit;
}

if ($action === 'logout') {
    $user = get_current_user_session();
    if ($user) {
        log_audit('USER_LOGOUT', 'USER', $user['id'], "User '{$user['username']}' logged out.");
    }
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// Default status
$user = get_current_user_session();
echo json_encode([
    'authenticated' => $user !== null,
    'user' => $user
]);
