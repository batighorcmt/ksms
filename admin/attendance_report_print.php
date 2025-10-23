<?php
require_once '../config.php';
require_once 'print_common.php';
require_once __DIR__ . '/inc/enrollment_helpers.php';

if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    header('location: login.php'); exit();
}

$type = $_GET['type'] ?? 'absent';
$class_id = isset($_GET['class_id']) && $_GET['class_id'] !== 'all' ? intval($_GET['class_id']) : 'all';
$section_id = isset($_GET['section_id']) && $_GET['section_id'] !== '' ? intval($_GET['section_id']) : '';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

// reuse the same query logic as attendance_report.php
$yearId = current_academic_year_id($pdo);
if ($type === 'present') {
    $sql = "SELECT s.id, s.first_name, s.last_name, se.roll_number, c.name as class_name, sec.name as section_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN students_enrollment se ON se.student_id = s.id AND se.academic_year_id = ?
        LEFT JOIN classes c ON c.id = se.class_id
        LEFT JOIN sections sec ON sec.id = se.section_id
        WHERE a.date = ? AND a.status = 'present'";
    $params = [$yearId, $date];
} elseif ($type === 'absent') {
    $sql = "SELECT s.id, s.first_name, s.last_name, se.roll_number, c.name as class_name, sec.name as section_name, a.remarks
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN students_enrollment se ON se.student_id = s.id AND se.academic_year_id = ?
        LEFT JOIN classes c ON c.id = se.class_id
        LEFT JOIN sections sec ON sec.id = se.section_id
        WHERE a.date = ? AND a.status = 'absent'";
    $params = [$yearId, $date];
} else {
    $sql = "SELECT s.id, s.first_name, s.last_name, se.roll_number, c.name as class_name, sec.name as section_name
        FROM students s
        JOIN students_enrollment se ON se.student_id = s.id AND se.academic_year_id = ?
        LEFT JOIN classes c ON c.id = se.class_id
        LEFT JOIN sections sec ON sec.id = se.section_id
        WHERE (se.status='active' OR se.status IS NULL) AND s.id NOT IN (SELECT student_id FROM attendance WHERE date = ?)";
    $params = [$yearId, $date];
}
if ($class_id !== 'all') { $sql .= " AND se.class_id = ?"; $params[] = $class_id; }
if ($section_id) { $sql .= " AND se.section_id = ?"; $params[] = $section_id; }
$sql .= " ORDER BY c.numeric_value ASC, sec.name ASC, se.roll_number ASC, s.first_name ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $results = $stmt->fetchAll();

?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance Report Print</title>
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body{font-family:'SolaimanLipi',sans-serif;color:#222} .table{width:100%;border-collapse:collapse} .table th,.table td{border:1px solid #e0e0e0;padding:8px}</style>
</head>
<body>
    <?php echo print_header($pdo, 'তারিখ: '.htmlspecialchars($date).' | টাইপ: '.htmlspecialchars($type)); ?>
    <table class="table">
        <thead>
            <tr><th>#</th><th>নাম</th><th>ক্লাস</th><th>শাখা</th><th>রোল</th><?php if($type==='absent') echo '<th>নোট</th>'; ?></tr>
        </thead>
        <tbody>
            <?php $i=1; foreach($results as $r): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></td>
                <td><?php echo htmlspecialchars($r['class_name']); ?></td>
                <td><?php echo htmlspecialchars($r['section_name']); ?></td>
                <td><?php echo htmlspecialchars($r['roll_number']); ?></td>
                <?php if($type==='absent'): ?><td><?php echo htmlspecialchars($r['remarks'] ?? ''); ?></td><?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php echo print_footer(); ?>
    <script>window.onload=function(){ window.print(); }</script>
</body>
</html>
