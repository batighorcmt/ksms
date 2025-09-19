<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['teacher'])) redirect('../login.php');
$teacher_id = $_SESSION['user_id'];

/* সব পরীক্ষা */
$exams = $pdo->query("
    SELECT e.*, c.name as class_name, t.name as type_name
    FROM exams e
    JOIN classes c ON e.class_id=c.id
    JOIN exam_types t ON e.exam_type_id=t.id
    ORDER BY e.id DESC
")->fetchAll();

/* সব ক্লাস */
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_value ASC")->fetchAll();

/* Form input */
$exam_id    = intval($_GET['exam_id'] ?? 0);
$class_id   = intval($_GET['class_id'] ?? 0);
$section_id = intval($_GET['section_id'] ?? 0);
$subject_id = intval($_GET['subject_id'] ?? 0);

$sections = [];
if ($class_id) {
    $sections = $pdo->prepare("SELECT * FROM sections WHERE class_id=? ORDER BY name");
    $sections->execute([$class_id]);
    $sections = $sections->fetchAll();
}

$subjects = [];
if ($class_id && $section_id) {
  $subjects_stmt = $pdo->prepare("SELECT s.* FROM routines r JOIN subjects s ON r.subject_id=s.id WHERE r.class_id=? AND r.section_id=? AND r.teacher_id=? GROUP BY s.id ORDER BY s.name");
  $subjects_stmt->execute([$class_id, $section_id, $teacher_id]);
  $subjects = $subjects_stmt->fetchAll();
}

/* Validation */
$exam = $exam_id ? $pdo->query("SELECT * FROM exams WHERE id=$exam_id")->fetch() : null;
$exam_subject = null;
$students = [];

if ($exam_id && $class_id && $section_id && $subject_id) {
    // পরীক্ষা-সাবজেক্ট আছে কি?
    $stmt = $pdo->prepare("SELECT * FROM exam_subjects WHERE exam_id=? AND subject_id=?");
    $stmt->execute([$exam_id, $subject_id]);
    $exam_subject = $stmt->fetch();

    if ($exam_subject) {
        // শিক্ষার্থী লোড
        $students_stmt = $pdo->prepare("SELECT * FROM students WHERE class_id=? AND section_id=? AND status='active' ORDER BY roll_number ASC");
        $students_stmt->execute([$class_id, $section_id]);
        $students = $students_stmt->fetchAll();
    }
}
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
<?php include '../admin/inc/header.php'; include '../teacher/inc/sidebar.php'; ?>

<div class="content-wrapper p-3">
  <section class="content-header"><h1>মার্ক এন্ট্রি</h1></section>
  <section class="content">
    <div class="container-fluid">
      
      <!-- Search Form -->
      <div class="card mb-3">
        <div class="card-header bg-primary text-white">সার্চ করুন</div>
        <div class="card-body">
          <form method="get" class="form-row">
            <div class="form-group col-md-3">
              <label>পরীক্ষা</label>
              <select name="exam_id" class="form-control">
                <option value="">-- নির্বাচন করুন --</option>
                <?php foreach($exams as $ex): ?>
                  <option value="<?= $ex['id'] ?>" <?= $ex['id']==$exam_id?'selected':'' ?>>
                    <?= htmlspecialchars($ex['name'])." (".$ex['class_name'].")" ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-3">
              <label>শ্রেণি</label>
              <select name="class_id" class="form-control" onchange="this.form.submit()">
                <option value="">-- নির্বাচন করুন --</option>
                <?php foreach($classes as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= $c['id']==$class_id?'selected':'' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-3">
              <label>শাখা</label>
              <select name="section_id" class="form-control">
                <option value="">-- নির্বাচন করুন --</option>
                <?php foreach($sections as $s): ?>
                  <option value="<?= $s['id'] ?>" <?= $s['id']==$section_id?'selected':'' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-3">
              <label>বিষয়</label>
              <select name="subject_id" class="form-control">
                <option value="">-- নির্বাচন করুন --</option>
                <?php foreach($subjects as $s): ?>
                  <option value="<?= $s['id'] ?>" <?= $s['id']==$subject_id?'selected':'' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-12 mt-2">
              <button type="submit" class="btn btn-success"><i class="fa fa-search"></i> দেখুন</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Result -->
      <?php if ($_GET): ?>
        <?php if (!$exam_subject): ?>
          <div class="alert alert-danger">এই বিষয়টি এই পরীক্ষার জন্য যুক্ত করা হয়নি।</div>
        <?php elseif ($exam_subject && !$students): ?>
          <div class="alert alert-warning">এই ক্লাস ও শাখার কোনো শিক্ষার্থী নেই।</div>
        <?php else: ?>
          <div class="card">
            <div class="card-header bg-info text-white">
              <?= htmlspecialchars($exam['name']) ?> | বিষয়: <?= htmlspecialchars($subjects[array_search($subject_id, array_column($subjects, 'id'))]['name']) ?>
            </div>
            <div class="card-body table-responsive">
              <table class="table table-sm table-bordered">
                <thead><tr><th>Roll</th><th>Student</th><th>Mark (<?= $exam_subject['full_mark'] ?>)</th></tr></thead>
                <tbody>
                  <?php foreach($students as $st): 
                    $m = $pdo->prepare("SELECT obtained_marks FROM marks WHERE exam_subject_id=? AND student_id=?");
                    $m->execute([$exam_subject['id'],$st['id']]);
                    $val = $m->fetchColumn();
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
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    </div>
  </section>
</div>

<?php include '../admin/inc/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
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
