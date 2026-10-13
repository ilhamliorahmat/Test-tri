<?php
/**
 * Phortal - Audit Logging Engine
 */

require_once __DIR__ . '/../config/db.php';

function log_audit($action, $target_type, $target_id, $details = '') {
    $db = get_db_connection();
    
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'guest_visitor';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    try {
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, username, action, target_type, target_id, details, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $username,
            $action,
            $target_type,
            (string)$target_id,
            $details,
            $ip_address
        ]);
    } catch (Exception $e) {
        error_log('Audit logging failed: ' . $e->getMessage());
    }
}
