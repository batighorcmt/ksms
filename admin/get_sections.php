<?php
require_once '../config.php';

if (isset($_GET['class_id'])) {
    $class_id = intval($_GET['class_id']);
    
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? AND status='active'");
    $stmt->execute([$class_id]);
    $sections = $stmt->fetchAll();
    
    echo '<option value="">নির্বাচন করুন</option>';
    foreach ($sections as $section) {
        echo '<option value="' . $section['id'] . '">' . $section['name'] . '</option>';
    }
}
?>