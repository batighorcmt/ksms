<?php
require_once '../config.php';
require_once 'print_common.php';

// Authentication - allow super_admin and teacher
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    header('location: login.php');
    exit();
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

$class = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$class->execute([$class_id]);
$class = $class->fetch();

$section = null;
if ($section_id) {
    $section_stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
    $section_stmt->execute([$section_id]);
    $section = $section_stmt->fetch();
}

if ($class_id && $section_id) {
    $routine = $pdo->prepare("SELECT r.*, s.name as subject_name, u.full_name as teacher_name FROM routines r LEFT JOIN subjects s ON r.subject_id = s.id LEFT JOIN users u ON r.teacher_id = u.id WHERE r.class_id = ? AND r.section_id = ? ORDER BY FIELD(r.day_of_week, 'saturday','sunday','monday','tuesday','wednesday','thursday','friday'), r.period_number");
    $routine->execute([$class_id, $section_id]);
    $routine_data = $routine->fetchAll();
} elseif ($class_id) {
    $routine = $pdo->prepare("SELECT r.*, s.name as subject_name, u.full_name as teacher_name, sec.name as section_name FROM routines r LEFT JOIN subjects s ON r.subject_id = s.id LEFT JOIN users u ON r.teacher_id = u.id LEFT JOIN sections sec ON r.section_id = sec.id WHERE r.class_id = ? ORDER BY sec.name, FIELD(r.day_of_week, 'saturday','sunday','monday','tuesday','wednesday','thursday','friday'), r.period_number");
    $routine->execute([$class_id]);
    $routine_data = $routine->fetchAll();
} else {
    echo "Class required"; exit;
}

$day_names = [
    'saturday' => 'শনিবার',
    'sunday' => 'রবিবার',
    'monday' => 'সোমবার',
    'tuesday' => 'মঙ্গলবার',
    'wednesday' => 'বুধবার',
    'thursday' => 'বৃহস্পতিবার',
    'friday' => 'শুক্রবার'
];

// group
$grouped_routine = [];
foreach ($routine_data as $r) {
    $day = $r['day_of_week'];
    if (!isset($grouped_routine[$day])) $grouped_routine[$day] = [];
    $grouped_routine[$day][$r['period_number']] = $r;
}

// Read institute info from school_info table
$school_info = $pdo->query("SELECT * FROM school_info LIMIT 1")->fetch();
$inst_name = $school_info['name'] ?? 'আপনার প্রতিষ্ঠান';
$inst_address = $school_info['address'] ?? 'ঠিকানা';
$inst_contact = '';
if (!empty($school_info['phone'])) $inst_contact .= 'ফোন: ' . $school_info['phone'];
if (!empty($school_info['email'])) $inst_contact .= ($inst_contact ? ' | ' : '') . 'ইমেল: ' . $school_info['email'];
$inst_logo = !empty($school_info['logo']) ? (BASE_URL . 'uploads/logo/' . $school_info['logo']) : '';

// determine max period: prefer stored class_periods value, else compute from data (minimum 1)
$maxP = 1;
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_periods (id INT AUTO_INCREMENT PRIMARY KEY, class_id INT NOT NULL, section_id INT NOT NULL, period_count INT NOT NULL DEFAULT 1, UNIQUE KEY(class_id, section_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pcStmt = $pdo->prepare("SELECT period_count FROM class_periods WHERE class_id = ? AND section_id = ?");
    $pcStmt->execute([$class_id, $section_id]);
    $storedPc = intval($pcStmt->fetchColumn());
    if ($storedPc > 0) $maxP = $storedPc;
} catch (Exception $e) {
    // ignore, fallback to computing from data
}
foreach($routine_data as $r) {
    if (intval($r['period_number']) > $maxP) $maxP = intval($r['period_number']);
}
if ($maxP < 1) $maxP = 1;

?>

<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>প্রিন্ট রুটিন - <?php echo htmlspecialchars($class['name']); ?></title>
</head>
<link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
<style>
body { font-family: 'SolaimanLipi', sans-serif; color:#222; }
.container { max-width: 1100px; margin: 18px auto; }
.header { display:flex; align-items:center; gap:16px; margin-bottom:8px; }
.logo { width:90px; height:90px; flex:0 0 90px; }
.header-center { text-align:center; flex:1; }
.inst-name { font-size:1.5rem; font-weight:700; margin-bottom:4px; }
.inst-meta { font-size:0.95rem; color:#444; }
.print-meta { margin-top:6px; font-size:0.9rem; color:#555 }
.table { width:100%; border-collapse: collapse; margin-top:18px; font-size:0.95rem; }
.table th, .table td { border:1px solid #e0e0e0; padding:10px; vertical-align:top; }
.table thead th { background:linear-gradient(180deg,#f8fafc,#eef2ff); border-bottom:2px solid #d1d5ff; }
.table tbody tr:nth-child(odd) { background:#fbfbff }
.subject { font-weight:600; color:#1f2937 }
.teacher { color:#374151 }
.time { color:#6b7280; font-size:0.9rem }
.section-note { color:#0f172a; font-size:0.85rem }
.no-data { text-align:center; color:#6b7280; padding:18px }
.no-print { margin-top:12px; text-align:center }
@media print { .no-print { display:none } body { margin:0 } }
</style>
</head>
</head>
<body>
<div class="container">
    <?php echo print_header($pdo, 'শ্রেণি: ' . htmlspecialchars($class['name']) . ($section ? ' - ' . htmlspecialchars($section['name']) . ' শাখা' : '') ); ?>

    <table class="table">
        <thead>
            <tr>
                <th>পিরিয়ড \ দিন</th>
                <?php foreach(['saturday','sunday','monday','tuesday','wednesday','thursday','friday'] as $d): ?>
                    <th><?php echo $day_names[$d]; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php for($p=1;$p<=$maxP;$p++): ?>
                <tr>
                    <th>পিরিয়ড <?php echo $p; ?></th>
                    <?php foreach(['saturday','sunday','monday','tuesday','wednesday','thursday','friday'] as $d): ?>
                        <td>
                            <?php if(isset($grouped_routine[$d][$p])): $cell = $grouped_routine[$d][$p]; ?>
                                <div class="subject"><?php echo htmlspecialchars($cell['subject_name']); ?></div>
                                <div class="teacher"><?php echo htmlspecialchars($cell['teacher_name']); ?></div>
                                <div class="time"><?php echo date('h:i A', strtotime($cell['start_time'])); ?> - <?php echo date('h:i A', strtotime($cell['end_time'])); ?></div>
                                <?php if(!$section_id && isset($cell['section_name'])): ?><div class="section-note">শাখা: <?php echo htmlspecialchars($cell['section_name']); ?></div><?php endif; ?>
                            <?php else: ?>
                                <div class="no-data">-</div>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <?php echo print_footer(); ?>

    <div class="no-print" style="margin-top:12px; text-align:center;">
        <button onclick="window.print()">প্রিন্ট করুন</button>
        <a href="routine_details.php?class_id=<?php echo $class_id; ?><?php echo $section_id ? '&section_id='.$section_id : ''; ?>">বাতিল</a>
    </div>
</div>
</body>
</html>
