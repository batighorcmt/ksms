<?php
ob_start();
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) redirect('../login.php');

$exams = $pdo->query("
  SELECT e.*, c.name as class_name, t.name as type_name
  FROM exams e
  JOIN classes c ON e.class_id=c.id
  JOIN exam_types t ON e.exam_type_id=t.id
  ORDER BY e.exam_date DESC, e.id DESC
")->fetchAll();
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
  <section class="content-header"><h1>Exam List</h1></section>
  <section class="content">
    <div class="container-fluid">
      <a class="btn btn-primary mb-2" href="create_exam.php">+ Create Exam</a>
      <div class="card"><div class="card-body table-responsive">
        <table class="table table-bordered table-striped">
          <thead><tr><th>#</th><th>Name</th><th>Class</th><th>Type</th><th>Year</th><th>Result Release</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($exams as $i=>$e): ?>
              <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($e['name']) ?></td>
                <td><?= htmlspecialchars($e['class_name']) ?></td>
                <td><?= htmlspecialchars($e['type_name']) ?></td>
                <td><?= htmlspecialchars($e['academic_year']) ?></td>
                <td><?= $e['result_release_date'] ?></td>
                <td>
                  <div class="btn-group">
                    <button class="btn btn-sm btn-secondary dropdown-toggle" data-toggle="dropdown">Actions</button>
                    <div class="dropdown-menu">
                      <a class="dropdown-item" href="exam_view.php?id=<?=$e['id']?>">Details</a>
                      <a class="dropdown-item" href="create_exam.php?edit=<?=$e['id']?>">Edit</a>
                      <a class="dropdown-item" href="tabulation.php?exam_id=<?=$e['id']?>" target="_blank">Tabulation</a>
                      <a class="dropdown-item" href="mark_entry.php?exam_id=<?=$e['id']?>">Give Marks</a>
                      <a class="dropdown-item" href="exam_stats.php?exam_id=<?=$e['id']?>">Statistics</a>
                      <a class="dropdown-item text-danger" href="delete_exam.php?id=<?=$e['id']?>" onclick="return confirm('Delete exam?');">Delete</a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
    </div>
  </section>
</div>
<?php include '../admin/inc/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
</body> 
</html>
<?php ob_end_flush(); ?>
