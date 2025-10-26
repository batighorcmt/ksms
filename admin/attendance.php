<?php
require_once '../config.php';
require_once __DIR__ . '/inc/enrollment_helpers.php';
require_once __DIR__ . '/inc/sms_api.php'; // Include SMS functionality

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    header('Location: ../login.php');
    exit;
}

// Get today's date for default selection
$current_date = date('Y-m-d');

// Get current academic year id (for filtering current-year active students)
$current_year_row = $pdo->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch();
$current_year_id = $current_year_row['id'] ?? null;

// Get classes and sections
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();

// Initialize variables
$selected_class = '';
$selected_section = '';
$selected_date = $current_date;
$attendance_data = [];
$students = [];
$is_existing_record = false;
$allowed = false;

// Get SMS settings to determine which statuses should trigger messages
$sms_settings = [];
try {
    $sms_settings_stmt = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'sms_%'");
    $sms_settings = $sms_settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    error_log("Error fetching SMS settings: " . $e->getMessage());
}

// Get SMS templates
$sms_templates = [];
try {
    $tpl_stmt = $pdo->query("SELECT * FROM sms_templates");
    foreach ($tpl_stmt->fetchAll() as $tpl) {
        if (mb_stripos($tpl['title'], 'অনুপস্থিত') !== false || mb_stripos($tpl['title'], 'Absent') !== false) {
            $sms_templates['absent'] = $tpl['content'];
        } elseif (mb_stripos($tpl['title'], 'Late') !== false || mb_stripos($tpl['title'], 'দেরি') !== false) {
            $sms_templates['late'] = $tpl['content'];
        } elseif (mb_stripos($tpl['title'], 'Present') !== false || mb_stripos($tpl['title'], 'উপস্থিতি') !== false) {
            $sms_templates['present'] = $tpl['content'];
        } elseif (mb_stripos($tpl['title'], 'Half Day') !== false || mb_stripos($tpl['title'], 'অর্ধদিবস') !== false) {
            $sms_templates['half_day'] = $tpl['content'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching SMS templates: " . $e->getMessage());
}

// --- SMS logging helpers (dynamic, compatible with existing sms_logs schema) ---
if (!function_exists('attendance_sms_log_available_columns')) {
    function attendance_sms_log_available_columns(PDO $pdo) {
        static $cols = null;
        if ($cols !== null) return $cols;
        $cols = [];
        try {
            $st = $pdo->query("SHOW COLUMNS FROM sms_logs");
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
                if (!empty($c['Field'])) $cols[$c['Field']] = true;
            }
        } catch (Exception $e) {
            $cols = [];
        }
        return $cols;
    }
}

if (!function_exists('attendance_insert_sms_log')) {
    function attendance_insert_sms_log(PDO $pdo, array $data) {
        $avail = attendance_sms_log_available_columns($pdo);
        $base = [
            'sent_by_user_id' => $data['sent_by_user_id'] ?? null,
            'recipient_type' => $data['recipient_type'] ?? null,
            'recipient_number' => $data['recipient_number'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => $data['status'] ?? 'success',
        ];
        $extra = [
            'recipient_category' => $data['recipient_category'] ?? null,
            'recipient_id' => $data['recipient_id'] ?? null,
            'recipient_name' => $data['recipient_name'] ?? null,
            'recipient_role' => $data['recipient_role'] ?? null,
            'roll_number' => $data['roll_number'] ?? null,
            'class_name' => $data['class_name'] ?? null,
            'section_name' => $data['section_name'] ?? null,
        ];
        $fields = [];
        $values = [];
        $params = [];
        foreach ($base as $k=>$v) { if (isset($avail[$k])) { $fields[]=$k; $values[]='?'; $params[]=$v; } }
        foreach ($extra as $k=>$v) { if (isset($avail[$k])) { $fields[]=$k; $values[]='?'; $params[]=$v; } }
        $created = isset($avail['created_at']);
        $sql = 'INSERT INTO sms_logs (' . implode(',', $fields) . ($created? ',created_at':'') . ') VALUES (' . implode(',', $values) . ($created? ',NOW()':'') . ')';
        $st = $pdo->prepare($sql);
        $st->execute($params);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $class_id = intval(isset($_POST['class_id']) ? $_POST['class_id'] : 0);
    $section_id = intval(isset($_POST['section_id']) ? $_POST['section_id'] : 0); // Section is mandatory
    // Force to today's date only
    $date = $current_date;

    // Permission check: only super_admin or the teacher assigned to the section may record attendance
    $allowed = false;
    if (hasRole(['super_admin'])) {
        $allowed = true;
    } else {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        // Detect which column stores the section teacher id
        $col = null;
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM sections")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('section_teacher_id', $cols)) {
                $col = 'section_teacher_id';
            } elseif (in_array('teacher_id', $cols)) {
                $col = 'teacher_id';
            }
        } catch (Exception $ex) {
            // ignore and leave $col null
        }

        if ($col) {
            $sec_stmt = $pdo->prepare("SELECT `" . $col . "` AS t FROM sections WHERE id = ? LIMIT 1");
            $sec_stmt->execute([$section_id]);
            $sec = $sec_stmt->fetch();
            if ($sec && intval($sec['t']) === intval($user_id)) {
                $allowed = true;
            }
        }
    }

    if (!$allowed) {
        $_SESSION['error'] = "আপনার এই ক্লাস/শাখার উপস্থিতি নেওয়ার অনুমতি নেই।";
        $selected_class = $class_id;
        $selected_section = $section_id;
        $selected_date = $date;
    } else {
        // Check if attendance already exists for this date, class, and section
        $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE class_id = ? AND section_id = ? AND date = ?");
        $check_stmt->execute([$class_id, $section_id, $date]);
        $is_existing_record = ($check_stmt->fetch()['count'] > 0);

        // Get class and section names for SMS
        $class_name = '';
        foreach ($classes as $class) {
            if (intval($class['id']) === intval($class_id)) {
                $class_name = $class['name'];
                break;
            }
        }
        $section_name = '';
        $sec_name_stmt = $pdo->prepare("SELECT name FROM sections WHERE id = ?");
        $sec_name_stmt->execute([$section_id]);
        $section_row = $sec_name_stmt->fetch();
        if ($section_row) { $section_name = $section_row['name']; }

        try {
            $pdo->beginTransaction();

            // Server-side validation: ensure every student has a selected status (mandatory attendance)
            $missingStatus = [];
            if (!empty($_POST['attendance']) && is_array($_POST['attendance'])) {
                foreach ($_POST['attendance'] as $sid => $data) {
                    if (!isset($data['status']) || $data['status'] === '') {
                        $missingStatus[] = $sid;
                    }
                }
            }
            if (!empty($missingStatus)) {
                $pdo->rollBack();
                $_SESSION['error'] = "সকল শিক্ষার্থীর হাজিরা নির্বাচন বাধ্যতামূলক।";
                // Preserve selected values for re-render
                $selected_class = $class_id;
                $selected_section = $section_id;
                $selected_date = $date;
                header('Location: attendance.php?view_attendance=1&class_id=' . urlencode($class_id) . '&section_id=' . urlencode($section_id) . '&date=' . urlencode($current_date));
                exit;
            }

            // Track changes for SMS on update
            $changed_student_ids = [];
            $changed_statuses = [];
            if ($is_existing_record) {
                // Map previous statuses
                $prev_stmt = $pdo->prepare("SELECT student_id, status FROM attendance WHERE class_id = ? AND section_id = ? AND date = ?");
                $prev_stmt->execute([$class_id, $section_id, $date]);
                $prev_status_map = [];
                foreach ($prev_stmt->fetchAll() as $row) {
                    $prev_status_map[$row['student_id']] = $row['status'];
                }

                // Update each posted record
                $update_stmt = $pdo->prepare("UPDATE attendance SET status = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE student_id = ? AND class_id = ? AND section_id = ? AND date = ?");
                foreach ($_POST['attendance'] as $student_id => $data) {
                    $status = isset($data['status']) ? $data['status'] : '';
                    $remarks = isset($data['remarks']) ? $data['remarks'] : '';
                    $prev_status = isset($prev_status_map[$student_id]) ? $prev_status_map[$student_id] : null;
                    if ($prev_status !== $status) {
                        $changed_student_ids[] = (int)$student_id;
                        $changed_statuses[] = (string)$status;
                    }
                    $update_stmt->execute([$status, $remarks, $student_id, $class_id, $section_id, $date]);
                }
                $_SESSION['success'] = "উপস্থিতি সফলভাবে আপডেট করা হয়েছে!";
            } else {
                // Insert new attendance records
                $insert_stmt = $pdo->prepare("INSERT INTO attendance (student_id, class_id, section_id, date, status, remarks, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $recorded_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                foreach ($_POST['attendance'] as $student_id => $data) {
                    $status = isset($data['status']) ? $data['status'] : '';
                    $remarks = isset($data['remarks']) ? $data['remarks'] : '';
                    $insert_stmt->execute([$student_id, $class_id, $section_id, $date, $status, $remarks, $recorded_by]);
                }
                $_SESSION['success'] = "উপস্থিতি সফলভাবে রেকর্ড করা হয়েছে!";
            }

            $pdo->commit();

            // After saving attendance, send SMS to all students whose status is enabled in settings
            $enabled_statuses = [];
            foreach (['present','absent','late'] as $st) {
                $key = 'sms_attendance_' . $st;
                if (!empty($sms_settings[$key]) && $sms_settings[$key] == '1') {
                    $enabled_statuses[] = $st;
                }
            }

            // On update: ignore settings and send only to changed students; On insert: honor settings
            if ( ($is_existing_record && !empty($changed_student_ids)) || (!$is_existing_record && !empty($enabled_statuses)) ) {
                // Allow long-running send and keep going even if client disconnects
                if (function_exists('ignore_user_abort')) { @ignore_user_abort(true); }
                if (function_exists('set_time_limit')) { @set_time_limit(300); }
                $status_filter = $is_existing_record ? array_values(array_unique($changed_statuses)) : $enabled_statuses;
                $ph_statuses = implode(',', array_fill(0, count($status_filter), '?'));
                // On update, restrict to only changed students; on insert, send to all
                $target_student_ids = ($is_existing_record && !empty($changed_student_ids)) ? array_values(array_unique($changed_student_ids)) : [];
                $restrict_students = $is_existing_record ? count($target_student_ids) > 0 : false;
                $ph_students = $restrict_students ? implode(',', array_fill(0, count($target_student_ids), '?')) : '';
                // Prefer enrollment roll_number when available for SMS merge fields; avoid legacy students.roll_number when dropped
                $use_enrollment = function_exists('enrollment_table_exists') ? enrollment_table_exists($pdo) : false;
                if ($use_enrollment) {
                    $sql = "SELECT a.student_id, a.status, s.first_name, s.last_name,
                                     se.roll_number AS roll_number,
                                     s.mobile_number
                            FROM attendance a
                            JOIN students s ON s.id = a.student_id
                            LEFT JOIN students_enrollment se ON se.student_id = s.id AND se.class_id = a.class_id AND se.section_id = a.section_id
                            WHERE a.class_id = ? AND a.section_id = ? AND a.date = ?
                              AND (se.status = 'active' OR se.status IS NULL) AND a.status IN ($ph_statuses)";
                    if ($restrict_students) {
                        $sql .= " AND a.student_id IN ($ph_students)";
                    }
                } else {
                    // Legacy fallback: only reference s.roll_number if column exists
                    $student_has_roll = false;
                    try {
                        $cols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
                        $student_has_roll = in_array('roll_number', $cols);
                    } catch (Exception $e) {
                        $student_has_roll = false;
                    }
                    $rollExpr = $student_has_roll ? 's.roll_number' : 'NULL';
                    $sql = "SELECT a.student_id, a.status, s.first_name, s.last_name,
                                     {$rollExpr} AS roll_number,
                                     s.mobile_number
                            FROM attendance a
                            JOIN students s ON s.id = a.student_id
                            WHERE a.class_id = ? AND a.section_id = ? AND a.date = ?
                              AND a.status IN ($ph_statuses)";
                    if ($restrict_students) {
                        $sql .= " AND a.student_id IN ($ph_students)";
                    }
                }
                $params = [$class_id, $section_id, $date];
                foreach ($status_filter as $st) { $params[] = $st; }
                if ($restrict_students) {
                    foreach ($target_student_ids as $sid) { $params[] = $sid; }
                }
                $qstmt = $pdo->prepare($sql);
                $qstmt->execute($params);
                $rows = $qstmt->fetchAll();

                $sent_count = 0;
                $sent_to_numbers = [];
                foreach ($rows as $r) {
                    $status = $r['status'];
                    $template = isset($sms_templates[$status]) ? $sms_templates[$status] : '';
                    $mobile = trim(isset($r['mobile_number']) ? $r['mobile_number'] : '');
                    // Basic normalization: strip spaces and non-digits
                    $mobile = preg_replace('/[^0-9]/', '', $mobile);
                    if ($mobile === '') { continue; }
                    // Skip duplicate sends to same number in this run
                    if (isset($sent_to_numbers[$mobile])) { continue; }
                    if ($template && $mobile !== '') {
                        $student_name = trim((isset($r['first_name']) ? $r['first_name'] : '') . ' ' . (isset($r['last_name']) ? $r['last_name'] : ''));
                        $msg = str_replace(
                            ['{student_name}', '{roll}', '{date}', '{status}', '{class}', '{section}'],
                            [
                                $student_name,
                                (isset($r['roll_number']) ? $r['roll_number'] : ''),
                                $date,
                                $status,
                                $class_name,
                                $section_name
                            ],
                            $template
                        );
                        $ok = send_sms($mobile, $msg);
                        // Retry once if failed
                        if (!$ok) {
                            usleep(300000); // 0.3s
                            $ok = send_sms($mobile, $msg);
                        }
                        // Unified logging: recipient_type captures attendance status; status captures delivery result
                        attendance_insert_sms_log($pdo, [
                            'sent_by_user_id' => (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null),
                            'recipient_type' => 'attendance_' . $status,
                            'recipient_number' => $mobile,
                            'message' => $msg,
                            'status' => $ok ? 'success' : 'failed',
                            'recipient_category' => 'student',
                            'recipient_id' => (isset($r['student_id']) ? $r['student_id'] : null),
                            'recipient_name' => $student_name,
                            'recipient_role' => 'student',
                            'roll_number' => (isset($r['roll_number']) ? $r['roll_number'] : null),
                            'class_name' => $class_name,
                            'section_name' => $section_name,
                        ]);
                        if ($ok) { $sent_count++; }
                        $sent_to_numbers[$mobile] = true;
                        // Gentle throttle to avoid provider rate-limits
                        usleep(250000); // 0.25s
                    }
                }
                if ($sent_count > 0) {
                    $_SESSION['success'] .= " SMS: {$sent_count} জন শিক্ষার্থীকে পাঠানো হয়েছে.";
                }
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $_SESSION['error'] = "উপস্থিতি রেকর্ড করতে সমস্যা হয়েছে: " . $e->getMessage();
        }

        // Preserve selected values for re-render
        $selected_class = $class_id;
        $selected_section = $section_id;
        $selected_date = $date;
    }
}

// Handle view attendance request
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['view_attendance'])) {
    $selected_class = intval($_GET['class_id']);
    $selected_section = intval($_GET['section_id']); // Section is now mandatory
    // Force selected date to today regardless of input
    $selected_date = $current_date;
    
    // Permission check: only super_admin or the section's assigned teacher may view/take attendance
    $allowed = false;
    if (hasRole(['super_admin'])) {
        $allowed = true;
    } else {
        $user_id = $_SESSION['user_id'] ?? 0;
        // detect the right column
        $col = null;
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM sections")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('section_teacher_id', $cols)) {
                $col = 'section_teacher_id';
            } elseif (in_array('teacher_id', $cols)) {
                $col = 'teacher_id';
            }
        } catch (Exception $ex) {
            // ignore
        }

        if ($col) {
            $sec_stmt = $pdo->prepare("SELECT `" . $col . "` AS t FROM sections WHERE id = ? LIMIT 1");
            $sec_stmt->execute([$selected_section]);
            $sec = $sec_stmt->fetch();
            if ($sec && intval($sec['t']) === intval($user_id)) {
                $allowed = true;
            }
        }
    }

    if (!$allowed) {
        $_SESSION['error'] = "আপনার এই ক্লাস/শাখার উপস্থিতি দেখার/নেওয়ার অনুমতি নেই।";
        // Ensure no students/attendance data is shown
        $attendance_data = [];
        $students = [];
        $is_existing_record = false;
    } else {
        // Check if attendance already exists for this date, class, and section
        $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE class_id = ? AND section_id = ? AND date = ?");
        $check_stmt->execute([$selected_class, $selected_section, $selected_date]);
        $result = $check_stmt->fetch();
        $is_existing_record = ($result['count'] > 0);

        // Get attendance data for the selected date, class, and section
        $attendance_data = [];
        if ($is_existing_record) {
            // Prefer enrollment roll_number; avoid referencing legacy students.roll_number if dropped
            $use_enrollment = function_exists('enrollment_table_exists') ? enrollment_table_exists($pdo) : false;
            if ($use_enrollment) {
                $attendance_stmt = $pdo->prepare("SELECT a.*, s.first_name, s.last_name, se.roll_number AS roll_number
                                                  FROM attendance a 
                                                  JOIN students s ON a.student_id = s.id
                                                  LEFT JOIN students_enrollment se ON se.student_id = s.id AND se.class_id = a.class_id AND se.section_id = a.section_id
                                                  WHERE a.class_id = ? AND a.section_id = ? AND a.date = ?
                                                  ORDER BY se.roll_number IS NULL, se.roll_number ASC, s.first_name ASC");
            } else {
                // Legacy fallback: only reference s.roll_number if the column exists
                $student_has_roll = false;
                try {
                    $cols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
                    $student_has_roll = in_array('roll_number', $cols);
                } catch (Exception $e) {
                    $student_has_roll = false;
                }
                $rollExpr = $student_has_roll ? 's.roll_number' : 'NULL';
                $orderBy = $student_has_roll ? 'ORDER BY s.roll_number ASC' : 'ORDER BY s.first_name ASC';
                $attendance_sql = "SELECT a.*, s.first_name, s.last_name, {$rollExpr} AS roll_number
                                   FROM attendance a 
                                   JOIN students s ON a.student_id = s.id
                                   WHERE a.class_id = ? AND a.section_id = ? AND a.date = ?
                                   {$orderBy}";
                $attendance_stmt = $pdo->prepare($attendance_sql);
            }
            $attendance_stmt->execute([$selected_class, $selected_section, $selected_date]);
            $attendance_data = $attendance_stmt->fetchAll();
        }

        // Get students via enrollment helper (falls back to legacy if table absent)
        $students = get_enrolled_students($pdo, (int)$selected_class, (int)$selected_section, $current_year_id);
    }
}

