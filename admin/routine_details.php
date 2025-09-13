<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    header('location: login.php');
    exit();
}

// শ্রেণি এবং শাখা আইডি পান
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

// শ্রেণি এবং শাখার তথ্য লোড করুন
$class = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$class->execute([$class_id]);
$class = $class->fetch();

$section = null;
if ($section_id) {
    $section_stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
    $section_stmt->execute([$section_id]);
    $section = $section_stmt->fetch();
}

// রুটিন ডেটা লোড করুন
if ($class_id && $section_id) {
    // নির্দিষ্ট শ্রেণি এবং শাখার রুটিন
    $routine = $pdo->prepare("
        SELECT r.*, s.name as subject_name, u.full_name as teacher_name 
        FROM routines r 
        LEFT JOIN subjects s ON r.subject_id = s.id 
        LEFT JOIN users u ON r.teacher_id = u.id 
        WHERE r.class_id = ? AND r.section_id = ? 
        ORDER BY 
            FIELD(r.day_of_week, 'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'),
            r.period_number
    ");
    $routine->execute([$class_id, $section_id]);
    $routine_data = $routine->fetchAll();
} elseif ($class_id) {
    // শ্রেণির সকল শাখার রুটিন
    $routine = $pdo->prepare("
        SELECT r.*, s.name as subject_name, u.full_name as teacher_name, sec.name as section_name 
        FROM routines r 
        LEFT JOIN subjects s ON r.subject_id = s.id 
        LEFT JOIN users u ON r.teacher_id = u.id 
        LEFT JOIN sections sec ON r.section_id = sec.id 
        WHERE r.class_id = ? 
        ORDER BY 
            sec.name,
            FIELD(r.day_of_week, 'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'),
            r.period_number
    ");
    $routine->execute([$class_id]);
    $routine_data = $routine->fetchAll();
} else {
    $_SESSION['error'] = "শ্রেণি নির্বাচন করুন!";
    header('Location: routine_list.php');
    exit();
}

// দিনের নাম বাংলায়
$day_names = [
    'saturday' => 'শনিবার',
    'sunday' => 'রবিবার',
    'monday' => 'সোমবার',
    'tuesday' => 'মঙ্গলবার',
    'wednesday' => 'বুধবার',
    'thursday' => 'বৃহস্পতিবার',
    'friday' => 'শুক্রবার'
];

// রুটিন ডেটা দিন অনুযায়ী গ্রুপ করুন
$grouped_routine = [];
foreach ($routine_data as $period) {
    $day = $period['day_of_week'];
    if (!isset($grouped_routine[$day])) {
        $grouped_routine[$day] = [];
    }
    $grouped_routine[$day][] = $period;
}

// শ্রেণির সকল শাখা লোড করুন
$sections = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? AND status='active' ORDER BY name ASC");
$sections->execute([$class_id]);
$all_sections = $sections->fetchAll();
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শ্রেণি রুটিন বিস্তারিত - কিন্ডার গার্ডেন</title>

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
        .routine-day {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .day-header {
            background: #4e73df;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .period-card {
            background: white;
            border-left: 4px solid #4e73df;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .period-card:hover {
            transform: translateX(5px);
        }
        .period-time {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .period-subject {
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
        }
        .period-teacher {
            font-size: 0.9rem;
            color: #555;
        }
        .section-filter {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .section-badge {
            font-size: 0.9rem;
            padding: 8px 15px;
            margin: 5px;
            border-radius: 20px;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
        }
        .section-badge:hover {
            transform: scale(1.05);
        }
        .print-btn {
            margin-left: 10px;
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
                        <h1 class="m-0">শ্রেণি রুটিন বিস্তারিত</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>routine_list.php">শ্রেণি রুটিন</a></li>
                            <li class="breadcrumb-item active">রুটিন বিস্তারিত</li>
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

                <!-- শ্রেণি তথ্য -->
                        <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <?php echo $class['name']; ?>
                            <?php if($section): ?>
                                - <?php echo $section['name']; ?> শাখা
                            <?php else: ?>
                                - সকল শাখা
                            <?php endif; ?>
                        </h3>
                        <div class="card-tools">
                            <a href="routine_print.php?class_id=<?php echo $class_id; ?>&section_id=<?php echo $section_id; ?>" target="_blank" class="btn btn-sm btn-primary print-btn">
                                <i class="fas fa-print"></i> প্রিন্ট
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- শাখা ফিল্টার -->
                        <div class="section-filter">
                            <h5>শাখা নির্বাচন করুন:</h5>
                            <a href="routine_details.php?class_id=<?php echo $class_id; ?>" class="section-badge badge badge-<?php echo !$section_id ? 'primary' : 'secondary'; ?>">
                                সকল শাখা
                            </a>
                            <?php foreach($all_sections as $sec): ?>
                                <a href="routine_details.php?class_id=<?php echo $class_id; ?>&section_id=<?php echo $sec['id']; ?>" class="section-badge badge badge-<?php echo $section_id == $sec['id'] ? 'primary' : 'secondary'; ?>">
                                    <?php echo $sec['name']; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <!-- রুটিন তালিকা (টেবিল: পিরিয়ড x দিন) -->
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>পিরিয়ড \ দিন</th>
                                        <?php foreach(['saturday','sunday','monday','tuesday','wednesday','thursday','friday'] as $d): ?>
                                            <th><?php echo $day_names[$d]; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // determine max period number: prefer stored class_periods value, else compute from data (minimum 1)
                                    $maxP = 1;
                                    try {
                                        // ensure table exists
                                        $pdo->exec("CREATE TABLE IF NOT EXISTS class_periods (id INT AUTO_INCREMENT PRIMARY KEY, class_id INT NOT NULL, section_id INT NOT NULL, period_count INT NOT NULL DEFAULT 1, UNIQUE KEY(class_id, section_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                                        $pcStmt = $pdo->prepare("SELECT period_count FROM class_periods WHERE class_id = ? AND section_id = ?");
                                        $pcStmt->execute([ $class_id, $section_id ]);
                                        $storedPc = intval($pcStmt->fetchColumn());
                                        if ($storedPc > 0) {
                                            $maxP = $storedPc;
                                        }
                                    } catch (Exception $e) {
                                        // ignore and fallback to computing from data below
                                    }

                                    // compute max from data as fallback and ensure at least 1
                                    foreach($routine_data as $r) {
                                        if (intval($r['period_number']) > $maxP) $maxP = intval($r['period_number']);
                                    }

                                    if ($maxP < 1) $maxP = 1;
                                    for($p=1;$p<=$maxP;$p++):
                                    ?>
                                    <tr>
                                        <th>পিরিয়ড <?php echo $p; ?></th>
                                        <?php foreach(['saturday','sunday','monday','tuesday','wednesday','thursday','friday'] as $d): ?>
                                            <td>
                                                <?php
                                                $found = null;
                                                if (isset($grouped_routine[$d])) {
                                                    foreach($grouped_routine[$d] as $item) {
                                                        if (intval($item['period_number']) == $p) {
                                                            if ($section_id && $item['section_id'] != $section_id) continue;
                                                            $found = $item; break;
                                                        }
                                                    }
                                                }
                                                if ($found):
                                                ?>
                                                    <div><strong><?php echo htmlspecialchars($found['subject_name']); ?></strong></div>
                                                    <div><?php echo htmlspecialchars($found['teacher_name']); ?></div>
                                                    <div class="text-muted small"><?php echo date('h:i A', strtotime($found['start_time'])); ?> - <?php echo date('h:i A', strtotime($found['end_time'])); ?></div>
                                                    <?php if(!$section_id): ?><div class="small text-info"><?php echo htmlspecialchars($found['section_name'] ?? ''); ?></div><?php endif; ?>
                                                <?php else: ?>
                                                    <div class="text-muted">-</div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- মুদ্রিত লাইন -->
                        <div class="mt-3 text-right text-muted">মুদ্রিত: <?php echo date('d M Y, h:i A'); ?></div>

                        <?php if(empty($grouped_routine)): ?>
                        <div class="alert alert-warning text-center">
                            <h5><i class="icon fas fa-exclamation-triangle"></i> কোনো রুটিন পাওয়া যায়নি</h5>
                            <p>এই শ্রেণির জন্য এখনও কোনো রুটিন তৈরি করা হয়নি।</p>
                            <a href="add_routine.php?class_id=<?php echo $class_id; ?><?php echo $section_id ? '&section_id=' . $section_id : ''; ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i> রুটিন তৈরি করুন
                            </a>
                        </div>
                        <?php endif; ?>
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