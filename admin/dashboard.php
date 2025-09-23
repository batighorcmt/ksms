<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../login.php');
}

// Fetch stats for dashboard
$total_students = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status='active'")->fetch()['total'];
$total_teachers = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role='teacher' AND status=1")->fetch()['total'];
$total_classes = $pdo->query("SELECT COUNT(*) as total FROM classes WHERE status='active'")->fetch()['total'];

// Today's attendance

// আজকের উপস্থিতি (attendance_overview.php-এর লজিক অনুসরণে)
$today = date('Y-m-d');
$attendance_stats = $pdo->prepare("
    SELECT 
        COUNT(st.id) as total_students,
        SUM(CASE WHEN a.status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN a.status = 'absent' OR a.status IS NULL THEN 1 ELSE 0 END) as absent
    FROM students st
    LEFT JOIN attendance a ON st.id = a.student_id AND a.date = ?
    WHERE st.status = 'active'
");
$attendance_stats->execute([$today]);
$stats = $attendance_stats->fetch();
$present_students = $stats['present'] ?? 0;
$attendance_percentage = $stats['total_students'] > 0 ? round(($present_students / $stats['total_students']) * 100, 2) : 0;

// Recent fee collections
$recent_fees = $pdo->query("
    SELECT fp.*, s.first_name, s.last_name, fc.name as fee_category
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN fee_structures fs ON fp.fee_structure_id = fs.id
    JOIN fee_categories fc ON fs.fee_category_id = fc.id
    ORDER BY fp.payment_date DESC, fp.id DESC
    LIMIT 5
")->fetchAll();

// Upcoming events
$upcoming_events = $pdo->query("
    SELECT * FROM events 
    WHERE event_date >= CURDATE() 
    ORDER BY event_date ASC 
    LIMIT 5
")->fetchAll();

// Monthly fee collection data for chart
$monthly_fees = $pdo->query("
    SELECT 
        YEAR(payment_date) as year,
        MONTH(payment_date) as month,
        SUM(amount) as total_amount
    FROM fee_payments 
    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(payment_date), MONTH(payment_date)
    ORDER BY year, month
")->fetchAll();

// Student gender ratio for chart
$gender_ratio = $pdo->query("
    SELECT 
        gender,
        COUNT(*) as count
    FROM students 
    WHERE status='active'
    GROUP BY gender
")->fetchAll();

// Class-wise student count
$class_stats = $pdo->query("
    SELECT 
        c.name as class_name,
        COUNT(s.id) as student_count
    FROM classes c
    LEFT JOIN students s ON c.id = s.class_id AND s.status='active'
    WHERE c.status='active'
    GROUP BY c.id
    ORDER BY c.numeric_value
")->fetchAll();

// Prepare data for charts
$monthly_labels = [];
$monthly_data = [];

foreach ($monthly_fees as $fee) {
    $month_name = date("M", mktime(0, 0, 0, $fee['month'], 1));
    $monthly_labels[] = $month_name . ' ' . $fee['year'];
    $monthly_data[] = $fee['total_amount'];
}

$gender_labels = [];
$gender_counts = [];
$gender_colors = ['#4e73df', '#1cc88a', '#36b9cc']; // Blue, Green, Teal

foreach ($gender_ratio as $gender) {
    $gender_labels[] = ucfirst($gender['gender']);
    $gender_counts[] = $gender['count'];
}

// If no gender data, add placeholder
if (empty($gender_ratio)) {
    $gender_labels = ['No Data'];
    $gender_counts = [1];
    $gender_colors = ['#858796']; // Gray
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ড্যাশবোর্ড - কিন্ডার গার্ডেন</title>

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
        .logo-custom {
            font-weight: bold;
            font-size: 22px;
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
        .small-chart-container {
            position: relative;
            height: 100px;
        }
        .bg-gradient-primary {
            background: linear-gradient(87deg, #5e72e4 0, #825ee4 100%) !important;
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
                        <h1 class="m-0">ড্যাশবোর্ড</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">ড্যাশবোর্ড</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Small boxes (Stat box) -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-info">
                            <div class="inner">
                                <h3><?php echo $total_students; ?></h3>
                                <p>মোট শিক্ষার্থী</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <a href="students.php" class="small-box-footer">বিস্তারিত <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-success">
                            <div class="inner">
                                <h3><?php echo $total_teachers; ?></h3>
                                <p>মোট শিক্ষক</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <a href="teachers.php" class="small-box-footer">বিস্তারিত <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-warning">
                            <div class="inner">
                                <h3><?php echo $total_classes; ?></h3>
                                <p>মোট ক্লাস</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-school"></i>
                            </div>
                            <a href="classes.php" class="small-box-footer">বিস্তারিত <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-primary">
                            <div class="inner">
                                <h3><?php echo $attendance_percentage; ?>%</h3>
                                <p>আজকের উপস্থিতি</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <a href="attendance_overview.php" class="small-box-footer">বিস্তারিত <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                </div>
                <!-- /.row -->

                <!-- Main row -->
                <div class="row">
                    <!-- Left col -->
                    <section class="col-lg-8 connectedSortable">
                        <!-- Custom tabs (Charts with tabs)-->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-line mr-1"></i>
                                    মাসিক ফি সংগ্রহ
                                </h3>
                            </div><!-- /.card-header -->
                            <div class="card-body">
                                <div class="tab-content p-0">
                                    <div class="chart tab-pane active" id="revenue-chart" style="position: relative; height: 300px;">
                                        <canvas id="revenue-chart-canvas" height="300" style="height: 300px;"></canvas>
                                    </div>
                                </div>
                            </div><!-- /.card-body -->
                        </div>
                        <!-- /.card -->

                        <!-- Class-wise student distribution -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-bar mr-1"></i>
                                    শ্রেণিভিত্তিক শিক্ষার্থী বণ্টন
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="chart">
                                    <canvas id="class-chart" height="150"></canvas>
                                </div>
                            </div>
                        </div>
                        <!-- /.card -->
                    </section>
                    <!-- /.Left col -->

                    <!-- right col (We are only adding the ID to make the widgets sortable)-->
                    <section class="col-lg-4 connectedSortable">

                        <!-- Recent Fee Payments -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-money-bill-wave mr-1"></i>
                                    সাম্প্রতিক ফি সংগ্রহ
                                </h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body p-0">
                                <ul class="products-list product-list-in-card pl-2 pr-2">
                                    <?php foreach($recent_fees as $fee): ?>
                                    <li class="item">
                                        <div class="product-info">
                                            <a href="javascript:void(0)" class="product-title"><?php echo $fee['first_name'] . ' ' . $fee['last_name']; ?>
                                                <span class="badge badge-success float-right"><?php echo number_format($fee['amount'], 2); ?> টাকা</span></a>
                                            <span class="product-description">
                                                <?php echo $fee['fee_category']; ?> - <?php echo date('d/m/Y', strtotime($fee['payment_date'])); ?>
                                            </span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <!-- /.card-body -->
                            <div class="card-footer text-center">
                                <a href="fees.php" class="uppercase">সমস্ত ফি সংগ্রহ দেখুন</a>
                            </div>
                            <!-- /.card-footer -->
                        </div>
                        <!-- /.card -->

                        <!-- Gender Ratio Pie Chart -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-pie mr-1"></i>
                                    শিক্ষার্থী লিঙ্গ অনুপাত
                                </h3>
                            </div>
                            <div class="card-body">
                                <canvas id="gender-chart" height="200"></canvas>
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
                                    <?php foreach($upcoming_events as $event): ?>
                                    <li class="item">
                                        <div class="product-info">
                                            <a href="javascript:void(0)" class="product-title"><?php echo $event['title']; ?>
                                                <span class="badge badge-info float-right"><?php echo date('d/m/Y', strtotime($event['event_date'])); ?></span></a>
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
                                <a href="events.php" class="uppercase">সমস্ত ইভেন্ট দেখুন</a>
                            </div>
                            <!-- /.card-footer -->
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
<!-- ChartJS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    $(document).ready(function() {
        // Revenue Chart
        var salesChartCanvas = document.getElementById('revenue-chart-canvas').getContext('2d');
        
        var salesChartData = {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [
                {
                    label: 'ফি সংগ্রহ',
                    backgroundColor: 'rgba(60,141,188,0.9)',
                    borderColor: 'rgba(60,141,188,0.8)',
                    pointRadius: false,
                    pointColor: '#3b8bba',
                    pointStrokeColor: 'rgba(60,141,188,1)',
                    pointHighlightFill: '#fff',
                    pointHighlightStroke: 'rgba(60,141,188,1)',
                    data: <?php echo json_encode($monthly_data); ?>
                }
            ]
        };

        var salesChartOptions = {
            maintainAspectRatio: false,
            responsive: true,
            legend: {
                display: false
            },
            scales: {
                xAxes: [{
                    gridLines: {
                        display: false
                    }
                }],
                yAxes: [{
                    gridLines: {
                        display: false
                    },
                    ticks: {
                        callback: function(value) {
                            return '৳' + value;
                        }
                    }
                }]
            }
        };

        // This will get the first returned node in the jQuery collection.
        var salesChart = new Chart(salesChartCanvas, {
            type: 'bar',
            data: salesChartData,
            options: salesChartOptions
        });

        // Gender Chart
        var genderChartCanvas = document.getElementById('gender-chart').getContext('2d');
        var genderChart = new Chart(genderChartCanvas, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($gender_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($gender_counts); ?>,
                    backgroundColor: <?php echo json_encode($gender_colors); ?>,
                }]
            },
            options: {
                maintainAspectRatio: false,
                legend: {
                    position: 'bottom'
                }
            }
        });

        // Class-wise student chart
        var classChartCanvas = document.getElementById('class-chart').getContext('2d');
        
        var classLabels = <?php echo json_encode(array_column($class_stats, 'class_name')); ?>;
        var classData = <?php echo json_encode(array_column($class_stats, 'student_count')); ?>;
        
        var classChart = new Chart(classChartCanvas, {
            type: 'bar',
            data: {
                labels: classLabels,
                datasets: [{
                    label: 'শিক্ষার্থী সংখ্যা',
                    backgroundColor: 'rgba(54, 185, 204, 0.7)',
                    borderColor: 'rgba(54, 185, 204, 1)',
                    borderWidth: 1,
                    data: classData
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        });
    });
</script>
</body>
</html>