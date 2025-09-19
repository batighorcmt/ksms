<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('../login.php');
}

$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();
$types   = $pdo->query("SELECT * FROM exam_types WHERE status='active'")->fetchAll();
$subjects= $pdo->query("SELECT * FROM subjects WHERE status='active'")->fetchAll();

$errors = [];
$edit_id = intval($_GET['edit'] ?? 0);
$exam    = null;
$exam_subjects = [];

// যদি এডিট মোড হয়
if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id=?");
    $stmt->execute([$edit_id]);
    $exam = $stmt->fetch();

    if ($exam) {
        $stmt2 = $pdo->prepare("SELECT * FROM exam_subjects WHERE exam_id=?");
        $stmt2->execute([$edit_id]);
        $exam_subjects = $stmt2->fetchAll();
    } else {
        $errors[] = "Exam not found!";
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name   = trim($_POST['name'] ?? '');
    $year   = trim($_POST['academic_year'] ?? '');
    $class_id = intval($_POST['class_id'] ?? 0);
    $exam_type_id = intval($_POST['exam_type_id'] ?? 0);
    $result_release_date = $_POST['result_release_date'] ?: null;

    if ($name==='' || $class_id==0 || $exam_type_id==0) $errors[]='নাম, শ্রেণি ও পরীক্ষার ধরন প্রয়োজন।';

    $sub_ids    = $_POST['subject_id'] ?? [];
    $full_marks = $_POST['full_mark'] ?? [];
    $pass_marks = $_POST['pass_mark'] ?? [];
    $exam_dates = $_POST['exam_date'] ?? [];
    $exam_times = $_POST['exam_time'] ?? [];

    if (empty($sub_ids)) $errors[] = 'কমপক্ষে একটি বিষয় নির্বাচন করুন।';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($edit_id) {
                // UPDATE
                $upd = $pdo->prepare("UPDATE exams SET name=?, academic_year=?, class_id=?, exam_type_id=?, result_release_date=?, updated_at=NOW() WHERE id=?");
                $upd->execute([$name, $year, $class_id, $exam_type_id, $result_release_date, $edit_id]);

                // পুরনো subjects ডিলিট
                $pdo->prepare("DELETE FROM exam_subjects WHERE exam_id=?")->execute([$edit_id]);

                $exam_id = $edit_id;
            } else {
                // INSERT
                $ins = $pdo->prepare("INSERT INTO exams (name, academic_year, class_id, exam_type_id, exam_date, exam_time, result_release_date, created_by) VALUES (?,?,?,?,?,?,?,?)");
                $ins->execute([$name, $year, $class_id, $exam_type_id, null, null, $result_release_date, $_SESSION['user_id']]);
                $exam_id = $pdo->lastInsertId();
            }

            // subject insert
            $es = $pdo->prepare("INSERT INTO exam_subjects (exam_id, subject_id, exam_date, exam_time, full_mark, pass_mark) VALUES (?,?,?,?,?,?)");
            foreach ($sub_ids as $i => $sid) {
                $s  = intval($sid);
                $fm = intval($full_marks[$i] ?? 0);
                $pm = intval($pass_marks[$i] ?? 0);
                $ed = $exam_dates[$i] ?: null;
                $et = $exam_times[$i] ?: null;
                $es->execute([$exam_id, $s, $ed, $et, $fm, $pm]);
            }

            $pdo->commit();
            $_SESSION['success'] = $edit_id ? 'Exam updated successfully.' : 'Exam created successfully.';
            header("Location: ../exam/exam_list.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "DB Error: ".$e->getMessage();
        }
    }
}

