<?php
ob_start();
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) redirect('../login.php');
include '../admin/inc/header.php';
include '../admin/inc/sidebar.php';

$exam_id = intval($_GET['id'] ?? 0);
if (!$exam_id) {
    $_SESSION['error'] = "Exam not selected.";
    header("Location: exam_list.php");
    exit;
}

// Exam basic info
$stmt = $pdo->prepare("
    SELECT e.*, c.name as class_name, t.name as type_name, u.full_name as creator
    FROM exams e
    JOIN classes c ON e.class_id = c.id
    JOIN exam_types t ON e.exam_type_id = t.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.id=?
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    $_SESSION['error'] = "Exam not found.";
    header("Location: exam_list.php");
    exit;
}

// Subject-wise schedule
$subs = $pdo->prepare("
    SELECT es.*, s.name as subject_name
    FROM exam_subjects es
    JOIN subjects s ON es.subject_id = s.id
    WHERE es.exam_id=?
    ORDER BY es.exam_date, s.name
");
$subs->execute([$exam_id]);
$exam_subjects = $subs->fetchAll();
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
  <section class="content-header">
    <h1>Exam Details: <?= htmlspecialchars($exam['name']) ?></h1>
  </section>
  <section class="content">
    <div class="container-fluid">

      <!-- Exam Info Card -->
      <div class="card mb-3">
        <div class="card-header bg-primary text-white">
          <h3 class="card-title"><i class="fas fa-book"></i> Basic Information</h3>
        </div>
        <div class="card-body">
          <table class="table table-bordered">
            <tr><th>Exam Name</th><td><?= htmlspecialchars($exam['name']) ?></td></tr>
            <tr><th>Class</th><td><?= htmlspecialchars($exam['class_name']) ?></td></tr>
            <tr><th>Year</th><td><?= htmlspecialchars($exam['academic_year']) ?></td></tr>
            <tr><th>Exam Type</th><td><?= htmlspecialchars($exam['type_name']) ?></td></tr>
            <tr><th>Result Release Date</th><td><?= $exam['result_release_date'] ? date('d M Y', strtotime($exam['result_release_date'])) : '-' ?></td></tr>
            <tr><th>Created By</th><td><?= htmlspecialchars($exam['creator'] ?? '-') ?></td></tr>
            <tr><th>Created At</th><td><?= date('d M Y h:i A', strtotime($exam['created_at'])) ?></td></tr>
            <?php if($exam['updated_at']): ?>
            <tr><th>Last Updated</th><td><?= date('d M Y h:i A', strtotime($exam['updated_at'])) ?></td></tr>
            <?php endif; ?>
          </table>
        </div>
      </div>

      <!-- Subject Schedule -->
      <div class="card">
        <div class="card-header bg-success text-white">
          <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Subject-wise Schedule & Marks</h3>
        </div>
        <div class="card-body table-responsive">
          <table class="table table-striped table-bordered">
            <thead class="thead-dark">
              <tr>
                <th>#</th>
                <th>Subject</th>
                <th>Exam Date</th>
                <th>Exam Time</th>
                <th>Full Mark</th>
                <th>Pass Mark</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($exam_subjects as $i=>$s): ?>
              <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($s['subject_name']) ?></td>
                <td><?= $s['exam_date'] ? date('d M Y', strtotime($s['exam_date'])) : '-' ?></td>
                <td><?= $s['exam_time'] ? date('h:i A', strtotime($s['exam_time'])) : '-' ?></td>
                <td><?= $s['full_mark'] ?></td>
                <td><?= $s['pass_mark'] ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($exam_subjects)): ?>
              <tr><td colspan="6" class="text-center text-muted">No subjects added.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="mt-3">
        <a href="create_exam.php?edit=<?= $exam_id ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        <a href="exam_list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
      </div>

    </div>
  </section>
</div>

<?php include '../admin/inc/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<style>@media print {.no-print{display:none!important;}}</style>
<?php ob_end_flush(); ?>
</body>
</html>