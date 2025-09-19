<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['teacher'])) redirect('../login.php');
$teacher_id = $_SESSION['user_id'];

/* 🔹 Exams list (সব পরীক্ষা দেখাবে) */
$exams = $pdo->query("
    SELECT e.*, c.name as class_name, t.name as type_name
    FROM exams e
    JOIN classes c ON e.class_id=c.id
    JOIN exam_types t ON e.exam_type_id=t.id
    ORDER BY e.id DESC
")->fetchAll();

/* 🔹 Teacher-এর রুটিন থেকে Class/Section/Subject */
$routines_stmt = $pdo->prepare("
    SELECT DISTINCT r.class_id, r.section_id, s.name as section_name, c.name as class_name
    FROM routines r
    JOIN sections s ON r.section_id=s.id
    JOIN classes c ON r.class_id=c.id
    WHERE r.teacher_id=?
    ORDER BY c.numeric_value ASC, s.name ASC
");
$routines_stmt->execute([$teacher_id]);
$routines = $routines_stmt->fetchAll();

/* ইউজার নির্বাচিত ক্লাস/শাখা */
$selected_class_id   = intval($_GET['class_id'] ?? ($routines[0]['class_id'] ?? 0));
$selected_section_id = intval($_GET['section_id'] ?? ($routines[0]['section_id'] ?? 0));

/* 🔹 নির্বাচিত ক্লাস-শাখার Subjects */
$subjects_stmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.name 
    FROM routines r
    JOIN subjects s ON r.subject_id=s.id
    WHERE r.teacher_id=? AND r.class_id=? AND r.section_id=?
    ORDER BY s.name
");
$subjects_stmt->execute([$teacher_id, $selected_class_id, $selected_section_id]);
$subjects = $subjects_stmt->fetchAll();

$selected_subject_id = intval($_GET['subject_id'] ?? ($subjects[0]['id'] ?? 0));

/* 🔹 Exam নির্বাচন */
$exam_id = intval($_GET['exam_id'] ?? ($exams[0]['id'] ?? 0));
$exam = null;
foreach ($exams as $ex) {
    if ($ex['id'] == $exam_id) $exam = $ex;
}

/* 🔹 Exam Subject খোঁজা */
$exam_subject = null;
if ($exam && $selected_subject_id) {
    $exam_subject_stmt = $pdo->prepare("SELECT * FROM exam_subjects WHERE exam_id=? AND subject_id=?");
    $exam_subject_stmt->execute([$exam_id, $selected_subject_id]);
    $exam_subject = $exam_subject_stmt->fetch();
}

/* 🔹 Student list */
$students = [];
if ($selected_class_id && $selected_section_id && $exam_subject) {
    $students_stmt = $pdo->prepare("SELECT * FROM students WHERE class_id=? AND section_id=? AND status='active' ORDER BY roll_number ASC");
    $students_stmt->execute([$selected_class_id, $selected_section_id]);
    $students = $students_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>শিক্ষক মার্ক এন্ট্রি</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <style>
    body { font-family: SolaimanLipi, Arial, sans-serif; }
    .card { border-radius:10px; box-shadow:0 0.25rem 1rem rgba(0,0,0,.1); }
    .table th, .table td { vertical-align: middle; }
  </style>
</head>
<body>
<?php include '../admin/inc/header.php'; include '../teacher/inc/sidebar.php'; ?>

<div class="content-wrapper p-3">
  <section class="content-header"><h1>মার্ক এন্ট্রি</h1></section>
  <section class="content">
    <div class="container-fluid">
      <form method="get" class="form-inline mb-3">
        <label class="mr-2">পরীক্ষা:</label>
        <select name="exam_id" class="form-control mr-3" onchange="this.form.submit()">
          <?php foreach($exams as $ex): ?>
            <option value="<?= $ex['id'] ?>" <?= $ex['id']==$exam_id?'selected':'' ?>>
              <?= htmlspecialchars($ex['name'])." (".$ex['class_name'].")" ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label class="mr-2">শ্রেণি/শাখা:</label>
        <select name="class_id" class="form-control mr-2" onchange="this.form.submit()">
          <?php foreach($routines as $r): ?>
            <option value="<?= $r['class_id'] ?>" <?= $r['class_id']==$selected_class_id?'selected':'' ?>>
              <?= htmlspecialchars($r['class_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="section_id" class="form-control mr-3" onchange="this.form.submit()">
          <?php foreach($routines as $r): if($r['class_id']==$selected_class_id): ?>
            <option value="<?= $r['section_id'] ?>" <?= $r['section_id']==$selected_section_id?'selected':'' ?>>
              <?= htmlspecialchars($r['section_name']) ?>
            </option>
          <?php endif; endforeach; ?>
        </select>

        <label class="mr-2">বিষয়:</label>
        <select name="subject_id" class="form-control mr-3" onchange="this.form.submit()">
          <?php foreach($subjects as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id']==$selected_subject_id?'selected':'' ?>>
              <?= htmlspecialchars($s['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>

      <?php if ($exam_subject && $students): ?>
        <div class="card"><div class="card-body table-responsive">
          <table class="table table-sm table-bordered">
            <thead>
              <tr><th>Roll</th><th>Student</th><th>Mark (<?= $exam_subject['full_mark'] ?>)</th></tr>
            </thead>
            <tbody>
              <?php foreach($students as $st): 
                $m = $pdo->prepare("SELECT obtained_marks FROM marks WHERE exam_subject_id=? AND student_id=? LIMIT 1");
                $m->execute([$exam_subject['id'],$st['id']]);
                $row = $m->fetch();
                $val = $row['obtained_marks'] ?? '';
              ?>
              <tr>
                <td><?= $st['roll_number'] ?></td>
                <td><?= htmlspecialchars($st['first_name'].' '.$st['last_name']) ?></td>
                <td>
                  <input type="number" min="0" max="<?=intval($exam_subject['full_mark'])?>" 
                         class="form-control mark-input"
                         data-exam="<?=$exam_id?>" data-sub="<?=$exam_subject['id']?>" data-stu="<?=$st['id']?>"
                         value="<?=$val?>">
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div></div>
      <?php elseif ($exam && $selected_subject_id && !$exam_subject): ?>
        <div class="alert alert-danger">এই বিষয়টি এই পরীক্ষার জন্য যুক্ত করা হয়নি।</div>
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
    $.post('ajax_save_mark.php', {
      exam_id: el.data('exam'),
      sub_id: el.data('sub'),
      stu_id: el.data('stu'),
      val: el.val()
    }, function(res){
      if(res.success){
        el.addClass('is-valid'); setTimeout(()=>el.removeClass('is-valid'),1200);
      } else { alert(res.error||'Save failed'); }
    },'json');
  });
});
</script>
</body>
</html>