include '../admin/inc/header.php';
include 'inc/sidebar.php';
?>
<div class="content-wrapper p-3">
  <section class="content-header">
    <h1><?= $edit_id ? "Edit Exam" : "Create Exam" ?></h1>
  </section>
  <section class="content">
    <div class="container-fluid">
      <?php if(!empty($errors)): ?><div class="alert alert-danger"><?=implode('<br>',$errors)?></div><?php endif; ?>
      <div class="card"><div class="card-body">
        <form method="post" id="examForm">
          <div class="row">
            <div class="col-md-6">
              <label>Exam Name</label>
              <input name="name" class="form-control" required value="<?=htmlspecialchars($exam['name'] ?? '')?>">
            </div>
            <div class="col-md-3">
              <label>Academic Year</label>
              <input name="academic_year" class="form-control" value="<?=htmlspecialchars($exam['academic_year'] ?? '')?>">
            </div>
            <div class="col-md-3">
              <label>Class</label>
              <select name="class_id" class="form-control" required>
                <option value="">--select--</option>
                <?php foreach($classes as $c): ?>
                  <option value="<?=$c['id']?>" <?=($exam['class_id']??'')==$c['id']?'selected':''?>><?=$c['name']?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 mt-2">
              <label>Exam Type</label>
              <select name="exam_type_id" class="form-control" required>
                <option value="">--select--</option>
                <?php foreach($types as $t): ?>
                  <option value="<?=$t['id']?>" <?=($exam['exam_type_id']??'')==$t['id']?'selected':''?>><?=$t['name']?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 mt-2">
              <label>Result Release Date</label>
              <input type="date" name="result_release_date" class="form-control" value="<?=htmlspecialchars($exam['result_release_date'] ?? '')?>">
            </div>
          </div>

          <hr>
          <h5>Subjects (subject-wise schedule & marks)</h5>
          <div id="subjectsWrap"></div>
          <div class="mt-2"><button class="btn btn-sm btn-secondary" type="button" id="addSubjectBtn">+ Add Subject</button></div>

          <div class="mt-3">
            <button class="btn btn-primary"><?= $edit_id ? "Update Exam" : "Create Exam" ?></button>
            <a class="btn btn-default" href="../exam/exam_list.php">Cancel</a>
          </div>
        </form>
      </div></div>
    </div>
  </section>
</div>

<?php include '../admin/inc/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
const subjects = <?= json_encode($subjects) ?>;
function makeRow(item={}) {
  const sel = subjects.map(s=>`<option value="${s.id}" ${item.subject_id==s.id?'selected':''}>${s.name}</option>`).join('');
  return $(`
  <div class="subject-row row g-2 align-items-end mb-2">
    <div class="col-md-4"><label>Subject</label><select name="subject_id[]" class="form-control" required><option value="">--select--</option>${sel}</select></div>
    <div class="col-md-2"><label>Exam Date</label><input type="date" name="exam_date[]" class="form-control" value="${item.exam_date||''}"></div>
    <div class="col-md-2"><label>Exam Time</label><input type="time" name="exam_time[]" class="form-control" value="${item.exam_time||''}"></div>
    <div class="col-md-2"><label>Full Mark</label><input type="number" name="full_mark[]" class="form-control" value="${item.full_mark||100}"></div>
    <div class="col-md-2"><label>Pass Mark</label><input type="number" name="pass_mark[]" class="form-control" value="${item.pass_mark||33}"></div>
    <div class="col-12"><button type="button" class="btn btn-danger btn-sm removeRow">Remove</button></div>
  </div>`);
}

$(function(){
  $('#addSubjectBtn').on('click', ()=>$('#subjectsWrap').append(makeRow()));
  $(document).on('click', '.removeRow', function(){ $(this).closest('.subject-row').remove(); });

  // যদি edit mode হয় তাহলে পুরনো subjects লোড করে দেখাবো
  <?php if($edit_id && !empty($exam_subjects)): ?>
    const oldSubs = <?= json_encode($exam_subjects) ?>;
    oldSubs.forEach(s => $('#subjectsWrap').append(makeRow(s)));
  <?php else: ?>
    // নতুন হলে ডিফল্ট একটি row
    $('#addSubjectBtn').click();
  <?php endif; ?>
});
</script>
</body>
</html> 