<?php
require_once '../config.php';
if (empty($_GET['id'])) { header('Location: exam_list.php'); exit; }
$id = $_GET['id'];

try {
	// remove dependent rows first to satisfy foreign key constraints
	$pdo->beginTransaction();
	$pdo->prepare('DELETE FROM exam_subjects WHERE exam_id = ?')->execute([$id]);
	// linking table in DB is `exam_term_tutorial_links`
	$pdo->prepare('DELETE FROM exam_term_tutorial_links WHERE term_exam_id = ? OR tutorial_exam_id = ?')->execute([$id, $id]);
	$pdo->prepare('DELETE FROM exams WHERE id = ?')->execute([$id]);
	$pdo->commit();
	$_SESSION['success'] = 'Exam deleted successfully.';
} catch (Exception $e) {
	if ($pdo->inTransaction()) $pdo->rollBack();
	$_SESSION['error'] = 'Could not delete exam: ' . $e->getMessage();
}

header('Location: exam_list.php');
exit;
