<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    header('Location: ../login.php');
    exit;
}

// শ্রেণি এবং শাখা লোড করুন
$classes = $pdo->query("
    SELECT c.*, COUNT(s.id) as section_count 
    FROM classes c 
    LEFT JOIN sections s ON c.id = s.class_id 
    WHERE c.status='active' 
    GROUP BY c.id 
    ORDER BY c.numeric_value ASC
")->fetchAll();

// প্রতিটি শ্রেণির জন্য শাখা লোড করুন
$class_sections = [];
foreach ($classes as $class) {
    $sections = $pdo->prepare("
        SELECT * FROM sections 
        WHERE class_id = ? AND status='active' 
        ORDER BY name ASC
    ");
    $sections->execute([$class['id']]);
    $class_sections[$class['id']] = $sections->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শ্রেণি রুটিন তালিকা - কিন্ডার গার্ডেন</title>

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
        .class-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border-radius: 10px;
            overflow: hidden;
        }
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        .section-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            margin: 3px;
            border-radius: 15px;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
        }
        .section-badge:hover {
            transform: scale(1.05);
        }
        .routine-day {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .period-card {
            border-left: 4px solid #4e73df;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .period-time {
            font-size: 0.85rem;
            color: #6c757d;
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
                        <h1 class="m-0">শ্রেণি রুটিন তালিকা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">শ্রেণি রুটিন</li>
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
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">শ্রেণির তালিকা</h3>
                            </div>
                            <div class="card-body table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>শ্রেণি</th>
                                            <th>রুম নম্বর</th>
                                            <th>শাখা সংখ্যা</th>
                                            <th>শাখাসমূহ</th>
                                            <th>অ্যাকশন</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i=1; foreach($classes as $class): ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td><?php echo htmlspecialchars($class['name']); ?></td>
                                                <td><?php echo $class['room_number'] ? htmlspecialchars($class['room_number']) : '—'; ?></td>
                                                <td><?php echo (int)$class['section_count']; ?></td>
                                                <td>
                                                    <?php if(isset($class_sections[$class['id']]) && count($class_sections[$class['id']]) > 0): ?>
                                                        <?php foreach($class_sections[$class['id']] as $section): ?>
                                                            <span class="badge badge-info mr-1"><?php echo htmlspecialchars($section['name']); ?></span>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">কোন শাখা নেই</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="routine_details.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> দেখুন</a>
                                                    <a href="add_routine.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-success"><i class="fas fa-plus"></i> রুটিন যোগ</a>
                                                    <a href="add_subject.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-book"></i> বিষয় মানচিত্র</a>
                                                    <a href="sections.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-layer-group"></i> শাখা পরিচালনা</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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

</body>
</html>