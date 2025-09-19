<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['teacher'])) redirect('../login.php');

$teacher_id = $_SESSION['user_id'];

// Fetch all classes and subjects assigned to this teacher
$teacher_classes = $pdo->prepare("SELECT c.* FROM classes c WHERE c.class_teacher_id = ?");
$teacher_classes->execute([$teacher_id]);
$teacher_classes = $teacher_classes->fetchAll();

// If no class assigned, show message
if (empty($teacher_classes)) {
  include '../admin/inc/header.php';
  include 'inc/sidebar.php';
  echo '<div class="content-wrapper p-3"><div class="alert alert-warning">আপনার জন্য কোনো ক্লাস নির্ধারিত নেই।</div></div>';
  include '../admin/inc/footer.php';
  exit;
}

// Handle class and subject selection
$selected_class_id = intval($_GET['class_id'] ?? $teacher_classes[0]['id']);

// Fetch subjects assigned to this teacher for the selected class (via class_subjects table)
$teacher_subjects = $pdo->prepare("
  SELECT s.* FROM class_subjects cs
  JOIN subjects s ON cs.subject_id = s.id
  WHERE cs.class_id = ? AND cs.teacher_id = ?
");
$teacher_subjects->execute([$selected_class_id, $teacher_id]);
$teacher_subjects = $teacher_subjects->fetchAll();

if (empty($teacher_subjects)) {
  include '../admin/inc/header.php';
  include 'inc/sidebar.php';
  echo '<div class="content-wrapper p-3"><div class="alert alert-warning">এই ক্লাসে আপনার জন্য কোনো বিষয় নির্ধারিত নেই।</div></div>';
  include '../admin/inc/footer.php';
  exit;
}

$selected_subject_id = intval($_GET['subject_id'] ?? $teacher_subjects[0]['id']);

// Find exam for this class and subject
$exam = $pdo->prepare("SELECT e.*, c.name as class_name, t.name as type_name FROM exams e JOIN classes c ON e.class_id=c.id JOIN exam_types t ON e.exam_type_id=t.id WHERE e.class_id=? AND e.id=?");
$exam_id = intval($_GET['exam_id'] ?? 0);
$exam->execute([$selected_class_id, $exam_id]);
$exam = $exam->fetch();
if (!$exam) {
  include '../admin/inc/header.php';
  include 'inc/sidebar.php';
  echo '<div class="content-wrapper p-3"><div class="alert alert-warning">সঠিক পরীক্ষা পাওয়া যায়নি।</div></div>';
  include '../admin/inc/footer.php';
  exit;
}

// Only allow mark entry for subjects assigned to this teacher
$subject = null;
foreach ($teacher_subjects as $subj) {
  if ($subj['id'] == $selected_subject_id) {
    $subject = $subj;
    break;
  }
}
if (!$subject) {
  include '../admin/inc/header.php';
  include 'inc/sidebar.php';
  echo '<div class="content-wrapper p-3"><div class="alert alert-danger">আপনি এই বিষয়ের নম্বর এন্ট্রি করতে পারবেন না।</div></div>';
  include '../admin/inc/footer.php';
  exit;
}

// Find exam_subjects row for this exam and subject
$exam_subject = $pdo->prepare("SELECT * FROM exam_subjects WHERE exam_id=? AND subject_id=?");
$exam_subject->execute([$exam['id'], $subject['id']]);
$exam_subject = $exam_subject->fetch();
if (!$exam_subject) {
  include '../admin/inc/header.php';
  include 'inc/sidebar.php';
  echo '<div class="content-wrapper p-3"><div class="alert alert-danger">এই পরীক্ষার জন্য বিষয়টি নির্ধারিত নেই।</div></div>';
  include '../admin/inc/footer.php';
  exit;
}

// Students in the class
$students = $pdo->prepare("SELECT * FROM students WHERE class_id=? AND status='active' ORDER BY roll_number ASC");
$students->execute([$selected_class_id]);
$students = $students->fetchAll();

?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>শিক্ষক ড্যাশবোর্ড - কিন্ডার গার্ডেন</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <!-- Bengali Font -->
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --primary-color: #4e73df;
      --secondary-color: #6f42c1;
      --success-color: #1cc88a;
    }
    body {
      font-family: SolaimanLipi, Arial, sans-serif;
      background-color: #f8f9fc;
    }
    .card {
      box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
      border: none;
      border-radius: 10px;
    }
    .card-header {
      border-radius: 10px 10px 0 0 !important;
      font-weight: 700;
    }
    .btn-primary {
      background-color: var(--primary-color);
      border: none;
    }
    .btn-primary:hover {
      background-color: darken(var(--primary-color), 10%);
    }
    .btn-success {
      background-color: var(--success-color);
      border: none;
    }
    .btn-success:hover {
      background-color: darken(var(--success-color), 10%);
    }
    .btn-danger {
      background-color: #e74a3b;
      border: none;
    }
    .btn-danger:hover {
      background-color: darken(#e74a3b, 10%);
    }
    .text-primary {
      color: var(--primary-color) !important;
    }
    .text-success {
      color: var(--success-color) !important;
    }
    .text-secondary {
      color: var(--secondary-color) !important;
    }

    .main-sidebar, .nav-link { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }
    .table th, .table td { vertical-align: middle; }
  </style>
</head>
<body>
<?php include '../admin/inc/header.php'; ?>
<?php include 'inc/sidebar.php'; ?>
?>

<div class="content-wrapper p-3">
  <section class="content-header"><h1>নম্বর প্রদান: <?=htmlspecialchars($exam['name'])?></h1></section>
  <section class="content">
    <div class="container-fluid">
      <form method="get" class="form-inline mb-3">
        <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
        <label class="mr-2">শ্রেণি:</label>
        <select name="class_id" class="form-control mr-3" onchange="this.form.submit()">
          <?php foreach($teacher_classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id']==$selected_class_id?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="mr-2">বিষয়:</label>
        <select name="subject_id" class="form-control mr-3" onchange="this.form.submit()">
          <?php foreach($teacher_subjects as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id']==$selected_subject_id?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <div class="card"><div class="card-body table-responsive">
        <table class="table table-sm table-bordered">
          <thead>
            <tr><th>Roll</th><th>Student</th><th><?=htmlspecialchars($subject['name'])?> (<?= $exam_subject['full_mark'] ?>)</th></tr>
          </thead>
          <tbody>
            <?php foreach($students as $st): ?>
              <tr>
                <td><?= $st['roll_number'] ?></td>
                <td><?= htmlspecialchars($st['first_name'].' '.$st['last_name']) ?></td>
                <?php 
                    // fetch existing mark
                    $m = $pdo->prepare("SELECT obtained_marks FROM marks WHERE exam_subject_id=? AND student_id=? LIMIT 1");
                    $m->execute([$exam_subject['id'],$st['id']]);
                    $row = $m->fetch();
                    $val = $row['obtained_marks'] ?? '';
                ?>
                <td>
                  <input type="number" min="0" max="<?=intval($exam_subject['full_mark'])?>" class="form-control mark-input" data-exam="<?=$exam['id']?>" data-sub="<?=$exam_subject['id']?>" data-stu="<?=$st['id']?>" value="<?=$val?>">
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
<script>
$(function(){
  // autosave on input blur
  $('.mark-input').on('change', function(){
    const el = $(this);
    const exam_id = el.data('exam');
    const sub_id = el.data('sub');
    const stu_id = el.data('stu');
    const val = el.val();

    $.post('ajax_save_mark.php', {exam_id, sub_id, stu_id, val}, function(res){
      // handle response (simple)
      if(res.success) {
        el.addClass('is-valid');
        setTimeout(()=>el.removeClass('is-valid'), 1200);
      } else {
        alert('Save error: '+(res.error||'unknown'));
      }
    }, 'json');
  });
});
</script>
