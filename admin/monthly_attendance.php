<?php
require_once '../config.php';
require_once __DIR__ . '/inc/enrollment_helpers.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// ডিফল্ট মান সেট করুন
$current_month = date('m');
$current_year = date('Y');
$months = [
    '01' => 'জানুয়ারি', '02' => 'ফেব্রুয়ারি', '03' => 'মার্চ', 
    '04' => 'এপ্রিল', '05' => 'মে', '06' => 'জুন', 
    '07' => 'জুলাই', '08' => 'আগস্ট', '09' => 'সেপ্টেম্বর', 
    '10' => 'অক্টোবর', '11' => 'নভেম্বর', '12' => 'ডিসেম্বর'
];

// স্কুল তথ্য লোড করুন
$school_info = $pdo->query("SELECT * FROM school_info WHERE id = 1")->fetch();

// ক্লাস এবং সেকশন লোড করুন
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();
$sections = [];

// সাপ্তাহিক ছুটির দিনগুলো লোড করুন
$weekly_holidays = $pdo->query("SELECT day_number FROM weekly_holidays WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN);

// সাধারণ ছুটির দিনগুলো লোড করুন
$holidays = $pdo->query("SELECT * FROM holidays WHERE status='active'")->fetchAll();
$holiday_dates = array_column($holidays, 'date');

// ভেরিয়েবল ডিক্লেয়ারেশন
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : null;
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;
$month = isset($_GET['month']) ? $_GET['month'] : $current_month;
$year = isset($_GET['year']) ? $_GET['year'] : $current_year;
$class_name = '';
$section_name = '';

// ফর্ম সাবমিট হলে
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['generate_report'])) {
    $class_id = intval($_GET['class_id']);
    $section_id = intval($_GET['section_id']);
    $month = $_GET['month'];
    $year = $_GET['year'];
    
    // নির্বাচিত ক্লাসের সেকশন লোড করুন
    $section_stmt = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? AND status='active'");
    $section_stmt->execute([$class_id]);
    $sections = $section_stmt->fetchAll();
    
    // নির্বাচিত ক্লাস ও সেকশনের নাম লোড করুন
    $class_stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
    $class_stmt->execute([$class_id]);
    $class_result = $class_stmt->fetch();
    $class_name = $class_result ? $class_result['name'] : '';
    
    $section_stmt = $pdo->prepare("SELECT name FROM sections WHERE id = ?");
    $section_stmt->execute([$section_id]);
    $section_result = $section_stmt->fetch();
    $section_name = $section_result ? $section_result['name'] : '';
    
    // মাসের দিন সংখ্যা এবং প্রথম দিন নির্ধারণ করুন
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// date('w') -> 0=Sunday ... 6=Saturday
$w = date('w', strtotime("$year-$month-01"));

// আপনার ডাটাবেজের ফরম্যাটে রূপান্তর (Sunday=1 ... Saturday=7)
$first_day = $w + 1;
    
        // বর্তমান শিক্ষাবর্ষ আইডি (যদি থাকে)
        $current_year_row = $pdo->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch();
        $current_year_id = $current_year_row['id'] ?? null;

        // শিক্ষার্থীদের লোড করুন (enrollment-aware)
        $students = [];
        $use_enrollment = function_exists('enrollment_table_exists') ? enrollment_table_exists($pdo) : false;
        if ($use_enrollment) {
            $sql = "SELECT s.id, s.first_name, s.last_name, se.roll_number\n                FROM students_enrollment se\n                JOIN students s ON s.id = se.student_id\n                WHERE se.class_id = ? AND se.section_id = ? "
                    . ($current_year_id ? "AND se.academic_year_id = ? " : "") .
                "AND (se.status = 'active' OR se.status IS NULL)\n                ORDER BY se.roll_number ASC, s.first_name ASC";
            $params = [$class_id, $section_id];
            if ($current_year_id) { $params[] = $current_year_id; }
            $students_stmt = $pdo->prepare($sql);
            $students_stmt->execute($params);
            $students = $students_stmt->fetchAll();
        } else {
            // লিগেসি ফ্যালব্যাক: students টেবিলে প্রয়োজনীয় কলামগুলো আছে কিনা চেক করুন
            $has_class = $has_section = $has_status = $has_roll = false;
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
                $has_class = in_array('class_id', $cols);
                $has_section = in_array('section_id', $cols);
                $has_status = in_array('status', $cols);
                $has_roll = in_array('roll_number', $cols);
            } catch (Exception $e) {
                // ignore
            }
            $selectRoll = $has_roll ? 'roll_number' : "NULL AS roll_number";
            $sql = "SELECT id, first_name, last_name, {$selectRoll} FROM students WHERE 1=1";
            $p = [];
            if ($has_class) { $sql .= " AND class_id = ?"; $p[] = $class_id; }
            if ($has_section) { $sql .= " AND section_id = ?"; $p[] = $section_id; }
            if ($has_status) { $sql .= " AND status = 'active'"; }
            $sql .= $has_roll ? " ORDER BY roll_number ASC" : " ORDER BY first_name ASC";
            $students_stmt = $pdo->prepare($sql);
            $students_stmt->execute($p);
            $students = $students_stmt->fetchAll();
        }

        // Fallback: if no students resolved (due to enrollment year mismatch or legacy data),
        // derive the roster from attendance records for the selected month.
        if (empty($students)) {
            try {
                // Determine roll_number column source
                $hasEnroll = $use_enrollment;
                $joinEnroll = '';
                $selectRoll = 'NULL AS roll_number';
                if ($hasEnroll) {
                    // If enrollment table exists, try to fetch roll for current year when available
                    if ($current_year_id) {
                        $joinEnroll = ' LEFT JOIN students_enrollment se ON se.student_id = s.id AND se.academic_year_id = :ayid';
                        $selectRoll = 'se.roll_number';
                    } else {
                        $joinEnroll = ' LEFT JOIN students_enrollment se ON se.student_id = s.id';
                        $selectRoll = 'se.roll_number';
                    }
                } else {
                    // Check legacy students.roll_number
                    try {
                        $cols = $pdo->query("SHOW COLUMNS FROM students LIKE 'roll_number'")->fetch();
                        if ($cols) { $selectRoll = 's.roll_number'; }
                    } catch (Exception $e) { /* ignore */ }
                }

                $fbSql = "SELECT DISTINCT s.id, s.first_name, s.last_name, $selectRoll
                          FROM attendance a
                          JOIN students s ON s.id = a.student_id
                          $joinEnroll
                          WHERE a.class_id = :cid AND a.section_id = :sid
                            AND MONTH(a.date) = :m AND YEAR(a.date) = :y
                          ORDER BY COALESCE($selectRoll, 999999), s.first_name ASC";
                $fb = $pdo->prepare($fbSql);
                $fb->bindValue(':cid', $class_id, PDO::PARAM_INT);
                $fb->bindValue(':sid', $section_id, PDO::PARAM_INT);
                $fb->bindValue(':m', (int)$month, PDO::PARAM_INT);
                $fb->bindValue(':y', (int)$year, PDO::PARAM_INT);
                if ($use_enrollment && $current_year_id) { $fb->bindValue(':ayid', $current_year_id, PDO::PARAM_INT); }
                $fb->execute();
                $students = $fb->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Exception $e) {
                // keep empty
            }
        }
    
    // উপস্থিতি ডেটা লোড করুন
    $attendance_data = [];
    if (!empty($students)) {
        $student_ids = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        
        $attendance_stmt = $pdo->prepare("
            SELECT student_id, DATE(date) as attendance_date, status 
            FROM attendance 
            WHERE student_id IN ($placeholders) 
            AND MONTH(date) = ? 
            AND YEAR(date) = ?
            ORDER BY student_id, attendance_date
        ");
        
        $params = array_merge($student_ids, [$month, $year]);
        $attendance_stmt->execute($params);
        
        while ($row = $attendance_stmt->fetch(PDO::FETCH_ASSOC)) {
            $attendance_data[$row['student_id']][$row['attendance_date']] = $row['status'];
        }
    }
}

