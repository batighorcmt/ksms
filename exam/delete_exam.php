<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) redirect('../login.php');
$id = intval($_GET['id'] ?? 0);
if($id){
  $pdo->prepare("DELETE FROM exams WHERE id=?")->execute([$id]);
}
header("Location: exam_list.php");
exit;