// Get sections based on selected class
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
    <title>উপস্থিতি ব্যবস্থাপনা - কিন্ডার গার্ডেন</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Bengali Font -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">

    <style>
        body, .main-sidebar, .nav-link {
            font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;
        }
        .attendance-card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        .attendance-card .card-header {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
        }
        .attendance-table th {
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 600;
            text-align: center;
            vertical-align: middle;
            padding: 10px 5px;
        }
        .attendance-table td {
            text-align: center;
            vertical-align: middle;
            padding: 8px 5px;
        }
        .radio-cell {
            width: 80px;
            text-align: center;
        }
        .radio-label {
            display: block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 auto;
            font-size: 18px;
            background-color: #e9ecef;
            color: #6c757d;
            border: 2px solid #6c757d;
        }
        
        .radio-present input[type="radio"]:checked + .radio-label {
            background-color: #28a745;
            color: white;
            border-color: #28a745;
        }
        .radio-absent input[type="radio"]:checked + .radio-label {
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        .radio-late input[type="radio"]:checked + .radio-label {
            background-color: #ffc107;
            color: white;
            border-color: #ffc107;
        }
        input[type="radio"] {
            display: none;
        }
        .sticky-submit {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 5px 10px;
            border-top: 1px solid #eee;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 100;
        }
        .btn-sm-compact {
            padding: 0.2rem 0.4rem;
            font-size: 0.8rem;
            line-height: 1.2;
            border-radius: 0.2rem;
        }
        .student-name {
            text-align: left;
            padding-left: 15px !important;
        }
        .btn-attendance-header {
            width: 100%;
            font-size: 1rem;
            font-weight: bold;
            color: #adb5bd;
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            transition: all 0.3s;
            padding: 10px 0;
        }
        .btn-attendance-header.active-present {
            background-color: #28a745;
            color: white;
        }
        .btn-attendance-header.active-absent {
            background-color: #dc3545;
            color: white;
        }
        .btn-attendance-header.active-late {
            background-color: #ffc107;
            color: white;
        }
        /* Removed Half Day option */
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Navbar -->
    <?php include 'inc/header.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php
    if (hasRole(['super_admin'])) {
        include 'inc/sidebar.php';
    } elseif (hasRole(['teacher'])) {
        include '../teacher/inc/sidebar.php';
    }
    ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">উপস্থিতি ব্যবস্থাপনা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">উপস্থিতি ব্যবস্থাপনা</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Notification Alerts -->
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-check"></i> সফল!</h5>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-ban"></i> ত্রুটি!</h5>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card attendance-card">
                            <div class="card-header">
                                <h3 class="card-title">উপস্থিতি রেকর্ড/দেখুন</h3>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="" class="mb-4">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="class_id" class="required-field">ক্লাস নির্বাচন করুন</label>
                                                <select class="form-control" id="class_id" name="class_id" required>
                                                    <option value="">নির্বাচন করুন</option>
                                                    <?php foreach($classes as $class): ?>
                                                        <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class == $class['id']) ? 'selected' : ''; ?>>
                                                            <?php echo $class['name']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="section_id" class="required-field">শাখা নির্বাচন করুন</label>
                                                <select class="form-control" id="section_id" name="section_id" required>
                                                    <option value="">শাখা নির্বাচন করুন</option>
                                                    <?php foreach($sections as $section): ?>
                                                        <option value="<?php echo $section['id']; ?>" <?php echo ($selected_section == $section['id']) ? 'selected' : ''; ?>>
                                                            <?php echo $section['name']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="date_display" class="required-field">তারিখ</label>
                                                <input type="text" class="form-control" id="date_display" value="<?php echo date('d/m/Y', strtotime($current_date)); ?>" readonly>
                                                <input type="hidden" id="date" name="date" value="<?php echo $current_date; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group" style="margin-top: 32px;">
                                                <button type="submit" name="view_attendance" class="btn btn-primary">
                                                    <i class="fas fa-eye"></i> দেখুন
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <?php if($allowed && (!empty($students) || !empty($attendance_data))): ?>
                                    <hr>

                                    <?php if($is_existing_record): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> এই তারিখের উপস্থিতি ইতিমধ্যে রেকর্ড করা হয়েছে। আপনি এখন এটি আপডেট করতে পারেন।
                                        </div>
                                    <?php endif; ?>

                                    <!-- Mark/Update Attendance Form -->
                                    <form method="POST" action="" id="attendanceForm">
                                        <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                                        <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
                                        <input type="hidden" name="date" value="<?php echo $selected_date; ?>">

                                        <!-- Top Submit Button -->
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h4>
                                                <?php echo $is_existing_record ? 'উপস্থিতি আপডেট করুন' : 'উপস্থিতি রেকর্ড করুন'; ?>
                                                <small class="text-muted">(<?php echo date('d/m/Y', strtotime($selected_date)); ?>)</small>
                                            </h4>
                                            <button type="submit" name="mark_attendance" class="btn btn-success">
                                                <i class="fas fa-save"></i> <?php echo $is_existing_record ? 'আপডেট করুন' : 'সংরক্ষণ করুন'; ?>
                                            </button>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped attendance-table">
                                                <thead>
                                                    <tr>
                                                        <th width="60">রোল</th>
                                                        <th>শিক্ষার্থীর নাম</th>
                                                        <!-- Attendance Header Buttons -->
                                                        <th class="radio-cell">
                                                            <button type="button" class="btn btn-attendance-header" data-status="present" id="select-all-present">
                                                                <i class="fas fa-check-circle"></i><br>Present
                                                            </button>
                                                        </th>
                                                        <th class="radio-cell">
                                                            <button type="button" class="btn btn-attendance-header" data-status="absent" id="select-all-absent">
                                                                <i class="fas fa-times-circle"></i><br>Absent
                                                            </button>
                                                        </th>
                                                        <th class="radio-cell">
                                                            <button type="button" class="btn btn-attendance-header" data-status="late" id="select-all-late">
                                                                <i class="fas fa-clock"></i><br>Late
                                                            </button>
                                                        </th>
                                                        <!-- Half Day option removed -->
                                                        <th width="200">মন্তব্য</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                        $record_map = [];
                                                        foreach ($attendance_data as $record) {
                                                            $record_map[$record['student_id']] = $record;
                                                        }

                                                        foreach($students as $student): 
                                                            $student_id = $student['id'];
                                                            $current_status = '';
                                                            $current_remarks = '';

                                                            if (isset($record_map[$student_id])) {
                                                                $record = $record_map[$student_id];
                                                                $current_status = $record['status'];
                                                                $current_remarks = $record['remarks'];
                                                            }
                                                    ?>
                                                        <tr>
                                                            <td><?php echo $student['roll_number']; ?></td>
                                                            <td class="student-name"><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>

                                                            <!-- Present Radio -->
                                                            <td class="radio-present">
                                                                <input type="radio" name="attendance[<?php echo $student_id; ?>][status]" id="present_<?php echo $student_id; ?>" value="present" <?php echo ($current_status == 'present') ? 'checked' : ''; ?>>
                                                                <label for="present_<?php echo $student_id; ?>" class="radio-label">
                                                                    <i class="fas fa-check-circle"></i>
                                                                </label>
                                                            </td>

                                                            <!-- Absent Radio -->
                                                            <td class="radio-absent">
                                                                <input type="radio" name="attendance[<?php echo $student_id; ?>][status]" id="absent_<?php echo $student_id; ?>" value="absent" <?php echo ($current_status == 'absent') ? 'checked' : ''; ?>>
                                                                <label for="absent_<?php echo $student_id; ?>" class="radio-label">
                                                                    <i class="fas fa-times-circle"></i>
                                                                </label>
                                                            </td>

                                                            <!-- Late Radio -->
                                                            <td class="radio-late">
                                                                <input type="radio" name="attendance[<?php echo $student_id; ?>][status]" id="late_<?php echo $student_id; ?>" value="late" <?php echo ($current_status == 'late') ? 'checked' : ''; ?>>
                                                                <label for="late_<?php echo $student_id; ?>" class="radio-label">
                                                                    <i class="fas fa-clock"></i>
                                                                </label>
                                                            </td>

                                                            <!-- Half Day option removed per requirement -->

                                                            <td>
                                                                <input type="text" class="form-control form-control-sm" name="attendance[<?php echo $student_id; ?>][remarks]" value="<?php echo $current_remarks; ?>" placeholder="মন্তব্য">
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Bottom Submit Button -->
                                        <div class="sticky-submit text-right mt-2">
                                            <button type="submit" name="mark_attendance" class="btn btn-success btn-lg">
                                                <i class="fas fa-save"></i> <?php echo $is_existing_record ? 'আপডেট করুন' : 'সংরক্ষণ করুন'; ?>
                                            </button>
                                        </div>
                                    </form>
                                <?php elseif($selected_class && $selected_section): ?>
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle"></i> এই ক্লাস এবং শাখায় কোনো শিক্ষার্থী নেই।
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Main Footer -->
    <?php include 'inc/footer.php'; ?>
