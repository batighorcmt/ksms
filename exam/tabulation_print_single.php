<?php
require_once '../config.php';
require_once __DIR__ . '/../admin/inc/enrollment_helpers.php';
if (!isAuthenticated()) redirect('../login.php');

$exam_id = intval($_GET['exam_id'] ?? 0);
$class_id = intval($_GET['class_id'] ?? 0);
$year = intval($_GET['year'] ?? 0);

if (!$exam_id || !$class_id) {
    echo 'Missing parameters'; exit;
}

// helper
function getExamSubjectsPrint($pdo, $examId) {
    $s = $pdo->prepare("SELECT es.subject_id, sub.name as subject_name, es.max_marks, es.pass_marks FROM exam_subjects es JOIN subjects sub ON sub.id = es.subject_id WHERE es.exam_id = ? ORDER BY es.id");
    $s->execute([$examId]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

$exam = $pdo->prepare("SELECT e.*, et.name as exam_type_name FROM exams e LEFT JOIN exam_types et ON et.id = e.exam_type_id WHERE e.id = ?");
$exam->execute([$exam_id]);
$exam = $exam->fetch(PDO::FETCH_ASSOC);

// Load students via enrollment for the exam's academic year when available
$students = [];
if (!empty($exam) && !empty($exam['academic_year_id']) && function_exists('enrollment_table_exists') && enrollment_table_exists($pdo)) {
        $st = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, se.roll_number AS roll
                                                 FROM students s
                                                 JOIN students_enrollment se ON se.student_id = s.id
                                                 WHERE se.academic_year_id = ? AND se.class_id = ?
                                                     AND (se.status='active' OR se.status IS NULL OR se.status='Active' OR se.status=1 OR se.status='1')
                                                 ORDER BY se.roll_number ASC, s.id ASC");
        $st->execute([intval($exam['academic_year_id']), $class_id]);
        $students = $st->fetchAll(PDO::FETCH_ASSOC);
} else {
        $st = $pdo->prepare("SELECT id, first_name, last_name, roll_number AS roll FROM students WHERE class_id = ? AND status='active' ORDER BY roll_number, id");
        $st->execute([$class_id]);
        $students = $st->fetchAll(PDO::FETCH_ASSOC);
}

$subjects = getExamSubjectsPrint($pdo, $exam_id);

$marks_map = [];
if (!empty($students)) {
    $studentIds = array_column($students, 'id');
    $in = implode(',', array_fill(0, count($studentIds), '?'));
    $sql = "SELECT student_id, subject_id, marks_obtained FROM marks WHERE exam_id = ? AND student_id IN ($in)";
    $params = array_merge([$exam_id], $studentIds);
    $ms = $pdo->prepare($sql);
    $ms->execute($params);
    while ($r = $ms->fetch(PDO::FETCH_ASSOC)) {
        $marks_map[$r['student_id']][$r['subject_id']] = $r['marks_obtained'];
    }
}

include '../admin/print_common.php';
echo print_header($pdo, 'টেবুলেশন শীট - ' . ($exam['name'] ?? ''));

?>
<!-- ensure Bengali font for print -->
<link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
<style>body, table, th, td, h2 { font-family: 'SolaimanLipi','Source Sans Pro',sans-serif; }</style>

<?php
// compute totals and rankings (only for students who have marks)
$totals = [];
foreach ($students as $stu) {
    $total = 0; $has = 0;
    foreach ($subjects as $s) {
        $val = $marks_map[$stu['id']][$s['subject_id']] ?? null;
        if ($val !== null) { $total += floatval($val); $has++; }
    }
    if ($has > 0) $totals[$stu['id']] = $total;
}
// sort and assign ranks (standard competition ranking)
$rankings = [];
if (!empty($totals)) {
    arsort($totals);
    $i = 0; $prev = null; $displayRank = 0;
    foreach ($totals as $sid => $t) {
        $i++;
        if ($prev === null) { $displayRank = 1; }
        else { if ($t != $prev) $displayRank = $i; }
        $rankings[$sid] = $displayRank;
        $prev = $t;
    }
}

?>

<div style="margin-top:6px;">
    <h2 style="text-align:center;margin-bottom:8px;font-size:20px;">টেবুলেশন শীট - <?= htmlspecialchars($exam['name'] ?? '') ?></h2>
    <div style="overflow:auto;">
        <table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:13px;">
            <thead>
                <tr>
                    <th>মেধাক্রম</th>
                    <th>রোল</th>
                    <th>শিক্ষার্থী</th>
                    <?php foreach($subjects as $s): ?><th><?= htmlspecialchars($s['subject_name']) ?></th><?php endforeach; ?>
                    <th>মোট</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($students as $stu): $total=0; $has=0; ?>
                    <tr>
                        <td><?= isset($rankings[$stu['id']]) ? htmlspecialchars($rankings[$stu['id']]) : '-' ?></td>
                        <td><?= htmlspecialchars($stu['roll'] ?? $stu['id']) ?></td>
                        <td><?= htmlspecialchars(trim($stu['first_name'].' '.$stu['last_name'])) ?></td>
                        <?php foreach($subjects as $s):
                            $val = $marks_map[$stu['id']][$s['subject_id']] ?? null;
                            if ($val !== null) { $total += floatval($val); $has++; }
                        ?>
                            <td><?= $val===null?'-':htmlspecialchars($val) ?></td>
                        <?php endforeach; ?>
                        <td><?= $has?htmlspecialchars($total):'-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php echo print_footer(); ?>
