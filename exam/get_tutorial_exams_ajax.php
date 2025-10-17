<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');
$year = $_GET['year'] ?? null;
$class_id = $_GET['class_id'] ?? null;
if (!$year || !$class_id) { echo json_encode([]); exit; }

// Find the tutorial-type id (try code or English name 'Tutorial', then Bengali name fallback)
$tidStmt = $pdo->prepare("SELECT id FROM exam_types WHERE code = 'Tutorial' OR name LIKE '%Tutorial%' OR name LIKE '%টিউটোর%' LIMIT 1");
$tidStmt->execute();
$tidRow = $tidStmt->fetch(PDO::FETCH_ASSOC);
$tutorial_type_id = $tidRow['id'] ?? null;

if (!$tutorial_type_id) { echo json_encode([]); exit; }

// Return tutorial exams along with total max marks (sum of subject max_marks)
$stmt = $pdo->prepare("SELECT e.id, e.name, IFNULL(SUM(es.max_marks),0) AS total_max_marks
	FROM exams e
	LEFT JOIN exam_subjects es ON es.exam_id = e.id
	WHERE e.academic_year_id = ? AND e.class_id = ? AND e.exam_type_id = ?
	GROUP BY e.id, e.name
	ORDER BY e.created_at DESC");
try {
	$stmt->execute([$year, $class_id, $tutorial_type_id]);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
	$rows = [];
}
echo json_encode($rows);
