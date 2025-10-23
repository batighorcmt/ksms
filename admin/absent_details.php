<?php
require_once '../config.php';
require_once 'print_common.php';
require_once __DIR__ . '/inc/enrollment_helpers.php';

if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    header('location: login.php'); exit();
}

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
// basic date validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

if (function_exists('enrollment_table_exists') && enrollment_table_exists($pdo)) {
    // Assume current academic year for the given date; fallback to legacy if needed
    $yearId = current_academic_year_id($pdo);
    $sql = "SELECT a.*, s.first_name, s.last_name, se.roll_number, c.name AS class_name, sec.name AS section_name
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            JOIN students_enrollment se ON se.student_id = s.id AND se.academic_year_id = :yid
            LEFT JOIN classes c ON c.id = se.class_id
            LEFT JOIN sections sec ON sec.id = se.section_id
            WHERE a.date = :dt AND a.status = 'absent'
            ORDER BY c.numeric_value ASC, sec.name ASC, se.roll_number ASC, s.first_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':dt' => $date, ':yid' => $yearId]);
    $absents = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT a.*, s.first_name, s.last_name, s.roll_number, c.name as class_name, sec.name as section_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE a.date = ? AND a.status = 'absent'
        ORDER BY c.numeric_value ASC, sec.name ASC, s.roll_number ASC, s.first_name ASC");
    $stmt->execute([$date]);
    $absents = $stmt->fetchAll();
}

?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>অনুপস্থিত তালিকা - <?php echo htmlspecialchars($date); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body, .main-sidebar, .nav-link { font-family: 'SolaimanLipi', sans-serif; } .no-data{color:#6b7280}</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">অনুপস্থিত তালিকা</h1></div>
                    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="dashboard.php">হোম</a></li><li class="breadcrumb-item active">অনুপস্থিত তালিকা</li></ol></div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>তারিখ: <?php echo htmlspecialchars($date); ?></div>
                        <div>
                            <a href="absent_details.php?date=<?php echo $date; ?>&print=1" target="_blank" class="btn btn-sm btn-primary">প্রিন্ট</a>
                            <a href="dashboard.php" class="btn btn-sm btn-secondary">ফিরে যান</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if(empty($absents)): ?>
                            <div class="alert alert-info">কোনো অনুপস্থিতি পাওয়া যায়নি।</div>
                        <?php else: ?>
                            <?php if(isset($_GET['print'])): // print-friendly header ?>
                                <?php echo print_header($pdo, 'তারিখ: ' . htmlspecialchars($date)); ?>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>নাম</th>
                                            <th>ক্লাস</th>
                                            <th>শাখা</th>
                                            <th>রোল</th>
                                            <th>নোটস</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i=1; foreach($absents as $a): ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($a['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($a['section_name']); ?></td>
                                                <td><?php echo htmlspecialchars($a['roll_number']); ?></td>
                                                <td><?php echo htmlspecialchars($a['note'] ?? ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include 'inc/footer.php'; ?>
</div>

<?php if(isset($_GET['print'])): ?>
    <?php echo print_footer(); ?>
    <script>window.onload = function(){ window.print(); }</script>
<?php endif; ?>
</body>
</html>
