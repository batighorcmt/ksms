<?php
require_once '../config.php';
require_once 'print_common.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['teacher', 'super_admin'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Fetch teacher's routine (class, section, subject) for today (with subject name)
$routine_stmt = $pdo->prepare("SELECT r.id, c.id as class_id, c.name as class_name, s.id as section_id, s.name as section_name, r.subject_id, sub.name as subject_name FROM routines r JOIN classes c ON r.class_id = c.id JOIN sections s ON r.section_id = s.id JOIN subjects sub ON r.subject_id = sub.id WHERE r.teacher_id = ?");
$routine_stmt->execute([$user_id]);
$routines = $routine_stmt->fetchAll();

// Fetch teacher name
$teacher_name = '';
$teacher_stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$teacher_stmt->execute([$user_id]);
$teacher_name = $teacher_stmt->fetchColumn() ?: '';

// Handle add/update evaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = $_POST['class_id'];
    $section_id = $_POST['section_id'];
    $subject = $_POST['subject'];
    $date = $_POST['date'];
    $students = isset($_POST['students']) ? $_POST['students'] : [];
    $is_completed = isset($_POST['is_completed']) ? 1 : 0;
    $remarks = trim($_POST['remarks']);
    $now = date('Y-m-d H:i:s');
    $eval_id = isset($_POST['eval_id']) ? intval($_POST['eval_id']) : 0;
    $students_json = json_encode($students, JSON_UNESCAPED_UNICODE);

    if ($eval_id > 0) {
        // Update
        $stmt = $pdo->prepare("UPDATE lesson_evaluation SET evaluated_students=?, is_completed=?, remarks=?, updated_at=? WHERE id=? AND teacher_id=?");
        $stmt->execute([$students_json, $is_completed, $remarks, $now, $eval_id, $user_id]);
        $_SESSION['success'] = 'মূল্যায়ন আপডেট হয়েছে!';
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO lesson_evaluation (teacher_id, class_id, section_id, subject, date, evaluated_students, is_completed, remarks, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $class_id, $section_id, $subject, $date, $students_json, $is_completed, $remarks, $now]);
        $_SESSION['success'] = 'মূল্যায়ন রেকর্ড হয়েছে!';
    }
    header('Location: lesson_evaluation.php');
    exit();
}

// Fetch all evaluations (with teacher name)
$eval_stmt = $pdo->prepare("SELECT le.*, c.name as class_name, s.name as section_name, u.full_name as teacher_name FROM lesson_evaluation le JOIN classes c ON le.class_id = c.id JOIN sections s ON le.section_id = s.id JOIN users u ON le.teacher_id = u.id ORDER BY le.date DESC, le.id DESC");
$eval_stmt->execute();
$evaluations = $eval_stmt->fetchAll();

// For student select2
$students = [];
if (isset($_GET['class_id']) && isset($_GET['section_id'])) {
    $st_stmt = $pdo->prepare("SELECT id, first_name, last_name FROM students WHERE class_id=? AND section_id=? AND status='active' ORDER BY roll_number ASC");
    $st_stmt->execute([$_GET['class_id'], $_GET['section_id']]);
    $students = $st_stmt->fetchAll();
}

