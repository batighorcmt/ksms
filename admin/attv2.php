<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// Get today's date
$current_date = date('Y-m-d');

// Get classes
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();

// Variables
$selected_class = '';
$selected_section = '';
$selected_date = $current_date;
$attendance_data = [];
$students = [];
$is_existing_record = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $class_id = intval($_POST['class_id']);
    $section_id = intval($_POST['section_id']);
    $date = $_POST['date'];

    // Check if attendance already exists
    $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE class_id = ? AND section_id = ? AND date = ?");
    $check_stmt->execute([$class_id, $section_id, $date]);
    $result = $check_stmt->fetch();
    $is_existing_record = ($result['count'] > 0);

    try {
        $pdo->beginTransaction();

        if ($is_existing_record) {
            foreach ($_POST['attendance'] as $student_id => $data) {
                $status = $data['status'] ?? null;
                $remarks = $data['remarks'] ?? '';

                $update_stmt = $pdo->prepare("
                    UPDATE attendance 
                    SET status = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE student_id = ? AND class_id = ? AND section_id = ? AND date = ?
                ");
                $update_stmt->execute([$status, $remarks, $student_id, $class_id, $section_id, $date]);
            }
            $_SESSION['success'] = "উপস্থিতি সফলভাবে আপডেট করা হয়েছে!";
        } else {
            $attendance_stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, class_id, section_id, date, status, remarks, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $recorded_by = $_SESSION['user_id'];

            foreach ($_POST['attendance'] as $student_id => $data) {
                $status = $data['status'] ?? null;
                $remarks = $data['remarks'] ?? '';

                $attendance_stmt->execute([$student_id, $class_id, $section_id, $date, $status, $remarks, $recorded_by]);
            }
            $_SESSION['success'] = "উপস্থিতি সফলভাবে রেকর্ড করা হয়েছে!";
        }
        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "উপস্থিতি রেকর্ড করতে সমস্যা হয়েছে: " . $e->getMessage();
    }

    $selected_class = $class_id;
    $selected_section = $section_id;
    $selected_date = $date;
}

