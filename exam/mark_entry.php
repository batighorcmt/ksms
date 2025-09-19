<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['teacher'])) redirect('../login.php');
$teacher_id = $_SESSION['user_id'];

// Exams list for teacher's classes
$exams = $pdo->prepare("SELECT e.*, c.name as class_name, t.name as type_name FROM exams e JOIN classes c ON e.class_id=c.id JOIN exam_types t ON e.exam_type_id=t.id ORDER BY e.id DESC");
$exams->execute([$teacher_id]);
$exams = $exams->fetchAll();

// Classes for this teacher
$classes = $pdo->prepare("SELECT c.* FROM classes c WHERE c.class_teacher_id=?");
$classes->execute([$teacher_id]);
$classes = $classes->fetchAll();

// Sections for selected class
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : ($classes[0]['id'] ?? 0);
$sections = [];
if ($selected_class_id) {
  $sections_stmt = $pdo->prepare("SELECT * FROM sections WHERE class_id=?");
  $sections_stmt->execute([$selected_class_id]);
  $sections = $sections_stmt->fetchAll();
}

$selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : ($sections[0]['id'] ?? 0);

// Subjects for this teacher in selected class/section from routines table
$subjects = [];
if ($selected_class_id && $selected_section_id) {
  $subjects_stmt = $pdo->prepare("SELECT s.* FROM routines r JOIN subjects s ON r.subject_id=s.id WHERE r.class_id=? AND r.section_id=? AND r.teacher_id=? GROUP BY s.id");
  $subjects_stmt->execute([$selected_class_id, $selected_section_id, $teacher_id]);
  $subjects = $subjects_stmt->fetchAll();
}

$selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : ($subjects[0]['id'] ?? 0);

// Find exam for this class
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : ($exams[0]['id'] ?? 0);
$exam = null;
foreach ($exams as $ex) {
  if ($ex['id'] == $exam_id) {
    $exam = $ex;
    break;
  }
}

// Find exam_subjects row for this exam and subject
$exam_subject = null;
if ($exam && $selected_subject_id) {
  $exam_subject_stmt = $pdo->prepare("SELECT * FROM exam_subjects WHERE exam_id=? AND subject_id=?");
  $exam_subject_stmt->execute([$exam['id'], $selected_subject_id]);
  $exam_subject = $exam_subject_stmt->fetch();
}

// Students in the class/section
$students = [];
if ($selected_class_id && $selected_section_id && $exam_subject) {
  $students_stmt = $pdo->prepare("SELECT * FROM students WHERE class_id=? AND section_id=? AND status='active' ORDER BY roll_number ASC");
  $students_stmt->execute([$selected_class_id, $selected_section_id]);
  $students = $students_stmt->fetchAll();
}

?><!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>শিক্ষক মার্ক এন্ট্রি - কিন্ডার গার্ডেন</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <style>
    :root { --primary-color: #4e73df; --secondary-color: #6f42c1; --success-color: #1cc88a; }
    body { font-family: SolaimanLipi, Arial, sans-serif; background-color: #f8f9fc; }
    .card { box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); border: none; border-radius: 10px; }
    .card-header { border-radius: 10px 10px 0 0 !important; font-weight: 700; }
    .main-sidebar, .nav-link { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }
    .table th, .table td { vertical-align: middle; }
  </style>
</head>
<body>
<?php include '../admin/inc/header.php';
      include '../teacher/inc/sidebar.php'; ?>

<div class="content-wrapper p-3">
  <section class="content-header"><h1>মার্ক এন্ট্রি</h1></section>
  <section class="content">
    <div class="container-fluid">
      <form method="get" class="form-inline mb-3">
        <label class="mr-2">পরীক্ষা:</label>
        <select name="exam_id" class="form-control mr-3" onchange="this.form.submit()">
          <?php foreach($exams as $ex): ?>
            <option value="<?= $ex['id'] ?>" <?= $ex['id']==$exam_id?'selected':'' ?>><?= htmlspecialchars($ex['name']) ?> (<?= htmlspecialchars($ex['class_name']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <label class="mr-2">শ্রেণি:</label>
        <select name="class_id" class="form-control mr-3" onchange="this.form.submit()">
          <?php foreach($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id']==$selected_class_id?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="mr-2">শাখা:</label>
        <select name="section_id" class="form-control mr-3" onchange="this.form.submit()">
          <?php foreach($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>" <?= $sec['id']==$selected_section_id?'selected':'' ?>><?= htmlspecialchars($sec['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="mr-2">বিষয়:</label>
        <select name="subject_id" class="form-control mr-3" onchange="this.form.submit()">
          <?php foreach($subjects as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id']==$selected_subject_id?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>

      <?php if ($selected_subject_id && !$subjects): ?>
        <div class="alert alert-warning">আপনার রুটিনে এই বিষয়টি নেই।</div>
      <?php elseif ($selected_subject_id && !$exam_subject): ?>
        <div class="alert alert-danger">এই বিষয়টি এই পরীক্ষার জন্য যুক্ত করা হয়নি। অনুগ্রহ করে পরীক্ষার সেটিংসে বিষয়টি যুক্ত করুন।</div>
      <?php elseif ($exam_subject && $students): ?>
      <div class="card"><div class="card-body table-responsive">
        <table class="table table-sm table-bordered">
          <thead>
            <tr><th>Roll</th><th>Student</th><th><?=htmlspecialchars($subjects[array_search($selected_subject_id, array_column($subjects, 'id'))]['name'])?> (<?= $exam_subject['full_mark'] ?>)</th></tr>
          </thead>
          <tbody>
            <?php foreach($students as $st): ?>
              <tr>
                <td><?= $st['roll_number'] ?></td>
                <td><?= htmlspecialchars($st['first_name'].' '.$st['last_name']) ?></td>
                <?php 
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
      <?php elseif ($exam_subject && !$students): ?>
        <div class="alert alert-warning">এই ক্লাস ও শাখার কোনো শিক্ষার্থী নেই।</div>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php include '../admin/inc/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){
  $('.mark-input').on('change', function(){
    const el = $(this);
    const exam_id = el.data('exam');
    const sub_id = el.data('sub');
    const stu_id = el.data('stu');
    const val = el.val();
    $.post('ajax_save_mark.php', {exam_id, sub_id, stu_id, val}, function(res){
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
</body>
</html>
