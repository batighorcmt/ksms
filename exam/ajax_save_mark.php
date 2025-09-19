<?php
require_once '../config.php';
header('Content-Type: application/json');
if (!isAuthenticated()) { echo json_encode(['success'=>false,'error'=>'auth']); exit; }

$exam_id = intval($_POST['exam_id'] ?? 0);
$exam_subject_id = intval($_POST['sub_id'] ?? $_POST['sub_id'] ?? $_POST['sub'] ?? $_POST['sub_id']);
$student_id = intval($_POST['stu_id'] ?? $_POST['stu_id'] ?? $_POST['stu'] ?? $_POST['stu_id']);
$val = $_POST['val'] ?? $_POST['value'] ?? $_POST['val'] ?? null;
if($val==='') $val = 0;
$obt = floatval($val);

// normalize incoming keys (our front uses exam_id, sub, stu, val)
$exam_id = intval($_POST['exam_id'] ?? $_POST['exam_id']);
$exam_subject_id = intval($_POST['sub_id'] ?? $_POST['sub']);
$student_id = intval($_POST['stu_id'] ?? $_POST['stu']);
$obt = floatval($_POST['val'] ?? $_POST['value'] ?? 0);

if(!$exam_id || !$exam_subject_id || !$student_id) {
  echo json_encode(['success'=>false,'error'=>'invalid']);
  exit;
}

try {
  // insert or update
  $s = $pdo->prepare("SELECT id FROM marks WHERE exam_subject_id=? AND student_id=?");
  $s->execute([$exam_subject_id, $student_id]);
  $r = $s->fetch();
  if($r) {
    $pdo->prepare("UPDATE marks SET obtained_marks=?, entered_by=? WHERE id=?")->execute([$obt, $_SESSION['user_id'], $r['id']]);
  } else {
    // fetch exam id for referential integrity (optional)
    $es = $pdo->prepare("SELECT exam_id FROM exam_subjects WHERE id=?");
    $es->execute([$exam_subject_id]);
    $er = $es->fetch();
    $ref_exam_id = $er['exam_id'] ?? $exam_id;
    $pdo->prepare("INSERT INTO marks (exam_id, exam_subject_id, student_id, obtained_marks, entered_by) VALUES (?,?,?,?,?)")
        ->execute([$ref_exam_id, $exam_subject_id, $student_id, $obt, $_SESSION['user_id']]);
  }

  // update tabulation cache (recompute for this student & exam)
  require_once 'tabulation_compute_helper.php'; // see next file (function compute_tabulation_for_student)
  compute_tabulation_for_student($pdo, $ref_exam_id, $student_id);

  echo json_encode(['success'=>true]);
} catch (Exception $e) {
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
