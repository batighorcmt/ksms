<?php
ob_start();
require_once '../config.php';


$teacher_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Fetch attendance time settings
$settings = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'teacher_attendance_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$checkin_start = $settings['teacher_attendance_checkin_start'] ?? '08:00:00';
$checkin_end = $settings['teacher_attendance_checkin_end'] ?? '09:30:00';
$checkout_start = $settings['teacher_attendance_checkout_start'] ?? '13:00:00';
$checkout_end = $settings['teacher_attendance_checkout_end'] ?? '16:00:00';

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
$action = $_POST['action'] ?? '';

// আজকের রেকর্ড আছে কিনা
$stmt = $pdo->prepare("SELECT * FROM teacher_attendance WHERE teacher_id=? AND date=?");
$stmt->execute([$teacher_id, $today]);
$record = $stmt->fetch();


if($action === 'check_in' && !$record) {
    // Check-in
    $now = date('H:i:s');
    if ($now < $checkin_start) {
        $status = 'early';
    } elseif ($now > $checkin_end) {
        $status = 'late';
    } else {
        $status = 'present';
    }
    $pdo->prepare("INSERT INTO teacher_attendance 
      (teacher_id,date,check_in,status,check_in_photo,check_in_location) 
      VALUES (?,?,?,?,?,?)")
        ->execute([$teacher_id, $today, $now, $status, $file, $location]);
} elseif($action === 'check_out' && $record && !$record['check_out']) {
    // Check-out
    $now = date('H:i:s');
    $pdo->prepare("UPDATE teacher_attendance 
      SET check_out=?, check_out_photo=?, check_out_location=? 
      WHERE id=?")
        ->execute([$now, $file, $location, $record['id']]);
}

header("Location: teacher_attendance.php");
exit;
ob_end_flush();
?>