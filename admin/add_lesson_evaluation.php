<?php
require_once '../config.php';
require_once __DIR__ . '/inc/enrollment_helpers.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['teacher', 'super_admin'])) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Fetch teacher's routine (class, section, subject) for today
$routine_stmt = $pdo->prepare("SELECT r.id, c.id as class_id, c.name as class_name, s.id as section_id, s.name as section_name, r.subject_id, sub.name as subject_name FROM routines r JOIN classes c ON r.class_id = c.id JOIN sections s ON r.section_id = s.id JOIN subjects sub ON r.subject_id = sub.id WHERE r.teacher_id = ?");
$routine_stmt->execute([$user_id]);
$routines = $routine_stmt->fetchAll();

// For student select2
$students = [];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
if ($class_id && $section_id) {
    if (function_exists('enrollment_table_exists') && enrollment_table_exists($pdo)) {
        $yearId = current_academic_year_id($pdo);
        $sql = "SELECT s.id, s.first_name, s.last_name
                FROM students s
                JOIN students_enrollment se ON se.student_id = s.id
                WHERE se.academic_year_id = ? AND se.class_id = ? AND se.section_id = ?
                  AND (se.status='active' OR se.status IS NULL OR se.status='Active' OR se.status=1 OR se.status='1')
                ORDER BY se.roll_number ASC, s.id ASC";
        $st_stmt = $pdo->prepare($sql);
        $st_stmt->execute([$yearId, $class_id, $section_id]);
        $students = $st_stmt->fetchAll();
    } else {
        $st_stmt = $pdo->prepare("SELECT id, first_name, last_name FROM students WHERE class_id=? AND section_id=? AND status='active' ORDER BY roll_number ASC");
        $st_stmt->execute([$class_id, $section_id]);
        $students = $st_stmt->fetchAll();
    }
}

// Handle add evaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = $_POST['class_id'];
    $section_id = $_POST['section_id'];
    $subject_id = $_POST['subject_id'];
    $date = $_POST['date'];
    $students = isset($_POST['students']) ? $_POST['students'] : [];
    $is_completed = isset($_POST['is_completed']) ? 1 : 0;
    $remarks = trim($_POST['remarks']);
    $now = date('Y-m-d H:i:s');
    $students_json = json_encode($students, JSON_UNESCAPED_UNICODE);

    // Get subject name
    $sub_stmt = $pdo->prepare("SELECT name FROM subjects WHERE id=?");
    $sub_stmt->execute([$subject_id]);
    $subject_name = $sub_stmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO lesson_evaluation (teacher_id, class_id, section_id, subject, date, evaluated_students, is_completed, remarks, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $class_id, $section_id, $subject_name, $date, $students_json, $is_completed, $remarks, $now]);
    $_SESSION['success'] = 'মূল্যায়ন রেকর্ড হয়েছে!';
    redirect('lesson_evaluation.php');
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>নতুন শ্রেণি মূল্যায়ন</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>body, .main-sidebar, .nav-link { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">নতুন শ্রেণি মূল্যায়ন</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="lesson_evaluation.php">মূল্যায়ন</a></li>
                            <li class="breadcrumb-item active">নতুন</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                <div class="card mb-4">
                    <div class="card-header"><b>মূল্যায়ন ফরম</b></div>
                    <div class="card-body">
                        <form method="GET" class="mb-3">
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>শ্রেণি</label>
                                    <select name="class_id" class="form-control" required onchange="this.form.submit()">
                                        <option value="">নির্বাচন করুন</option>
                                        <?php foreach($routines as $r): ?>
                                            <option value="<?php echo $r['class_id']; ?>" <?php if($class_id==$r['class_id']) echo 'selected'; ?>><?php echo htmlspecialchars($r['class_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>শাখা</label>
                                    <select name="section_id" class="form-control" required onchange="this.form.submit()">
                                        <option value="">নির্বাচন করুন</option>
                                        <?php foreach($routines as $r): if($class_id==$r['class_id']): ?>
                                            <option value="<?php echo $r['section_id']; ?>" <?php if($section_id==$r['section_id']) echo 'selected'; ?>><?php echo htmlspecialchars($r['section_name']); ?></option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>বিষয়</label>
                                    <select name="subject_id" class="form-control" required onchange="this.form.submit()">
                                        <option value="">নির্বাচন করুন</option>
                                        <?php foreach($routines as $r): if($class_id==$r['class_id'] && $section_id==$r['section_id']): ?>
                                            <option value="<?php echo $r['subject_id']; ?>" <?php if($subject_id==$r['subject_id']) echo 'selected'; ?>><?php echo htmlspecialchars($r['subject_name']); ?></option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>তারিখ</label>
                                    <input type="date" name="date" class="form-control" value="<?php echo $today; ?>" required>
                                </div>
                            </div>
                        </form>
                        <?php if($class_id && $section_id && $subject_id): ?>
                        <form method="POST">
                            <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                            <input type="hidden" name="section_id" value="<?php echo $section_id; ?>">
                            <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                            <input type="hidden" name="date" value="<?php echo isset($_GET['date']) ? $_GET['date'] : $today; ?>">
                            <div class="form-group">
                                <label>ছাত্র/ছাত্রী (মাল্টি-সিলেক্ট)</label>
                                <select name="students[]" class="form-control select2" multiple required style="width:100%">
                                    <?php foreach($students as $st): ?>
                                        <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>শিক্ষক</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>" readonly>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>পড়া হয়েছে কি?</label><br>
                                    <input type="checkbox" name="is_completed" value="1"> হ্যাঁ
                                </div>
                                <div class="form-group col-md-4">
                                    <label>মন্তব্য</label>
                                    <input type="text" name="remarks" class="form-control">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">সংরক্ষণ করুন</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php include 'inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>$(function() { $('.select2').select2({ width: 'resolve' }); });</script>
</body>
</html>
