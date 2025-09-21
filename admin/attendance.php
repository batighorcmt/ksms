<?php
require_once '../config.php';
require_once __DIR__ . '/inc/sms_api.php'; // Include SMS functionality

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
$allowed = false;

// Get SMS settings to determine which statuses should trigger messages
$sms_settings = [];
try {
    $sms_settings_stmt = $pdo->query("SELECT `key`, value FROM settings WHERE `key` LIKE 'sms_%'");
    $sms_settings = $sms_settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    // If settings table doesn't exist or doesn't have the required fields
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $class_id = intval($_POST['class_id']);
    $section_id = intval($_POST['section_id']); // Section is now mandatory
    $date = $_POST['date'];
    
    // Permission check: only super_admin or the teacher assigned to the section may record attendance
    $allowed = false;
    if (hasRole(['super_admin'])) {
        $allowed = true;
    } else {
        $user_id = $_SESSION['user_id'] ?? 0;
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
        // Preserve selected values so user sees the same selection
        $selected_class = $class_id;
        $selected_section = $section_id;
        $selected_date = $date;
    } else {
        // Check if attendance already exists for this date, class, and section
        $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE class_id = ? AND section_id = ? AND date = ?");
        $check_stmt->execute([$class_id, $section_id, $date]);
        $result = $check_stmt->fetch();
        $is_existing_record = ($result['count'] > 0);

        // Get students with their mobile numbers
        $student_map = [];
        $student_stmt = $pdo->prepare("SELECT id, first_name, last_name, roll_number, mobile_number FROM students WHERE class_id = ? AND section_id = ? AND status='active'");
        $student_stmt->execute([$class_id, $section_id]);
        foreach ($student_stmt->fetchAll() as $stu) {
            $student_map[$stu['id']] = $stu;
        }

        // Get class and section names for SMS
        $class_name = '';
        $section_name = '';
        foreach ($classes as $class) {
            if ($class['id'] == $class_id) {
                $class_name = $class['name'];
                break;
            }
        }
        
        $sec_name_stmt = $pdo->prepare("SELECT name FROM sections WHERE id = ?");
        $sec_name_stmt->execute([$section_id]);
        $section_data = $sec_name_stmt->fetch();
        if ($section_data) {
            $section_name = $section_data['name'];
        }

        try {
            $pdo->beginTransaction();

            if ($is_existing_record) {
                // Get previous attendance for all students
                $prev_stmt = $pdo->prepare("SELECT student_id, status FROM attendance WHERE class_id = ? AND section_id = ? AND date = ?");
                $prev_stmt->execute([$class_id, $section_id, $date]);
                $prev_status_map = [];
                foreach ($prev_stmt->fetchAll() as $row) {
                    $prev_status_map[$row['student_id']] = $row['status'];
                }

                // Update existing attendance records
                foreach ($_POST['attendance'] as $student_id => $data) {
                    $status = $data['status'] ?? '';
                    $remarks = $data['remarks'] ?? '';
                    $prev_status = $prev_status_map[$student_id] ?? '';

                    $update_stmt = $pdo->prepare("UPDATE attendance SET status = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE student_id = ? AND class_id = ? AND section_id = ? AND date = ?");
                    $update_stmt->execute([$status, $remarks, $student_id, $class_id, $section_id, $date]);

                    // Send SMS if status changed and SMS is enabled for this status
                    if ($status !== $prev_status && isset($student_map[$student_id]) && !empty($student_map[$student_id]['mobile_number'])) {
                        $sms_setting_key = 'sms_' . $status;
                        if (isset($sms_settings[$sms_setting_key]) && $sms_settings[$sms_setting_key] == '1') {
                            $sms_body = $sms_templates[$status] ?? '';
                            if ($sms_body) {
                                $msg = str_replace([
                                    '{student_name}', '{roll}', '{date}', '{status}', '{class}', '{section}'
                                ], [
                                    $student_map[$student_id]['first_name'] . ' ' . $student_map[$student_id]['last_name'],
                                    $student_map[$student_id]['roll_number'],
                                    $date,
                                    $status,
                                    $class_name,
                                    $section_name
                                ], $sms_body);
                                
                                // Send SMS
                                $sms_result = send_sms($student_map[$student_id]['mobile_number'], $msg);
                                
                                // Log SMS
                                $log_stmt = $pdo->prepare("INSERT INTO sms_logs (student_id, mobile, message, status, prev_status, sent_by) VALUES (?, ?, ?, ?, ?, ?)");
                                $log_stmt->execute([
                                    $student_id, 
                                    $student_map[$student_id]['mobile_number'], 
                                    $msg, 
                                    $sms_result ? 'sent' : 'failed', 
                                    $prev_status,
                                    $_SESSION['user_id']
                                ]);
                            }
                        }
                    }
                }
                $_SESSION['success'] = "উপস্থিতি সফলভাবে আপডেট করা হয়েছে!";
            } else {
                // Insert new attendance records
                $attendance_stmt = $pdo->prepare("INSERT INTO attendance (student_id, class_id, section_id, date, status, remarks, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $recorded_by = $_SESSION['user_id'];
                
                foreach ($_POST['attendance'] as $student_id => $data) {
                    $status = $data['status'] ?? '';
                    $remarks = $data['remarks'] ?? '';
                    $attendance_stmt->execute([$student_id, $class_id, $section_id, $date, $status, $remarks, $recorded_by]);
                    
                    // Send SMS for new attendance if SMS is enabled for this status
                    if (isset($student_map[$student_id]) && !empty($student_map[$student_id]['mobile_number'])) {
                        $sms_setting_key = 'sms_' . $status;
                        if (isset($sms_settings[$sms_setting_key]) && $sms_settings[$sms_setting_key] == '1') {
                            $sms_body = $sms_templates[$status] ?? '';
                            if ($sms_body) {
                                $msg = str_replace([
                                    '{student_name}', '{roll}', '{date}', '{status}', '{class}', '{section}'
                                ], [
                                    $student_map[$student_id]['first_name'] . ' ' . $student_map[$student_id]['last_name'],
                                    $student_map[$student_id]['roll_number'],
                                    $date,
                                    $status,
                                    $class_name,
                                    $section_name
                                ], $sms_body);
                                
                                // Send SMS
                                $sms_result = send_sms($student_map[$student_id]['mobile_number'], $msg);
                                
                                // Log SMS
                                $log_stmt = $pdo->prepare("INSERT INTO sms_logs (student_id, mobile, message, status, sent_by) VALUES (?, ?, ?, ?, ?)");
                                $log_stmt->execute([
                                    $student_id, 
                                    $student_map[$student_id]['mobile_number'], 
                                    $msg, 
                                    $sms_result ? 'sent' : 'failed',
                                    $_SESSION['user_id']
                                ]);
                            }
                        }
                    }
                }
                $_SESSION['success'] = "উপস্থিতি সফলভাবে রেকর্ড করা হয়েছে!";
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "উপস্থিতি রেকর্ড করতে সমস্যা হয়েছে: " . $e->getMessage();
        }
// Check if SMS should be sent for this status
if (should_send_attendance_sms($status)) {
    // Get student mobile number from database
    $student_mobile = $student_map[$student_id]['mobile_number'] ?? '';
    
    if (!empty($student_mobile)) {
        // Get SMS template for this status
        $template = get_sms_template($status);
        
        if ($template) {
            // Prepare data for template
            $template_data = [
                'student_name' => $student_map[$student_id]['first_name'] . ' ' . $student_map[$student_id]['last_name'],
                'roll' => $student_map[$student_id]['roll_number'],
                'date' => $date,
                'status' => $status,
                'class' => $class_name,
                'section' => $section_name
            ];
            
            // Process template
            $message = process_sms_template($template, $template_data);
            
            // Send SMS
            $sms_sent = send_sms($student_mobile, $message);
            
            if ($sms_sent) {
                // Log successful SMS sending
                error_log("SMS sent to $student_mobile for $status status");
            } else {
                error_log("Failed to send SMS to $student_mobile");
            }
        }
    }
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
    $selected_section = intval($_GET['section_id']); // Section is now mandatory
    $selected_date = $_GET['date'];
    
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
            $attendance_stmt = $pdo->prepare("SELECT a.*, s.first_name, s.last_name, s.roll_number FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.class_id = ? AND a.section_id = ? AND a.date = ? ORDER BY s.roll_number ASC");
            $attendance_stmt->execute([$selected_class, $selected_section, $selected_date]);
            $attendance_data = $attendance_stmt->fetchAll();
        }

        // Get students list for the selected class and section
        $student_stmt = $pdo->prepare("SELECT id, first_name, last_name, roll_number FROM students WHERE class_id = ? AND section_id = ? AND status='active' ORDER BY roll_number ASC");
        $student_stmt->execute([$selected_class, $selected_section]);
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
        .radio-half_day input[type="radio"]:checked + .radio-label {
            background-color: #17a2b8;
            color: white;
            border-color: #17a2b8;
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
        .btn-attendance-header.active-half_day {
            background-color: #17a2b8;
            color: white;
        }
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
                                                <label for="date" class="required-field">তারিখ</label>
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
                                                        <th class="radio-cell">
                                                            <button type="button" class="btn btn-attendance-header" data-status="half_day" id="select-all-half_day">
                                                                <i class="fas fa-hourglass-half"></i><br>Half Day
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

                                                            <!-- Half Day Radio -->
                                                            <td class="radio-half_day">
                                                                <input type="radio" name="attendance[<?php echo $student_id; ?>][status]" id="half_day_<?php echo $student_id; ?>" value="half_day" <?php echo ($current_status == 'half_day') ? 'checked' : ''; ?>>
                                                                <label for="half_day_<?php echo $student_id; ?>" class="radio-label">
                                                                    <i class="fas fa-hourglass-half"></i>
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
            var halfDayCount = $('input[value="half_day"]:checked').length;
            
            // Remove active class from all header buttons
            $('.btn-attendance-header').removeClass('active-present active-absent active-late active-half_day');

            // If all students have the same status, activate the corresponding header button
            if (totalStudents > 0) {
                if (presentCount === totalStudents) {
                    $('#select-all-present').addClass('active-present');
                } else if (absentCount === totalStudents) {
                    $('#select-all-absent').addClass('active-absent');
                } else if (lateCount === totalStudents) {
                    $('#select-all-late').addClass('active-late');
                } else if (halfDayCount === totalStudents) {
                    $('#select-all-half_day').addClass('active-half_day');
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
            updateHeaderButtons();
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