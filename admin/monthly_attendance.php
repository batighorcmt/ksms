<?php
require_once '../config.php';

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

// ছুটির দিনগুলো লোড করুন
$holidays = $pdo->query("SELECT * FROM holidays WHERE status='active'")->fetchAll();
$holiday_dates = array_column($holidays, 'date');

// সাপ্তাহিক ছুটির দিনগুলো লোড করুন
$weekly_holidays = $pdo->query("SELECT day_number FROM weekly_holidays WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN);

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
    
    // মাসের দিন সংখ্যা এবং প্রথম দিন নির্ধারণ করুন
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $first_day = date('N', strtotime("$year-$month-01")); // 1 (সোমবার) থেকে 7 (রবিবার)
    
    // নির্বাচিত ক্লাস ও শাখার নাম পান
    $class_name = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
    $class_name->execute([$class_id]);
    $class = $class_name->fetch();
    
    $section_name = $pdo->prepare("SELECT name FROM sections WHERE id = ?");
    $section_name->execute([$section_id]);
    $section = $section_name->fetch();
    
    // শিক্ষার্থীদের লোড করুন
    $students_stmt = $pdo->prepare("
        SELECT id, first_name, last_name, roll_number 
        FROM students 
        WHERE class_id = ? AND section_id = ? AND status='active'
        ORDER BY roll_number ASC
    ");
    $students_stmt->execute([$class_id, $section_id]);
    $students = $students_stmt->fetchAll();
    
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
        .present {
            color: #28a745;
        }
        .absent {
            color: #dc3545;
        }
        .late {
            color: #ffc107;
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
        @media print {
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
                font-size: 28px !important;
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
            .print-summary {
                display: block !important;
                margin-top: 20px;
                page-break-before: always;
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
        .print-summary {
            display: none;
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
    <?php include 'inc/sidebar.php'; ?>
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
                                    <input type="hidden" name="generate_report" value="1">
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
                                                <button type="submit" class="btn btn-primary">
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
                                            if(isset($class_id) && isset($section_id)) {
                                                echo $class['name'] . ' - ' . $section['name'] . ' শাখার ' . $months[$month] . ', ' . $year . ' এর উপস্থিতি রিপোর্ট';
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
                                        ক্লাস: <?php echo $class['name']; ?> | 
                                        শাখা: <?php echo $section['name']; ?> | 
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
                                                            $is_holiday = in_array($date, $holiday_dates);
                                                            $is_weekly_holiday = in_array($day_of_week, $weekly_holidays);
                                                            $is_day_off = $is_holiday || $is_weekly_holiday;
                                                        ?>
                                                            <th class="<?php echo $is_day_off ? 'day-off' : ''; ?>">
                                                                <?php 
                                                                $bangla_days = ['', 'রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহস্পতি', 'শুক্র', 'শনি'];
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
                                                            $is_holiday = in_array($date, $holiday_dates);
                                                            $is_weekly_holiday = in_array($day_of_week, $weekly_holidays);
                                                            $is_day_off = $is_holiday || $is_weekly_holiday;
                                                        ?>
                                                            <th class="<?php echo $is_day_off ? 'day-off' : ''; ?>">
                                                                <?php 
                                                                $bangla_days = ['', 'রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহস্পতি', 'শুক্র', 'শনি'];
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
                                                    $daily_totals = array_fill(1, $days_in_month, ['present' => 0, 'absent' => 0]);
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
                                                                $is_holiday = in_array($date, $holiday_dates);
                                                                $is_weekly_holiday = in_array($day_of_week, $weekly_holidays);
                                                                $is_day_off = $is_holiday || $is_weekly_holiday;
                                                                
                                                                $status = isset($attendance_data[$student_id][$date]) ? $attendance_data[$student_id][$date] : '';
                                                                
                                                                if($is_day_off) {
                                                                    echo '<td class="day-off">ছুটি</td>';
                                                                } else {
                                                                    if($status == 'present') {
                                                                        echo '<td class="present"><i class="fas fa-check-circle"></i></td>';
                                                                        $total_present++;
                                                                        $daily_totals[$day]['present']++;
                                                                    } elseif($status == 'absent') {
                                                                        echo '<td class="absent"><i class="fas fa-times-circle"></i></td>';
                                                                        $total_absent++;
                                                                        $daily_totals[$day]['absent']++;
                                                                    } elseif($status == 'late') {
                                                                        echo '<td class="late"><i class="fas fa-clock"></i></td>';
                                                                        $total_present++; // দেরীতে আসলেও উপস্থিত ধরা হয়
                                                                        $daily_totals[$day]['present']++;
                                                                    } else {
                                                                        echo '<td>-</td>';
                                                                    }
                                                                }
                                                            endfor; ?>
                                                            
                                                            <?php 
                                                            $total_days = $days_in_month - count(array_filter(range(1, $days_in_month), function($day) use ($year, $month, $holiday_dates, $weekly_holidays) {
                                                                $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                                                $day_of_week = date('N', strtotime($date));
                                                                $is_holiday = in_array($date, $holiday_dates);
                                                                $is_weekly_holiday = in_array($day_of_week, $weekly_holidays);
                                                                return $is_holiday || $is_weekly_holiday;
                                                            }));
                                                            
                                                            $attendance_percentage = $total_days > 0 ? round(($total_present / $total_days) * 100, 2) : 0;
                                                            
                                                            $total_present_all += $total_present;
                                                            $total_absent_all += $total_absent;
                                                            ?>
                                                            
                                                            <td class="present"><?php echo $total_present; ?></td>
                                                            <td class="absent"><?php echo $total_absent; ?></td>
                                                            <td><?php echo $attendance_percentage; ?>%</td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    
                                                    <!-- Daily Total Rows -->
                                                    <tr class="daily-total">
                                                        <td colspan="2">মোট উপস্থিতি</td>
                                                        <?php for($day = 1; $day <= $days_in_month; $day++): 
                                                            $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                                            $day_of_week = date('N', strtotime($date));
                                                            $is_holiday = in_array($date, $holiday_dates);
                                                            $is_weekly_holiday = in_array($day_of_week, $weekly_holidays);
                                                            $is_day_off = $is_holiday || $is_weekly_holiday;
                                                        ?>
                                                            <?php if($is_day_off): ?>
                                                                <td class="day-off">-</td>
                                                            <?php else: ?>
                                                                <td class="present"><?php echo $daily_totals[$day]['present']; ?></td>
                                                            <?php endif; ?>
                                                        <?php endfor; ?>
                                                        <td class="present"><?php echo $total_present_all; ?></td>
                                                        <td colspan="2"><?php echo round(($total_present_all / ($total_present_all + $total_absent_all)) * 100, 2); ?>%</td>
                                                    </tr>
                                                    <tr class="daily-total">
                                                        <td colspan="2">মোট অনুপস্থিতি</td>
                                                        <?php for($day = 1; $day <= $days_in_month; $day++): 
                                                            $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                                            $day_of_week = date('N', strtotime($date));
                                                            $is_holiday = in_array($date, $holiday_dates);
                                                            $is_weekly_holiday = in_array($day_of_week, $weekly_holidays);
                                                            $is_day_off = $is_holiday || $is_weekly_holiday;
                                                        ?>
                                                            <?php if($is_day_off): ?>
                                                                <td class="day-off">-</td>
                                                            <?php else: ?>
                                                                <td class="absent"><?php echo $daily_totals[$day]['absent']; ?></td>
                                                            <?php endif; ?>
                                                        <?php endfor; ?>
                                                        <td class="absent"><?php echo $total_absent_all; ?></td>
                                                        <td colspan="2"><?php echo round(($total_absent_all / ($total_present_all + $total_absent_all)) * 100, 2); ?>%</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Print Summary Section -->
                                        <div class="print-summary">
                                            <div class="row">
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
                                                                            <span class="info-box-number"><?php echo round(($total_present_all / ($total_present_all + $total_absent_all)) * 100, 2); ?>%</span>
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
                                            
                                            <div class="mt-3">
                                                <div class="alert alert-info">
                                                    <h5><i class="icon fas fa-info"></i> চিহ্নিতকরণ</h5>
                                                    <p>
                                                        <span class="present"><i class="fas fa-check-circle"></i></span> = উপস্থিত, 
                                                        <span class="absent"><i class="fas fa-times-circle"></i></span> = অনুপস্থিত, 
                                                        <span class="late"><i class="fas fa-clock"></i></span> = দেরীতে উপস্থিত,
                                                        <span class="day-off">ছুটি</span> = সাপ্তাহিক বা সাধারণ ছুটি
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if (!$is_print_view): ?>
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
                                                                        <span class="info-box-number"><?php echo round(($total_present_all / ($total_present_all + $total_absent_all)) * 100, 2); ?>%</span>
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
                                                    <span class="present"><i class="fas fa-check-circle"></i></span> = উপস্থিত, 
                                                    <span class="absent"><i class="fas fa-times-circle"></i></span> = অনুপস্থিত, 
                                                    <span class="late"><i class="fas fa-clock"></i></span> = দেরীতে উপস্থিত,
                                                    <span class="day-off">ছুটি</span> = সাপ্তাহিক বা সাধারণ ছুটি
                                                </p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
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