// Handle view attendance
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['view_attendance'])) {
    $selected_class = intval($_GET['class_id']);
    $selected_section = intval($_GET['section_id']);
    $selected_date = $_GET['date'];

    $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE class_id = ? AND section_id = ? AND date = ?");
    $check_stmt->execute([$selected_class, $selected_section, $selected_date]);
    $result = $check_stmt->fetch();
    $is_existing_record = ($result['count'] > 0);

    $attendance_stmt = $pdo->prepare("
        SELECT a.*, s.first_name, s.last_name, s.roll_number 
        FROM attendance a 
        JOIN students s ON a.student_id = s.id 
        WHERE a.class_id = ? AND a.section_id = ? AND a.date = ?
        ORDER BY s.roll_number ASC
    ");
    $attendance_stmt->execute([$selected_class, $selected_section, $selected_date]);
    $attendance_data = $attendance_stmt->fetchAll();

    $student_stmt = $pdo->prepare("
        SELECT id, first_name, last_name, roll_number 
        FROM students 
        WHERE class_id = ? AND section_id = ? AND status='active'
        ORDER BY roll_number ASC
    ");
    $student_stmt->execute([$selected_class, $selected_section]);
    $students = $student_stmt->fetchAll();
}

// Get sections
$sections = [];
if ($selected_class) {
    $section_stmt = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? AND status='active'");
    $section_stmt->execute([$selected_class]);
    $sections = $section_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <title>উপস্থিতি ব্যবস্থাপনা - কিন্ডার গার্ডেন</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'SolaimanLipi', sans-serif; }
        .attendance-table th { text-align: center; }
        input[type="radio"] { display: none; }
        .radio-label {
            display:inline-block; width:40px; height:40px; line-height:40px;
            border-radius:50%; cursor:pointer; font-size:18px;
            background:#eee; color:#888; border:2px solid #ccc;
        }
        input[type="radio"]:checked + .radio-label.present { background:#2e7d32; color:#fff; border-color:#2e7d32; }
        input[type="radio"]:checked + .radio-label.absent { background:#c62828; color:#fff; border-color:#c62828; }
        input[type="radio"]:checked + .radio-label.late { background:#f57f17; color:#fff; border-color:#f57f17; }
        .header-btn { cursor:pointer; font-size:20px; margin:0 5px; }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebarv.php'; ?>

<div class="content-wrapper">
<section class="content">
<div class="container-fluid">

<!-- Filter Card -->
<div class="card">
  <div class="card-header bg-primary text-white">
    <h3 class="card-title">উপস্থিতি অনুসন্ধান</h3>
  </div>
  <div class="card-body">
    <form method="GET">
      <div class="row">
        <div class="col-md-3">
            <select class="form-control" name="class_id" required>
                <option value="">ক্লাস</option>
                <?php foreach($classes as $class): ?>
                    <option value="<?= $class['id'] ?>" <?= ($selected_class==$class['id'])?'selected':'' ?>>
                        <?= $class['name'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-control" name="section_id" required>
                <option value="">শাখা</option>
                <?php foreach($sections as $section): ?>
                    <option value="<?= $section['id'] ?>" <?= ($selected_section==$section['id'])?'selected':'' ?>>
                        <?= $section['name'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="date" class="form-control" name="date" value="<?= $selected_date ?>" required>
        </div>
        <div class="col-md-3">
            <button type="submit" name="view_attendance" class="btn btn-primary btn-block">
              <i class="fas fa-search"></i> দেখুন
            </button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php if(!empty($students)): ?>
<!-- Attendance Card -->
<div class="card">
  <div class="card-header bg-success text-white">
    <h3 class="card-title">উপস্থিতি তালিকা</h3>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="class_id" value="<?= $selected_class ?>">
      <input type="hidden" name="section_id" value="<?= $selected_section ?>">
      <input type="hidden" name="date" value="<?= $selected_date ?>">

      <table class="table table-bordered attendance-table">
      <thead>
      <tr>
          <th>রোল</th>
          <th>নাম</th>
          <th><i class="fas fa-check-circle text-success header-btn" id="selectAllPresent"></i></th>
          <th><i class="fas fa-times-circle text-danger header-btn" id="selectAllAbsent"></i></th>
          <th><i class="fas fa-clock text-warning header-btn" id="selectAllLate"></i></th>
          <th>মন্তব্য</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach($students as $student):
          $student_id = $student['id'];
          $current_status = null;
          $current_remarks = '';
          if($is_existing_record){
              foreach($attendance_data as $record){
                  if($record['student_id']==$student_id){
                      $current_status = $record['status'];
                      $current_remarks = $record['remarks'];
                      break;
                  }
              }
          }
      ?>
      <tr>
      <td><?= $student['roll_number'] ?></td>
      <td><?= $student['first_name'].' '.$student['last_name'] ?></td>
      <td>
        <input type="radio" name="attendance[<?= $student_id ?>][status]" id="p<?= $student_id ?>" value="present" <?= ($current_status=='present')?'checked':'' ?>>
        <label for="p<?= $student_id ?>" class="radio-label present"><i class="fas fa-check"></i></label>
      </td>
      <td>
        <input type="radio" name="attendance[<?= $student_id ?>][status]" id="a<?= $student_id ?>" value="absent" <?= ($current_status=='absent')?'checked':'' ?>>
        <label for="a<?= $student_id ?>" class="radio-label absent"><i class="fas fa-times"></i></label>
      </td>
      <td>
        <input type="radio" name="attendance[<?= $student_id ?>][status]" id="l<?= $student_id ?>" value="late" <?= ($current_status=='late')?'checked':'' ?>>
        <label for="l<?= $student_id ?>" class="radio-label late"><i class="fas fa-clock"></i></label>
      </td>
      <td>
        <input type="text" class="form-control form-control-sm" name="attendance[<?= $student_id ?>][remarks]" value="<?= $current_remarks ?>">
      </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      </table>

      <button type="submit" name="mark_attendance" class="btn btn-success">
        <i class="fas fa-save"></i> সংরক্ষণ করুন
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

</div>
</section>
</div>

<?php include 'inc/footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){
    $("#selectAllPresent").click(function(){
        $("input[value='present']").prop("checked",true).trigger("change");
    });
    $("#selectAllAbsent").click(function(){
        $("input[value='absent']").prop("checked",true).trigger("change");
    });
    $("#selectAllLate").click(function(){
        $("input[value='late']").prop("checked",true).trigger("change");
    });
});
</script>
</body>
</html>