<?php
require_once '../config.php';
if (!isAuthenticated()) redirect('../login.php');

$exam_id = intval($_GET['exam_id'] ?? 0);
if(!$exam_id) { echo "Invalid"; exit; }

$exam = $pdo->prepare("SELECT e.*, c.name class_name, t.name type_name FROM exams e JOIN classes c ON e.class_id=c.id JOIN exam_types t ON e.exam_type_id=t.id WHERE e.id=?");
$exam->execute([$exam_id]); $exam = $exam->fetch();

$subjects = $pdo->prepare("SELECT es.*, sub.name subject_name FROM exam_subjects es JOIN subjects sub ON es.subject_id=sub.id WHERE es.exam_id=?");
$subjects->execute([$exam_id]); $subjects = $subjects->fetchAll();

$tab = $pdo->prepare("SELECT tc.*, s.first_name, s.last_name, s.roll_number FROM tabulation_cache tc JOIN students s ON tc.student_id=s.id WHERE tc.exam_id=? ORDER BY tc.position ASC, tc.total_marks DESC");
$tab->execute([$exam_id]); $tabulation = $tab->fetchAll();

include '../admin/inc/header.php';
include 'inc/sidebar.php';
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
          <?php foreach($tabulation as $row): 
              // for each subject show marks from marks table
            ?>
            <tr>
              <td><?= $row['position'] ?></td>
              <td><?= $row['roll_number'] ?></td>
              <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
              <?php foreach($subjects as $s):
                $m = $pdo->prepare("SELECT obtained_marks FROM marks WHERE exam_subject_id=? AND student_id=?");
                $m->execute([$s['id'],$row['student_id']]);
                $mr = $m->fetch();
                $obt = $mr ? $mr['obtained_marks'] : 0;
              ?>
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
