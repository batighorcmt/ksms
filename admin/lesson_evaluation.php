<?php
require_once '../config.php';
require_once 'print_common.php';
require_once __DIR__ . '/inc/enrollment_helpers.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['teacher', 'super_admin'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Fetch teacher's routine (class, section, subject) for today (with subject name), ordered by class numeric value ASC
// Show only today's and upcoming classes for the teacher
$routine_stmt = $pdo->prepare("
    SELECT r.id, c.id as class_id, c.name as class_name, c.numeric_value as class_numeric, s.id as section_id, s.name as section_name, r.subject_id, sub.name as subject_name, r.day_of_week
    FROM routines r
    JOIN classes c ON r.class_id = c.id
    JOIN sections s ON r.section_id = s.id
    JOIN subjects sub ON r.subject_id = sub.id
    WHERE r.teacher_id = ?
    ORDER BY c.numeric_value ASC, s.name ASC, sub.name ASC
");
$routine_stmt->execute([$user_id]);
$all_routines = $routine_stmt->fetchAll();

// Filter: only today's and future classes
$today_weekday = strtolower(date('l')); // e.g. 'monday', 'tuesday', etc.
$routines = array_filter($all_routines, function($r) use ($today_weekday) {
    // If routine has 'day_of_week' column, compare
    if (isset($r['day_of_week']) && $r['day_of_week']) {
        $routine_day = strtolower($r['day_of_week']);
        // Show if today or future day in week
        $weekdays = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
        $today_index = array_search($today_weekday, $weekdays);
        $routine_index = array_search($routine_day, $weekdays);
        return $routine_index !== false && $routine_index >= $today_index;
    }
    // If no day_of_week, fallback: show only today
    return true;
});

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

// For student select2 (current academic year via enrollment)
$students = [];
if (isset($_GET['class_id']) && isset($_GET['section_id'])) {
    $classId = intval($_GET['class_id']);
    $sectionId = intval($_GET['section_id']);
    if (function_exists('enrollment_table_exists') && enrollment_table_exists($pdo)) {
        $yearId = current_academic_year_id($pdo);
        $sql = "SELECT s.id, se.roll_number, s.first_name, s.last_name
                FROM students s
                JOIN students_enrollment se ON se.student_id = s.id
                WHERE se.academic_year_id = ? AND se.class_id = ? AND se.section_id = ?
                  AND (se.status='active' OR se.status IS NULL OR se.status='Active' OR se.status=1 OR se.status='1')
                ORDER BY se.roll_number ASC, s.id ASC";
        $st_stmt = $pdo->prepare($sql);
        $st_stmt->execute([$yearId, $classId, $sectionId]);
        $students = $st_stmt->fetchAll();
    } else {
        // Fallback when enrollment table is not available: select without relying on students.roll_number (may not exist)
        $st_stmt = $pdo->prepare("SELECT id, first_name, last_name, NULL AS roll_number FROM students WHERE class_id=? AND section_id=? AND status='active' ORDER BY first_name ASC, last_name ASC");
        $st_stmt->execute([$classId, $sectionId]);
        $students = $st_stmt->fetchAll();
    }
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
                        $st_map = [];
                        if (function_exists('enrollment_table_exists') && enrollment_table_exists($pdo)) {
                            $yearId = current_academic_year_id($pdo);
                            // 1) Try: current year + class/section
                            $params1 = array_merge([$yearId, $ev['class_id'], $ev['section_id']], $st_ids);
                            $st_stmt = $pdo->prepare("SELECT s.id, se.roll_number, s.first_name, s.last_name
                                                       FROM students s
                                                       JOIN students_enrollment se ON se.student_id = s.id
                                                       WHERE se.academic_year_id = ? AND se.class_id = ? AND se.section_id = ? AND s.id IN ($in)");
                            $st_stmt->execute($params1);
                            foreach($st_stmt->fetchAll() as $st) { $st_map[(int)$st['id']] = $st; }

                            // 2) Fallback: any year but same class/section
                            $presentIds = array_map('intval', array_keys($st_map));
                            $missing = array_values(array_diff(array_map('intval', $st_ids), $presentIds));
                            if (!empty($missing)) {
                                $in2 = str_repeat('?,', count($missing)-1) . '?';
                                $params2 = array_merge([$ev['class_id'], $ev['section_id']], $missing);
                                $st_stmt2 = $pdo->prepare("SELECT s.id, se.roll_number, s.first_name, s.last_name
                                                           FROM students s
                                                           JOIN students_enrollment se ON se.student_id = s.id
                                                           WHERE se.class_id = ? AND se.section_id = ? AND s.id IN ($in2)");
                                $st_stmt2->execute($params2);
                                foreach($st_stmt2->fetchAll() as $st) { $st_map[(int)$st['id']] = $st; }
                            }

                            // 3) Final fallback: students table only (no roll)
                            $presentIds = array_map('intval', array_keys($st_map));
                            $missing = array_values(array_diff(array_map('intval', $st_ids), $presentIds));
                            if (!empty($missing)) {
                                $in3 = str_repeat('?,', count($missing)-1) . '?';
                                $st_stmt3 = $pdo->prepare("SELECT id, first_name, last_name FROM students WHERE id IN ($in3)");
                                $st_stmt3->execute($missing);
                                foreach($st_stmt3->fetchAll() as $st) { $st_map[(int)$st['id']] = ['id'=>$st['id'], 'first_name'=>$st['first_name'], 'last_name'=>$st['last_name'], 'roll_number'=>null]; }
                            }
                        } else {
                            // Fallback without enrollment: avoid selecting non-existent students.roll_number
                            $st_stmt = $pdo->prepare("SELECT id, first_name, last_name, NULL AS roll_number FROM students WHERE id IN ($in)");
                            $st_stmt->execute($st_ids);
                            foreach($st_stmt->fetchAll() as $st) { $st_map[(int)$st['id']] = $st; }
                        }
                        foreach($st_ids as $sid) {
                            $key = (int)$sid;
                            if(isset($st_map[$key])) {
                                $st = $st_map[$key];
                                $name = trim(($st['first_name'] ?? '').' '.($st['last_name'] ?? ''));
                                $roll = isset($st['roll_number']) && $st['roll_number'] !== null && $st['roll_number'] !== '' ? (string)$st['roll_number'] : '';
                                // Format: roll-name (no spaces)
                                $label = $roll !== '' ? ($roll.'-'.$name) : $name;
                                echo '<span class="badge">'.htmlspecialchars($label).'</span> ';
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
        font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;
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
/* Custom Select2 Styling */
.select2-container--default .select2-selection--multiple {
    background-color: #ffffff;
    border: 1px solid #d1d3e2;
    border-radius: 0.35rem;
    cursor: text;
    min-height: 45px;
    padding: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: border-color 0.2s ease-in-out;
}

.select2-container--default .select2-selection--multiple:focus {
    border-color: #4e73df;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #4e73df;
    border: none;
    color: #fff;
    border-radius: 20px;
    padding: 5px 10px;
    margin: 3px 5px 3px 0;
    font-size: 0.9rem;
    font-weight: 500;
    transition: background-color 0.2s ease;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    margin-left: 8px;
    color: #ddd;
    cursor: pointer;
    font-weight: bold;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #f8d7da;
}

/* Dropdown Styling */
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #4e73df;
    color: white;
}

.select2-container--default .select2-results__option {
    padding: 8px 12px;
}

/* Adjust search input inside dropdown */
.select2-container--default .select2-search--dropdown .select2-search__field {
    padding: 8px;
    border-radius: 0.35rem;
    border: 1px solid #ccc;
}
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php
    if (hasRole(['super_admin'])) {
        include 'inc/sidebar.php';
    } elseif (hasRole(['teacher'])) {
        include '../teacher/inc/sidebar.php';
    }
    ?>
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

                        // Check if evaluation date is today or future
                        $can_edit = true;
                        if($eval && isset($eval['date'])) {
                            $eval_date = $eval['date'];
                            if(strtotime($eval_date) < strtotime($today)) {
                                $can_edit = false;
                            }
                        }
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
                                <select name="students[]" class="form-control select2-student" multiple required style="width:100%; min-height:48px;" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                    <?php foreach($students as $st): ?>
                                        <?php
                                            $student_id = $st['id'];
                                            $first_name = htmlspecialchars($st['first_name'] ?? '');
                                            $last_name = htmlspecialchars($st['last_name'] ?? '');
                                            $roll = isset($st['roll_number']) && $st['roll_number'] !== null ? htmlspecialchars($st['roll_number']) : '';
                                            $is_selected = in_array($student_id, $selected_students ?? []) ? 'selected' : '';
                                            // Format: roll-name (no spaces), e.g., 18-হালিম
                                            $option_text = ($roll !== '' ? $roll . '-' : '') . $first_name . ' ' . $last_name;
                                        ?>
                                        <option value="<?php echo $student_id; ?>" <?php echo $is_selected; ?>><?php echo $option_text; ?></option>
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
                                    <input type="checkbox" name="is_completed" value="1" <?php echo ($eval && $eval['is_completed']) ? 'checked' : ''; ?> <?php echo !$can_edit ? 'disabled' : ''; ?>> হ্যাঁ
                                </div>
                                <div class="form-group col-md-3">
                                    <label>মন্তব্য</label>
                                    <input type="text" name="remarks" class="form-control" value="<?php echo $eval['remarks'] ?? ''; ?>" <?php echo !$can_edit ? 'readonly' : ''; ?>>
                                </div>
                            </div>
                            <?php if($can_edit): ?>
                            <button type="submit" class="btn btn-success">
                                <?php echo $eval ? 'আপডেট করুন' : 'সংরক্ষণ করুন'; ?>
                            </button>
                            <?php else: ?>
                            <div class="alert alert-warning mt-2">মূল্যায়নের দিন পার হয়ে গেছে, আর পরিবর্তন করা যাবে না।</div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <b>মূল্যায়ন রিপোর্ট</b>
                        <a href="?print=1<?php
                            if(isset($_GET['report_date'])) echo '&report_date='.urlencode($_GET['report_date']);
                            if(isset($_GET['class_name'])) echo '&class_name='.urlencode($_GET['class_name']);
                            if(isset($_GET['section_name'])) echo '&section_name='.urlencode($_GET['section_name']);
                            if(isset($_GET['subject'])) echo '&subject='.urlencode($_GET['subject']);
                            if(isset($_GET['teacher_name'])) echo '&teacher_name='.urlencode($_GET['teacher_name']);
                            if(isset($_GET['student_name'])) echo '&student_name='.urlencode($_GET['student_name']);
                        ?>" target="_blank" class="btn btn-sm btn-primary"><i class="fa fa-print"></i> প্রিন্ট</a>
                    </div>
                    <div class="card-body table-responsive">
                        <?php
                        // Load classes, teachers, students for filter dropdowns
                        $filter_classes = $pdo->query("SELECT id, name FROM classes ORDER BY numeric_value ASC")->fetchAll();
                        $filter_teachers = $pdo->query("SELECT id, full_name FROM users WHERE status='active' AND role='teacher' ORDER BY full_name ASC")->fetchAll();
                        $filter_students = $pdo->query("SELECT id, first_name, last_name FROM students WHERE status='active' ORDER BY first_name, last_name ASC")->fetchAll();
                        ?>
                        <form method="get" class="mb-3" id="filterForm">
                            <div class="form-row">
                                <div class="form-group col-md-2">
                                    <label>শুরুর তারিখ</label>
                                    <input type="date" name="date_start" class="form-control" value="<?= htmlspecialchars($_GET['date_start'] ?? $today) ?>">
                                </div>
                                <div class="form-group col-md-2">
                                    <label>শেষ তারিখ</label>
                                    <input type="date" name="date_end" class="form-control" value="<?= htmlspecialchars($_GET['date_end'] ?? $today) ?>">
                                </div>
                                <div class="form-group col-md-2">
                                    <label>শ্রেণি</label>
                                    <select name="class_id" id="filter_class" class="form-control">
                                        <option value="">সব</option>
                                        <?php foreach($filter_classes as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= (isset($_GET['class_id']) && $_GET['class_id']==$c['id'])?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label>শাখা</label>
                                    <select name="section_id" id="filter_section" class="form-control">
                                        <option value="">সব</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label>বিষয়</label>
                                    <select name="subject" id="filter_subject" class="form-control">
                                        <option value="">সব</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label>শিক্ষক</label>
                                    <select name="teacher_id" id="filter_teacher" class="form-control select2-filter">
                                        <option value="">সব</option>
                                        <?php foreach($filter_teachers as $t): ?>
                                            <option value="<?= $t['id'] ?>" <?= (isset($_GET['teacher_id']) && $_GET['teacher_id']==$t['id'])?'selected':'' ?>><?= htmlspecialchars($t['full_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label>শিক্ষার্থী</label>
                                    <select name="student_id" id="filter_student" class="form-control select2-filter">
                                        <option value="">সব</option>
                                        <?php foreach($filter_students as $st): ?>
                                            <option value="<?= $st['id'] ?>" <?= (isset($_GET['student_id']) && $_GET['student_id']==$st['id'])?'selected':'' ?>><?= htmlspecialchars($st['first_name'].' '.$st['last_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search"></i> খুঁজুন</button>
                        </form>
<?php /* JS for filter form */ ?>
<script>
$(function(){
    // Select2 for teacher/student
    $('.select2-filter').select2({width:'100%',placeholder:'নির্বাচন করুন',allowClear:true});
    // Dependent dropdowns for section/subject
    $('#filter_class').on('change', function(){
        var classId = $(this).val();
        // Load sections
        $.get('ajax/get_sections.php', {class_id:classId}, function(data){
            var opts = '<option value="">সব</option>';
            $.each(data, function(i, s){
                opts += '<option value="'+s.id+'">'+s.name+'</option>';
            });
            $('#filter_section').html(opts);
        },'json');
        // Load subjects
        $.get('ajax/get_subjects_by_class.php', {class_id:classId}, function(data){
            var opts = '<option value="">সব</option>';
            $.each(data, function(i, s){
                opts += '<option value="'+s.id+'">'+s.name+'</option>';
            });
            $('#filter_subject').html(opts);
        },'json');
    });
    // Trigger on page load if class selected
    if($('#filter_class').val()){
        $('#filter_class').trigger('change');
    }
});
</script>
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
                                <?php
                                // Filter evaluations
                                $filtered_evaluations = array_filter($evaluations, function($ev) {
                                    $date_start = $_GET['date_start'] ?? date('Y-m-d');
                                    $date_end = $_GET['date_end'] ?? date('Y-m-d');
                                    if($date_start && $ev['date'] < $date_start) return false;
                                    if($date_end && $ev['date'] > $date_end) return false;
                                    if(isset($_GET['class_id']) && $_GET['class_id'] && $ev['class_id'] != $_GET['class_id']) return false;
                                    if(isset($_GET['section_id']) && $_GET['section_id'] && $ev['section_id'] != $_GET['section_id']) return false;
                                    if(isset($_GET['subject']) && $_GET['subject'] && $ev['subject'] != $_GET['subject']) return false;
                                    if(isset($_GET['teacher_id']) && $_GET['teacher_id'] && $ev['teacher_id'] != $_GET['teacher_id']) return false;
                                    if(isset($_GET['student_id']) && $_GET['student_id']) {
                                        $st_ids = json_decode($ev['evaluated_students'], true) ?? [];
                                        if(!in_array($_GET['student_id'], $st_ids)) return false;
                                    }
                                    return true;
                                });
                                foreach($filtered_evaluations as $ev): ?>
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
                                            // Prefer enrollment for roll numbers when available
                                            $st_map = [];
                                            if (function_exists('enrollment_table_exists') && enrollment_table_exists($pdo)) {
                                                $yearId = current_academic_year_id($pdo);
                                                // 1) Try: current year + class/section
                                                $params1 = array_merge([$yearId, $ev['class_id'], $ev['section_id']], $st_ids);
                                                $st_stmt = $pdo->prepare("SELECT s.id, se.roll_number, s.first_name, s.last_name FROM students s JOIN students_enrollment se ON se.student_id = s.id WHERE se.academic_year_id = ? AND se.class_id = ? AND se.section_id = ? AND s.id IN ($in)");
                                                $st_stmt->execute($params1);
                                                foreach($st_stmt->fetchAll() as $st) { $st_map[(int)$st['id']] = $st; }

                                                // 2) Fallback: any year but same class/section, for remaining
                                                $presentIds = array_map('intval', array_keys($st_map));
                                                $missing = array_values(array_diff(array_map('intval', $st_ids), $presentIds));
                                                if (!empty($missing)) {
                                                    $in2 = str_repeat('?,', count($missing)-1) . '?';
                                                    $params2 = array_merge([$ev['class_id'], $ev['section_id']], $missing);
                                                    $st_stmt2 = $pdo->prepare("SELECT s.id, se.roll_number, s.first_name, s.last_name FROM students s JOIN students_enrollment se ON se.student_id = s.id WHERE se.class_id = ? AND se.section_id = ? AND s.id IN ($in2)");
                                                    $st_stmt2->execute($params2);
                                                    foreach($st_stmt2->fetchAll() as $st) { $st_map[(int)$st['id']] = $st; }
                                                }

                                                // 3) Final fallback: students table only (no roll)
                                                $presentIds = array_map('intval', array_keys($st_map));
                                                $missing = array_values(array_diff(array_map('intval', $st_ids), $presentIds));
                                                if (!empty($missing)) {
                                                    $in3 = str_repeat('?,', count($missing)-1) . '?';
                                                    $st_stmt3 = $pdo->prepare("SELECT id, first_name, last_name FROM students WHERE id IN ($in3)");
                                                    $st_stmt3->execute($missing);
                                                    foreach($st_stmt3->fetchAll() as $st) { $st_map[(int)$st['id']] = ['id'=>$st['id'], 'first_name'=>$st['first_name'], 'last_name'=>$st['last_name'], 'roll_number'=>null]; }
                                                }
                                            } else {
                                                $st_stmt = $pdo->prepare("SELECT id, first_name, last_name, NULL AS roll_number FROM students WHERE id IN ($in)");
                                                $st_stmt->execute($st_ids);
                                                foreach($st_stmt->fetchAll() as $st) { $st_map[(int)$st['id']] = $st; }
                                            }
                                            foreach($st_ids as $sid) {
                                                $key = (int)$sid;
                                                if(isset($st_map[$key])) {
                                                    $st = $st_map[$key];
                                                    $name = trim(($st['first_name'] ?? '').' '.($st['last_name'] ?? ''));
                                                    $roll = isset($st['roll_number']) && $st['roll_number'] !== null && $st['roll_number'] !== '' ? (string)$st['roll_number'] : '';
                                                    // Format: roll-name (no spaces)
                                                    $label = $roll !== '' ? ($roll.'-'.$name) : $name;
                                                    echo '<span class="badge badge-info">'.htmlspecialchars($label).'</span> ';
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

<script>
$(function() {
    $('.select2-student').select2({
        width: '100%',
        placeholder: "ছাত্র/ছাত্রী নির্বাচন করুন",
        allowClear: true,
        templateSelection: function (data, container) {
            // Default Select2 behavior: each selected student is a separate badge
            return data.text;
        }
    });
    // Fix select2 height and X button alignment
    $('.select2-student').on('select2:open', function() {
        $('.select2-results__option').css('font-size', '1rem');
    });
    // Custom CSS for X button
    $('<style>\
    .select2-container--default .select2-selection--multiple {\
        display: flex;\
        flex-wrap: wrap;\
        align-items: flex-start;\
        min-height: 45px;\
    }\
    .select2-container--default .select2-selection--multiple .select2-selection__rendered {\
        display: flex;\
        flex-wrap: wrap;\
        align-items: center;\
        gap: 4px;\
        padding: 0;\
    }\
    .select2-container--default .select2-selection--multiple .select2-selection__choice {\
        min-height: 32px;\
        display: flex;\
        align-items: center;\
        padding: 5px 10px 5px 18px;\
        position: relative;\
        margin: 3px 5px 3px 0;\
        font-size: 0.95rem;\
        font-weight: 500;\
        background-color: #4e73df;\
        color: #fff;\
        border-radius: 20px;\
    }\
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {\
        position: absolute;\
        left: 6px;\
        top: 50%;\
        transform: translateY(-50%);\
        color: #fff;\
        background: #e74c3c;\
        border-radius: 50%;\
        width: 18px;\
        height: 18px;\
        display: flex;\
        align-items: center;\
        justify-content: center;\
        font-size: 1rem;\
        line-height: 1;\
        opacity: 0.85;\
        transition: background 0.2s;\
        margin-right: 8px;\
    }\
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {\
        background: #c0392b;\
        opacity: 1;\
    }\
    </style>').appendTo('head');
});
</script>

</body>
</html>
