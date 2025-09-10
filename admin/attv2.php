<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

$current_date = date('Y-m-d');
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();

$selected_class = '';
$selected_section = '';
$selected_date = $current_date;
$attendance_data = [];
$students = [];
$is_existing_record = false;

// Attendance submit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $class_id = intval($_POST['class_id']);
    $section_id = intval($_POST['section_id']);
    $date = $_POST['date'];

    $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE class_id = ? AND section_id = ? AND date = ?");
    $check_stmt->execute([$class_id, $section_id, $date]);
    $is_existing_record = ($check_stmt->fetch()['count'] > 0);

    try {
        $pdo->beginTransaction();

        if ($is_existing_record) {
            foreach ($_POST['attendance'] as $student_id => $data) {
                $status = $data['status'] ?? '';
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
                $status = $data['status'] ?? '';
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

// Attendance view
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['view_attendance'])) {
    $selected_class = intval($_GET['class_id']);
    $selected_section = intval($_GET['section_id']);
    $selected_date = $_GET['date'];

    $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE class_id = ? AND section_id = ? AND date = ?");
    $check_stmt->execute([$selected_class, $selected_section, $selected_date]);
    $is_existing_record = ($check_stmt->fetch()['count'] > 0);

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

// Sections
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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>উপস্থিতি ব্যবস্থাপনা</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
<style>
    body {font-family:'SolaimanLipi', sans-serif;}
    .attendance-table th {text-align:center;}
    .radio-cell {width:70px;text-align:center;}
    input[type="radio"]{display:none;}
    .radio-label{display:inline-block;width:40px;height:40px;line-height:40px;border-radius:50%;cursor:pointer;color:#999;border:2px solid #ccc;transition:.3s;}
    input[type="radio"]:checked + .radio-label.present{background:#2e7d32;color:#fff;border-color:#2e7d32;}
    input[type="radio"]:checked + .radio-label.absent{background:#c62828;color:#fff;border-color:#c62828;}
    input[type="radio"]:checked + .radio-label.late{background:#f57f17;color:#fff;border-color:#f57f17;}
    .header-btn{font-size:20px;margin:0 5px;cursor:pointer;}
</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; ?>

<div class="content-wrapper">
<section class="content">
<div class="container-fluid">
    <form method="GET">
        <div class="row">
            <div class="col-md-3">
                <select name="class_id" class="form-control" required>
                    <option value="">ক্লাস</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?=$c['id']?>" <?=$selected_class==$c['id']?'selected':''?>><?=$c['name']?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="section_id" class="form-control" required>
                    <option value="">শাখা</option>
                    <?php foreach($sections as $s): ?>
                        <option value="<?=$s['id']?>" <?=$selected_section==$s['id']?'selected':''?>><?=$s['name']?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control" name="date" value="<?=$selected_date?>" required>
            </div>
            <div class="col-md-3">
                <button type="submit" name="view_attendance" class="btn btn-primary">দেখুন</button>
            </div>
        </div>
    </form>

<?php if(!empty($students)||!empty($attendance_data)): ?>
<form method="POST">
<input type="hidden" name="class_id" value="<?=$selected_class?>">
<input type="hidden" name="section_id" value="<?=$selected_section?>">
<input type="hidden" name="date" value="<?=$selected_date?>">
<div class="text-center my-2">
    <span class="header-btn text-success" id="markAllPresent"><i class="fas fa-check-circle"></i> সকল উপস্থিত</span>
    <span class="header-btn text-danger" id="markAllAbsent"><i class="fas fa-times-circle"></i> সকল অনুপস্থিত</span>
    <span class="header-btn text-warning" id="markAllLate"><i class="fas fa-clock"></i> সকল দেরি</span>
</div>
<div class="table-responsive">
<table class="table table-bordered attendance-table">
<thead>
<tr>
    <th>রোল</th>
    <th>শিক্ষার্থীর নাম</th>
    <th>Present</th>
    <th>Absent</th>
    <th>Late</th>
    <th>মন্তব্য</th>
</tr>
</thead>
<tbody>
<?php foreach($students as $st):
    $sid=$st['id']; $status=''; $remarks='';
    if($is_existing_record){
        foreach($attendance_data as $rec){if($rec['student_id']==$sid){$status=$rec['status'];$remarks=$rec['remarks'];}}
    }
?>
<tr>
    <td><?=$st['roll_number']?></td>
    <td><?=$st['first_name'].' '.$st['last_name']?></td>
    <td class="radio-cell">
        <input type="radio" id="p<?=$sid?>" name="attendance[<?=$sid?>][status]" value="present" <?=$status=='present'?'checked':''?>>
        <label for="p<?=$sid?>" class="radio-label present"><i class="fas fa-check"></i></label>
    </td>
    <td class="radio-cell">
        <input type="radio" id="a<?=$sid?>" name="attendance[<?=$sid?>][status]" value="absent" <?=$status=='absent'?'checked':''?>>
        <label for="a<?=$sid?>" class="radio-label absent"><i class="fas fa-times"></i></label>
    </td>
    <td class="radio-cell">
        <input type="radio" id="l<?=$sid?>" name="attendance[<?=$sid?>][status]" value="late" <?=$status=='late'?'checked':''?>>
        <label for="l<?=$sid?>" class="radio-label late"><i class="fas fa-clock"></i></label>
    </td>
    <td><input type="text" class="form-control form-control-sm" name="attendance[<?=$sid?>][remarks]" value="<?=$remarks?>"></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
<button type="submit" name="mark_attendance" class="btn btn-success">সংরক্ষণ করুন</button>
</form>
<?php endif; ?>
</div>
</section>
</div>
<?php include 'inc/footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$('#markAllPresent').click(()=>{$('input[value="present"]').prop('checked',true).trigger('change');});
$('#markAllAbsent').click(()=>{$('input[value="absent"]').prop('checked',true).trigger('change');});
$('#markAllLate').click(()=>{$('input[value="late"]').prop('checked',true).trigger('change');});
</script>
</body>
</html>