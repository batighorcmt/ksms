<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher', 'guardian'])) {
    redirect('index.php');
}

// শিক্ষার্থী আইডি পান
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// শিক্ষার্থী ডেটা লোড করুন
$stmt = $pdo->prepare("
    SELECT s.*, 
           c.name as class_name, 
           sec.name as section_name,
           u.full_name as guardian_name,
           u.phone as guardian_phone,
           u.email as guardian_email
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id 
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN users u ON s.guardian_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = "শিক্ষার্থী পাওয়া যায়নি!";
    redirect('students.php');
}

// রোল-ভিত্তিক এক্সেস চেক
if ($_SESSION['role'] === 'guardian' && $student['guardian_id'] != $_SESSION['user_id']) {
    $_SESSION['error'] = "আপনি এই শিক্ষার্থীর তথ্য দেখার অনুমতি রাখেন না!";
    redirect('dashboard.php');
}

// উপস্থিতি ডেটা লোড করুন
$attendance = $pdo->prepare("
    SELECT COUNT(*) as total_days,
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
           SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
           SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
    FROM attendance 
    WHERE student_id = ?
");
$attendance->execute([$student_id]);
$attendance_data = $attendance->fetch();

// পরীক্ষার ফলাফল (marks টেবিল) লোড করুন
$marks_stmt = $pdo->prepare("
    SELECT m.*, e.name AS exam_name, e.exam_date, sub.name AS subject_name
    FROM marks m
    JOIN exams e ON m.exam_id = e.id
    LEFT JOIN subjects sub ON m.subject_id = sub.id
    WHERE m.student_id = ?
    ORDER BY e.exam_date DESC
");
$marks_stmt->execute([$student_id]);
$marks_data = $marks_stmt->fetchAll();

// ফি পেমেন্ট হিস্ট্রি লোড করুন
$fee_payments = $pdo->prepare("
    SELECT fp.*, fc.name as fee_category
    FROM fee_payments fp
    JOIN fee_structures fs ON fp.fee_structure_id = fs.id
    JOIN fee_categories fc ON fs.fee_category_id = fc.id
    WHERE fp.student_id = ?
    ORDER BY fp.payment_date DESC
");
$fee_payments->execute([$student_id]);
$fee_payments_data = $fee_payments->fetchAll();
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষার্থীর বিস্তারিত তথ্য - কিন্ডার গার্ডেন</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Bengali Font -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    
    <style>
        body, .main-sidebar, .nav-link {
            font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;
        }
        .student-profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #3c8dbc;
        }
        .info-box {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .info-box:hover {
            transform: translateY(-5px);
        }
        .tab-content {
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
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
                        <h1 class="m-0">শিক্ষার্থীর বিস্তারিত তথ্য</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>students.php">শিক্ষার্থী ব্যবস্থাপনা</a></li>
                            <li class="breadcrumb-item active">শিক্ষার্থীর বিস্তারিত তথ্য</li>
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
                    <div class="col-md-4">
                        <!-- Student Profile Card -->
                        <div class="card card-primary">
                            <div class="card-body box-profile">
                                <div class="text-center">
                                    <?php if(!empty($student['photo'])): ?>
                                        <img class="student-profile-img" src="../uploads/students/<?php echo $student['photo']; ?>" alt="শিক্ষার্থীর ছবি">
                                    <?php else: ?>
                                        <img class="student-profile-img" src="https://via.placeholder.com/150" alt="ছবি নেই">
                                    <?php endif; ?>
                                </div>

                                <h3 class="profile-username text-center"><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></h3>

                                <p class="text-muted text-center"><?php echo $student['class_name'] . ' - ' . $student['section_name']; ?></p>

                                <ul class="list-group list-group-unbordered mb-3">
                                    <li class="list-group-item">
                                        <b>রোল নম্বর</b> <a class="float-right"><?php echo $student['roll_number']; ?></a>
                                    </li>
                                    <li class="list-group-item">
                                        <b>শিক্ষার্থী আইডি</b> <a class="float-right"><?php echo $student['student_id']; ?></a>
                                    </li>
                                    <li class="list-group-item">
                                        <b>ভর্তির তারিখ</b> <a class="float-right"><?php echo !empty($student['admission_date']) ? date('d/m/Y', strtotime($student['admission_date'])) : '-'; ?></a>
                                    </li>
                                    <li class="list-group-item">
                                        <b>স্ট্যাটাস</b> 
                                        <span class="float-right">
                                            <?php if($student['status'] == 'active'): ?>
                                                <span class="badge badge-success">সক্রিয়</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">নিষ্ক্রিয়</span>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                </ul>

                                <div class="text-center">
                                    <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit mr-1"></i> তথ্য সম্পাদনা
                                    </a>
                                </div>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->

                        <!-- Guardian Information -->
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">অভিভাবকের তথ্য</h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <strong><i class="fas fa-user mr-1"></i> নাম</strong>
                                <p class="text-muted"><?php echo $student['guardian_name']; ?></p>
                                <hr>

                                <strong><i class="fas fa-phone mr-1"></i> ফোন</strong>
                                <p class="text-muted"><?php echo $student['guardian_phone']; ?></p>
                                <hr>

                                <strong><i class="fas fa-envelope mr-1"></i> ইমেইল</strong>
                                <p class="text-muted"><?php echo $student['guardian_email']; ?></p>
                                <hr>

                                <strong><i class="fas fa-link mr-1"></i> সম্পর্ক</strong>
                                <p class="text-muted"><?php echo $student['guardian_relation']; ?></p>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->
                    </div>
                    <!-- /.col -->

                    <div class="col-md-8">
                        <!-- Attendance Summary -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">উপস্থিতি সংক্ষিপ্ত</h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="info-box bg-success">
                                            <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">উপস্থিত</span>
                                                <span class="info-box-number"><?php echo $attendance_data['present_days']; ?> দিন</span>
                                                <div class="progress">
                                                    <div class="progress-bar" style="width: <?php echo $attendance_data['total_days'] > 0 ? ($attendance_data['present_days'] / $attendance_data['total_days'] * 100) : 0; ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /.col -->
                                    <div class="col-md-4">
                                        <div class="info-box bg-danger">
                                            <span class="info-box-icon"><i class="fas fa-times-circle"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">অনুপস্থিত</span>
                                                <span class="info-box-number"><?php echo $attendance_data['absent_days']; ?> দিন</span>
                                                <div class="progress">
                                                    <div class="progress-bar" style="width: <?php echo $attendance_data['total_days'] > 0 ? ($attendance_data['absent_days'] / $attendance_data['total_days'] * 100) : 0; ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /.col -->
                                    <div class="col-md-4">
                                        <div class="info-box bg-warning">
                                            <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">বিলম্বিত</span>
                                                <span class="info-box-number"><?php echo $attendance_data['late_days']; ?> দিন</span>
                                                <div class="progress">
                                                    <div class="progress-bar" style="width: <?php echo $attendance_data['total_days'] > 0 ? ($attendance_data['late_days'] / $attendance_data['total_days'] * 100) : 0; ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /.col -->
                                </div>
                                <!-- /.row -->
                            </div>
                            <!-- /.card-body -->
                            <div class="card-footer">
                                <a href="attendance.php?student_id=<?php echo $student['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-list"></i> সম্পূর্ণ উপস্থিতি রেকর্ড দেখুন
                                </a>
                            </div>
                        </div>
                        <!-- /.card -->

                        <!-- Custom Tabs -->
                        <div class="card">
                            <div class="card-header d-flex p-0">
                                <h3 class="card-title p-3">অন্যান্য তথ্য</h3>
                                <ul class="nav nav-pills ml-auto p-2">
                                    <li class="nav-item"><a class="nav-link active" href="#tab_1" data-toggle="tab">ব্যক্তিগত তথ্য</a></li>
                                    <li class="nav-item"><a class="nav-link" href="#tab_2" data-toggle="tab">পরীক্ষার ফলাফল</a></li>
                                    <li class="nav-item"><a class="nav-link" href="#tab_3" data-toggle="tab">ফি পরিশোধ</a></li>
                                </ul>
                            </div><!-- /.card-header -->
                            <div class="card-body">
                                <div class="tab-content">
                                    <div class="tab-pane active" id="tab_1">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong><i class="fas fa-birthday-cake mr-1"></i> জন্ম তারিখ</strong>
                                                <p class="text-muted"><?php echo !empty($student['date_of_birth']) ? date('d/m/Y', strtotime($student['date_of_birth'])) : '-'; ?></p>
                                                <hr>

                                                <strong><i class="fas fa-venus-mars mr-1"></i> লিঙ্গ</strong>
                                                <p class="text-muted">
                                                    <?php 
                                                    if($student['gender'] == 'male') echo 'ছেলে';
                                                    elseif($student['gender'] == 'female') echo 'মেয়ে';
                                                    else echo 'অন্যান্য';
                                                    ?>
                                                </p>
                                                <hr>

                                                <strong><i class="fas fa-tint mr-1"></i> রক্তের গ্রুপ</strong>
                                                <p class="text-muted"><?php echo $student['blood_group'] ?: 'নির্ধারিত হয়নি'; ?></p>
                                                <hr>
                                            </div>
                                            <div class="col-md-6">
                                                <strong><i class="fas fa-book mr-1"></i> ধর্ম</strong>
                                                <p class="text-muted"><?php echo $student['religion'] ?: 'নির্ধারিত হয়নি'; ?></p>
                                                <hr>

                                                <strong><i class="fas fa-phone mr-1"></i> মোবাইল নম্বর</strong>
                                                <p class="text-muted"><?php echo $student['mobile_number']; ?></p>
                                                <hr>

                                                <strong><i class="fas fa-file-alt mr-1"></i> জন্ম নিবন্ধন নম্বর</strong>
                                                <p class="text-muted"><?php echo $student['birth_certificate_no'] ?: 'নির্ধারিত হয়নি'; ?></p>
                                                <hr>
                                            </div>
                                        </div>

                                        <strong><i class="fas fa-map-marker-alt mr-1"></i> বর্তমান ঠিকানা</strong>
                                        <p class="text-muted"><?php echo $student['present_address']; ?></p>
                                        <hr>

                                        <strong><i class="fas fa-map-marker-alt mr-1"></i> স্থায়ী ঠিকানা</strong>
                                        <p class="text-muted"><?php echo $student['permanent_address']; ?></p>
                                    </div>
                                    <!-- /.tab-pane -->

                                    

                                    <div class="tab-pane" id="tab_2">
                                        <?php if(count($marks_data) > 0): ?>
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>পরীক্ষার নাম</th>
                                                        <th>বিষয়</th>
                                                        <th>তারিখ</th>
                                                        <th>প্রাপ্ত নম্বর</th>
                                                        <th>গ্রেড</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($marks_data as $row): ?>
                                                    <tr>
                                                        <td><?php echo $row['exam_name']; ?></td>
                                                        <td><?php echo $row['subject_name'] ?: '-'; ?></td>
                                                        <td><?php echo !empty($row['exam_date']) ? date('d/m/Y', strtotime($row['exam_date'])) : '-'; ?></td>
                                                        <td><?php echo number_format((float)$row['marks_obtained'], 2); ?></td>
                                                        <td>
                                                            <?php 
                                                                $m = (float)$row['marks_obtained'];
                                                                if ($m >= 80) echo '<span class="badge badge-success">A+</span>';
                                                                elseif ($m >= 70) echo '<span class="badge badge-primary">A</span>';
                                                                elseif ($m >= 60) echo '<span class="badge badge-info">A-</span>';
                                                                elseif ($m >= 50) echo '<span class="badge badge-warning">B</span>';
                                                                elseif ($m >= 40) echo '<span class="badge badge-warning">C</span>';
                                                                elseif ($m >= 33) echo '<span class="badge badge-warning">D</span>';
                                                                else echo '<span class="badge badge-danger">F</span>';
                                                            ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <h5><i class="icon fas fa-info"></i> তথ্য নেই</h5>
                                                এই শিক্ষার্থীর কোনো পরীক্ষার নম্বর পাওয়া যায়নি।
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <!-- /.tab-pane -->

                                    <div class="tab-pane" id="tab_3">
                                        <?php if(count($fee_payments_data) > 0): ?>
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>ফি ক্যাটাগরি</th>
                                                        <th>পরিমাণ</th>
                                                        <th>পরিশোধের তারিখ</th>
                                                        <th>পদ্ধতি</th>
                                                        <th>স্ট্যাটাস</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($fee_payments_data as $payment): ?>
                                                    <tr>
                                                        <td><?php echo $payment["fee_category"]; ?></td>
                                                        <td><?php echo number_format($payment["amount"], 2); ?> টাকা</td>
                                                        <td><?php echo !empty($payment["payment_date"]) ? date('d/m/Y', strtotime($payment["payment_date"])) : '-'; ?></td>
                                                        <td>
                                                            <?php 
                                                            if($payment['payment_method'] == 'cash') echo 'নগদ';
                                                            elseif($payment['payment_method'] == 'bank_transfer') echo 'ব্যাংক ট্রান্সফার';
                                                            elseif($payment['payment_method'] == 'check') echo 'চেক';
                                                            elseif($payment['payment_method'] == 'mobile_banking') echo 'মোবাইল ব্যাংকিং';
                                                            else echo $payment['payment_method'];
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php if($payment['status'] == 'paid'): ?>
                                                                <span class="badge badge-success">পরিশোধিত</span>
                                                            <?php elseif($payment['status'] == 'pending'): ?>
                                                                <span class="badge badge-warning">বকেয়া</span>
                                                            <?php elseif($payment['status'] == 'partial'): ?>
                                                                <span class="badge badge-info">আংশিক</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-secondary"><?php echo htmlspecialchars($payment['status']); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <h5><i class="icon fas fa-info"></i> তথ্য নেই</h5>
                                                এই শিক্ষার্থীর কোনো ফি পরিশোধের রেকর্ড পাওয়া যায়নি।
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <!-- /.tab-pane -->
                                </div>
                                <!-- /.tab-content -->
                            </div><!-- /.card-body -->
                        </div>
                        <!-- /.card -->
                    </div>
                    <!-- /.col -->
                </div>
                <!-- /.row -->
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
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        // DataTable initialization
        $('table').DataTable({
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
                    "previous": "পূর্ববর্তী",
                    "next": "পরবর্তী"
                }
            }
        });
    });
</script>
</body>
</html>