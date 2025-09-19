<?php
require_once '../config.php';
if (!isAuthenticated()) redirect('../login.php');

$exam_id = intval($_GET['exam_id'] ?? 0);
if(!$exam_id) { echo "Invalid exam"; exit; }

// load exam and subjects
$exam = $pdo->prepare("SELECT e.*, c.name as class_name, t.name as type_name FROM exams e JOIN classes c ON e.class_id=c.id JOIN exam_types t ON e.exam_type_id=t.id WHERE e.id=?");
$exam->execute([$exam_id]); $exam = $exam->fetch();
$subjects = $pdo->prepare("SELECT es.*, sub.name as subject_name FROM exam_subjects es JOIN subjects sub ON es.subject_id=sub.id WHERE es.exam_id=?");
$subjects->execute([$exam_id]); $subjects = $subjects->fetchAll();

// students in the class & section filter (optional)
$class_id = $exam['class_id'];
$students = $pdo->prepare("SELECT * FROM students WHERE class_id=? AND status='active' ORDER BY roll_number ASC");
$students->execute([$class_id]);
$students = $students->fetchAll();

include '../admin/inc/header.php';
include 'inc/sidebar.php';
?>
<div class="content-wrapper p-3">
  <section class="content-header"><h1>Give Marks: <?=htmlspecialchars($exam['name'])?></h1></section>
  <section class="content">
    <div class="container-fluid">
      <div class="card"><div class="card-body table-responsive">
        <table class="table table-sm table-bordered">
          <thead>
            <tr><th>Roll</th><th>Student</th>
            <?php foreach($subjects as $s): ?><th><?=htmlspecialchars($s['subject_name'])?> (<?= $s['full_mark'] ?>)</th><?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach($students as $st): ?>
              <tr>
                <td><?= $st['roll_number'] ?></td>
                <td><?= htmlspecialchars($st['first_name'].' '.$st['last_name']) ?></td>
                <?php foreach($subjects as $s): 
                    // fetch existing mark
                    $m = $pdo->prepare("SELECT obtained_marks FROM marks WHERE exam_subject_id=? AND student_id=? LIMIT 1");
                    $m->execute([$s['id'],$st['id']]);
                    $row = $m->fetch();
                    $val = $row['obtained_marks'] ?? '';
                ?>
                <td>
                  <input type="number" min="0" max="<?=intval($s['full_mark'])?>" class="form-control mark-input" data-exam="<?=$exam_id?>" data-sub="<?=$s['id']?>" data-stu="<?=$st['id']?>" value="<?=$val?>">
                </td>
                <?php endforeach; ?>
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
