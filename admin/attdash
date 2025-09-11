<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// বর্তমান তারিখ
$current_date = date('Y-m-d');

// ড্যাশবোর্ড সামারি ডেটা
$total_students = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status='active'")->fetch()['total'];
$present_today = $pdo->query("SELECT COUNT(DISTINCT student_id) as present FROM attendance WHERE date = '$current_date' AND status IN ('present', 'late')")->fetch()['present'];
$absent_today = $total_students - $present_today;
$attendance_rate = $total_students > 0 ? round(($present_today / $total_students) * 100, 2) : 0;

// শ্রেণি ও শাখাভিত্তিক উপস্থিতি ডেটা
$class_attendance = $pdo->query("
    SELECT 
        c.name as class_name,
        s.name as section_name,
        sec.name as section_name,
        SUM(CASE WHEN st.gender = 'male' THEN 1 ELSE 0 END) as total_boys,
        SUM(CASE WHEN st.gender = 'female' THEN 1 ELSE 0 END) as total_girls,
        SUM(CASE WHEN st.gender = 'male' AND a.status IN ('present', 'late') THEN 1 ELSE 0 END) as present_boys,
        SUM(CASE WHEN st.gender = 'female' AND a.status IN ('present', 'late') THEN 1 ELSE 0 END) as present_girls,
        SUM(CASE WHEN st.gender = 'male' AND a.status = 'absent' THEN 1 ELSE 0 END) as absent_boys,
        SUM(CASE WHEN st.gender = 'female' AND a.status = 'absent' THEN 1 ELSE 0 END) as absent_girls,
        COUNT(a.student_id) as total_attendance,
        COUNT(DISTINCT st.id) as total_students_section
    FROM classes c
    LEFT JOIN sections sec ON c.id = sec.class_id
    LEFT JOIN students st ON sec.id = st.section_id AND st.status = 'active'
    LEFT JOIN attendance a ON st.id = a.student_id AND a.date = '$current_date'
    WHERE c.status = 'active' AND sec.status = 'active'
    GROUP BY c.id, sec.id
    ORDER BY c.numeric_value, sec.name
")->fetchAll();

// অনুপস্থিত শিক্ষার্থীদের তালিকা
$absent_students = $pdo->query("
    SELECT 
        st.id,
        st.first_name,
        st.last_name,
        st.roll_number,
        st.mobile,
        st.village,
        c.name as class_name,
        sec.name as section_name
    FROM students st
    JOIN classes c ON st.class_id = c.id
    JOIN sections sec ON st.section_id = sec.id
    LEFT JOIN attendance a ON st.id = a.student_id AND a.date = '$current_date'
    WHERE (a.status = 'absent' OR a.id IS NULL) 
    AND st.status = 'active'
    ORDER BY c.numeric_value, sec.name, st.roll_number
")->fetchAll();

// Include header
include 'inc/header.php';
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>উপস্থিতি ড্যাশবোর্ড - কিন্ডার গার্ডেন</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Bengali Font -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    
    <style>
        body, .main-sidebar, .nav-link {
            font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;
        }
        .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        .dashboard-card .card-header {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
        }
        .info-box-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        .attendance-table th {
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 600;
            text-align: center;
            vertical-align: middle;
        }
        .attendance-table td {
            text-align: center;
            vertical-align: middle;
        }
        .present-cell {
            background-color: #e8f5e9;
            color: #2e7d32;
            font-weight: 600;
        }
        .absent-cell {
            background-color: #ffebee;
            color: #c62828;
            font-weight: 600;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .summary-box {
            border-left: 4px solid #4e73df;
            background: linear-gradient(45deg, #f8f9fc, #e3e6f0);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
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
                        <h1 class="m-0 text-dark">উপস্থিতি ড্যাশবোর্ড</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">উপস্থিতি ড্যাশবোর্ড</li>
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

                <!-- Small boxes (Stat box) -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?php echo $total_students; ?></h3>
                                <p>মোট শিক্ষার্থী</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <a href="<?php echo BASE_URL; ?>admin/students.php" class="small-box-footer">更多信息 <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?php echo $present_today; ?></h3>
                                <p>আজ উপস্থিত</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <a href="#" class="small-box-footer">更多信息 <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3><?php echo $absent_today; ?></h3>
                                <p>আজ অনুপস্থিত</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-user-times"></i>
                            </div>
                            <a href="#" class="small-box-footer">更多信息 <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3><?php echo $attendance_rate; ?>%</h3>
                                <p>উপস্থিতির হার</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <a href="#" class="small-box-footer">更多信息 <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                </div>
                <!-- /.row -->

                <!-- Charts Row -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h3 class="card-title">উপস্থিতি বনাম অনুপস্থিতি</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="attendanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h3 class="card-title">শ্রেণিভিত্তিক উপস্থিতির হার</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="classWiseChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Class-wise Attendance Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h3 class="card-title">শ্রেণি ও শাখাভিত্তিক উপস্থিতি বিবরণ</h3>
                                <div class="card-tools">
                                    <span class="badge badge-primary"><?php echo date('d/m/Y'); ?></span>
                                </div>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="classAttendanceTable" class="table table-bordered table-striped attendance-table">
                                        <thead>
                                            <tr>
                                                <th rowspan="2">শ্রেণি</th>
                                                <th rowspan="2">শাখা</th>
                                                <th colspan="2">মোট শিক্ষার্থী</th>
                                                <th colspan="2">উপস্থিত</th>
                                                <th colspan="2">অনুপস্থিত</th>
                                                <th rowspan="2">মোট উপস্থিত</th>
                                                <th rowspan="2">মোট অনুপস্থিত</th>
                                                <th rowspan="2">উপস্থিতির হার</th>
                                            </tr>
                                            <tr>
                                                <th>ছেলে</th>
                                                <th>মেয়ে</th>
                                                <th>ছেলে</th>
                                                <th>মেয়ে</th>
                                                <th>ছেলে</th>
                                                <th>মেয়ে</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($class_attendance as $row): 
                                                $total_present = $row['present_boys'] + $row['present_girls'];
                                                $total_absent = $row['absent_boys'] + $row['absent_girls'];
                                                $total_students_section = $row['total_boys'] + $row['total_girls'];
                                                $attendance_rate_section = $total_students_section > 0 ? round(($total_present / $total_students_section) * 100, 2) : 0;
                                            ?>
                                                <tr>
                                                    <td><?php echo $row['class_name']; ?></td>
                                                    <td><?php echo $row['section_name']; ?></td>
                                                    <td><?php echo $row['total_boys']; ?></td>
                                                    <td><?php echo $row['total_girls']; ?></td>
                                                    <td class="present-cell"><?php echo $row['present_boys']; ?></td>
                                                    <td class="present-cell"><?php echo $row['present_girls']; ?></td>
                                                    <td class="absent-cell"><?php echo $row['absent_boys']; ?></td>
                                                    <td class="absent-cell"><?php echo $row['absent_girls']; ?></td>
                                                    <td class="present-cell"><?php echo $total_present; ?></td>
                                                    <td class="absent-cell"><?php echo $total_absent; ?></td>
                                                    <td>
                                                        <div class="progress">
                                                            <div class="progress-bar <?php echo $attendance_rate_section >= 80 ? 'bg-success' : ($attendance_rate_section >= 60 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                                 role="progressbar" style="width: <?php echo $attendance_rate_section; ?>%" 
                                                                 aria-valuenow="<?php echo $attendance_rate_section; ?>" aria-valuemin="0" aria-valuemax="100">
                                                                <?php echo $attendance_rate_section; ?>%
                                                            </div>
                                                        </div>
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
                    </div>
                </div>

                <!-- Absent Students Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h3 class="card-title">অনুপস্থিত শিক্ষার্থীদের তালিকা</h3>
                                <div class="card-tools">
                                    <span class="badge badge-danger"><?php echo date('d/m/Y'); ?></span>
                                </div>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="absentStudentsTable" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>ক্র.নং</th>
                                                <th>নাম</th>
                                                <th>শ্রেণি</th>
                                                <th>শাখা</th>
                                                <th>রোল নং</th>
                                                <th>মোবাইল নং</th>
                                                <th>গ্রাম</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $serial = 1; ?>
                                            <?php foreach($absent_students as $student): ?>
                                                <tr>
                                                    <td><?php echo $serial++; ?></td>
                                                    <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                                                    <td><?php echo $student['class_name']; ?></td>
                                                    <td><?php echo $student['section_name']; ?></td>
                                                    <td><?php echo $student['roll_number']; ?></td>
                                                    <td><?php echo $student['mobile']; ?></td>
                                                    <td><?php echo $student['village']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->
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
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTables
        $('#classAttendanceTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
            "language": {
                "search": "খুঁজুন:",
                "lengthMenu": "প্রতি পৃষ্ঠায় _MENU_ এন্ট্রি দেখুন",
                "info": "পৃষ্ঠা _PAGE_ এর _PAGES_ থেকে দেখানো হচ্ছে",
                "infoEmpty": "কোন এন্ট্রি পাওয়া যায়নি",
                "infoFiltered": "(মোট _MAX_ এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
                "paginate": {
                    "previous": "আগে",
                    "next": "পরবর্তী"
                }
            }
        });

        $('#absentStudentsTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
            "language": {
                "search": "খুঁজুন:",
                "lengthMenu": "প্রতি পৃষ্ঠায় _MENU_ এন্ট্রি দেখুন",
                "info": "পৃষ্ঠা _PAGE_ এর _PAGES_ থেকে দেখানো হচ্ছে",
                "infoEmpty": "কোন এন্ট্রি পাওয়া যায়নি",
                "infoFiltered": "(মোট _MAX_ এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
                "paginate": {
                    "previous": "আগে",
                    "next": "পরবর্তী"
                }
            }
        });

        // Attendance Chart
        var attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        var attendanceChart = new Chart(attendanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['উপস্থিত', 'অনুপস্থিত'],
                datasets: [{
                    data: [<?php echo $present_today; ?>, <?php echo $absent_today; ?>],
                    backgroundColor: ['#28a745', '#dc3545'],
                    hoverBackgroundColor: ['#218838', '#c82333'],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    title: {
                        display: true,
                        text: 'আজকের উপস্থিতি অবস্থা'
                    }
                }
            }
        });

        // Class-wise Attendance Chart
        var classWiseCtx = document.getElementById('classWiseChart').getContext('2d');
        var classWiseChart = new Chart(classWiseCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['class_name'] . ' - ' . $item['section_name'] . "'"; }, $class_attendance)); ?>],
                datasets: [{
                    label: 'উপস্থিতির হার (%)',
                    data: [<?php 
                        $rates = [];
                        foreach($class_attendance as $row) {
                            $total_present = $row['present_boys'] + $row['present_girls'];
                            $total_students_section = $row['total_boys'] + $row['total_girls'];
                            $rate = $total_students_section > 0 ? round(($total_present / $total_students_section) * 100, 2) : 0;
                            $rates[] = $rate;
                        }
                        echo implode(',', $rates);
                    ?>],
                    backgroundColor: '#4e73df',
                    hoverBackgroundColor: '#2e59d9',
                    borderColor: '#4e73df',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
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
                    title: {
                        display: true,
                        text: 'শ্রেণিভিত্তিক উপস্থিতির হার'
                    }
                }
            }
        });
    });
</script>
</body>
</html>