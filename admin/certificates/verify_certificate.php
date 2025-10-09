<?php

require_once '../../config.php';

// Get student ID from query (string, not int)
$student_id = isset($_GET['id']) ? trim($_GET['id']) : '';

if ($student_id === '') {
    echo '<h2 style="color:red;text-align:center;margin-top:40px;">শিক্ষার্থী আইডি প্রদান করা হয়নি।</h2>';
    exit;
}

// Fetch student data
$stmt = $pdo->prepare("
    SELECT s.*, c.name as class_name, sec.name as section_name 
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id 
    LEFT JOIN sections sec ON s.section_id = sec.id 
    WHERE s.student_id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    echo '<h2 style="color:red;text-align:center;margin-top:40px;">শিক্ষার্থী খুঁজে পাওয়া যায়নি।</h2>';
    exit;
}

// Fetch school info
$school_info = $pdo->query("SELECT * FROM school_info LIMIT 1")->fetch();
if (!$school_info) {
    $school_info = [
        'name' => 'আমাদের স্কুল',
        'address' => 'স্কুলের ঠিকানা',
        'phone' => '০১XXXXXXXXX',
        'email' => 'school@example.com'
    ];
}

?><!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>প্রত্যয়নপত্র যাচাইকরণ</title>
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        body { font-family: 'SolaimanLipi', Arial, sans-serif; background: #f5f5f5; }
        .verify-container { max-width: 500px; margin: 40px auto; background: #fff; box-shadow: 0 0 12px rgba(0,0,0,0.08); border-radius: 8px; padding: 32px 24px; text-align: center; }
        .school-name { font-size: 22px; font-weight: bold; color: #006400; margin-bottom: 8px; }
        .result { font-size: 18px; color: #222; margin: 18px 0; }
        .info-table { width: 100%; margin: 18px 0; border-collapse: collapse; }
        .info-table td { padding: 6px 10px; border-bottom: 1px solid #eee; text-align: left; }
        .label { color: #555; font-weight: bold; width: 120px; }
        .value { color: #222; }
        .success { color: #006400; font-weight: bold; }
        .fail { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="school-name"><?php echo htmlspecialchars($school_info['name']); ?></div>
        <div class="result success">✅ প্রত্যয়নপত্রটি বৈধ</div>
        <table class="info-table">
            <tr><td class="label">শিক্ষার্থীর নাম</td><td class="value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td></tr>
            <tr><td class="label">শ্রেণি ও শাখা</td><td class="value"><?php echo htmlspecialchars($student['class_name']); if (!empty($student['section_name'])) echo ' (' . htmlspecialchars($student['section_name']) . ')'; ?></td></tr>
            <tr><td class="label">রোল নম্বর</td><td class="value"><?php echo htmlspecialchars($student['roll_number']); ?></td></tr>
            <tr><td class="label">স্টুডেন্ট আইডি</td><td class="value"><?php echo htmlspecialchars($student['student_id']); ?></td></tr>
            <tr><td class="label">জন্ম তারিখ</td><td class="value"><?php echo !empty($student['date_of_birth']) ? date('d/m/Y', strtotime($student['date_of_birth'])) : 'প্রদান করা হয়নি'; ?></td></tr>
        </table>
        <div style="margin-top:18px;color:#666;font-size:14px;">বিদ্যালয়ের তথ্য: <?php echo htmlspecialchars($school_info['address']); ?> | ফোন: <?php echo htmlspecialchars($school_info['phone']); ?></div>
    </div>
</body>
</html>
