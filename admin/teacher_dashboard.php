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

// সাম্প্রতিক উপস্থিতি রেকর্ড
$recent_attendance = $pdo->prepare("
    SELECT a.*, s.first_name, s.last_name, c.name as class_name, sec.name as section_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN classes c ON a.class_id = c.id
    JOIN sections sec ON a.section_id = sec.id
    WHERE a.recorded_by = ?
    ORDER BY a.date DESC, a.id DESC
    LIMIT 5
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
    LIMIT 5
")->fetchAll();

// আসন্ন ইভেন্ট
$events = $pdo->query("
    SELECT * FROM events 
    WHERE (audience = 'teachers' OR audience = 'all')
    AND event_date >= CURDATE() 
    ORDER BY event_date ASC 
    LIMIT 5
")->fetchAll();

// শিক্ষকের মোট শিক্ষার্থী সংখ্যা
$total_students = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE c.class_teacher_id = ? OR sec.section_teacher_id = ?
");
$total_students->execute([$teacher_id, $teacher_id]);
$total_students = $total_students->fetch()['total'];
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
    
    <style>
        body, .main-sidebar, .nav-link {
            font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;
        }
        .info-box {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .info-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .progress-sm {
            height: 10px;
        }
        .bg-gradient-primary {
            background: linear-gradient(87deg, #4e73df 0, #825ee4 100%) !important;
        }
        .bg-gradient-success {
            background: linear-gradient(87deg, #2dce89 0, #2dcecc 100%) !important;
        }
        .bg-gradient-info {
            background: linear-gradient(87deg, #11cdef 0, #1171ef 100%) !important;
        }
        .bg-gradient-warning {
            background: linear-gradient(87deg, #fb6340 0, #fbb140 100%) !important;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Navbar -->
    <?php include 'inc/header.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php include 'inc/sidebar.php'; ?>

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
                <!-- Welcome Message -->
                <div class="alert alert-info">
                    <h5><i class="icon fas fa-info"></i> স্বাগতম!</h5>
                    প্রিয় শিক্ষক <?php echo $teacher['full_name']; ?>, আপনার ড্যাশবোর্ডে স্বাগতম।
                </div>

                <!-- Small boxes (Stat box) -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-info">
                            <div class="inner">
                                <h3><?php echo count($teacher_classes); ?></h3>
                                <p>মোট ক্লাস</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-school"></i>
                            </div>
                            <a href="<?php echo ADMIN_URL; ?>classes.php" class="small-box-footer">বিস্তারিত <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-success">
                            <div class="inner">
                                <h3><?php echo $total_students; ?></h3>
                                <p>মোট শিক্ষার্থী</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <a href="<?php echo ADMIN_URL; ?>students.php" class="small-box-footer">বিস্তারিত <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-warning">
                            <div class="inner">
                                <h3><?php echo $attendance_today_data['present'] ?? 0; ?></h3>
                                <p>আজকের উপস্থিতি</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <a href="<?php echo ADMIN_URL; ?>attendance.php" class="small-box-footer">বিস্তারিত <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-primary">
                            <div class="inner">
                                <h3><?php echo count($notices); ?></h3>
                                <p>নতুন নোটিশ</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <a href="#" class="small-box-footer">বিস্তারিত <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                </div>
                <!-- /.row -->

                <!-- Main row -->
                <div class="row">
                    <!-- Left col -->
                    <section class="col-lg-8 connectedSortable">
                        <!-- Teacher's Classes -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chalkboard-teacher mr-1"></i>
                                    আমার ক্লাসগুলো
                                </h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>ক্লাসের নাম</th>
                                                <th>শাখা</th>
                                                <th>শিক্ষার্থী সংখ্যা</th>
                                                <th>অ্যাকশন</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($teacher_classes as $class): 
                                                // প্রতিটি ক্লাসের শিক্ষার্থী সংখ্যা
                                                $student_count = $pdo->prepare("
                                                    SELECT COUNT(*) as count 
                                                    FROM students 
                                                    WHERE class_id = ? AND status = 'active'
                                                ");
                                                $student_count->execute([$class['id']]);
                                                $student_count = $student_count->fetch()['count'];
                                            ?>
                                            <tr>
                                                <td><?php echo $class['name']; ?></td>
                                                <td><?php echo $class['section_name'] ?? 'N/A'; ?></td>
                                                <td><?php echo $student_count; ?></td>
                                                <td>
                                                    <a href="<?php echo ADMIN_URL; ?>attendance.php?class_id=<?php echo $class['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-clipboard-check"></i> উপস্থিতি
                                                    </a>
                                                    <a href="<?php echo ADMIN_URL; ?>students.php?class_id=<?php echo $class['id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-users"></i> শিক্ষার্থী
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->

                        <!-- Recent Attendance -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-history mr-1"></i>
                                    সাম্প্রতিক উপস্থিতি রেকর্ড
                                </h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>শিক্ষার্থীর নাম</th>
                                                <th>ক্লাস</th>
                                                <th>তারিখ</th>
                                                <th>স্ট্যাটাস</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($recent_attendance_data as $attendance): ?>
                                            <tr>
                                                <td><?php echo $attendance['first_name'] . ' ' . $attendance['last_name']; ?></td>
                                                <td><?php echo $attendance['class_name'] . ' (' . $attendance['section_name'] . ')'; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($attendance['date'])); ?></td>
                                                <td>
                                                    <?php if($attendance['status'] == 'present'): ?>
                                                        <span class="badge badge-success">উপস্থিত</span>
                                                    <?php elseif($attendance['status'] == 'absent'): ?>
                                                        <span class="badge badge-danger">অনুপস্থিত</span>
                                                    <?php elseif($attendance['status'] == 'late'): ?>
                                                        <span class="badge badge-warning">বিলম্বিত</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-info"><?php echo $attendance['status']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- /.card-body -->
                            <div class="card-footer text-center">
                                <a href="<?php echo ADMIN_URL; ?>attendance.php" class="btn btn-primary">
                                    <i class="fas fa-list"></i> সমস্ত উপস্থিতি রেকর্ড দেখুন
                                </a>
                            </div>
                        </div>
                        <!-- /.card -->
                    </section>
                    <!-- /.Left col -->

                    <!-- right col -->
                    <section class="col-lg-4 connectedSortable">
                        <!-- Notices -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-bullhorn mr-1"></i>
                                    নোটিশবোর্ড
                                </h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body p-0">
                                <ul class="products-list product-list-in-card pl-2 pr-2">
                                    <?php foreach($notices as $notice): ?>
                                    <li class="item">
                                        <div class="product-info">
                                            <a href="javascript:void(0)" class="product-title"><?php echo $notice['title']; ?>
                                                <span class="badge badge-info float-right"><?php echo date('d/m/Y', strtotime($notice['publish_date'])); ?></span></a>
                                            <span class="product-description">
                                                <?php echo strlen($notice['content']) > 50 ? substr($notice['content'], 0, 50) . '...' : $notice['content']; ?>
                                            </span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <!-- /.card-body -->
                            <div class="card-footer text-center">
                                <a href="#" class="btn btn-sm btn-secondary">সমস্ত নোটিশ দেখুন</a>
                            </div>
                        </div>
                        <!-- /.card -->

                        <!-- Upcoming Events -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    আসন্ন ইভেন্ট
                                </h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body p-0">
                                <ul class="products-list product-list-in-card pl-2 pr-2">
                                    <?php foreach($events as $event): ?>
                                    <li class="item">
                                        <div class="product-info">
                                            <a href="javascript:void(0)" class="product-title"><?php echo $event['title']; ?>
                                                <span class="badge badge-success float-right"><?php echo date('d/m/Y', strtotime($event['event_date'])); ?></span></a>
                                            <span class="product-description">
                                                <?php echo strlen($event['description']) > 50 ? substr($event['description'], 0, 50) . '...' : $event['description']; ?>
                                            </span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <!-- /.card-body -->
                            <div class="card-footer text-center">
                                <a href="#" class="btn btn-sm btn-secondary">সমস্ত ইভেন্ট দেখুন</a>
                            </div>
                        </div>
                        <!-- /.card -->

                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-bolt mr-1"></i>
                                    দ্রুত অ্যাকশন
                                </h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <a href="<?php echo ADMIN_URL; ?>attendance.php" class="btn btn-primary btn-block mb-2">
                                            <i class="fas fa-clipboard-check"></i> উপস্থিতি নিন
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="<?php echo ADMIN_URL; ?>students.php" class="btn btn-info btn-block mb-2">
                                            <i class="fas fa-users"></i> শিক্ষার্থী দেখুন
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="<?php echo ADMIN_URL; ?>exam.php" class="btn btn-success btn-block mb-2">
                                            <i class="fas fa-book"></i> পরীক্ষা ব্যবস্থাপনা
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="<?php echo ADMIN_URL; ?>reports.php" class="btn btn-warning btn-block mb-2">
                                            <i class="fas fa-chart-bar"></i> রিপোর্ট
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->
                    </section>
                    <!-- right col -->
                </div>
                <!-- /.row (main row) -->
            </div><!-- /.container-fluid -->
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
        // Auto refresh the page every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    });
</script>
</body>
</html>