<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
if (!isAuthenticated()) { echo json_encode(['error'=>'unauthenticated']); exit; }

$exam_id = intval($_GET['exam_id'] ?? 0);
$class_id = intval($_GET['class_id'] ?? 0);
$subject_id = intval($_GET['subject_id'] ?? 0);

if (!$exam_id || !$subject_id) { echo json_encode(['students'=>[]]); exit; }

try {
    // Resolve class_id from exam to avoid mismatch
    $ex = $pdo->prepare("SELECT class_id FROM exams WHERE id=? LIMIT 1");
    $ex->execute([$exam_id]);
    $examRow = $ex->fetch(PDO::FETCH_ASSOC);
    $resolvedClassId = intval($examRow['class_id'] ?? 0) ?: $class_id;
    if (!$resolvedClassId) { echo json_encode(['students'=>[]]); exit; }

    // students in class (include common active variants if present)
    $st = $pdo->prepare("SELECT id, first_name, last_name, roll_number AS roll FROM students WHERE class_id=? AND (status='active' OR status='Active' OR status=1 OR status='1' OR status IS NULL) ORDER BY roll_number, id");
    $st->execute([$resolvedClassId]);
    $students = $st->fetchAll(PDO::FETCH_ASSOC);

    // existing marks for this exam/subject
    $mm = $pdo->prepare("SELECT student_id, marks_obtained FROM marks WHERE exam_id=? AND subject_id=?");
    $mm->execute([$exam_id, $subject_id]);
    $markMap = [];
    while ($r=$mm->fetch(PDO::FETCH_ASSOC)) { $markMap[$r['student_id']] = $r['marks_obtained']; }

    $out = ['students'=>[]];
    foreach ($students as $s) {
        $out['students'][] = [
            'id' => intval($s['id']),
            'roll' => $s['roll'],
            'name' => trim(($s['first_name']??'').' '.($s['last_name']??'')),
            'mark' => $markMap[$s['id']] ?? null,
        ];
    }
    echo json_encode($out);
} catch (Exception $e) {
    echo json_encode(['students'=>[], 'error'=>'exception']);
}
