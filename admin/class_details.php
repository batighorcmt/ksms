<?php
require_once '../config.php';
require_once __DIR__ . '/inc/enrollment_helpers.php';
/** @var PDO $pdo */

// Auth: super_admin or teacher can view
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('../login.php');
}

// Input: class id
$class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($class_id <= 0) {
    $_SESSION['error'] = 'অবৈধ শ্রেণি আইডি!';
    header('Location: classes.php');
    exit();
}

// Load class info
$cstmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$cstmt->execute([$class_id]);
$class = $cstmt->fetch(PDO::FETCH_ASSOC);
if (!$class) {
    $_SESSION['error'] = 'শ্রেণি পাওয়া যায়নি!';
    header('Location: classes.php');
    exit();
}

// Resolve users name column
$nameCol = 'full_name';
try {
    $uCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if ($uCols && !in_array('full_name', $uCols, true) && in_array('name', $uCols, true)) {
        $nameCol = 'name';
    }
} catch (Exception $e) { /* keep default */ }

// Month selection
$today = date('Y-m-d');
$selectedMonth = (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) ? $_GET['month'] : date('Y-m');
$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$monthEndBound = ($selectedMonth === date('Y-m')) ? min($monthEnd, $today) : $monthEnd;

// Sections of this class
$sections = $pdo->prepare("SELECT s.id, s.name, s.section_teacher_id, u.$nameCol AS section_teacher_name
                           FROM sections s
                           LEFT JOIN users u ON u.id = s.section_teacher_id
                           WHERE s.class_id = ?
                           ORDER BY s.name ASC");
$sections->execute([$class_id]);
$sections = $sections->fetchAll(PDO::FETCH_ASSOC);

$sectionIds = array_map(function($r){ return (int)$r['id']; }, $sections);

// Determine enrollment usage
$useEnroll = function_exists('enrollment_table_exists') && enrollment_table_exists($pdo);
$currentYearId = function_exists('current_academic_year_id') ? current_academic_year_id($pdo) : null;

// Student counts per section (total/boys/girls)
$studentCounts = [];
try {
    if ($useEnroll && $currentYearId) {
        $sql = "SELECT se.section_id,
                       COUNT(*) AS total,
                       SUM(CASE WHEN LOWER(s.gender) IN ('male','boy','ছাত্র') THEN 1 ELSE 0 END) AS boys,
                       SUM(CASE WHEN LOWER(s.gender) IN ('female','girl','ছাত্রী') THEN 1 ELSE 0 END) AS girls
                FROM students_enrollment se
                JOIN students s ON s.id = se.student_id
                WHERE se.class_id = ? AND se.academic_year_id = ? AND (se.status = 'active' OR se.status IS NULL)
                GROUP BY se.section_id";
        $st = $pdo->prepare($sql);
        $st->execute([$class_id, $currentYearId]);
    } else {
        // Detect students.status existence
        $hasStatus = false;
        try { $hasStatus = $pdo->query("SHOW COLUMNS FROM students LIKE 'status'")->fetch() ? true : false; } catch (Exception $e) { $hasStatus = false; }
        $sql = "SELECT section_id,
                       COUNT(*) AS total,
                       SUM(CASE WHEN LOWER(gender) IN ('male','boy','ছাত্র') THEN 1 ELSE 0 END) AS boys,
                       SUM(CASE WHEN LOWER(gender) IN ('female','girl','ছাত্রী') THEN 1 ELSE 0 END) AS girls
                FROM students
                WHERE class_id = ?" . ($hasStatus ? " AND status='active'" : "") . "
                GROUP BY section_id";
        $st = $pdo->prepare($sql);
        $st->execute([$class_id]);
    }
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int)($row['section_id'] ?? 0);
        $studentCounts[$sid] = [
            'total' => (int)($row['total'] ?? 0),
            'boys'  => (int)($row['boys'] ?? 0),
            'girls' => (int)($row['girls'] ?? 0),
        ];
    }
} catch (Exception $e) { /* leave empty */ }

