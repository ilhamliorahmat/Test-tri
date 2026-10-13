<?php
/**
 * Phortal API - Administrative Audit Logs Controller
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db_connection();
$limit = min(500, max(10, (int)($_GET['limit'] ?? 100)));
$action_filter = trim($_GET['action'] ?? '');

if ($action_filter !== '') {
    $stmt = $db->prepare("SELECT * FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT ?");
    $stmt->execute([$action_filter, $limit]);
} else {
    $stmt = $db->prepare("SELECT * FROM audit_logs ORDER BY id DESC LIMIT ?");
    $stmt->execute([$limit]);
}

$logs = $stmt->fetchAll();
echo json_encode(['audit_logs' => $logs]);
