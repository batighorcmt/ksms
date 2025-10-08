<?php
require_once '../config.php';
// Filter
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : '';
// Fetch teachers
$teachers = $pdo->query("SELECT id, full_name FROM users WHERE role='teacher' AND status='active' ORDER BY full_name ASC")->fetchAll();

// Build attendance map for the selected month
$attendanceMap = [];
if ($month) {
    $start_date = $month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    $sql = "SELECT ta.*, u.full_name FROM teacher_attendance ta JOIN users u ON ta.teacher_id = u.id WHERE ta.date BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    if ($teacher_id) {
        $sql .= " AND ta.teacher_id = ?";
        $params[] = $teacher_id;
    }
    $sql .= " ORDER BY ta.date ASC, u.full_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $rec) {
        $attendanceMap[$rec['teacher_id']][$rec['date']] = $rec;
    }
}

// Prepare days of month
$days = [];
if ($month) {
    $start = strtotime($month . '-01');
    $end = strtotime(date('Y-m-t', $start));
    for ($d = $start; $d <= $end; $d += 86400) {
        $days[] = date('Y-m-d', $d);
    }
}

?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষক মাসিক হাজিরা রিপোর্ট</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }
    .table th, .table td { font-size: 0.95em; }
    .absent { background: #ffe5e5; color: #d00; }
    .present { background: #e6ffe6; color: #080; }
        .late { background: #e6f0ff; color: #0056b3; font-weight: bold; }
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
                    <div class="col-sm-6"><h1 class="m-0">শিক্ষক মাসিক হাজিরা রিপোর্ট</h1></div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <form class="form-inline mb-3" method="get">
                    <label class="mr-2">মাস:</label>
                    <input type="month" name="month" value="<?php echo htmlspecialchars($month); ?>" class="form-control mr-2">
                    <label class="mr-2">শিক্ষক:</label>
                    <select name="teacher_id" class="form-control mr-2">
                        <option value="">সকল শিক্ষক</option>
                        <?php foreach($teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php if($teacher_id==$t['id']) echo 'selected'; ?>><?php echo htmlspecialchars($t['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">ফিল্টার</button>
                </form>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <b>মাসিক হাজিরা শিট</b>
                        <button type="button" class="btn btn-success btn-sm" onclick="printReport()"><i class="fa fa-print"></i> প্রিন্ট</button>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-striped" id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>তারিখ</th>
                                    <?php foreach($teachers as $t): ?>
                                        <?php if(!$teacher_id || $teacher_id==$t['id']): ?>
                                            <th><?php echo htmlspecialchars($t['full_name']); ?></th>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($days as $day): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($day); ?></td>
                                    <?php foreach($teachers as $t): ?>
                                        <?php if(!$teacher_id || $teacher_id==$t['id']): ?>
                                            <?php $rec = $attendanceMap[$t['id']][$day] ?? null; ?>
                                            <?php
                                                $status = $rec['status'] ?? '';
                                                $cell_class = 'absent';
                                                $badge = '<span class="badge badge-danger">Absent</span>';
                                                if ($rec) {
                                                    if ($status === 'present') {
                                                        $cell_class = 'present';
                                                        $badge = '<span class="badge badge-success">Present</span>';
                                                    } elseif ($status === 'late') {
                                                        $cell_class = 'late';
                                                        $badge = '<span class="badge badge-info">Late</span>';
                                                    } else {
                                                        $cell_class = 'present';
                                                        $badge = '<span class="badge badge-success">Present</span>';
                                                    }
                                                }
                                            ?>
                                            <td class="<?php echo $cell_class; ?>">
                                                <?php if($rec): ?>
                                                    <b>ইন:</b> <?php echo htmlspecialchars($rec['check_in'] ?? ''); ?><br>
                                                    <b>আউট:</b> <?php echo htmlspecialchars($rec['check_out'] ?? ''); ?><br>
                                                    <?php echo $badge; ?>
                                                <?php else: ?>
                                                    <?php echo $badge; ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
function printReport() {
    var table = document.querySelector('.card-body').innerHTML;
    var header = '<div style="text-align:center;margin-bottom:10px;">' +
        '<h2 style="margin:0;font-family:SolaimanLipi,sans-serif;">শিক্ষক মাসিক হাজিরা রিপোর্ট</h2>' +
        '<div style="font-size:1.1em;">মাস: ' + (document.querySelector('input[name=month]').value || '') + '</div>' +
        '</div>';
    var footer = '<div style="margin-top:30px;text-align:right;font-size:0.95em;">Powered by KSMS</div>';
    var style = '<link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">'+
        '<style>body{font-family:\'SolaimanLipi\',sans-serif;color:#222} .table{width:100%;border-collapse:collapse} .table th,.table td{border:1px solid #e0e0e0;padding:8px} .badge{display:inline-block;padding:2px 7px;background:#e74c3c;color:#fff;border-radius:4px;margin:1px 1px;font-size:0.95em}</style>';
        var style = '<link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">'+
            '<style>body{font-family:\'SolaimanLipi\',sans-serif;color:#222} .table{width:100%;border-collapse:collapse} .table th,.table td{border:1px solid #e0e0e0;padding:8px} .badge{display:inline-block;padding:2px 7px;border-radius:4px;margin:1px 1px;font-size:0.95em} .badge-success{background:#28a745;color:#fff;} .badge-danger{background:#dc3545;color:#fff;} .badge-info{background:#007bff;color:#fff;} .absent{background:#ffe5e5;color:#d00;} .present{background:#e6ffe6;color:#080;} .late{background:#e6f0ff;color:#0056b3;font-weight:bold;}</style>';
    var win = window.open('', '', 'height=700,width=1000');
    win.document.write('<html><head><title>শিক্ষক মাসিক হাজিরা রিপোর্ট</title>');
    win.document.write(style);
    win.document.write('</head><body>');
    win.document.write(header);
    win.document.write(table);
    win.document.write(footer);
    win.document.write('<script>window.onload=function(){window.print();window.close();}<\/script>');
    win.document.write('</body></html>');
    win.document.close();
    win.focus();
}
</script>
</body>
</html>
