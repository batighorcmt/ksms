<?php
require_once '../../config.php';
header('Content-Type: application/json');
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit;
}

$id = intval($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'error' => 'Invalid id']); exit; }

try {
    $stmt = $pdo->prepare('DELETE FROM certificate_issues WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