</div>

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
    $(document).ready(function() {
        // Load sections when class is selected
        $('#class_id').change(function() {
            var class_id = $(this).val();
            if (class_id) {
                $.ajax({
                    url: 'get_sections.php',
                    type: 'GET',
                    data: {class_id: class_id},
                    success: function(data) {
                        $('#section_id').html(data);
                    }
                });
            } else {
                $('#section_id').html('<option value="">শাখা নির্বাচন করুন</option>');
            }
        });

        // Function to update header button status based on all students' attendance
        function updateHeaderButtons() {
            var totalStudents = $('tbody tr').length;
            var presentCount = $('input[value="present"]:checked').length;
            var absentCount = $('input[value="absent"]:checked').length;
            var lateCount = $('input[value="late"]:checked').length;
            // Half Day removed
            
            // Remove active class from all header buttons
            $('.btn-attendance-header').removeClass('active-present active-absent active-late');

            // If all students have the same status, activate the corresponding header button
            if (totalStudents > 0) {
                if (presentCount === totalStudents) {
                    $('#select-all-present').addClass('active-present');
                } else if (absentCount === totalStudents) {
                    $('#select-all-absent').addClass('active-absent');
                } else if (lateCount === totalStudents) {
                    $('#select-all-late').addClass('active-late');
                }
            }
        }

        // Handle "select all" buttons (Half Day removed)
        $('.btn-attendance-header').click(function() {
            var statusToSelect = $(this).data('status');
            
            // Loop through all radio buttons and select the correct one
            $('input[name^="attendance["][type="radio"]').each(function() {
                if ($(this).val() === statusToSelect) {
                    $(this).prop('checked', true);
                } else {
                    $(this).prop('checked', false);
                }
            });
            updateHeaderButtons();
        });

        // Update header button status whenever a single radio button is clicked
        $('input[name^="attendance["][type="radio"]').on('change', function() {
            updateHeaderButtons();
        });

        // Call the function on page load to set initial state
        updateHeaderButtons();

        // Client-side validation: ensure every student has a selected status before submitting
        $('#attendanceForm').on('submit', function(e) {
            var allOk = true;
            $('tbody tr').each(function() {
                var row = $(this);
                var hasChecked = row.find('input[type="radio"]').is(':checked');
                if (!hasChecked) {
                    allOk = false;
                    row.addClass('table-danger');
                } else {
                    row.removeClass('table-danger');
                }
            });
            if (!allOk) {
                e.preventDefault();
                alert('সকল শিক্ষার্থীর জন্য উপস্থিতি নির্বাচন বাধ্যতামূলক।');
                return false;
            }
        });

        // Prevent form submission on Enter key in remark fields
        $('#attendanceForm').on('keyup keypress', function(e) {
            var keyCode = e.keyCode || e.which;
            if (keyCode === 13) { 
                e.preventDefault();
                return false;
            }
        });
    });
</script>
</body>
</html>