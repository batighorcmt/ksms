<?php

// Include SMS API function
require_once __DIR__ . '/inc/sms_api.php';

require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    header('Location: ../login.php');
    exit;
}

// Get today's date for default selection
$current_date = date('Y-m-d');

// Get classes and sections
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();

// Initialize variables
$selected_class = '';
$selected_section = '';
$selected_date = $current_date;
$attendance_data = [];
$students = [];
$is_existing_record = false;
$sections = []; // FIX: আগে থেকেই ডিফাইন করে দেওয়া হলো

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $class_id = intval($_POST['class_id']);
    $section_id = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
    $date = $_POST['date'];
    // Permission check: only super_admin or the teacher assigned to the section may record attendance
    $allowed = false;
    if (hasRole(['super_admin'])) {
        $allowed = true;
    } else {
        $user_id = $_SESSION['user_id'] ?? 0;
        if ($section_id !== null) {
            // Detect which column stores the section teacher id and use it safely
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
    }

    if (!$allowed) {
        $_SESSION['error'] = "আপনার এই ক্লাস/শাখার উপস্থিতি নেওয়ার অনুমতি নেই।";
        // Preserve selected values so user sees the same selection
        $selected_class = $class_id;
        $selected_section = $section_id;
        $selected_date = $date;
    } else {
        // Check if attendance already exists for this date, class, and optional section
        $check_query = "SELECT COUNT(*) as count FROM attendance WHERE class_id = ? AND date = ?";
        $params = [$class_id, $date];
        if ($section_id !== null) {
            $check_query .= " AND section_id = ?";
            $params[] = $section_id;
        }
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute($params);
        $result = $check_stmt->fetch();
        $is_existing_record = ($result['count'] > 0);

        // --- SMS Template and Log Setup ---
        // Get students with mobile numbers
        $student_map = [];
        $student_stmt = $pdo->prepare("SELECT id, first_name, last_name, roll_number, mobile_number FROM students WHERE class_id = ?" . ($section_id ? " AND section_id = ?" : "") . " AND status='active'");
        $student_params = [$class_id];
        if ($section_id) $student_params[] = $section_id;
        $student_stmt->execute($student_params);
        foreach ($student_stmt->fetchAll() as $stu) {
            $student_map[$stu['id']] = $stu;
        }

        // Get SMS templates for each status
        $sms_templates = [];
        $tpl_stmt = $pdo->query("SELECT * FROM sms_templates");
        foreach ($tpl_stmt->fetchAll() as $tpl) {
            if (mb_stripos($tpl['title'], 'অনুপস্থিত') !== false || mb_stripos($tpl['title'], 'Absent') !== false) {
                $sms_templates['absent'] = $tpl['content'];
            } elseif (mb_stripos($tpl['title'], 'Late') !== false || mb_stripos($tpl['title'], 'দেরি') !== false) {
                $sms_templates['late'] = $tpl['content'];
            } elseif (mb_stripos($tpl['title'], 'Present') !== false || mb_stripos($tpl['title'], 'উপস্থিতি') !== false) {
                $sms_templates['present'] = $tpl['content'];
            }
        }

        // Prepare SMS log table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS sms_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT,
            mobile VARCHAR(20),
            message TEXT,
            status VARCHAR(20),
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            type VARCHAR(50) DEFAULT 'attendance',
            prev_status VARCHAR(20) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try {
            $pdo->beginTransaction();

            if ($is_existing_record) {
                // Get previous attendance for all students
                $prev_stmt = $pdo->prepare("SELECT student_id, status FROM attendance WHERE class_id = ? AND date = ?" . ($section_id ? " AND section_id = ?" : ""));
                $prev_params = [$class_id, $date];
                if ($section_id) $prev_params[] = $section_id;
                $prev_stmt->execute($prev_params);
                $prev_status_map = [];
                foreach ($prev_stmt->fetchAll() as $row) {
                    $prev_status_map[$row['student_id']] = $row['status'];
                }

                // Update existing attendance records and send SMS if status changed
                foreach ($_POST['attendance'] as $student_id => $data) {
                    // Always set $selected_section for SMS template
                    $selected_section = $section_id ?? null;
                    if (!isset($section_id)) {
                        $section_id = $selected_section ?? null;
                    }
                    $selected_section = $section_id;
                    $status = $data['status'] ?? '';
                    $remarks = $data['remarks'] ?? '';
                    $prev_status = $prev_status_map[$student_id] ?? '';

                    // Update attendance as before
                    if ($section_id !== null) {
                        $update_stmt = $pdo->prepare("
                            UPDATE attendance 
                            SET status = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP 
                            WHERE student_id = ? AND class_id = ? AND section_id = ? AND date = ?
                        ");
                        $update_stmt->execute([$status, $remarks, $student_id, $class_id, $section_id, $date]);
                    } else {
                        $update_stmt = $pdo->prepare("
                            UPDATE attendance 
                            SET status = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP 
                            WHERE student_id = ? AND class_id = ? AND date = ?
                        ");
                        $update_stmt->execute([$status, $remarks, $student_id, $class_id, $date]);
                    }

                    // Only send SMS if student is marked absent (and status changed to absent)
                    if ($status === 'absent' && $status !== $prev_status && isset($student_map[$student_id]) && !empty($student_map[$student_id]['mobile_number'])) {
                        $sms_body = $sms_templates['absent'] ?? '';
                        if ($sms_body) {
                            $msg = $sms_body;
                            $msg = str_replace([
                                '{student_name}', '{roll}', '{date}', '{status}', '{class}', '{section}'
                            ], [
                                $student_map[$student_id]['first_name'] . ' ' . $student_map[$student_id]['last_name'],
                                $student_map[$student_id]['roll_number'],
                                $date,
                                $status,
                                $classes[array_search($class_id, array_column($classes, 'id'))]['name'] ?? '',
                                $sections ? ($selected_section ? (array_values(array_filter($sections, function($s){return $s['id']==$selected_section;}))[0]['name'] ?? '') : '') : ''
                            ], $msg);
                            send_sms($student_map[$student_id]['mobile_number'], $msg);
                            $log_stmt = $pdo->prepare("INSERT INTO sms_logs (student_id, mobile, message, status, prev_status) VALUES (?, ?, ?, ?, ?)");
                            $log_stmt->execute([$student_id, $student_map[$student_id]['mobile_number'], $msg, $status, $prev_status]);
                        }
                    }
                }
                $_SESSION['success'] = "উপস্থিতি সফলভাবে আপডেট করা হয়েছে!";
            } else {
                // Insert new attendance records
                $attendance_stmt = $pdo->prepare("INSERT INTO attendance (student_id, class_id, section_id, date, status, remarks, recorded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
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

        // Set selected values for form
        $selected_class = $class_id;
        $selected_section = $section_id;
        $selected_date = $date;
    }
}

// Handle view attendance request
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['view_attendance'])) {
    $selected_class = intval($_GET['class_id']);
    $selected_section = !empty($_GET['section_id']) ? intval($_GET['section_id']) : null;
    $selected_date = $_GET['date'];
    // Permission check: only super_admin or the section's assigned teacher may view/take attendance
    $allowed = false;
    if (hasRole(['super_admin'])) {
        $allowed = true;
    } else {
        $user_id = $_SESSION['user_id'] ?? 0;
        if ($selected_section !== null) {
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
    }

    if (!$allowed) {
        $_SESSION['error'] = "আপনার এই ক্লাস/শাখার উপস্থিতি দেখার/নেওয়ার অনুমতি নেই।";
        // Ensure no attendance data is shown
        $attendance_data = [];
        $is_existing_record = false;
        // But still fetch students list for the selected class and section
        $student_query = "
            SELECT id, first_name, last_name, roll_number 
            FROM students 
            WHERE class_id = ? AND status='active'
        ";
        $student_params = [$selected_class];
        if ($selected_section !== null) {
            $student_query .= " AND section_id = ?";
            $student_params[] = $selected_section;
        }
        $student_query .= " ORDER BY roll_number ASC";
        $student_stmt = $pdo->prepare($student_query);
        $student_stmt->execute($student_params);
        $students = $student_stmt->fetchAll();
    } else {
        // Check if attendance already exists for this date, class, and optional section
        $check_query = "SELECT COUNT(*) as count FROM attendance WHERE class_id = ? AND date = ?";
        $params = [$selected_class, $selected_date];
        if ($selected_section !== null) {
            $check_query .= " AND section_id = ?";
            $params[] = $selected_section;
        }
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute($params);
        $result = $check_stmt->fetch();
        $is_existing_record = ($result['count'] > 0);

        // Get attendance data for the selected date, class, and optional section
        $attendance_data = [];
        if ($is_existing_record) {
            $attendance_query = "
                SELECT a.*, s.first_name, s.last_name, s.roll_number 
                FROM attendance a 
                JOIN students s ON a.student_id = s.id 
                WHERE a.class_id = ? AND a.date = ?
            ";
            $attendance_params = [$selected_class, $selected_date];
            if ($selected_section !== null) {
                $attendance_query .= " AND a.section_id = ?";
                $attendance_params[] = $selected_section;
            }
            $attendance_query .= " ORDER BY s.roll_number ASC";
            $attendance_stmt = $pdo->prepare($attendance_query);
            $attendance_stmt->execute($attendance_params);
            $attendance_data = $attendance_stmt->fetchAll();
        }

        // Always get students list for the selected class and optional section
        $student_query = "
            SELECT id, first_name, last_name, roll_number 
            FROM students 
            WHERE class_id = ? AND status='active'
        ";
        $student_params = [$selected_class];
        if ($selected_section !== null) {
            $student_query .= " AND section_id = ?";
            $student_params[] = $selected_section;
        }
        $student_query .= " ORDER BY roll_number ASC";
        $student_stmt = $pdo->prepare($student_query);
        $student_stmt->execute($student_params);
        $students = $student_stmt->fetchAll();
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
            background-color: #e9ecef; /* Light gray background */
            color: #6c757d;            /* Gray icon color */
            border: 2px solid #6c757d; /* Gray border */
        }
        
        /* Only change colors when the radio button is checked */
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
                                                <label for="class_id">ক্লাস নির্বাচন করুন</label>
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
                                                <label for="section_id">শাখা নির্বাচন করুন (ঐচ্ছিক)</label>
                                                <select class="form-control" id="section_id" name="section_id">
                                                    <option value="">সকল শাখা</option>
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
                                                <label for="date">তারিখ</label>
                                                <input type="date" class="form-control" id="date" name="date" value="<?php echo $selected_date; ?>" required>
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

                                <?php if(!empty($students) || !empty($attendance_data)): ?>
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
                                                        <!-- New Attendance Header Buttons -->
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
                                                            $current_status = ''; // Default to no status for new records
                                                            $current_remarks = '';

                                                            // Find existing attendance record for this student
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
                                            <button type="submit" name="mark_attendance" class="btn btn-success btn-sm-compact">
                                                <i class="fas fa-save"></i> <?php echo $is_existing_record ? 'আপডেট করুন' : 'সংরক্ষণ করুন'; ?>
                                            </button>
                                        </div>
                                    </form>
                                <?php elseif($selected_class): ?>
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle"></i> এই ক্লাস এবং শাখায় কোনো শিক্ষার্থী নেই।
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Main Footer -->
    <?php include 'inc/footer.php'; ?>
</div>
<!-- ./wrapper -->

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
                        $('#section_id').html('<option value="">সকল শাখা</option>' + data);
                    }
                });
            } else {
                $('#section_id').html('<option value="">সকল শাখা</option>');
            }
        });

        // Function to update header button status based on all students' attendance
        function updateHeaderButtons() {
            var totalStudents = $('tbody tr').length;
            var presentCount = $('input[value="present"]:checked').length;
            var absentCount = $('input[value="absent"]:checked').length;
            var lateCount = $('input[value="late"]:checked').length;
            
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

        // Handle "select all" buttons
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
            updateHeaderButtons(); // Update header buttons after a bulk selection
        });

        // Update header button status whenever a single radio button is clicked
        $('input[name^="attendance["][type="radio"]').on('change', function() {
            updateHeaderButtons();
        });

        // Call the function on page load to set initial state
        updateHeaderButtons();

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