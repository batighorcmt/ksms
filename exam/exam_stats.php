<?php
ob_start();
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) redirect('../login.php');

$exam_id = intval($_GET['exam_id'] ?? 0);
if(!$exam_id) { echo "Invalid"; exit; }

$exam = $pdo->prepare("SELECT e.*, c.name class_name, t.name type_name FROM exams e JOIN classes c ON e.class_id=c.id JOIN exam_types t ON e.exam_type_id=t.id WHERE e.id=?");
$exam->execute([$exam_id]); $exam = $exam->fetch();

$subjects = $pdo->prepare("
  SELECT es.*, sub.name subject_name, es.full_mark, es.pass_mark
  FROM exam_subjects es
  JOIN subjects sub ON es.subject_id=sub.id
  JOIN class_subjects cs ON cs.subject_id = es.subject_id AND cs.class_id = ?
  WHERE es.exam_id=?
  ORDER BY cs.numeric_value ASC, cs.id ASC
");
$subjects->execute([$exam['class_id'], $exam_id]);
$subjects = $subjects->fetchAll();

$students = $pdo->prepare("SELECT * FROM students WHERE class_id=? AND status='active' ORDER BY roll_number ASC");
$students->execute([$exam['class_id']]);
$students = $students->fetchAll();

// Build statistics
$total_students = count($students);
$total_passed = 0;
$total_failed = 0;
$subject_stats = [];
$failed_students = [];
$highest_marks = array_fill_keys(array_column($subjects, 'id'), 0);
$subject_pass_count = array_fill_keys(array_column($subjects, 'id'), 0);
$subject_fail_count = array_fill_keys(array_column($subjects, 'id'), 0);

foreach ($students as $stu) {
    $stu_passed = true;
    $stu_marks = [];
    $stu_failed_subjects = [];
    foreach ($subjects as $s) {
        $m = $pdo->prepare("SELECT obtained_marks FROM marks WHERE exam_subject_id=? AND student_id=?");
        $m->execute([$s['id'], $stu['id']]);
        $mr = $m->fetch();
        $obt = $mr ? floatval($mr['obtained_marks']) : 0.00;
        $obt = number_format($obt, 2, '.', '');
        $stu_marks[$s['id']] = $obt;
        if ((float)$obt > $highest_marks[$s['id']]) $highest_marks[$s['id']] = (float)$obt;
        if ((float)$obt >= floatval($s['pass_mark'])) {
            $subject_pass_count[$s['id']]++;
        } else {
            $subject_fail_count[$s['id']]++;
            $stu_passed = false;
            $stu_failed_subjects[] = $s['subject_name'];
        }
    }
    if ($stu_passed) {
        $total_passed++;
    } else {
        $total_failed++;
        $failed_students[] = [
            'roll_number' => $stu['roll_number'],
            'name' => $stu['first_name'].' '.$stu['last_name'],
            'failed_subjects' => $stu_failed_subjects
        ];
    }
}

?><!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Exam Statistics</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <style>
    body { font-family: SolaimanLipi, Arial, sans-serif; }
    .card { border-radius: 10px; }
    .table th, .table td { vertical-align: middle; }
    .chart-container { width: 100%; max-width: 600px; margin: 0 auto; }
    @media print {.no-print{display:none!important;}}
  </style>
</head>
<body>
<?php include '../admin/inc/header.php'; ?>
<?php include '../admin/inc/sidebar.php'; ?>
<div class="content-wrapper p-3">
  <section class="content-header"><h1>Exam Statistics - <?= htmlspecialchars($exam['name']) ?></h1></section>
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-6">
          <div class="card mb-3">
            <div class="card-header bg-primary text-white"><b>Overall Statistics</b></div>
            <div class="card-body">
              <table class="table table-bordered">
                <tr><th>Total Students</th><td><?= $total_students ?></td></tr>
                <tr><th>Passed</th><td><?= $total_passed ?></td></tr>
                <tr><th>Failed</th><td><?= $total_failed ?></td></tr>
              </table>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card mb-3">
            <div class="card-header bg-success text-white"><b>Subject-wise Pass/Fail</b></div>
            <div class="card-body">
              <table class="table table-bordered">
                <thead><tr><th>Subject</th><th>Passed</th><th>Failed</th><th>Highest</th></tr></thead>
                <tbody>
                  <?php foreach($subjects as $s): ?>
                  <tr>
                    <td><?= htmlspecialchars($s['subject_name']) ?></td>
                    <td><?= $subject_pass_count[$s['id']] ?></td>
                    <td><?= $subject_fail_count[$s['id']] ?></td>
                    <td><?= number_format($highest_marks[$s['id']],2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <div class="card mb-3">
            <div class="card-header bg-danger text-white"><b>Failed Students & Subjects</b></div>
            <div class="card-body">
              <table class="table table-bordered">
                <thead><tr><th>Roll</th><th>Name</th><th>Failed Subjects</th></tr></thead>
                <tbody>
                  <?php foreach($failed_students as $fs): ?>
                  <tr>
                    <td><?= htmlspecialchars($fs['roll_number']) ?></td>
                    <td><?= htmlspecialchars($fs['name']) ?></td>
                    <td><?= htmlspecialchars(implode(', ', $fs['failed_subjects'])) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(empty($failed_students)): ?>
                  <tr><td colspan="3" class="text-center text-success">No failed students!</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <div class="card mb-3">
            <div class="card-header bg-info text-white"><b>Charts</b></div>
            <div class="card-body">
              <div class="chart-container">
                <canvas id="passFailPie"></canvas>
              </div>
              <div class="chart-container mt-4">
                <canvas id="subjectBar"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="no-print" style="text-align:center; margin:18px 0;">
        <button onclick="window.print()" class="btn btn-primary"><i class="fa fa-print"></i> Print</button>
        <a href="exam_list.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to List</a>
      </div>
    </div>
  </section>
</div>
<?php include '../admin/inc/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Pie chart for pass/fail
const passFailPie = new Chart(document.getElementById('passFailPie'), {
  type: 'pie',
  data: {
    labels: ['Passed', 'Failed'],
    datasets: [{
      data: [<?= $total_passed ?>, <?= $total_failed ?>],
      backgroundColor: ['#28a745', '#dc3545']
    }]
  },
  options: {responsive:true, plugins:{legend:{position:'bottom'}}}
});
// Bar chart for subject-wise pass
const subjectBar = new Chart(document.getElementById('subjectBar'), {
  type: 'bar',
  data: {
    labels: [<?php foreach($subjects as $s){echo "'".addslashes($s['subject_name'])."',";}?>],
    datasets: [
      {
        label: 'Passed',
        data: [<?php foreach($subjects as $s){echo $subject_pass_count[$s['id']].",";}?>],
        backgroundColor: '#28a745'
      },
      {
        label: 'Failed',
        data: [<?php foreach($subjects as $s){echo $subject_fail_count[$s['id']].",";}?>],
        backgroundColor: '#dc3545'
      }
    ]
  },
  options: {responsive:true, plugins:{legend:{position:'bottom'}}}
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>
