<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');
$class_id = $_GET['class_id'] ?? null;
if (!$class_id) { echo json_encode([]); exit; }
$stmt = $pdo->prepare("SELECT s.id, s.name FROM subjects s JOIN class_subjects cs ON cs.subject_id = s.id WHERE cs.class_id = ?");
// Fallback: if class_subjects doesn't exist, try subjects table directly
try { $stmt->execute([$class_id]); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); }
catch(Exception $e) { $rows = $pdo->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC); }
echo json_encode($rows);
