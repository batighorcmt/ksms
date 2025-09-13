<?php
require_once '../config.php';
require_once 'print_common.php';

if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    header('location: login.php'); exit();
}

$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();
$sections = $pdo->query("SELECT * FROM sections WHERE status='active' ORDER BY class_id, name ASC")->fetchAll();

$type = $_GET['type'] ?? 'absent'; // present, absent, not_recorded
$class_id = isset($_GET['class_id']) && $_GET['class_id'] !== 'all' ? intval($_GET['class_id']) : 'all';
$section_id = isset($_GET['section_id']) && $_GET['section_id'] !== '' ? intval($_GET['section_id']) : '';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$results = [];

// Build queries depending on type
if ($type === 'present') {
    $sql = "SELECT s.id, s.first_name, s.last_name, s.roll_number, c.name as class_name, sec.name as section_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE a.date = ? AND a.status = 'present'";
    $params = [$date];
} elseif ($type === 'absent') {
    $sql = "SELECT s.id, s.first_name, s.last_name, s.roll_number, c.name as class_name, sec.name as section_name, a.remarks
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE a.date = ? AND a.status = 'absent'";
    $params = [$date];
} else { // not_recorded
    // students who do not have attendance record for given date
    $sql = "SELECT s.id, s.first_name, s.last_name, s.roll_number, c.name as class_name, sec.name as section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.status='active' AND s.id NOT IN (SELECT student_id FROM attendance WHERE date = ?)";
    $params = [$date];
}

// Apply class/section filters
if ($class_id !== 'all') {
    $sql .= " AND s.class_id = ?"; $params[] = $class_id;
}
if ($section_id) { $sql .= " AND s.section_id = ?"; $params[] = $section_id; }

$sql .= " ORDER BY c.numeric_value ASC, sec.name ASC, s.roll_number ASC, s.first_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

$title_extra = ucfirst($type) . ' - ' . ($class_id === 'all' ? 'All Classes' : ($class_id ? ($pdo->query("SELECT name FROM classes WHERE id=".intval($class_id))->fetchColumn()) : ''));

$qs = http_build_query($_GET);
$printPageUrl = 'attendance_report_print.php' . ($qs ? ('?' . $qs) : '');

?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance Report</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body, .main-sidebar, .nav-link { font-family: 'SolaimanLipi', sans-serif; } .no-data{color:#6b7280}</style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">উপস্থিতি রিপোর্ট</h1></div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-body">
                        <form method="get" class="mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="form-row align-items-end">
                                        <div class="col-md-3 mb-2">
                                            <label class="small text-muted">ক্লাস</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-school"></i></span></div>
                                                <select id="class_id" name="class_id" class="form-control">
                                                    <option value="all">All Classes</option>
                                                    <?php foreach($classes as $c): ?><option value="<?php echo $c['id']; ?>" <?php if($class_id===$c['id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="small text-muted">শাখা</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-columns"></i></span></div>
                                                <select id="section_id" name="section_id" class="form-control">
                                                    <option value="">-- সব --</option>
                                                    <?php foreach($sections as $s): ?><option data-class="<?php echo $s['class_id']; ?>" value="<?php echo $s['id']; ?>" <?php if($section_id===$s['id']) echo 'selected'; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="small text-muted">টাইপ</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-list"></i></span></div>
                                                <select name="type" class="form-control">
                                                    <option value="present" <?php if($type==='present') echo 'selected'; ?>>Present</option>
                                                    <option value="absent" <?php if($type==='absent') echo 'selected'; ?>>Absent</option>
                                                    <option value="not_recorded" <?php if($type==='not_recorded') echo 'selected'; ?>>Not Recorded</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="small text-muted">তারিখ</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div>
                                                <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date); ?>" />
                                            </div>
                                        </div>
                                        <div class="col-md-2 text-right mb-2">
                                            <button class="btn btn-primary btn-block mb-2" type="submit"><i class="fas fa-search mr-1"></i> খুঁজুন</button>
                                            <a class="btn btn-outline-secondary btn-block" id="printBtn" href="#"><i class="fas fa-print mr-1"></i> প্রিন্ট</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <?php if(empty($results)): ?>
                            <div class="alert alert-info">ডেটাতে কোনো রেজাল্ট নেই।</div>
                        <?php else: ?>
                            <?php if(isset($_GET['print'])): echo print_header($pdo, 'তারিখ: '.htmlspecialchars($date).' | টাইপ: '.htmlspecialchars($type)); endif; ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr><th>#</th><th>নাম</th><th>ক্লাস</th><th>শাখা</th><th>রোল</th><?php if($type==='absent') echo '<th>নোট</th>'; ?></tr>
                                    </thead>
                                    <tbody>
                                        <?php $i=1; foreach($results as $r): ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($r['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($r['section_name']); ?></td>
                                                <td><?php echo htmlspecialchars($r['roll_number']); ?></td>
                                                <?php if($type==='absent'): ?><td><?php echo htmlspecialchars($r['remarks'] ?? ''); ?></td><?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if(isset($_GET['print'])): echo print_footer(); else: ?><div class="mt-2 text-right"><a id="openPrint" class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars($printPageUrl); ?>" target="_blank">প্রিন্ট দেখুন</a></div><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include 'inc/footer.php'; ?>
</div>

<!-- AdminLTE & Bootstrap JS (ensure these are loaded so pushmenu works) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
    // filter sections by class
    $('#section_id option').each(function(){ var cls = $(this).data('class'); if(!cls) return; $(this).hide(); });
    $('#class_id').on('change', function(){ var cls = $(this).val(); if(cls === 'all'){ $('#section_id option').each(function(){ $(this).show(); }); $('#section_id').val(''); return; } $('#section_id option').each(function(){ var opt = $(this); if(opt.data('class') == cls) opt.show(); else if(!opt.data('class')) opt.show(); else opt.hide(); }); $('#section_id').val(''); });
    // print button - open print-only page
    $('#printBtn').on('click', function(e){
        e.preventDefault();
        var qs = '<?php echo http_build_query($_GET); ?>';
        var base = 'attendance_report_print.php';
        var url = base + (qs ? ('?' + qs) : '');
        window.open(url, '_blank');
    });

    // ensure sidebar pushmenu toggling works (AdminLTE)
    if (typeof $.AdminLTE === 'undefined' && typeof $.fn.pushMenu === 'function') {
        // older AdminLTE plugin
        $('[data-widget="pushmenu"]').on('click', function(){ $('body').toggleClass('sidebar-collapse'); });
    } else if (typeof $.AdminLTE !== 'undefined' && $.AdminLTE.pushMenu) {
        // nothing to do, plugin present
    } else {
        // fallback: allow manual toggle class on body when toggle button clicked
        $(document).on('click', '[data-widget="pushmenu"]', function(){ $('body').toggleClass('sidebar-collapse'); });
    }
</script>
</body>
</html>
