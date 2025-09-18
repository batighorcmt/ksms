<?php
ob_start();
require_once '../config.php';

$teacher_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// ছবি প্রোসেস
$file = null;
if(!empty($_POST['photo'])){
    $img = $_POST['photo'];
    $img = str_replace('data:image/jpeg;base64,','',$img);
    $img = str_replace(' ','+',$img);
    $data = base64_decode($img);
    $filename = $teacher_id."_".time().".jpg";

    // ফোল্ডার সঠিকভাবে সেট করুন
    $uploadDir = __DIR__."/../uploads/attendance/";
    if(!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

    $file = "uploads/attendance/".$filename;
    file_put_contents($uploadDir.$filename, $data);
}

$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;
$location = ($lat && $lng) ? $lat.",".$lng : null;

// আজকের রেকর্ড আছে কিনা
$stmt = $pdo->prepare("SELECT * FROM teacher_attendance WHERE teacher_id=? AND date=?");
$stmt->execute([$teacher_id, $today]);
$record = $stmt->fetch();

if(!$record){
    // Check-in
    $status = (date('H:i:s') > '09:15:00') ? 'late' : 'present';
    $pdo->prepare("INSERT INTO teacher_attendance 
      (teacher_id,date,check_in,status,check_in_photo,check_in_location) 
      VALUES (?,?,?,?,?,?)")
        ->execute([$teacher_id, $today, date('H:i:s'), $status, $file, $location]);
} elseif($record && !$record['check_out']){
    // Check-out
    $pdo->prepare("UPDATE teacher_attendance 
      SET check_out=?, check_out_photo=?, check_out_location=? 
      WHERE id=?")
        ->execute([date('H:i:s'), $file, $location, $record['id']]);
}

header("Location: teacher_attendance.php");
exit;
ob_end_flush();
