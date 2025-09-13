<?php
require_once '../config.php';

// Authentication: allow super_admin and teacher to print
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// Fetch students same as students.php
$stmt = $pdo->query("SELECT s.*, c.name as class_name, sec.name as section_name, u.full_name as guardian_name 
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id 
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN users u ON s.guardian_id = u.id
    ORDER BY s.id DESC");
$students = $stmt->fetchAll();

include 'print_common.php';
?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষার্থীদের তালিকা - প্রিন্ট</title>
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        body { font-family: 'SolaimanLipi', sans-serif; color:#111 }
        table { width:100%; border-collapse:collapse; font-size:13px }
        th, td { border:1px solid #ccc; padding:6px 8px; text-align:left }
        th { background:#f5f5f5 }
        .photo { width:60px; height:80px; object-fit:cover }
        .meta { margin-bottom:8px }
        @media print { .no-print { display:none } }
    </style>
</head>
<body>
<div class="print-container">
    <?php echo print_header($pdo, 'শিক্ষার্থীদের তালিকা'); ?>

    <div class="meta">
        <strong>মোট শিক্ষার্থী: </strong><?php echo count($students); ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>ছবি</th>
                <th>আইডি</th>
                <th>নাম</th>
                <th>পিতার নাম</th>
                <th>মোবাইল</th>
                <th>ক্লাস (শাখা)</th>
                <th>রোল</th>
                <th>স্ট্যাটাস</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($students as $student): ?>
            <tr>
                <td>
                    <?php if(!empty($student['photo'])): ?>
                        <img src="<?php echo BASE_URL . 'uploads/students/' . $student['photo']; ?>" class="photo" alt="ছবি">
                    <?php else: ?>
                        <div style="width:60px;height:80px;background:#eee;display:inline-block"></div>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                <td><?php echo htmlspecialchars($student['mobile_number']); ?></td>
                <td><?php echo htmlspecialchars($student['class_name'] . ' (' . $student['section_name'] . ')'); ?></td>
                <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                <td><?php echo ($student['status'] == 'active') ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php echo print_footer(); ?>
</div>

<script>
    // trigger print once content is ready
    window.addEventListener('load', function(){
        setTimeout(function(){ window.print(); }, 300);
    });
</script>
</body>
</html>