// Print mode
$is_print = isset($_GET['print']) && $_GET['print'] == '1';
if ($is_print) {
    ?><!doctype html>
    <html lang="bn">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>মূল্যায়ন রিপোর্ট প্রিন্ট</title>
        <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
        <style>body{font-family:'SolaimanLipi',sans-serif;color:#222} .table{width:100%;border-collapse:collapse} .table th,.table td{border:1px solid #e0e0e0;padding:8px} .badge{display:inline-block;padding:2px 7px;background:#e0e7ef;border-radius:4px;margin:1px 1px;font-size:0.95em}</style>
    </head>
    <body>
    <?php echo print_header($pdo, 'মূল্যায়ন রিপোর্ট'); ?>
    <table class="table">
        <thead>
            <tr>
                <th>তারিখ</th>
                <th>শ্রেণি</th>
                <th>শাখা</th>
                <th>বিষয়</th>
                <th>শিক্ষক</th>
                <th>ছাত্র/ছাত্রী</th>
                <th>পড়া হয়েছে?</th>
                <th>মন্তব্য</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($evaluations as $ev): ?>
            <tr>
                <td><?php echo htmlspecialchars($ev['date']); ?></td>
                <td><?php echo htmlspecialchars($ev['class_name']); ?></td>
                <td><?php echo htmlspecialchars($ev['section_name']); ?></td>
                <td><?php echo htmlspecialchars($ev['subject']); ?></td>
                <td><?php echo htmlspecialchars($ev['teacher_name']); ?></td>
                <td>
                    <?php 
                    $st_ids = json_decode($ev['evaluated_students'], true) ?? []; 
                    if ($st_ids) {
                        $in = str_repeat('?,', count($st_ids)-1) . '?';
                        $st_stmt = $pdo->prepare("SELECT id, roll_number, first_name, last_name FROM students WHERE id IN ($in)");
                        $st_stmt->execute($st_ids);
                        $st_map = [];
                        foreach($st_stmt->fetchAll() as $st) {
                            $st_map[$st['id']] = $st;
                        }
                        foreach($st_ids as $sid) {
                            if(isset($st_map[$sid])) {
                                $st = $st_map[$sid];
                                echo '<span class="badge">'.htmlspecialchars($st['roll_number']).' - '.htmlspecialchars($st['first_name'].' '.$st['last_name']).'</span> ';
                            } else {
                                echo '<span class="badge">'.$sid.'</span> ';
                            }
                        }
                    }
                    ?>
                </td>
                <td><?php echo $ev['is_completed'] ? 'হ্যাঁ' : 'না'; ?></td>
                <td><?php echo htmlspecialchars($ev['remarks']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php echo print_footer(); ?>
    <script>window.onload=function(){ window.print(); }</script>
    </body></html><?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শ্রেণি মূল্যায়ন</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>body, .main-sidebar, .nav-link { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }
        :root {
        --primary-color: #4e73df;
        --primary-light: #6e87f7;
        --secondary-color: #1cc88a;
        --accent-color: #36b9cc;
        --bg-light: #f8f9fc;
        --bg-dark: #ffffff;
        --text-dark: #5a5c69;
        --text-light: #858796;
        --border-color: #e3e6f0;
        --badge-bg: #f6c23e;
        --badge-text: #ffffff;
        --table-header-bg: #4e73df;
        --table-header-text: #ffffff;
        --btn-hover-darken: rgba(0,0,0,0.1);
        --shadow: 0 4px 6px rgba(0,0,0,0.1);
        --radius: 0.35rem;
    }

    body {
        background-color: var(--bg-light);
        color: var(--text-dark);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .card {
        background: var(--bg-dark);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        margin-bottom: 1.5rem;
    }

    .card-header {
        background: var(--primary-color);
        color: var(--table-header-text);
        padding: 0.75rem 1rem;
        border-bottom: none;
        border-top-left-radius: var(--radius);
        border-top-right-radius: var(--radius);
    }

    .card-body {
        padding: 1rem;
    }

    .btn {
        background-color: var(--primary-color);
        color: #ffffff;
        border: none;
        border-radius: var(--radius);
        padding: 0.5rem 1rem;
        transition: background-color 0.2s ease, transform 0.1s ease;
    }

    .btn:hover, .btn:focus {
        background-color: var(--primary-light);
        transform: scale(1.02);
    }

    .btn-info {
        background-color: var(--secondary-color);
        color: #ffffff;
    }
    .btn-info:hover {
        background-color: #17d389;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        background: #ffffff;
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow);
    }

    .table th, .table td {
        padding: 0.75rem 1rem;
        border: 1px solid var(--border-color);
    }

    .table thead {
        background: var(--table-header-bg);
        color: var(--table-header-text);
    }

    .badge {
        display: inline-block;
        padding: 0.25rem 0.6rem;
        font-size: 0.85rem;
        font-weight: bold;
        background-color: var(--badge-bg);
        color: var(--badge-text);
        border-radius: var(--radius);
        margin: 0.2rem 0.2rem 0.2rem 0;
    }

    input[readonly], input.form-control[readonly] {
        background-color: #e9ecef;
    }

    .form-control:focus {
        border-color: var(--primary-light);
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, .25);
    }

    /* Select2 overrides */
    .select2-container--default .select2-selection--multiple {
        background-color: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background: var(--accent-color);
        color: #fff;
        border: none;
        border-radius: var(--radius);
    }

    /* Breadcrumbs, nav links etc */
    .breadcrumb .breadcrumb-item a {
        color: var(--primary-color);
    }
    .breadcrumb .breadcrumb-item.active {
        color: var(--text-dark);
    }

    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">শ্রেণি মূল্যায়ন</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">মূল্যায়ন</li>
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
                    <div class="card-header"><b>রুটিন অনুযায়ী ক্লাস তালিকা</b></div>
                    <div class="card-body">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>শ্রেণি</th>
                                    <th>শাখা</th>
                                    <th>বিষয়</th>
                                    <th>মূল্যায়ন</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($routines as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['section_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['subject_name'] ?? ''); ?></td>
                                    <td>
                                        <a href="?class_id=<?php echo $r['class_id']; ?>&section_id=<?php echo $r['section_id']; ?>&subject=<?php echo isset($r['subject_name']) ? urlencode($r['subject_name']) : ''; ?>" class="btn btn-info btn-sm">দেখুন/মূল্যায়ন</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if(isset($_GET['class_id'], $_GET['section_id'], $_GET['subject'])): ?>
                <div class="card mb-4">
                    <div class="card-header"><b>মূল্যায়ন ফরম</b></div>
                    <div class="card-body">
                        <?php
                        // Check if evaluation exists for this class/section/subject/date
                        $eval_stmt = $pdo->prepare("SELECT * FROM lesson_evaluation WHERE teacher_id=? AND class_id=? AND section_id=? AND subject=? AND date=?");
                        $eval_stmt->execute([$user_id, $_GET['class_id'], $_GET['section_id'], $_GET['subject'], $today]);
                        $eval = $eval_stmt->fetch();
                        $selected_students = $eval ? json_decode($eval['evaluated_students'], true) : [];
                        // Find the routine row matching the selected class/section/subject
                        $selected_class_name = '';
                        $selected_section_name = '';
                        $selected_subject_name = '';
                        foreach ($routines as $r) {
                            if ($r['class_id'] == $_GET['class_id'] && $r['section_id'] == $_GET['section_id'] && $r['subject_name'] == $_GET['subject']) {
                                $selected_class_name = $r['class_name'];
                                $selected_section_name = $r['section_name'];
                                $selected_subject_name = $r['subject_name'];
                                break;
                            }
                        }
                        // fallback if not found
                        if ($selected_class_name === '') $selected_class_name = htmlspecialchars($_GET['class_id']);
                        if ($selected_section_name === '') $selected_section_name = htmlspecialchars($_GET['section_id']);
                        if ($selected_subject_name === '') $selected_subject_name = htmlspecialchars($_GET['subject']);
                        ?>
                        <form method="POST">
                            <input type="hidden" name="class_id" value="<?php echo (int)$_GET['class_id']; ?>">
                            <input type="hidden" name="section_id" value="<?php echo (int)$_GET['section_id']; ?>">
                            <input type="hidden" name="subject" value="<?php echo htmlspecialchars($_GET['subject']); ?>">
                            <input type="hidden" name="date" value="<?php echo $today; ?>">
                            <?php if($eval): ?><input type="hidden" name="eval_id" value="<?php echo $eval['id']; ?>"><?php endif; ?>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>শ্রেণি</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($selected_class_name); ?>" readonly>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>শাখা</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($selected_section_name); ?>" readonly>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>বিষয়</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($selected_subject_name); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>ছাত্র/ছাত্রী (মাল্টি-সিলেক্ট)</label>
                                <select name="students[]" class="form-control select2" multiple required style="width:100%">
                                    <?php foreach($students as $st): ?>
                                        <option value="<?php echo $st['id']; ?>" <?php echo in_array($st['id'], $selected_students ?? []) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>তারিখ</label>
                                    <input type="text" class="form-control" value="<?php echo $today; ?>" readonly>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>শিক্ষক</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($teacher_name); ?>" readonly>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>পড়া হয়েছে কি?</label><br>
                                    <input type="checkbox" name="is_completed" value="1" <?php echo ($eval && $eval['is_completed']) ? 'checked' : ''; ?>> হ্যাঁ
                                </div>
                                <div class="form-group col-md-3">
                                    <label>মন্তব্য</label>
                                    <input type="text" name="remarks" class="form-control" value="<?php echo $eval['remarks'] ?? ''; ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">সংরক্ষণ করুন</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <b>মূল্যায়ন রিপোর্ট</b>
                        <a href="?print=1" target="_blank" class="btn btn-sm btn-primary"><i class="fa fa-print"></i> প্রিন্ট</a>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>তারিখ</th>
                                    <th>শ্রেণি</th>
                                    <th>শাখা</th>
                                    <th>বিষয়</th>
                                    <th>শিক্ষক</th>
                                    <th>ছাত্র/ছাত্রী</th>
                                    <th>পড়া হয়েছে?</th>
                                    <th>মন্তব্য</th>
                                    <th>সময়</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($evaluations as $ev): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ev['date']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['section_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['teacher_name']); ?></td>
                                    <td>
                                        <?php 
                                        $st_ids = json_decode($ev['evaluated_students'], true) ?? []; 
                                        if ($st_ids) {
                                            $in = str_repeat('?,', count($st_ids)-1) . '?';
                                            $st_stmt = $pdo->prepare("SELECT id, roll_number, first_name, last_name FROM students WHERE id IN ($in)");
                                            $st_stmt->execute($st_ids);
                                            $st_map = [];
                                            foreach($st_stmt->fetchAll() as $st) {
                                                $st_map[$st['id']] = $st;
                                            }
                                            foreach($st_ids as $sid) {
                                                if(isset($st_map[$sid])) {
                                                    $st = $st_map[$sid];
                                                    echo '<span class="badge badge-info">'.htmlspecialchars($st['roll_number']).' - '.htmlspecialchars($st['first_name'].' '.$st['last_name']).'</span> ';
                                                } else {
                                                    echo '<span class="badge badge-secondary">'.$sid.'</span> ';
                                                }
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $ev['is_completed'] ? 'হ্যাঁ' : 'না'; ?></td>
                                    <td><?php echo htmlspecialchars($ev['remarks']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($ev['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
