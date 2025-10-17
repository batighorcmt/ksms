<?php
require_once '../config.php';
if (!isAuthenticated()) redirect('../login.php');

$exam_id = intval($_GET['exam_id'] ?? 0);
$class_id = intval($_GET['class_id'] ?? 0);

if (!$exam_id || !$class_id) { echo 'Missing parameters'; exit; }

$stmt = $pdo->prepare("SELECT e.*, et.name as exam_type_name FROM exams e LEFT JOIN exam_types et ON et.id = e.exam_type_id WHERE e.id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

$students = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND status='active'");
$students->execute([$class_id]);
$studentIds = array_column($students->fetchAll(PDO::FETCH_ASSOC), 'id');

$subjects = $pdo->prepare("SELECT es.subject_id, sub.name as subject_name FROM exam_subjects es JOIN subjects sub ON sub.id = es.subject_id WHERE es.exam_id = ? ORDER BY es.id");
$subjects->execute([$exam_id]);
$subjects = $subjects->fetchAll(PDO::FETCH_ASSOC);

$subject_stats = [];
if (!empty($studentIds)) {
    foreach ($subjects as $s) {
        $sid = $s['subject_id'];
        $in = implode(',', array_fill(0, count($studentIds), '?'));
        $sql = "SELECT marks_obtained FROM marks WHERE exam_id = ? AND subject_id = ? AND student_id IN ($in)";
        $params = array_merge([$exam_id, $sid], $studentIds);
        $ms = $pdo->prepare($sql);
        $ms->execute($params);
        $vals = array_map('floatval', array_column($ms->fetchAll(PDO::FETCH_ASSOC), 'marks_obtained'));
        if (!empty($vals)) {
            $subject_stats[] = ['name'=>$s['subject_name'],'avg'=>array_sum($vals)/count($vals),'max'=>max($vals),'min'=>min($vals),'count'=>count($vals)];
        } else {
            $subject_stats[] = ['name'=>$s['subject_name'],'avg'=>0,'max'=>0,'min'=>0,'count'=>0];
        }
    }
}

include '../admin/print_common.php';
echo print_header($pdo, 'টেবুলেশন শীট - ' . ($exam['name'] ?? ''));

?>
<!-- ensure Bengali font for print -->
<link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
<style>body, table, th, td, h2 { font-family: 'SolaimanLipi','Source Sans Pro',sans-serif; }</style>

<?php
// compute top performers by total average across subjects
$topList = [];
foreach ($subjects as $s) {
    // handled in subject_stats already
}
// if we have subject_stats, compute a simple total average per subject set per student
if (!empty($studentIds) && !empty($subjects)) {
    // fetch per-student total across all subjects
    $studentTotals = [];
    foreach ($studentIds as $sid) $studentTotals[$sid] = 0;
    foreach ($subjects as $s) {
        $subId = $s['subject_id'];
        $in = implode(',', array_fill(0, count($studentIds), '?'));
        $sql = "SELECT student_id, marks_obtained FROM marks WHERE exam_id = ? AND subject_id = ? AND student_id IN ($in)";
        $params = array_merge([$exam_id, $subId], $studentIds);
        $ms = $pdo->prepare($sql);
        $ms->execute($params);
        while ($r = $ms->fetch(PDO::FETCH_ASSOC)) { $studentTotals[$r['student_id']] += floatval($r['marks_obtained']); }
    }
    // compute averages and rank
    $studentAverages = [];
    $subCount = count($subjects);
    foreach ($studentTotals as $sid => $tot) { $studentAverages[$sid] = $subCount? $tot / $subCount : 0; }
    arsort($studentAverages);
    $i=0; $prev=null; $displayRank=0;
    foreach ($studentAverages as $sid=>$avg) { $i++; if ($prev===null) $displayRank=1; else if ($avg!=$prev) $displayRank=$i; $topList[]=['student_id'=>$sid,'avg'=>$avg,'rank'=>$displayRank]; $prev=$avg; }
}

?>

<div style="margin-top:6px;">
    <h2 style="text-align:center;margin-bottom:8px;font-size:20px;">টেবুলেশন শীট - <?= htmlspecialchars($exam['name'] ?? '') ?></h2>
    <div style="overflow:auto;">
        <?php if (!empty($topList)): ?>
            <div style="margin-bottom:12px;">
                <strong>সেরা শিক্ষার্থী (Top 5)</strong>
                <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px;margin-top:6px;">
                    <thead><tr><th>র‌্যাংক</th><th>শিক্ষার্থী</th><th>গড়</th></tr></thead>
                    <tbody>
                        <?php $cnt=0; foreach($topList as $t) { if ($cnt++>=5) break; $stu = $pdo->prepare('SELECT first_name,last_name,roll_number FROM students WHERE id = ?'); $stu->execute([$t['student_id']]); $st = $stu->fetch(PDO::FETCH_ASSOC); ?>
                        <tr>
                            <td><?= htmlspecialchars($t['rank']) ?></td>
                            <td><?= htmlspecialchars(($st['roll_number'] ?? '') . ' - ' . trim(($st['first_name']??'').' '.($st['last_name']??''))) ?></td>
                            <td><?= number_format($t['avg'],2) ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:13px;">
            <thead><tr><th>Subject</th><th>Average</th><th>Highest</th><th>Lowest</th><th>Count</th></tr></thead>
            <tbody>
                <?php foreach($subject_stats as $stat): ?>
                    <tr>
                        <td><?= htmlspecialchars($stat['name']) ?></td>
                        <td><?= number_format($stat['avg'],2) ?></td>
                        <td><?= htmlspecialchars($stat['max']) ?></td>
                        <td><?= htmlspecialchars($stat['min']) ?></td>
                        <td><?= htmlspecialchars($stat['count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php echo print_footer(); ?>
