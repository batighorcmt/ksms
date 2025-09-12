<?php
require_once '../config.php';

// Authentication check - শুধুমাত্র শিক্ষক এক্সেস করতে পারবে
if (!isAuthenticated() || !hasRole(['teacher'])) {
    redirect('login.php');
}

// বর্তমান শিক্ষকের তথ্য
$teacher_id = $_SESSION['user_id'];

// শিক্ষকের তথ্য লোড করুন
$teacher = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$teacher->execute([$teacher_id]);
$teacher = $teacher->fetch();

// শিক্ষকের ক্লাস এবং শাখা লোড করুন
$teacher_classes = $pdo->prepare("
    SELECT c.*, s.name as section_name 
    FROM classes c 
    LEFT JOIN sections s ON c.class_teacher_id = ? OR s.section_teacher_id = ?
    WHERE c.class_teacher_id = ? OR s.section_teacher_id = ?
    GROUP BY c.id
");
$teacher_classes->execute([$teacher_id, $teacher_id, $teacher_id, $teacher_id]);
$teacher_classes = $teacher_classes->fetchAll();

// আজকের তারিখ
$today = date('Y-m-d');

// আজকের উপস্থিতি ডেটা
$attendance_today = $pdo->prepare("
    SELECT COUNT(*) as total, 
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
           SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
           SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
    FROM attendance 
    WHERE date = ? AND recorded_by = ?
");
$attendance_today->execute([$today, $teacher_id]);
$attendance_today_data = $attendance_today->fetch();

// এই মাসের উপস্থিতি ডেটা
$current_month = date('Y-m');
$attendance_month = $pdo->prepare("
    SELECT COUNT(*) as total, 
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
           SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
           SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
    FROM attendance 
    WHERE date LIKE ? AND recorded_by = ?
");
$attendance_month->execute([$current_month . '%', $teacher_id]);
$attendance_month_data = $attendance_month->fetch();

// সাম্প্রতিক উপস্থিতি রেকর্ড
$recent_attendance = $pdo->prepare("
    SELECT a.*, s.first_name, s.last_name, c.name as class_name, sec.name as section_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN classes c ON a.class_id = c.id
    JOIN sections sec ON a.section_id = sec.id
    WHERE a.recorded_by = ?
    ORDER BY a.date DESC, a.id DESC
    LIMIT 8
");
$recent_attendance->execute([$teacher_id]);
$recent_attendance_data = $recent_attendance->fetchAll();

// শিক্ষকের জন্য সাম্প্রতিক নোটিশ
$notices = $pdo->query("
    SELECT * FROM notices 
    WHERE (target_audience = 'teachers' OR target_audience = 'all')
    AND publish_date <= CURDATE() 
    AND (expire_date IS NULL OR expire_date >= CURDATE())
    ORDER BY publish_date DESC 
    LIMIT 6
")->fetchAll();

// আসন্ন ইভেন্ট
$events = $pdo->query("
    SELECT * FROM events 
    WHERE (audience = 'teachers' OR audience = 'all')
    AND event_date >= CURDATE() 
    ORDER BY event_date ASC 
    LIMIT 6
")->fetchAll();

// শিক্ষকের মোট শিক্ষার্থী সংখ্যা
$total_students = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE (c.class_teacher_id = ? OR sec.section_teacher_id = ?) AND s.status = 'active'
");
$total_students->execute([$teacher_id, $teacher_id]);
$total_students = $total_students->fetch()['total'];

// শিক্ষকের জন্য সাম্প্রতিক পরীক্ষার ফলাফল
$recent_exams = $pdo->prepare("
    SELECT e.*, c.name as class_name, sec.name as section_name
    FROM exams e
    JOIN classes c ON e.class_id = c.id
    JOIN sections sec ON e.section_id = sec.id
    WHERE c.class_teacher_id = ? OR sec.section_teacher_id = ?
    ORDER BY e.exam_date DESC
    LIMIT 5
");
$recent_exams->execute([$teacher_id, $teacher_id]);
$recent_exams_data = $recent_exams->fetchAll();

// সপ্তাহের দিন অনুযায়ী উপস্থিতি ডেটা (চার্টের জন্য)
$attendance_weekly = $pdo->prepare("
    SELECT DATE_FORMAT(date, '%W') as day, 
           COUNT(*) as total,
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
    FROM attendance 
    WHERE recorded_by = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE_FORMAT(date, '%W'), date
    ORDER BY date DESC
");
$attendance_weekly->execute([$teacher_id]);
$attendance_weekly_data = $attendance_weekly->fetchAll();

// চার্ট ডেটা প্রস্তুত করুন
$chart_labels = [];
$chart_data = [];
$chart_bg_colors = [
    'rgba(78, 115, 223, 0.8)',
    'rgba(28, 200, 138, 0.8)',
    'rgba(54, 185, 204, 0.8)',
    'rgba(246, 194, 62, 0.8)',
    'rgba(231, 74, 59, 0.8)',
    'rgba(133, 135, 150, 0.8)',
    'rgba(105, 0, 132, 0.8)'
];

foreach ($attendance_weekly_data as $data) {
    $chart_labels[] = $data['day'];
    $percentage = $data['total'] > 0 ? round(($data['present'] / $data['total']) * 100, 2) : 0;
    $chart_data[] = $percentage;
}

// যদি কোনো ডেটা না থাকে
if (empty($chart_labels)) {
    $chart_labels = ['কোন ডেটা নেই'];
    $chart_data = [0];
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষক ড্যাশবোর্ড - কিন্ডার গার্ডেন</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Bengali Font -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body, .main-sidebar, .nav-link {
            font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;
        }
        .teacher-welcome {
            background: linear-gradient(120deg, #4e73df, #224abe);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .info-box {
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            border-radius: 10px;
            overflow: hidden;
        }
        .info-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        .progress-sm {
            height: 12px;
            border-radius: 6px;
        }
        .bg-gradient-primary {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%) !important;
        }
        .bg-gradient-success {
            background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%) !important;
        }
        .bg-gradient-info {
            background: linear-gradient(135deg, #36b9cc 0%, #258391 100%) !important;
        }
        .bg-gradient-warning {
            background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%) !important;
        }
        .bg-gradient-danger {
            background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%) !important;
        }
        .bg-gradient-purple {
            background: linear-gradient(135deg, #6f42c1 0%, #4e2a8e 100%) !important;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border: none;
        }
        .card-header {
            border-top-left-radius: 10px !important;
            border-top-right-radius: 10px !important;
            background: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        .dashboard-widget {
            transition: all 0.3s;
        }
        .dashboard-widget:hover {
            transform: translateY(-3px);
        }
        .attendance-badge {
            font-size: 0.85rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .event-date {
            background: #4e73df;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            font-weight: bold;
            margin-right: 15px;
        }
        .quick-action-btn {
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s;
            background: white;
            border: 1px solid #e3e6f0;
            height: 100%;
        }
        .quick-action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: #4e73df;
        }
        .quick-action-btn i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #4e73df;
        }
        .notification-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 10px;
            height: 10px;
            background: #e74a3b;
            border-radius: 50%;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Navbar -->
    <?php include '../admin/inc/header.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php include '../admin/inc/sidebar.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">শিক্ষক ড্যাশবোর্ড</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">শিক্ষক ড্যাশবোর্ড</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Welcome Section -->
                <div class="teacher-welcome">
                    <div class="row">
                        <div class="col-md-8">
                            <h3>প্রিয় শিক্ষক <?php echo $teacher['full_name']; ?>,</h3>
                            <p class="mb-0">আপনার ড্যাশবোর্ডে স্বাগতম। আজ <?php echo date('l, d F Y'); ?> | <?php echo date('h:i A'); ?></p>
                        </div>
                        <div class="col-md-4 text-right">
                            <div class="btn-group">
                                <a href="<?php echo ADMIN_URL; ?>attendance.php" class="btn btn-light">
                                    <i class="fas fa-clipboard-check mr-1"></i> আজকের উপস্থিতি নিন
                                </a>
                                <a href="<?php echo ADMIN_URL; ?>exam.php" class="btn btn-light ml-2">
                                    <i class="fas fa-book mr-1"></i> পরীক্ষা যোগ করুন
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Row -->
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="info-box bg-gradient-primary dashboard-widget">
                            <span class="info-box-icon"><i class="fas fa-school"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">মোট ক্লাস</span>
                                <span class="info-box-number"><?php echo count($teacher_classes); ?></span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: 100%"></div>
                                </div>
                                <span class="progress-description">
                                    আপনি মোট <?php echo count($teacher_classes); ?>টি ক্লাসে পড়ান
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-box bg-gradient-success dashboard-widget">
                            <span class="info-box-icon"><i class="fas fa-user-graduate"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">মোট শিক্ষার্থী</span>
                                <span class="info-box-number"><?php echo $total_students; ?></span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: 100%"></div>
                                </div>
                                <span class="progress-description">
                                    আপনার মোট <?php echo $total_students; ?> জন শিক্ষার্থী
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-box bg-gradient-info dashboard-widget">
                            <span class="info-box-icon"><i class="fas fa-clipboard-check"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">আজকের উপস্থিতি</span>
                                <span class="info-box-number"><?php echo $attendance_today_data['present'] ?? 0; ?>/<?php echo $attendance_today_data['total'] ?? 0; ?></span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo $attendance_today_data['total'] > 0 ? ($attendance_today_data['present'] / $attendance_today_data['total'] * 100) : 0; ?>%"></div>
                                </div>
                                <span class="progress-description">
                                    <?php echo $attendance_today_data['total'] > 0 ? round(($attendance_today_data['present'] / $attendance_today_data['total'] * 100), 2) : 0; ?>% উপস্থিতি
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-box bg-gradient-warning dashboard-widget">
                            <span class="info-box-icon"><i class="fas fa-calendar-alt"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">এই মাসের উপস্থিতি</span>
                                <span class="info-box-number"><?php echo $attendance_month_data['present'] ?? 0; ?>/<?php echo $attendance_month_data['total'] ?? 0; ?></span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo $attendance_month_data['total'] > 0 ? ($attendance_month_data['present'] / $attendance_month_data['total'] * 100) : 0; ?>%"></div>
                                </div>
                                <span class="progress-description">
                                    <?php echo $attendance_month_data['total'] > 0 ? round(($attendance_month_data['present'] / $attendance_month_data['total'] * 100), 2) : 0; ?>% উপস্থিতি
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Row -->
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-lg-8">
                        <!-- Attendance Chart -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-line mr-1"></i>
                                    গত সপ্তাহের উপস্থিতি রিপোর্ট
                                </h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <canvas id="attendanceChart" height="250"></canvas>
                            </div>
                        </div>

                        <!-- My Classes & Recent Attendance -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-chalkboard-teacher mr-1"></i>
                                            আমার ক্লাসগুলো
                                        </h3>
                                    </div>
                                    <div class="card-body p-0">
                                        <ul class="products-list product-list-in-card pl-2 pr-2">
                                            <?php foreach($teacher_classes as $class): 
                                                $student_count = $pdo->prepare("
                                                    SELECT COUNT(*) as count 
                                                    FROM students 
                                                    WHERE class_id = ? AND status = 'active'
                                                ");
                                                $student_count->execute([$class['id']]);
                                                $student_count = $student_count->fetch()['count'];
                                            ?>
                                            <li class="item">
                                                <div class="product-img">
                                                    <i class="fas fa-school fa-2x text-primary"></i>
                                                </div>
                                                <div class="product-info">
                                                    <a href="javascript:void(0)" class="product-title"><?php echo $class['name']; ?>
                                                        <span class="badge badge-info float-right"><?php echo $student_count; ?> শিক্ষার্থী</span></a>
                                                    <span class="product-description">
                                                        <?php echo $class['section_name'] ?? 'সাধারণ শাখা'; ?>
                                                    </span>
                                                </div>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="card-footer text-center">
                                        <a href="<?php echo ADMIN_URL; ?>classes.php" class="btn btn-sm btn-primary">সমস্ত ক্লাস দেখুন</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-history mr-1"></i>
                                            সাম্প্রতিক উপস্থিতি
                                        </h3>
                                    </div>
                                    <div class="card-body p-0">
                                        <ul class="products-list product-list-in-card pl-2 pr-2">
                                            <?php foreach($recent_attendance_data as $attendance): ?>
                                            <li class="item">
                                                <div class="product-img">
                                                    <?php if($attendance['status'] == 'present'): ?>
                                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                                    <?php elseif($attendance['status'] == 'absent'): ?>
                                                        <i class="fas fa-times-circle fa-2x text-danger"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-clock fa-2x text-warning"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="product-info">
                                                    <a href="javascript:void(0)" class="product-title"><?php echo $attendance['first_name'] . ' ' . $attendance['last_name']; ?>
                                                        <span class="badge attendance-badge float-right 
                                                            <?php echo $attendance['status'] == 'present' ? 'badge-success' : ($attendance['status'] == 'absent' ? 'badge-danger' : 'badge-warning'); ?>">
                                                            <?php echo $attendance['status'] == 'present' ? 'উপস্থিত' : ($attendance['status'] == 'absent' ? 'অনুপস্থিত' : 'বিলম্বিত'); ?>
                                                        </span>
                                                    </a>
                                                    <span class="product-description">
                                                        <?php echo $attendance['class_name']; ?> | <?php echo date('d/m/Y', strtotime($attendance['date'])); ?>
                                                    </span>
                                                </div>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="card-footer text-center">
                                        <a href="<?php echo ADMIN_URL; ?>attendance.php" class="btn btn-sm btn-primary">সমস্ত উপস্থিতি দেখুন</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Exams -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-book mr-1"></i>
                                    সাম্প্রতিক পরীক্ষা
                                </h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>পরীক্ষার নাম</th>
                                                <th>ক্লাস</th>
                                                <th>তারিখ</th>
                                                <th>মোট নম্বর</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($recent_exams_data as $exam): ?>
                                            <tr>
                                                <td><?php echo $exam['name']; ?></td>
                                                <td><?php echo $exam['class_name'] . ' (' . $exam['section_name'] . ')'; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($exam['exam_date'])); ?></td>
                                                <td><?php echo $exam['total_marks']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer text-center">
                                <a href="<?php echo ADMIN_URL; ?>exam.php" class="btn btn-sm btn-primary">পরীক্ষা ব্যবস্থাপনা</a>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-lg-4">
                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-bolt mr-1"></i>
                                    দ্রুত অ্যাকশন
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <a href="<?php echo ADMIN_URL; ?>attendance.php" class="quick-action-btn">
                                            <i class="fas fa-clipboard-check"></i>
                                            <h6>উপস্থিতি নিন</h6>
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="<?php echo ADMIN_URL; ?>students.php" class="quick-action-btn">
                                            <i class="fas fa-users"></i>
                                            <h6>শিক্ষার্থী দেখুন</h6>
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="<?php echo ADMIN_URL; ?>exam.php" class="quick-action-btn">
                                            <i class="fas fa-book"></i>
                                            <h6>পরীক্ষা যোগ করুন</h6>
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="<?php echo ADMIN_URL; ?>exam_results.php" class="quick-action-btn">
                                            <i class="fas fa-tasks"></i>
                                            <h6>নম্বর দিন</h6>
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="<?php echo ADMIN_URL; ?>reports.php" class="quick-action-btn">
                                            <i class="fas fa-chart-bar"></i>
                                            <h6>রিপোর্ট দেখুন</h6>
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="<?php echo ADMIN_URL; ?>settings.php" class="quick-action-btn">
                                            <i class="fas fa-cog"></i>
                                            <h6>সেটিংস</h6>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notices -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-bullhorn mr-1"></i>
                                    নোটিশবোর্ড
                                </h3>
                                <span class="badge badge-danger"><?php echo count($notices); ?> নতুন</span>
                            </div>
                            <div class="card-body p-0">
                                <ul class="products-list product-list-in-card pl-2 pr-2">
                                    <?php foreach($notices as $notice): ?>
                                    <li class="item">
                                        <div class="product-info">
                                            <a href="javascript:void(0)" class="product-title"><?php echo $notice['title']; ?>
                                                <span class="badge badge-info float-right"><?php echo date('d/m/Y', strtotime($notice['publish_date'])); ?></span></a>
                                            <span class="product-description">
                                                <?php echo strlen($notice['content']) > 60 ? substr($notice['content'], 0, 60) . '...' : $notice['content']; ?>
                                            </span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="card-footer text-center">
                                <a href="#" class="btn btn-sm btn-primary">সমস্ত নোটিশ দেখুন</a>
                            </div>
                        </div>

                        <!-- Upcoming Events -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    আসন্ন ইভেন্ট
                                </h3>
                            </div>
                            <div class="card-body p-0">
                                <ul class="products-list product-list-in-card pl-2 pr-2">
                                    <?php foreach($events as $event): 
                                        $event_date = strtotime($event['event_date']);
                                        $day = date('d', $event_date);
                                        $month = date('M', $event_date);
                                    ?>
                                    <li class="item">
                                        <div class="event-date">
                                            <span><?php echo $day; ?></span>
                                            <small><?php echo $month; ?></small>
                                        </div>
                                        <div class="product-info">
                                            <a href="javascript:void(0)" class="product-title"><?php echo $event['title']; ?></a>
                                            <span class="product-description">
                                                <?php echo strlen($event['description']) > 50 ? substr($event['description'], 0, 50) . '...' : $event['description']; ?>
                                            </span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="card-footer text-center">
                                <a href="#" class="btn btn-sm btn-primary">সমস্ত ইভেন্ট দেখুন</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Main Footer -->
    <?php include '../admin/inc/footer.php'; ?>
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
        // Attendance Chart
        var ctx = document.getElementById('attendanceChart').getContext('2d');
        var attendanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'উপস্থিতি (%)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: <?php echo json_encode(array_slice($chart_bg_colors, 0, count($chart_labels))); ?>,
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'উপস্থিতি: ' + context.parsed.y + '%';
                            }
                        }
                    }
                }
            }
        });

        // Auto refresh the page every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    });
</script>
</body>
</html>