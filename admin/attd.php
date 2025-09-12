<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// Get selected date (default to today)
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Fetch total active students count
$total_students_query = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status='active'");
$total_students = $total_students_query->fetch()['total'];

// Fetch attendance stats for the selected date
// This query now considers students without attendance records as absent
$attendance_stats = $pdo->prepare("
    SELECT 
        COUNT(st.id) as total_students,
        SUM(CASE WHEN a.status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN a.status = 'absent' OR a.status IS NULL THEN 1 ELSE 0 END) as absent
    FROM students st
    LEFT JOIN attendance a ON st.id = a.student_id AND a.date = ?
    WHERE st.status = 'active'
");
$attendance_stats->execute([$selected_date]);
$stats = $attendance_stats->fetch();

$present_students = $stats['present'] ?? 0;
$absent_students = $stats['absent'] ?? 0;
$attendance_percentage = $total_students > 0 ? round(($present_students / $total_students) * 100, 2) : 0;

// Fetch class and section-wise attendance data
// This query now considers students without attendance records as absent
$class_attendance = $pdo->prepare("
    SELECT 
        c.name as class_name,
        s.name as section_name,
        COUNT(st.id) as total_students,
        SUM(CASE WHEN st.gender = 'male' THEN 1 ELSE 0 END) as total_boys,
        SUM(CASE WHEN st.gender = 'female' THEN 1 ELSE 0 END) as total_girls,
        SUM(CASE WHEN st.gender = 'male' AND a.status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) as present_boys,
        SUM(CASE WHEN st.gender = 'female' AND a.status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) as present_girls,
        SUM(CASE WHEN st.gender = 'male' AND (a.status = 'absent' OR a.status IS NULL) THEN 1 ELSE 0 END) as absent_boys,
        SUM(CASE WHEN st.gender = 'female' AND (a.status = 'absent' OR a.status IS NULL) THEN 1 ELSE 0 END) as absent_girls,
        SUM(CASE WHEN a.status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) as total_present,
        SUM(CASE WHEN a.status = 'absent' OR a.status IS NULL THEN 1 ELSE 0 END) as total_absent,
        CASE 
            WHEN COUNT(st.id) > 0 THEN ROUND((SUM(CASE WHEN a.status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) * 100.0 / COUNT(st.id)), 2)
            ELSE 0 
        END as attendance_percentage
    FROM classes c
    JOIN sections s ON c.id = s.class_id
    JOIN students st ON s.id = st.section_id AND st.status = 'active'
    LEFT JOIN attendance a ON st.id = a.student_id AND a.date = ?
    GROUP BY c.id, s.id
    ORDER BY c.numeric_value, s.name
");
$class_attendance->execute([$selected_date]);
$attendance_data = $class_attendance->fetchAll();

// Fetch absent students list (including those without attendance records)
$absent_students_list = $pdo->prepare("
    SELECT 
        st.id,
        st.first_name,
        st.last_name,
        c.name as class_name,
        s.name as section_name,
        st.roll_number,
        st.mobile_number,
        st.present_address as village,
        st.father_name,
        st.mother_name,
        st.guardian_relation,
        st.photo,
        CASE WHEN a.status IS NULL THEN 'রেকর্ড করা হয়নি' ELSE 'অনুপস্থিত' END as status_type
    FROM students st
    JOIN classes c ON st.class_id = c.id
    JOIN sections s ON st.section_id = s.id
    LEFT JOIN attendance a ON st.id = a.student_id AND a.date = ?
    WHERE st.status = 'active' AND (a.status = 'absent' OR a.status IS NULL)
    ORDER BY c.numeric_value, s.name, st.roll_number
");
$absent_students_list->execute([$selected_date]);
$absent_list = $absent_students_list->fetchAll();

// Fetch data for charts
// Gender distribution for present students
$gender_present_data = $pdo->prepare("
    SELECT 
        st.gender,
        COUNT(*) as count
    FROM students st
    LEFT JOIN attendance a ON st.id = a.student_id AND a.date = ?
    WHERE st.status = 'active' AND (a.status IN ('present', 'late', 'half_day') OR a.status IS NULL)
    GROUP BY st.gender
");
$gender_present_data->execute([$selected_date]);
$gender_present = $gender_present_data->fetchAll();

// Class-wise attendance percentage for chart
$class_attendance_chart = $pdo->prepare("
    SELECT 
        c.name as class_name,
        COUNT(st.id) as total,
        SUM(CASE WHEN a.status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) as present,
        CASE 
            WHEN COUNT(st.id) > 0 THEN ROUND((SUM(CASE WHEN a.status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) * 100.0 / COUNT(st.id)), 2)
            ELSE 0 
        END as percentage
    FROM classes c
    JOIN sections s ON c.id = s.class_id
    JOIN students st ON s.id = st.section_id AND st.status = 'active'
    LEFT JOIN attendance a ON st.id = a.student_id AND a.date = ?
    GROUP BY c.id
    ORDER BY c.numeric_value
");
$class_attendance_chart->execute([$selected_date]);
$class_chart_data = $class_attendance_chart->fetchAll();

// Prepare data for charts
$class_labels = [];
$class_percentages = [];

foreach ($class_chart_data as $class) {
    $class_labels[] = $class['class_name'];
    $class_percentages[] = $class['percentage'];
}

$gender_labels = [];
$gender_counts = [];
$gender_colors = ['#4e73df', '#1cc88a', '#36b9cc']; // Blue, Green, Teal

foreach ($gender_present as $gender) {
    $gender_labels[] = ucfirst($gender['gender']);
    $gender_counts[] = $gender['count'];
}

// If no gender data, add placeholder
if (empty($gender_present)) {
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
    <title>হাজিরা ড্যাশবোর্ড - কিন্ডার গার্ডেন</title>

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
        .bg-gradient-danger {
            background: linear-gradient(87deg, #f5365c 0, #f56036 100%) !important;
        }
        .attendance-table th {
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 600;
        }
        .present {
            color: #28a745;
            font-weight: bold;
        }
        .absent {
            color: #dc3545;
            font-weight: bold;
        }
        .not-recorded {
            color: #6c757d;
            font-style: italic;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-absent {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-not-recorded {
            background-color: #e2e3e5;
            color: #383d41;
        }
        .student-name-link {
            cursor: pointer;
            color: #4e73df;
            font-weight: 500;
        }
        .student-name-link:hover {
            text-decoration: underline;
            color: #224abe;
        }
        .student-photo {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #f8f9fc;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .student-info-label {
            font-weight: 600;
            color: #4e73df;
            min-width: 120px;
            display: inline-block;
        }
        .consecutive-absent {
            font-size: 18px;
            font-weight: bold;
            color: #dc3545;
        }
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 10px;
            }
            .student-photo {
                width: 100px;
                height: 100px;
            }
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
                        <h1 class="m-0">হাজিরা ড্যাশবোর্ড</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">হাজিরা ড্যাশবোর্ড</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Date selection form -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" action="" class="form-inline">
                                    <div class="form-group mr-2">
                                        <label for="date" class="mr-2">তারিখ নির্বাচন করুন:</label>
                                        <input type="date" class="form-control" id="date" name="date" value="<?php echo $selected_date; ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary">দেখুন</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

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
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-success">
                            <div class="inner">
                                <h3><?php echo $present_students; ?></h3>
                                <p>উপস্থিত</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-danger">
                            <div class="inner">
                                <h3><?php echo $absent_students; ?></h3>
                                <p>অনুপস্থিত</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-user-times"></i>
                            </div>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-primary">
                            <div class="inner">
                                <h3><?php echo $attendance_percentage; ?>%</h3>
                                <p>উপস্থিতির হার</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                    </div>
                    <!-- ./col -->
                </div>
                <!-- /.row -->

                <!-- Additional info row -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-info-circle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">হাজিরা রেকর্ডের অবস্থা</span>
                                <span class="info-box-number">
                                    <?php 
                                    $recorded_count = $present_students + $absent_students;
                                    $not_recorded = $total_students - $recorded_count;
                                    echo "আজকের তারিখে " . $recorded_count . " জন শিক্ষার্থীর হাজিরা রেকর্ড করা হয়েছে, " . $not_recorded . " জনের রেকর্ড করা হয়নি (অনুপস্থিত হিসেবে গণ্য)";
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main row -->
                <div class="row">
                    <!-- Left col -->
                    <section class="col-lg-8 connectedSortable">
                        <!-- Class-wise attendance summary -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-bar mr-1"></i>
                                    শ্রেণিভিত্তিক হাজিরা সারাংশ
                                </h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped attendance-table">
                                        <thead>
                                            <tr>
                                                <th>শ্রেণি</th>
                                                <th>শাখা</th>
                                                <th>মোট ছেলে</th>
                                                <th>মোট মেয়ে</th>
                                                <th>উপস্থিত ছেলে</th>
                                                <th>উপস্থিত মেয়ে</th>
                                                <th>অনুপস্থিত ছেলে</th>
                                                <th>অনুপস্থিত মেয়ে</th>
                                                <th>মোট উপস্থিত</th>
                                                <th>মোট অনুপস্থিত</th>
                                                <th>উপস্থিতির হার</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($attendance_data as $data): ?>
                                            <tr>
                                                <td><?php echo $data['class_name']; ?></td>
                                                <td><?php echo $data['section_name']; ?></td>
                                                <td><?php echo $data['total_boys']; ?></td>
                                                <td><?php echo $data['total_girls']; ?></td>
                                                <td class="present"><?php echo $data['present_boys']; ?></td>
                                                <td class="present"><?php echo $data['present_girls']; ?></td>
                                                <td class="absent"><?php echo $data['absent_boys']; ?></td>
                                                <td class="absent"><?php echo $data['absent_girls']; ?></td>
                                                <td class="present"><?php echo $data['total_present']; ?></td>
                                                <td class="absent"><?php echo $data['total_absent']; ?></td>
                                                <td><?php echo $data['attendance_percentage']; ?>%</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->

                        <!-- Class-wise attendance chart -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-line mr-1"></i>
                                    শ্রেণিভিত্তিক উপস্থিতির হার
                                </h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <div class="chart">
                                    <canvas id="class-attendance-chart" height="100"></canvas>
                                </div>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->
                    </section>
                    <!-- /.Left col -->

                    <!-- right col -->
                    <section class="col-lg-4 connectedSortable">
                        <!-- Gender distribution chart -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-pie mr-1"></i>
                                    উপস্থিত শিক্ষার্থীদের লিঙ্গ অনুপাত
                                </h3>
                            </div>
                            <div class="card-body">
                                <canvas id="gender-chart" height="200"></canvas>
                            </div>
                        </div>
                        <!-- /.card -->

                        <!-- Absent students list -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-users mr-1"></i>
                                    অনুপস্থিত শিক্ষার্থীদের তালিকা
                                </h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ক্রমিক</th>
                                                <th>নাম</th>
                                                <th>শ্রেণি</th>
                                                <th>শাখা</th>
                                                <th>রোল</th>
                                                <th>স্থিতি</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $serial = 1; ?>
                                            <?php foreach($absent_list as $student): 
                                                // Fetch consecutive absent days for each student
                                                $consecutive_absent_query = $pdo->prepare("
                                                    SELECT COUNT(*) as consecutive_days
                                                    FROM (
                                                        SELECT date, status, 
                                                            @absent_count := IF(status = 'absent' OR status IS NULL, @absent_count + 1, 0) as consecutive_count,
                                                            @reset := IF(status IN ('present', 'late', 'half_day'), 1, 0) as reset_flag
                                                        FROM (
                                                            SELECT date, status
                                                            FROM attendance 
                                                            WHERE student_id = ? AND date <= ?
                                                            UNION ALL
                                                            SELECT ? as date, NULL as status
                                                        ) a
                                                        CROSS JOIN (SELECT @absent_count := 0, @reset := 0) vars
                                                        ORDER BY date DESC
                                                    ) t
                                                    WHERE consecutive_count > 0
                                                    LIMIT 1
                                                ");
                                                $consecutive_absent_query->execute([$student['id'], $selected_date, $selected_date]);
                                                $consecutive_absent = $consecutive_absent_query->fetch();
                                                $consecutive_days = $consecutive_absent['consecutive_days'] ?? 0;

                                                // Fetch latest remarks for the student
                                                $remarks_query = $pdo->prepare("
                                                    SELECT remarks 
                                                    FROM attendance 
                                                    WHERE student_id = ? AND date <= ? AND remarks IS NOT NULL AND remarks != ''
                                                    ORDER BY date DESC 
                                                    LIMIT 1
                                                ");
                                                $remarks_query->execute([$student['id'], $selected_date]);
                                                $remarks = $remarks_query->fetch();
                                                $latest_remarks = $remarks ? $remarks['remarks'] : 'কোন মন্তব্য পাওয়া যায়নি';
                                            ?>
                                            <tr>
                                                <td><?php echo $serial++; ?></td>
                                                <td>
                                                    <span class="student-name-link" data-toggle="modal" data-target="#studentModal" 
                                                          data-student-id="<?php echo $student['id']; ?>"
                                                          data-student-name="<?php echo $student['first_name'] . ' ' . $student['last_name']; ?>"
                                                          data-class-name="<?php echo $student['class_name']; ?>"
                                                          data-section-name="<?php echo $student['section_name']; ?>"
                                                          data-roll-number="<?php echo $student['roll_number']; ?>"
                                                          data-mobile-number="<?php echo $student['mobile_number']; ?>"
                                                          data-village="<?php echo $student['village']; ?>"
                                                          data-father-name="<?php echo $student['father_name']; ?>"
                                                          data-mother-name="<?php echo $student['mother_name']; ?>"
                                                          data-guardian-relation="<?php echo $student['guardian_relation']; ?>"
                                                          data-photo="<?php echo $student['photo'] ? '../uploads/students/' . $student['photo'] : '../assets/img/default-student.png'; ?>"
                                                          data-status-type="<?php echo $student['status_type']; ?>"
                                                          data-consecutive-days="<?php echo $consecutive_days; ?>"
                                                          data-latest-remarks="<?php echo htmlspecialchars($latest_remarks); ?>">
                                                        <?php echo $student['first_name'] . ' ' . $student['last_name']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $student['class_name']; ?></td>
                                                <td><?php echo $student['section_name']; ?></td>
                                                <td><?php echo $student['roll_number']; ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $student['status_type'] == 'অনুপস্থিত' ? 'status-absent' : 'status-not-recorded'; ?>">
                                                        <?php echo $student['status_type']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- /.card-body -->
                            <div class="card-footer">
                                <a href="absent_details.php?date=<?php echo $selected_date; ?>" class="btn btn-primary btn-sm">সমস্ত অনুপস্থিত শিক্ষার্থী দেখুন</a>
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

<!-- Student Modal -->
<div class="modal fade" id="studentModal" tabindex="-1" role="dialog" aria-labelledby="studentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title" id="studentModalLabel">শিক্ষার্থী বিস্তারিত তথ্য</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <img id="modalStudentPhoto" src="../assets/img/default-student.png" class="student-photo mb-3" alt="Student Photo">
                        <h4 id="modalStudentName" class="mb-1"></h4>
                        <p id="modalStudentClass" class="text-muted"></p>
                        <p id="modalStudentStatus" class="status-badge status-absent"></p>
                    </div>
                    <div class="col-md-8">
                        <div class="row mb-3">
                            <div class="col-12">
                                <h5 class="border-bottom pb-2">ব্যক্তিগত তথ্য</h5>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4">
                                <span class="student-info-label">পিতার নাম:</span>
                            </div>
                            <div class="col-sm-8">
                                <span id="modalFatherName"></span>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4">
                                <span class="student-info-label">মাতার নাম:</span>
                            </div>
                            <div class="col-sm-8">
                                <span id="modalMotherName"></span>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4">
                                <span class="student-info-label">অভিভাবক:</span>
                            </div>
                            <div class="col-sm-8">
                                <span id="modalGuardianRelation"></span>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4">
                                <span class="student-info-label">মোবাইল নং:</span>
                            </div>
                            <div class="col-sm-8">
                                <span id="modalMobileNumber"></span>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4">
                                <span class="student-info-label">গ্রাম:</span>
                            </div>
                            <div class="col-sm-8">
                                <span id="modalVillage"></span>
                            </div>
                        </div>
                        <div class="row mb-3 mt-3">
                            <div class="col-12">
                                <h5 class="border-bottom pb-2">হাজিরা তথ্য</h5>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4">
                                <span class="student-info-label">একটানা অনুপস্থিত:</span>
                            </div>
                            <div class="col-sm-8">
                                <span class="consecutive-absent" id="modalConsecutiveAbsent"></span>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4">
                                <span class="student-info-label">সর্বশেষ মন্তব্য:</span>
                            </div>
                            <div class="col-sm-8">
                                <span id="modalRemarks"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="modalCallButton" class="btn btn-success">
                    <i class="fas fa-phone"></i> কল করুন
                </a>
                <a href="#" id="modalSmsButton" class="btn btn-info">
                    <i class="fas fa-sms"></i> মেসেজ পাঠান
                </a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">বন্ধ করুন</button>
            </div>
        </div>
    </div>
</div>

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

        // Class-wise attendance chart
        var classAttendanceChartCanvas = document.getElementById('class-attendance-chart').getContext('2d');
        var classAttendanceChart = new Chart(classAttendanceChartCanvas, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($class_labels); ?>,
                datasets: [{
                    label: 'উপস্থিতির হার (%)',
                    data: <?php echo json_encode($class_percentages); ?>,
                    backgroundColor: 'rgba(78, 115, 223, 0.7)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            max: 100,
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }]
                }
            }
        });

        // Student Modal functionality
        $('.student-name-link').on('click', function() {
            var studentId = $(this).data('student-id');
            var studentName = $(this).data('student-name');
            var className = $(this).data('class-name');
            var sectionName = $(this).data('section-name');
            var rollNumber = $(this).data('roll-number');
            var mobileNumber = $(this).data('mobile-number');
            var village = $(this).data('village');
            var fatherName = $(this).data('father-name');
            var motherName = $(this).data('mother-name');
            var guardianRelation = $(this).data('guardian-relation');
            var photo = $(this).data('photo');
            var statusType = $(this).data('status-type');
            var consecutiveDays = $(this).data('consecutive-days');
            var latestRemarks = $(this).data('latest-remarks');

            // Set modal content
            $('#modalStudentPhoto').attr('src', photo);
            $('#modalStudentName').text(studentName);
            $('#modalStudentClass').text(className + ' - ' + sectionName + ' (রোল: ' + rollNumber + ')');
            $('#modalStudentStatus').text(statusType);
            $('#modalFatherName').text(fatherName || 'তথ্য নেই');
            $('#modalMotherName').text(motherName || 'তথ্য নেই');
            $('#modalGuardianRelation').text(guardianRelation || 'তথ্য নেই');
            $('#modalMobileNumber').text(mobileNumber || 'তথ্য নেই');
            $('#modalVillage').text(village || 'তথ্য নেই');
            $('#modalConsecutiveAbsent').text(consecutiveDays + ' দিন');
            $('#modalRemarks').text(latestRemarks);

            // Set button actions
            if (mobileNumber) {
                $('#modalCallButton').attr('href', 'tel:' + mobileNumber);
                $('#modalSmsButton').attr('href', 'sms:' + mobileNumber);
                $('#modalCallButton').removeClass('disabled');
                $('#modalSmsButton').removeClass('disabled');
            } else {
                $('#modalCallButton').addClass('disabled');
                $('#modalSmsButton').addClass('disabled');
            }
        });

        // Reset modal state when closed
        $('#studentModal').on('hidden.bs.modal', function () {
            $('#modalCallButton').removeClass('disabled');
            $('#modalSmsButton').removeClass('disabled');
        });
    });
</script>
</body>
</html>