// Attendance by section for selected month
$attBySection = [];
try {
    $ast = $pdo->prepare("SELECT section_id,
                                 SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) AS present_count,
                                 SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
                                 COUNT(*) AS recorded
                          FROM attendance
                          WHERE class_id = ? AND date BETWEEN ? AND ?
                          GROUP BY section_id");
    $ast->execute([$class_id, $monthStart, $monthEndBound]);
    foreach($ast->fetchAll(PDO::FETCH_ASSOC) as $r){
        $sid = (int)($r['section_id'] ?? 0);
        $attBySection[$sid] = [
            'present' => (int)($r['present_count'] ?? 0),
            'absent'  => (int)($r['absent_count'] ?? 0),
            'recorded'=> (int)($r['recorded'] ?? 0),
        ];
    }
} catch(Exception $e){ /* ignore */ }

// Overall attendance for class (selected month)
$overall = ['present'=>0,'absent'=>0,'recorded'=>0];
try {
    $tot = $pdo->prepare("SELECT 
                SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
                COUNT(*) AS recorded
            FROM attendance
            WHERE class_id = ? AND date BETWEEN ? AND ?");
    $tot->execute([$class_id, $monthStart, $monthEndBound]);
    $r = $tot->fetch(PDO::FETCH_ASSOC);
    if ($r) { $overall = ['present'=>(int)$r['present_count'],'absent'=>(int)$r['absent_count'],'recorded'=>(int)$r['recorded']]; }
} catch (Exception $e) { /* ignore */ }

// Aggregate student totals for summary
$sumTotal = 0; $sumBoys = 0; $sumGirls = 0;
foreach ($sections as $sec) {
    $sid = (int)$sec['id'];
    $sumTotal += $studentCounts[$sid]['total'] ?? 0;
    $sumBoys  += $studentCounts[$sid]['boys'] ?? 0;
    $sumGirls += $studentCounts[$sid]['girls'] ?? 0;
}

function percent($num, $den){ return ($den > 0) ? round(($num/$den)*100, 1) : 0; }

?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শ্রেণির বিস্তারিত - <?php echo htmlspecialchars($class['name']); ?></title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        body, .main-sidebar, .nav-link { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }
        .stat-card { border-left: .25rem solid #4e73df; }
        .stat-card .card-body { padding:.85rem 1rem; }
        .stat-value { font-size: 1.3rem; font-weight: 700; }
        .stat-label { font-size: .85rem; color:#6c757d; }
        .table-sm td, .table-sm th { padding: .5rem; }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <?php include 'inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">শ্রেণির বিস্তারিত: <?php echo htmlspecialchars($class['name']); ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>classes.php">শ্রেণিসমূহ</a></li>
                            <li class="breadcrumb-item active">শ্রেণির বিস্তারিত</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <i class="icon fas fa-check"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <i class="icon fas fa-ban"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Summary Row -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-label">মোট শিক্ষার্থী</div>
                                <div class="stat-value text-primary"><?php echo (int)$sumTotal; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card" style="border-left-color:#1cc88a;">
                            <div class="card-body">
                                <div class="stat-label">ছাত্র</div>
                                <div class="stat-value text-success"><?php echo (int)$sumBoys; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card" style="border-left-color:#e83e8c;">
                            <div class="card-body">
                                <div class="stat-label">ছাত্রী</div>
                                <div class="stat-value" style="color:#e83e8c;">&nbsp;<?php echo (int)$sumGirls; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card" style="border-left-color:#36b9cc;">
                            <div class="card-body">
                                <div class="stat-label">নির্বাচিত মাসে উপস্থিতির হার</div>
                                <?php $pRate = percent($overall['present'], max(1, $overall['present'] + $overall['absent'])); ?>
                                <div class="stat-value text-info"><?php echo $pRate; ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Month Filter -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title">শাখাভিত্তিক বিবরণ</h3>
                        <form method="get" class="d-flex align-items-center" id="monthFilterForm">
                            <input type="hidden" name="id" value="<?php echo (int)$class_id; ?>">
                            <div class="btn-group btn-group-sm mr-2" role="group">
                                <button type="button" class="btn btn-default" id="prevMonthBtn"><i class="fa-solid fa-chevron-left"></i></button>
                                <button type="button" class="btn btn-default" id="nextMonthBtn"><i class="fa-solid fa-chevron-right"></i></button>
                            </div>
                            <label for="monthPicker" class="mr-2 mb-0 small text-muted">মাস</label>
                            <input type="month" name="month" id="monthPicker" value="<?php echo htmlspecialchars($selectedMonth); ?>" class="form-control form-control-sm" style="width:160px;">
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>শাখা</th>
                                        <th>শ্রেণি শিক্ষক</th>
                                        <th>মোট</th>
                                        <th>ছাত্র</th>
                                        <th>ছাত্রী</th>
                                        <th>উপস্থিত</th>
                                        <th>অনুপস্থিত</th>
                                        <th>উপস্থিতির হার</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if(empty($sections)): ?>
                                    <tr><td colspan="8" class="text-center text-muted">কোন শাখা পাওয়া যায়নি</td></tr>
                                <?php else: ?>
                                    <?php foreach($sections as $sec): 
                                        $sid = (int)$sec['id'];
                                        $counts = $studentCounts[$sid] ?? ['total'=>0,'boys'=>0,'girls'=>0];
                                        $att = $attBySection[$sid] ?? ['present'=>0,'absent'=>0,'recorded'=>0];
                                        $rate = percent($att['present'], max(1, $att['present'] + $att['absent']));
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sec['name']); ?></td>
                                        <td><?php echo $sec['section_teacher_name'] ? htmlspecialchars($sec['section_teacher_name']) : '<span class="text-muted">নির্ধারিত নয়</span>'; ?></td>
                                        <td class="text-center"><?php echo (int)$counts['total']; ?></td>
                                        <td class="text-center text-success"><?php echo (int)$counts['boys']; ?></td>
                                        <td class="text-center" style="color:#e83e8c;">&nbsp;<?php echo (int)$counts['girls']; ?></td>
                                        <td class="text-center text-success"><?php echo (int)$att['present']; ?></td>
                                        <td class="text-center text-danger"><?php echo (int)$att['absent']; ?></td>
                                        <td class="text-center text-info"><?php echo $rate; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2" class="text-right">মোট</th>
                                        <th class="text-center"><?php echo (int)$sumTotal; ?></th>
                                        <th class="text-center text-success"><?php echo (int)$sumBoys; ?></th>
                                        <th class="text-center" style="color:#e83e8c;">&nbsp;<?php echo (int)$sumGirls; ?></th>
                                        <th class="text-center text-success"><?php echo (int)$overall['present']; ?></th>
                                        <th class="text-center text-danger"><?php echo (int)$overall['absent']; ?></th>
                                        <th class="text-center text-info"><?php echo percent($overall['present'], max(1, $overall['present'] + $overall['absent'])); ?>%</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <?php include 'inc/footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
// Month navigation and auto-submit
(function(){
    function changeMonthOffset(offset){
        var mp = document.getElementById('monthPicker');
        if (!mp || !mp.value) return;
        var parts = mp.value.split('-');
        var y = parseInt(parts[0],10);
        var m = parseInt(parts[1],10);
        m += offset;
        while (m < 1) { m += 12; y -= 1; }
        while (m > 12) { m -= 12; y += 1; }
        var mm = String(m).padStart(2,'0');
        mp.value = y + '-' + mm;
        document.getElementById('monthFilterForm').submit();
    }
    document.addEventListener('DOMContentLoaded', function(){
        var prev = document.getElementById('prevMonthBtn');
        var next = document.getElementById('nextMonthBtn');
        if (prev) prev.addEventListener('click', function(){ changeMonthOffset(-1); });
        if (next) next.addEventListener('click', function(){ changeMonthOffset(1); });
        var mp = document.getElementById('monthPicker');
        if (mp) mp.addEventListener('change', function(){ document.getElementById('monthFilterForm').submit(); });
    });
})();
</script>
</body>
</html>
