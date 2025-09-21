<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    echo json_encode([]);
    exit;
}
$class_id = intval($_GET['class_id'] ?? 0);
if ($class_id > 0) {
    // Try to order by numeric_value if exists, else fallback to name
    $orderBy = 's.name ASC';
    $columns = $pdo->query("SHOW COLUMNS FROM subjects LIKE 'numeric_value'")->fetch();
    if ($columns) {
        $orderBy = 's.numeric_value ASC';
    }
    $stmt = $pdo->prepare("SELECT s.id, s.name FROM subjects s INNER JOIN class_subjects cs ON cs.subject_id = s.id WHERE cs.class_id = ? AND s.status = 'active' ORDER BY $orderBy");
    $stmt->execute([$class_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($subjects, JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode([]);