// প্রিন্ট ভিউ এর জন্য
$is_print_view = isset($_GET['print']) && $_GET['print'] == 'true';
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>মাসিক হাজিরা রিপোর্ট - কিন্ডার গার্ডেন</title>

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
        .report-card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        .report-card .card-header {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
        }
        .attendance-table {
            font-size: 0.75rem;
        }
        .attendance-table th {
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 600;
            text-align: center;
            vertical-align: middle;
            padding: 5px 3px;
        }
        .attendance-table td {
            text-align: center;
            vertical-align: middle;
            padding: 4px 2px;
        }
        .day-off {
            background-color: #f8f9fa;
        }
        .present-icon {
            color: #28a745;
        }
        .absent-icon {
            color: #dc3545;
        }
        .late-icon {
            color: #ffc107;
        }
        .half-day-icon {
            color: #17a2b8;
        }
        .holiday {
            background-color: #fff3cd;
            color: #856404;
        }
        .summary-card {
            background: linear-gradient(45deg, #f8f9fc, #e3e6f0);
            border-left: 4px solid #4e73df;
        }
        .school-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #4e73df;
        }
        .school-name {
            font-size: 24px;
            font-weight: bold;
            color: #4e73df;
            margin-bottom: 5px;
        }
        .school-details {
            font-size: 14px;
            color: #6c757d;
        }
        .report-title {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
            color: #4e73df;
        }
        .criteria-info {
            font-size: 14px;
            text-align: center;
            margin-bottom: 15px;
            color: #6c757d;
        }
        .daily-total {
            font-weight: bold;
            background-color: #e9ecef;
        }
        .signature-area {
            margin-top: 50px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 300px;
            margin: 0 auto;
            padding-top: 40px;
        }
        @media print {
            @page {
                size: landscape;
                margin: 0.5cm;
            }
            .no-print {
                display: none !important;
            }
            body {
                font-size: 12px;
                margin: 0;
                padding: 10px;
            }
            .attendance-table {
                font-size: 10px;
            }
            .school-header {
                margin-bottom: 10px;
                padding-bottom: 5px;
            }
            .school-name {
                font-size: 18px;
            }
            .report-title {
                font-size: 16px;
                margin: 10px 0;
            }
            .container-fluid {
                padding: 0 5px;
            }
            .card {
                border: none;
                box-shadow: none;
                margin-bottom: 10px;
            }
            .card-header {
                background-color: #4e73df !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .summary-card {
                background: #f8f9fc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .content-wrapper {
                margin-left: 0 !important;
            }
            .main-sidebar, .navbar {
                display: none !important;
            }
        }
        .print-only {
            display: none;
        }
        @media print {
            .print-only {
                display: block;
            }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini <?php echo $is_print_view ? 'sidebar-collapse' : ''; ?>">
<div class="wrapper">

    <?php if (!$is_print_view): ?>
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
    <?php endif; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <?php if (!$is_print_view): ?>
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">মাসিক হাজিরা রিপোর্ট</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/reports.php">রিপোর্ট</a></li>
                            <li class="breadcrumb-item active">মাসিক হাজিরা রিপোর্ট</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Notification Alerts -->
                <?php if(!$is_print_view && isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-check"></i> সফল!</h5>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if(!$is_print_view && isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-ban"></i> ত্রুটি!</h5>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card report-card">
                            <?php if (!$is_print_view): ?>
                            <div class="card-header">
                                <h3 class="card-title">রিপোর্ট জেনারেট করুন</h3>
                            </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <?php if (!$is_print_view): ?>
                                <form method="GET" action="">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="class_id">ক্লাস নির্বাচন করুন</label>
                                                <select class="form-control" id="class_id" name="class_id" required>
                                                    <option value="">নির্বাচন করুন</option>
                                                    <?php foreach($classes as $class): ?>
                                                        <option value="<?php echo $class['id']; ?>" <?php echo (isset($class_id) && $class_id == $class['id']) ? 'selected' : ''; ?>>
                                                            <?php echo $class['name']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="section_id">শাখা নির্বাচন করুন</label>
                                                <select class="form-control" id="section_id" name="section_id" required>
                                                    <option value="">নির্বাচন করুন</option>
                                                    <?php foreach($sections as $section): ?>
                                                        <option value="<?php echo $section['id']; ?>" <?php echo (isset($section_id) && $section_id == $section['id']) ? 'selected' : ''; ?>>
                                                            <?php echo $section['name']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="month">মাস</label>
                                                <select class="form-control" id="month" name="month" required>
                                                    <?php foreach($months as $key => $name): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo (isset($month) && $month == $key) ? 'selected' : (($key == $current_month) ? 'selected' : ''); ?>>
                                                            <?php echo $name; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="year">বছর</label>
                                                <select class="form-control" id="year" name="year" required>
                                                    <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                                        <option value="<?php echo $y; ?>" <?php echo (isset($year) && $year == $y) ? 'selected' : (($y == $current_year) ? 'selected' : ''); ?>>
                                                            <?php echo $y; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group" style="margin-top: 32px;">
                                                <button type="submit" name="generate_report" class="btn btn-primary">
                                                    <i class="fas fa-chart-bar"></i> রিপোর্ট দেখুন
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <?php endif; ?>

                                <?php if(isset($students) && isset($attendance_data)): ?>
                                    <?php if (!$is_print_view): ?>
                                    <hr>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                                        <h4>
                                            <?php 
                                            if(!empty($class_name) && !empty($section_name)) {
                                                echo $class_name . ' - ' . $section_name . ' শাখার ' . $months[$month] . ', ' . $year . ' এর উপস্থিতি রিপোর্ট';
                                            }
                                            ?>
                                        </h4>
                                        <div>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['print' => 'true'])); ?>" target="_blank" class="btn btn-success mr-2">
                                                <i class="fas fa-print"></i> প্রিন্ট ভিউ
                                            </a>
                                            <button onclick="window.print()" class="btn btn-info">
                                                <i class="fas fa-file-pdf"></i> প্রিন্ট করুন
                                            </button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Print Header -->
                                    <div class="school-header <?php echo !$is_print_view ? 'no-print' : ''; ?>">
                                        <div class="school-name"><?php echo $school_info['name']; ?></div>
                                        <div class="school-details">
                                            <?php echo $school_info['address']; ?> | 
                                            ফোন: <?php echo $school_info['phone']; ?> | 
                                            ইমেইল: <?php echo $school_info['email']; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="report-title">
                                        মাসিক হাজিরা রিপোর্ট
                                    </div>
                                    
                                    <div class="criteria-info">
                                        ক্লাস: <?php echo $class_name; ?> | 
                                        শাখা: <?php echo $section_name; ?> | 
                                        মাস: <?php echo $months[$month] . ', ' . $year; ?>
                                    </div>
                                    
                                    <?php if(!empty($students)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped attendance-table">
                                                <thead>
                                                    <tr>
                                                        <th rowspan="2" style="width: 30px;">রোল</th>
                                                        <th rowspan="2" style="min-width: 120px;">শিক্ষার্থীর নাম</th>
                                                        <?php for($day = 1; $day <= $days_in_month; $day++): 
                                                            $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                                            $day_of_week = date('N', strtotime($date));
                                                            $is_weekend = in_array($day_of_week, $weekly_holidays);
                                                            $is_holiday = in_array($date, $holiday_dates);
                                                            $is_day_off = $is_weekend || $is_holiday;
                                                        ?>
                                                            <th class="<?php echo $is_day_off ? 'day-off' : ''; ?>">
                                                                <?php 
                                                                $bangla_days = ['', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহস্পতি', 'শুক্র', 'শনি', 'রবি'];
                                                                echo $day; 
                                                                ?>
                                                            </th>
                                                        <?php endfor; ?>
                                                        <th rowspan="2" style="width: 40px;">মোট উপ.</th>
                                                        <th rowspan="2" style="width: 40px;">মোট অনু.</th>
                                                        <th rowspan="2" style="width: 40px;">%</th>
                                                    </tr>
                                                    <tr>
                                                        <?php for($day = 1; $day <= $days_in_month; $day++): 
                                                            $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                                            $day_of_week = date('N', strtotime($date));
                                                            $is_weekend = in_array($day_of_week, $weekly_holidays);
                                                            $is_holiday = in_array($date, $holiday_dates);
                                                            $is_day_off = $is_weekend || $is_holiday;
                                                        ?>
                                                            <th class="<?php echo $is_day_off ? 'day-off' : ''; ?>">
                                                                <?php 
                                                                $bangla_days = ['', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহস্পতি', 'শুক্র', 'শনি', 'রবি'];
                                                                echo $bangla_days[$day_of_week]; 
                                                                ?>
                                                            </th>
                                                        <?php endfor; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $total_present_all = 0;
                                                    $total_absent_all = 0;
                                                    $total_students = count($students);
                                                    $daily_present_totals = array_fill(1, $days_in_month, 0);
                                                    $daily_absent_totals = array_fill(1, $days_in_month, 0);
                                                    ?>
                                                    
                                                    <?php foreach($students as $student): 
                                                        $student_id = $student['id'];
                                                        $total_present = 0;
                                                        $total_absent = 0;
                                                    ?>
                                                        <tr>
                                                            <td><?php echo $student['roll_number']; ?></td>
                                                            <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                                                            
                                                            <?php for($day = 1; $day <= $days_in_month; $day++): 
                                                                $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                                                $day_of_week = date('N', strtotime($date));
                                                                $is_weekend = in_array($day_of_week, $weekly_holidays);
                                                                $is_holiday = in_array($date, $holiday_dates);
                                                                $is_day_off = $is_weekend || $is_holiday;
                                                                
                                                                $status = isset($attendance_data[$student_id][$date]) ? $attendance_data[$student_id][$date] : '';
                                                                
                                                                if($is_day_off) {
                                                                    echo '<td class="day-off">ছুটি</td>';
                                                                } else {
                                                                    if($status == 'present') {
                                                                        echo '<td class="present-icon"><i class="fas fa-check-circle"></i></td>';
                                                                        $total_present++;
                                                                        $daily_present_totals[$day]++;
                                                                    } elseif($status == 'absent') {
                                                                        echo '<td class="absent-icon"><i class="fas fa-times-circle"></i></td>';
                                                                        $total_absent++;
                                                                        $daily_absent_totals[$day]++;
                                                                    } elseif($status == 'late') {
                                                                        echo '<td class="late-icon"><i class="fas fa-clock"></i></td>';
                                                                        $total_present++; // দেরীতে আসলেও উপস্থিত ধরা হয়
                                                                        $daily_present_totals[$day]++;
                                                                    } elseif($status == 'half_day') {
                                                                        echo '<td class="half-day-icon"><i class="fas fa-adjust"></i></td>';
                                                                        $total_present++; // অর্ধদিবসও উপস্থিত ধরা হয়
                                                                        $daily_present_totals[$day]++;
                                                                    } else {
                                                                        echo '<td>-</td>';
                                                                    }
                                                                }
                                                            endfor; ?>
                                                            
                                                            <?php 
                                                            // কর্মদিবস গণনা (ছুটি বাদে)
                                                            $working_days = 0;
                                                            for($day = 1; $day <= $days_in_month; $day++) {
                                                                $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                                                $day_of_week = date('N', strtotime($date));
                                                                $is_weekend = in_array($day_of_week, $weekly_holidays);
                                                                $is_holiday = in_array($date, $holiday_dates);
                                                                $is_day_off = $is_weekend || $is_holiday;
                                                                
                                                                if(!$is_day_off) {
                                                                    $working_days++;
                                                                }
                                                            }
                                                            
                                                            // Division by Zero Error প্রতিরোধ
                                                            $attendance_percentage = 0;
                                                            if ($working_days > 0) {
                                                                $attendance_percentage = round(($total_present / $working_days) * 100, 2);
                                                            }
                                                            
                                                            $total_present_all += $total_present;
                                                            $total_absent_all += $total_absent;
                                                            ?>
                                                            
                                                            <td class="present-icon"><?php echo $total_present; ?></td>
                                                            <td class="absent-icon"><?php echo $total_absent; ?></td>
                                                            <td><?php echo $attendance_percentage; ?>%</td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    
                                                    <!-- Daily Present Total Row -->
                                                    <tr class="daily-total">
                                                        <td colspan="2">দৈনিক মোট উপস্থিতি</td>
                                                        <?php for($day = 1; $day <= $days_in_month; $day++): 
                                                            $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                                            $day_of_week = date('N', strtotime($date));
                                                            $is_weekend = in_array($day_of_week, $weekly_holidays);
                                                            $is_holiday = in_array($date, $holiday_dates);
                                                            $is_day_off = $is_weekend || $is_holiday;
                                                        ?>
                                                            <?php if($is_day_off): ?>
                                                                <td class="day-off">-</td>
                                                            <?php else: ?>
                                                                <td class="present-icon"><?php echo $daily_present_totals[$day]; ?></td>
                                                            <?php endif; ?>
                                                        <?php endfor; ?>
                                                        <td class="present-icon"><?php echo $total_present_all; ?></td>
                                                        <td colspan="2">মোট উপস্থিতি</td>
                                                    </tr>
                                                    
                                                    <!-- Daily Absent Total Row -->
                                                    <tr class="daily-total">
                                                        <td colspan="2">দৈনিক মোট অনুপস্থিতি</td>
                                                        <?php for($day = 1; $day <= $days_in_month; $day++): 
                                                            $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                                            $day_of_week = date('N', strtotime($date));
                                                            $is_weekend = in_array($day_of_week, $weekly_holidays);
                                                            $is_holiday = in_array($date, $holiday_dates);
                                                            $is_day_off = $is_weekend || $is_holiday;
                                                        ?>
                                                            <?php if($is_day_off): ?>
                                                                <td class="day-off">-</td>
                                                            <?php else: ?>
                                                                <td class="absent-icon"><?php echo $daily_absent_totals[$day]; ?></td>
                                                            <?php endif; ?>
                                                        <?php endfor; ?>
                                                        <td class="absent-icon"><?php echo $total_absent_all; ?></td>
                                                        <td colspan="2">মোট অনুপস্থিতি</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- প্রধান শিক্ষকের স্বাক্ষর -->
                                        <div class="signature-area print-only">
                                            <div class="signature-line">
                                                <p>প্রতিষ্ঠান প্রধানের স্বাক্ষর</p> <br>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-4 no-print">
                                            <div class="col-md-12">
                                                <div class="card summary-card">
                                                    <div class="card-body">
                                                        <h5>উপস্থিতি সংক্ষিপ্তসার</h5>
                                                        <div class="row">
                                                            <div class="col-md-3">
                                                                <div class="info-box">
                                                                    <span class="info-box-icon bg-success"><i class="fas fa-user-check"></i></span>
                                                                    <div class="info-box-content">
                                                                        <span class="info-box-text">মোট উপস্থিতি</span>
                                                                        <span class="info-box-number"><?php echo $total_present_all; ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="info-box">
                                                                    <span class="info-box-icon bg-danger"><i class="fas fa-user-times"></i></span>
                                                                    <div class="info-box-content">
                                                                        <span class="info-box-text">মোট অনুপস্থিতি</span>
                                                                        <span class="info-box-number"><?php echo $total_absent_all; ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="info-box">
                                                                    <span class="info-box-icon bg-info"><i class="fas fa-chart-pie"></i></span>
                                                                    <div class="info-box-content">
                                                                        <span class="info-box-text">সর্বমোট উপস্থিতির হার</span>
                                                                        <span class="info-box-number">
                                                                            <?php 
                                                                            $total_possible_days = $working_days * $total_students;
                                                                            $overall_percentage = 0;
                                                                            if ($total_possible_days > 0) {
                                                                                $overall_percentage = round(($total_present_all / $total_possible_days) * 100, 2);
                                                                            }
                                                                            echo $overall_percentage . '%';
                                                                            ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="info-box">
                                                                    <span class="info-box-icon bg-primary"><i class="fas fa-users"></i></span>
                                                                    <div class="info-box-content">
                                                                        <span class="info-box-text">মোট শিক্ষার্থী</span>
                                                                        <span class="info-box-number"><?php echo $total_students; ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3 no-print">
                                            <div class="alert alert-info">
                                                <h5><i class="icon fas fa-info"></i> চিহ্নিতকরণ</h5>
                                                <p>
                                                    <span class="present-icon"><i class="fas fa-check-circle"></i></span> = উপস্থিত, 
                                                    <span class="absent-icon"><i class="fas fa-times-circle"></i></span> = অনুপস্থিত, 
                                                    <span class="late-icon"><i class="fas fa-clock"></i></span> = দেরীতে উপস্থিত,
                                                    <span class="half-day-icon"><i class="fas fa-adjust"></i></span> = অর্ধদিবস উপস্থিত,
                                                    <span class="day-off">ছুটি</span> = সাপ্তাহিক বা সাধারণ ছুটি
                                                </p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning text-center">
                                            <i class="fas fa-exclamation-triangle"></i> এই ক্লাস এবং শাখায় কোনো শিক্ষার্থী নেই।
                                        </div>
                                    <?php endif; ?>
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

    <?php if (!$is_print_view): ?>
    <!-- Main Footer -->
    <?php include 'inc/footer.php'; ?>
    <?php endif; ?>
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
                        $('#section_id').html(data);
                    }
                });
            } else {
                $('#section_id').html('<option value="">নির্বাচন করুন</option>');
            }
        });
        
        // Auto-submit form when in print view
        <?php if ($is_print_view): ?>
        window.print();
        <?php endif; ?>
    });
</script>
</body>
</html>
