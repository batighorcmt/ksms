<?php
require_once '../config.php';
if (!isAuthenticated()) redirect('../login.php');

$exam_id = intval($_GET['exam_id'] ?? 0);
$class_id = intval($_GET['class_id'] ?? 0);
$year = intval($_GET['year'] ?? 0);

if (!$exam_id || !$class_id) { echo 'Missing parameters'; exit; }

// load exam and linked tutorials
$stmt = $pdo->prepare("SELECT e.*, et.name as exam_type_name FROM exams e LEFT JOIN exam_types et ON et.id = e.exam_type_id WHERE e.id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

$lt = $pdo->prepare("SELECT l.tutorial_exam_id as id, e.name FROM exam_term_tutorial_links l JOIN exams e ON e.id = l.tutorial_exam_id WHERE l.term_exam_id = ?");
$lt->execute([$exam_id]);
$ltRows = $lt->fetchAll(PDO::FETCH_ASSOC);
$linked = []; $linked_map = [];
foreach ($ltRows as $r) { $linked[] = $r['id']; $linked_map[$r['id']] = $r['name']; }

$allExamIds = array_merge([$exam_id], $linked);

$students = $pdo->prepare("SELECT id, first_name, last_name, roll_number AS roll FROM students WHERE class_id = ? AND status='active' ORDER BY roll_number, id");
$students->execute([$class_id]);
$students = $students->fetchAll(PDO::FETCH_ASSOC);

// gather subjects union across exams
$combined_subjects = [];
foreach ($allExamIds as $eid) {
    $s = $pdo->prepare("SELECT es.subject_id, sub.name as subject_name FROM exam_subjects es JOIN subjects sub ON sub.id = es.subject_id WHERE es.exam_id = ? ORDER BY es.id");
    $s->execute([$eid]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) { $combined_subjects[$row['subject_id']] = $row['subject_name']; }
}

// fetch marks
$combined_marks = [];
if (!empty($students) && !empty($allExamIds)) {
    $studentIds = array_column($students, 'id');
    $examIn = implode(',', array_fill(0, count($allExamIds), '?'));
    $studentIn = implode(',', array_fill(0, count($studentIds), '?'));
    $sql = "SELECT exam_id, student_id, subject_id, marks_obtained FROM marks WHERE exam_id IN ($examIn) AND student_id IN ($studentIn)";
    $params = array_merge($allExamIds, $studentIds);
    $ms = $pdo->prepare($sql);
    $ms->execute($params);
    while ($r = $ms->fetch(PDO::FETCH_ASSOC)) {
        $combined_marks[$r['exam_id']][$r['student_id']][$r['subject_id']] = $r['marks_obtained'];
    }
}

include '../admin/print_common.php';
echo print_header($pdo, 'টেবুলেশন শীট - ' . ($exam['name'] ?? ''));

?>
<!-- ensure Bengali font for print -->
<link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
<style>body, table, th, td, h2 { font-family: 'SolaimanLipi','Source Sans Pro',sans-serif; }</style>

<?php
// compute combined totals per student
$totals = [];
foreach ($students as $stu) {
    $total = 0; $has = 0;
    foreach ($combined_subjects as $sid => $sname) {
        $m = $combined_marks[$exam_id][$stu['id']][$sid] ?? null;
        if ($m !== null) { $total += floatval($m); $has++; }
        foreach ($linked as $lid) {
            $tm = $combined_marks[$lid][$stu['id']][$sid] ?? null;
            if ($tm !== null) { $total += floatval($tm); $has++; }
        }
    }
    if ($has>0) $totals[$stu['id']] = $total;
}
// rankings
$rankings = [];
if (!empty($totals)) { arsort($totals); $i=0; $prev=null; $displayRank=0; foreach($totals as $sid=>$t){ $i++; if ($prev===null) $displayRank=1; else if ($t!=$prev) $displayRank=$i; $rankings[$sid]=$displayRank; $prev=$t; } }

?>

<div style="margin-top:6px;">
    <h2 style="text-align:center;margin-bottom:8px;font-size:20px;">টেবুলেশন শীট - <?= htmlspecialchars($exam['name'] ?? '') ?></h2>
    <div style="overflow:auto;">
        <table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:12px;">
            <thead>
                <tr>
                    <th rowspan="2">মেধাক্রম</th>
                    <th rowspan="2">রোল</th>
                    <th rowspan="2">শিক্ষার্থী</th>
                    <?php foreach($combined_subjects as $sid => $sname) { echo '<th colspan="'.(1+count($linked)).'">'.htmlspecialchars($sname).'</th>'; } ?>
                    <th rowspan="2">সর্বমোট</th>
                </tr>
                <tr>
                    <?php foreach($combined_subjects as $sid => $sname) {
                        echo '<th>'.htmlspecialchars($exam['name']).'</th>';
                        foreach ($linked as $lid) { echo '<th>'.htmlspecialchars($linked_map[$lid] ?? 'Tutorial').'</th>'; }
                    } ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($students as $stu): $combinedTotal=0; ?>
                    <tr>
                        <td><?= isset($rankings[$stu['id']])?htmlspecialchars($rankings[$stu['id']]):'-' ?></td>
                        <td><?= htmlspecialchars($stu['roll'] ?? $stu['id']) ?></td>
                        <td><?= htmlspecialchars(trim($stu['first_name'].' '.$stu['last_name'])) ?></td>
                        <?php foreach($combined_subjects as $sid => $sname) {
                            $m = $combined_marks[$exam_id][$stu['id']][$sid] ?? null; if ($m!==null) $combinedTotal+=floatval($m);
                            echo '<td>'.($m===null?'-':htmlspecialchars($m)).'</td>';
                            foreach ($linked as $lid) { $tm = $combined_marks[$lid][$stu['id']][$sid] ?? null; if ($tm!==null) $combinedTotal+=floatval($tm); echo '<td>'.($tm===null?'-':htmlspecialchars($tm)).'</td>'; }
                        } ?>
                        <td><?= htmlspecialchars($combinedTotal) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php echo print_footer(); ?>
