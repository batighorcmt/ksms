<?php
require_once '../config.php';
// simple list of exams with class name and academic year
$stmt = $pdo->query("SELECT e.*, et.name as exam_type_name, c.name as class_name, ay.year as academic_year FROM exams e LEFT JOIN exam_types et ON et.id = e.exam_type_id LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN academic_years ay ON ay.id = e.academic_year_id ORDER BY e.created_at DESC");
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>পরীক্ষার তালিকা</title>
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
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
                        <h1 class="m-0 text-dark">পরীক্ষার তালিকা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">পরীক্ষা</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-body">
                        <div class="mb-3 clearfix">
                            <a href="create_exam.php" class="btn btn-sm btn-primary float-end">নতুন পরীক্ষা</a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr><th>ID</th><th>নাম</th><th>শ্রেণী</th><th>বছর</th><th>ধরন</th><th>প্রকাশ তারিখ</th><th>অ্যাকশন</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach($exams as $e): ?>
                                    <tr>
                                        <td><?= $e['id'] ?></td>
                                        <td><?= htmlspecialchars($e['name']) ?></td>
                                        <td><?= htmlspecialchars($e['class_name'] ?? $e['class_id']) ?></td>
                                        <td><?= htmlspecialchars($e['academic_year'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($e['exam_type_name']) ?></td>
                                        <td><?= htmlspecialchars($e['result_publish_date'] ?? $e['result_release_date'] ?? '') ?></td>
                                                                                <td>
                                                                                        <?php
                                                                                            $yearId = (int)($e['academic_year_id'] ?? 0);
                                                                                            $classId = (int)($e['class_id'] ?? 0);
                                                                                            $examId  = (int)$e['id'];
                                                                                            $tabBase = 'tabulation.php?year='.$yearId.'&class_id='.$classId.'&exam_id='.$examId;
                                                                                        ?>
                                                                                        <div class="btn-group">
                                                                                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                                                                অ্যাকশন
                                                                                            </button>
                                                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                                                <li><a class="dropdown-item" href="create_exam.php?id=<?= $examId ?>"><i class="fa-solid fa-pen-to-square me-1"></i> Edit</a></li>
                                                                                                <li><a class="dropdown-item" href="<?= $tabBase ?>"><i class="fa-solid fa-table me-1"></i> টেবুলেশন দেখুন</a></li>
                                                                                                <li><hr class="dropdown-divider"></li>
                                                                                                <li><a class="dropdown-item" href="admit.php?exam_id=<?= $examId ?>" target="_blank"><i class="fa-solid fa-id-card me-1"></i> Admit Card</a></li>
                                                                                                <li><hr class="dropdown-divider"></li>
                                                                                                <li><a class="dropdown-item" href="<?= $tabBase ?>&print=single" target="_blank"><i class="fa-solid fa-print me-1"></i> Print (Single)</a></li>
                                                                                                <li><a class="dropdown-item" href="<?= $tabBase ?>&print=combined" target="_blank"><i class="fa-solid fa-print me-1"></i> Print (Combined)</a></li>
                                                                                                <li><a class="dropdown-item" href="<?= $tabBase ?>&print=stats" target="_blank"><i class="fa-solid fa-chart-column me-1"></i> Print (Stats)</a></li>
                                                                                                <li><hr class="dropdown-divider"></li>
                                                                                                <li><a class="dropdown-item text-danger" href="delete_exam.php?id=<?= $examId ?>" onclick="return confirm('Are you sure?')"><i class="fa-solid fa-trash me-1"></i> Delete</a></li>
                                                                                            </ul>
                                                                                        </div>
                                                                                </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

</body>
</html>
