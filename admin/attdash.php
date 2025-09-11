<?php
// config.php ফাইল থেকে ডাটাবেজ সংযোগ এবং অথেন্টিকেশন ফাংশন লোড করা হচ্ছে
require_once '../config.php';

// অথেন্টিকেশন চেক
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// আজকের তারিখ
$today = date('Y-m-d');

// ১. ড্যাশবোর্ডের সারসংক্ষেপ ডেটা সংগ্রহ
// মোট শিক্ষার্থী সংখ্যা
$total_students_query = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status='active'");
$total_students = $total_students_query->fetch()['total'];

// আজকের মোট উপস্থিতি ও অনুপস্থিতি
$attendance_query = $pdo->prepare("
    SELECT
        COUNT(*) as total_taken,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as total_present
    FROM attendance
    WHERE date = ?
");
$attendance_query->execute([$today]);
$attendance_data = $attendance_query->fetch();

$total_present = $attendance_data['total_present'];
$total_absent = $total_students - $total_present;
$attendance_percentage = $total_students > 0 ? round(($total_present / $total_students) * 100, 2) : 0;


// ২. ক্লাস ও শাখা অনুযায়ী ডেটা সংগ্রহ
$class_data_query = $pdo->prepare("
    SELECT
        c.name as class_name,
        s.name as section_name,
        COUNT(st.id) as total_students,
        SUM(CASE WHEN st.gender = 'male' THEN 1 ELSE 0 END) as total_male,
        SUM(CASE WHEN st.gender = 'female' THEN 1 ELSE 0 END) as total_female,
        SUM(CASE WHEN a.status = 'present' AND st.gender = 'male' THEN 1 ELSE 0 END) as present_male,
        SUM(CASE WHEN a.status = 'present' AND st.gender = 'female' THEN 1 ELSE 0 END) as present_female
    FROM students st
    JOIN classes c ON st.class_id = c.id
    JOIN sections s ON st.section_id = s.id
    LEFT JOIN attendance a ON st.id = a.student_id AND a.date = ?
    WHERE st.status = 'active'
    GROUP BY c.id, s.id
    ORDER BY c.sort_order, s.sort_order
");
$class_data_query->execute([$today]);
$class_data = $class_data_query->fetchAll();


// ৩. অনুপস্থিত শিক্ষার্থীদের তালিকা সংগ্রহ
$absent_students_query = $pdo->prepare("
    SELECT
        st.id,
        st.first_name,
        st.last_name,
        c.name as class_name,
        s.name as section_name,
        st.roll_no as roll,
        sp.guardian_mobile as mobile,
        sp.village as village
    FROM students st
    JOIN classes c ON st.class_id = c.id
    JOIN sections s ON st.section_id = s.id
    LEFT JOIN student_profiles sp ON st.id = sp.student_id
    WHERE st.id NOT IN (
        SELECT student_id FROM attendance WHERE date = ? AND status = 'present'
    ) AND st.status = 'active'
    ORDER BY st.class_id, st.section_id, st.roll_no
");
$absent_students_query->execute([$today]);
$absent_students = $absent_students_query->fetchAll();

// HTML শুরু AdminLTE টেমপ্লেট ব্যবহার করে
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>উপস্থিতি ড্যাশবোর্ড</title>

    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tiro+Bangla&display=swap">

    <style>
        body {
            font-family: 'Tiro Bangla', sans-serif;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <!-- হেডার এবং সাইডবার এখানে অন্তর্ভুক্ত থাকবে -->
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">উপস্থিতি ড্যাশবোর্ড</h1>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <div class="content">
            <div class="container-fluid">
                <!-- সারসংক্ষেপ কার্ডসমূহ -->
                <div class="row">
                    <!-- মোট শিক্ষার্থী সংখ্যা -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?php echo $total_students; ?></h3>
                                <p>মোট শিক্ষার্থী</p>
                            </div>
                            <div class="icon">
                                <i class="ion ion-person"></i>
                            </div>
                        </div>
                    </div>
                    <!-- উপস্থিত শিক্ষার্থী -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?php echo $total_present; ?></h3>
                                <p>উপস্থিত শিক্ষার্থী</p>
                            </div>
                            <div class="icon">
                                <i class="ion ion-checkmark-round"></i>
                            </div>
                        </div>
                    </div>
                    <!-- অনুপস্থিত শিক্ষার্থী -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3><?php echo $total_absent; ?></h3>
                                <p>অনুপস্থিত শিক্ষার্থী</p>
                            </div>
                            <div class="icon">
                                <i class="ion ion-close-round"></i>
                            </div>
                        </div>
                    </div>
                    <!-- উপস্থিতির হার -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3><?php echo $attendance_percentage; ?><sup style="font-size: 20px">%</sup></h3>
                                <p>উপস্থিতির হার</p>
                            </div>
                            <div class="icon">
                                <i class="ion ion-stats-bars"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.row -->

                <!-- চার্ট গ্রাফিক্স -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">উপস্থিতি গ্রাফ</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="attendanceChart" style="height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.row -->

                <!-- শাখা অনুযায়ী উপস্থিতির টেবিল -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">শাখা অনুযায়ী উপস্থিতি</h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>শ্রেণী</th>
                                            <th>শাখা</th>
                                            <th>মোট শিক্ষার্থী</th>
                                            <th>মোট ছাত্র</th>
                                            <th>মোট ছাত্রী</th>
                                            <th>উপস্থিত ছাত্র</th>
                                            <th>উপস্থিত ছাত্রী</th>
                                            <th>অনুপস্থিত ছাত্র</th>
                                            <th>অনুপস্থিত ছাত্রী</th>
                                            <th>উপস্থিতির হার</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($class_data as $row): ?>
                                            <?php
                                                $total_present_class = $row['present_male'] + $row['present_female'];
                                                $total_absent_class = $row['total_students'] - $total_present_class;
                                                $class_rate = $row['total_students'] > 0 ? round(($total_present_class / $row['total_students']) * 100, 2) : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                                                <td class="text-center"><?php echo $row['total_students']; ?></td>
                                                <td class="text-center"><?php echo $row['total_male']; ?></td>
                                                <td class="text-center"><?php echo $row['total_female']; ?></td>
                                                <td class="text-center"><?php echo $row['present_male']; ?></td>
                                                <td class="text-center"><?php echo $row['present_female']; ?></td>
                                                <td class="text-center"><?php echo $row['total_male'] - $row['present_male']; ?></td>
                                                <td class="text-center"><?php echo $row['total_female'] - $row['present_female']; ?></td>
                                                <td class="text-center"><?php echo $class_rate; ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- অনুপস্থিত শিক্ষার্থীদের তালিকা -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">অনুপস্থিত শিক্ষার্থীদের তালিকা</h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ক্রমিক নং</th>
                                            <th>নাম</th>
                                            <th>শ্রেণী</th>
                                            <th>শাখা</th>
                                            <th>রোল নং</th>
                                            <th>মোবাইল নং</th>
                                            <th>গ্রাম</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; ?>
                                        <?php foreach ($absent_students as $student): ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['section_name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['roll']); ?></td>
                                                <td><?php echo htmlspecialchars($student['mobile']); ?></td>
                                                <td><?php echo htmlspecialchars($student['village']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- ফুটার এবং অন্যান্য AdminLTE JS -->
    <aside class="control-sidebar control-sidebar-dark">
        <!-- Control sidebar content goes here -->
    </aside>
    <!-- /.control-sidebar -->

    <!-- Main Footer -->
    <footer class="main-footer">
        <!-- Default to the left -->
        <strong>Copyright &copy; 2024.</strong> All rights reserved.
    </footer>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/js/adminlte.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    $(function () {
        // আজকের উপস্থিতির ডেটা
        var present = <?php echo json_encode($total_present); ?>;
        var absent = <?php echo json_encode($total_absent); ?>;

        var ctx = document.getElementById('attendanceChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['উপস্থিত', 'অনুপস্থিত'],
                datasets: [{
                    label: 'শিক্ষার্থীর সংখ্যা',
                    data: [present, absent],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)', // সবুজ
                        'rgba(220, 53, 69, 0.7)'  // লাল
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'শিক্ষার্থীর সংখ্যা'
                        }
                    }
                }
            }
        });
    });
</script>
</body>
</html>