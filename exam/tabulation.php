<?php
ob_start();
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) redirect('../login.php');

$exam_id = intval($_GET['exam_id'] ?? 0);
if(!$exam_id) { echo "Invalid"; exit; }

$exam = $pdo->prepare("SELECT e.*, c.name class_name, t.name type_name FROM exams e JOIN classes c ON e.class_id=c.id JOIN exam_types t ON e.exam_type_id=t.id WHERE e.id=?");
$exam->execute([$exam_id]); $exam = $exam->fetch();

$subjects = $pdo->prepare("SELECT es.*, sub.name subject_name, es.full_mark, es.pass_mark FROM exam_subjects es JOIN subjects sub ON es.subject_id=sub.id WHERE es.exam_id=?");
$subjects->execute([$exam_id]); $subjects = $subjects->fetchAll();

// Get all students for this class
$students = $pdo->prepare("SELECT * FROM students WHERE class_id=? AND status='active' ORDER BY roll_number ASC");
$students->execute([$exam['class_id']]);
$students = $students->fetchAll();

// Build tabulation from marks table
$tabulation = [];
foreach ($students as $stu) {
    $total = 0;
    $subjects_passed = 0;
    $subjects_failed = 0;
    $marks = [];
    $all_passed = true;
    foreach ($subjects as $s) {
        $m = $pdo->prepare("SELECT obtained_marks FROM marks WHERE exam_subject_id=? AND student_id=?");
        $m->execute([$s['id'], $stu['id']]);
        $mr = $m->fetch();
        $obt = $mr ? floatval($mr['obtained_marks']) : 0;
        $marks[] = $obt;
        $total += $obt;
        if ($obt >= floatval($s['pass_mark'])) {
            $subjects_passed++;
        } else {
            $subjects_failed++;
            $all_passed = false;
        }
    }
    $tabulation[] = [
        'roll_number' => $stu['roll_number'],
        'first_name' => $stu['first_name'],
        'last_name' => $stu['last_name'],
        'marks' => $marks,
        'total_marks' => $total,
        'subjects_passed' => $subjects_passed,
        'subjects_failed' => $subjects_failed,
        'result_status' => $all_passed ? 'PASSED' : 'FAILED',
    ];
}

// Sort by total_marks desc
usort($tabulation, function($a, $b) {
    return $b['total_marks'] <=> $a['total_marks'];
});
// Assign position
foreach ($tabulation as $i => &$row) {
    $row['position'] = $i+1;
}
unset($row);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>মার্ক এন্ট্রি</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <style>
    body { font-family: SolaimanLipi, Arial, sans-serif; }
    .card { border-radius: 10px; }
    .table th, .table td { vertical-align: middle; }
  </style>
</head>
<body>
<?php
include '../admin/inc/header.php';
include '../admin/inc/sidebar.php';
?>
<div class="content-wrapper p-3">
  <section class="content-header"><h1>Tabulation - <?= htmlspecialchars($exam['name']) ?></h1></section>
  <section class="content">
    <div class="card"><div class="card-body table-responsive">
      <table class="table table-bordered table-sm">
        <thead>
          <tr><th>Pos</th><th>Roll</th><th>Name</th>
          <?php foreach($subjects as $s): ?><th><?=htmlspecialchars($s['subject_name'])?></th><?php endforeach; ?>
          <th>Total</th><th>Passed Subjects</th><th>Failed Subjects</th><th>Result</th></tr>
        </thead>
        <tbody>
          <?php foreach($tabulation as $row): ?>
            <tr>
              <td><?= $row['position'] ?></td>
              <td><?= $row['roll_number'] ?></td>
              <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
              <?php foreach($row['marks'] as $obt): ?>
                <td><?= $obt ?></td>
              <?php endforeach; ?>
              <td><?= $row['total_marks'] ?></td>
              <td><?= $row['subjects_passed'] ?></td>
              <td><?= $row['subjects_failed'] ?></td>
              <td><?= strtoupper($row['result_status']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </section>
</div>
<?php include '../admin/inc/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
