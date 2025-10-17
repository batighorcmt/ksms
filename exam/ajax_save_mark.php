<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
if (!isAuthenticated()) { echo json_encode(['success'=>false,'message'=>'unauthenticated']); exit; }

$exam_id = intval($_POST['exam_id'] ?? 0);
$subject_id = intval($_POST['subject_id'] ?? 0);
$student_id = intval($_POST['student_id'] ?? 0);
$mark = $_POST['mark'] ?? null;

if (!$exam_id || !$subject_id || !$student_id) { echo json_encode(['success'=>false,'message'=>'invalid-input']); exit; }

try {
    // If current user is a teacher, ensure they are allowed to enter marks for this subject/class by routine
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') {
        // get class_id for the exam
        $ex = $pdo->prepare('SELECT class_id FROM exams WHERE id=? LIMIT 1');
        $ex->execute([$exam_id]);
        $classId = intval($ex->fetchColumn());
        if ($classId) {
            $teacherId = intval($_SESSION['user_id'] ?? 0);
            $chk = $pdo->prepare('SELECT 1 FROM routines WHERE class_id=? AND subject_id=? AND teacher_id=? LIMIT 1');
            $chk->execute([$classId, $subject_id, $teacherId]);
            if (!$chk->fetchColumn()) {
                echo json_encode(['success'=>false,'message'=>'not-allowed']);
                exit;
            }
        }
    }
    if ($mark === '' || $mark === null) {
        // delete mark if exists
        $d = $pdo->prepare('DELETE FROM marks WHERE exam_id=? AND subject_id=? AND student_id=?');
        $d->execute([$exam_id, $subject_id, $student_id]);
        echo json_encode(['success'=>true, 'message'=>'deleted']);
        exit;
    }
    if (!is_numeric($mark)) { echo json_encode(['success'=>false,'message'=>'invalid-mark']); exit; }
    // normalize to float and round to 2 decimals (support values like 53.25)
    $markVal = round(floatval($mark), 2);
    // clamp by subject max if defined
    $maxQ = $pdo->prepare('SELECT max_marks FROM exam_subjects WHERE exam_id=? AND subject_id=? LIMIT 1');
    $maxQ->execute([$exam_id, $subject_id]);
    $maxRow = $maxQ->fetch(PDO::FETCH_ASSOC);
    if ($maxRow && is_numeric($maxRow['max_marks'])) {
        $mx = floatval($maxRow['max_marks']);
        if ($markVal > $mx) $markVal = $mx;
        if ($markVal < 0) $markVal = 0;
    }
    // re-round after clamping to avoid artefacts like 59.999999
    $markVal = round($markVal, 2);

    // upsert: update if exists else insert
    $sel = $pdo->prepare('SELECT id FROM marks WHERE exam_id=? AND subject_id=? AND student_id=? LIMIT 1');
    $sel->execute([$exam_id, $subject_id, $student_id]);
    $id = $sel->fetchColumn();
    if ($id) {
        $u = $pdo->prepare('UPDATE marks SET marks_obtained=? WHERE id=?');
        $u->execute([$markVal, $id]);
    } else {
        $i = $pdo->prepare('INSERT INTO marks (exam_id, subject_id, student_id, marks_obtained) VALUES (?,?,?,?)');
        $i->execute([$exam_id, $subject_id, $student_id, $markVal]);
    }
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>'error']);
}
