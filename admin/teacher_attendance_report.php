<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    header('Location: ../login.php');
    exit();
}

// Filter
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : '';

// Fetch teachers
$teachers = $pdo->query("SELECT id, full_name FROM users WHERE role='teacher' ORDER BY full_name ASC")->fetchAll();


// Build attendance map for the selected date
$attendanceMap = [];
if ($date) {
    $sql = "SELECT ta.*, u.full_name FROM teacher_attendance ta JOIN users u ON ta.teacher_id = u.id WHERE ta.date = ?";
    $params = [$date];
    if ($teacher_id) {
        $sql .= " AND ta.teacher_id = ?";
        $params[] = $teacher_id;
    }
    $sql .= " ORDER BY ta.check_in ASC, u.full_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $rec) {
        $attendanceMap[$rec['teacher_id']] = $rec;
    }
}

?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষক হাজিরা রিপোর্ট</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }
    .img-preview-modal { display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.7); align-items:center; justify-content:center; }
    .img-preview-modal img { max-width:90vw; max-height:90vh; border:5px solid #fff; border-radius:8px; }
    .img-preview-modal .close { position:absolute; top:20px; right:40px; color:#fff; font-size:2rem; cursor:pointer; }
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
                    <div class="col-sm-6"><h1 class="m-0">শিক্ষক হাজিরা রিপোর্ট</h1></div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <form class="form-inline mb-3" method="get">
                    <label class="mr-2">তারিখ:</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" class="form-control mr-2">
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
                        <b>হাজিরা তালিকা</b>
                        <button type="button" class="btn btn-success btn-sm" onclick="printReport()"><i class="fa fa-print"></i> প্রিন্ট</button>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-striped" id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>তারিখ</th>
                                    <th>শিক্ষক</th>
                                    <th>চেক-ইন</th>
                                    <th>চেক-আউট</th>
                                    <th>স্ট্যাটাস</th>
                                    <th>চেক-ইন ছবি</th>
                                    <th>চেক-আউট ছবি</th>
                                    <th>লোকেশন</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($teachers as $t) {
                                    // If filtering by teacher, skip others
                                    if ($teacher_id && $teacher_id != $t['id']) continue;
                                    $rec = $attendanceMap[$t['id']] ?? null;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($date); ?></td>
                                    <td><?php echo htmlspecialchars($t['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['check_in'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($rec['check_out'] ?? ''); ?></td>
                                    <td>
                                        <?php
                                        if ($rec) {
                                            echo htmlspecialchars($rec['status'] ?? '');
                                        } else {
                                            echo '<span class="badge badge-danger">Absent</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if($rec && $rec['check_in_photo']): ?>
                                            <img src="../<?php echo htmlspecialchars($rec['check_in_photo']); ?>" width="40" class="img-thumb" style="cursor:pointer" onclick="showImgPreview('../<?php echo htmlspecialchars($rec['check_in_photo']); ?>')">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($rec && $rec['check_out_photo']): ?>
                                            <img src="../<?php echo htmlspecialchars($rec['check_out_photo']); ?>" width="40" class="img-thumb" style="cursor:pointer" onclick="showImgPreview('../<?php echo htmlspecialchars($rec['check_out_photo']); ?>')">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $locs = [];
                                        if ($rec && !empty($rec['check_in_location'])) {
                                            $loc = explode(',', $rec['check_in_location']);
                                            if(count($loc) == 2) {
                                                $lat = trim($loc[0]);
                                                $lng = trim($loc[1]);
                                                $url = "https://maps.google.com/?q=$lat,$lng";
                                                $locs[] = '<a href="'.htmlspecialchars($url).'" target="_blank">চেক-ইন</a>';
                                            }
                                        }
                                        if ($rec && !empty($rec['check_out_location'])) {
                                            $loc = explode(',', $rec['check_out_location']);
                                            if(count($loc) == 2) {
                                                $lat = trim($loc[0]);
                                                $lng = trim($loc[1]);
                                                $url = "https://maps.google.com/?q=$lat,$lng";
                                                $locs[] = '<a href="'.htmlspecialchars($url).'" target="_blank">চেক-আউট</a>';
                                            }
                                        }
                                        echo implode(' | ', $locs);
                                        ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php include 'inc/footer.php'; ?>
    <!-- Image Preview Modal -->
    <div class="img-preview-modal" id="imgPreviewModal" onclick="hideImgPreview()">
        <span class="close" onclick="hideImgPreview(event)">&times;</span>
        <img id="imgPreview" src="" alt="Preview">
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
// Image preview popup
function showImgPreview(src) {
    var modal = document.getElementById('imgPreviewModal');
    var img = document.getElementById('imgPreview');
    img.src = src;
    modal.style.display = 'flex';
}
function hideImgPreview(e) {
    if (!e || e.target === this || e.target.classList.contains('close')) {
        document.getElementById('imgPreviewModal').style.display = 'none';
        document.getElementById('imgPreview').src = '';
    }
}
// Print button
function printReport() {
    // Use print_common.php for print styling and add header/footer like lesson evaluation print
    var table = document.querySelector('.card-body').innerHTML;
    var header = '';
    var footer = '';
    // Try to fetch header/footer from print_common.php if available
    // Fallback: simple header/footer
    header = '<div style="text-align:center;margin-bottom:10px;">' +
        '<h2 style="margin:0;font-family:SolaimanLipi,sans-serif;">শিক্ষক হাজিরা রিপোর্ট</h2>' +
        '<div style="font-size:1.1em;">তারিখ: ' + (document.querySelector('input[name=date]').value || '') + '</div>' +
        '</div>';
    footer = '<div style="margin-top:30px;text-align:right;font-size:0.95em;">Powered by KSMS</div>';
    var style = '<link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">'+
        '<style>body{font-family:\'SolaimanLipi\',sans-serif;color:#222} .table{width:100%;border-collapse:collapse} .table th,.table td{border:1px solid #e0e0e0;padding:8px} .badge{display:inline-block;padding:2px 7px;background:#e74c3c;color:#fff;border-radius:4px;margin:1px 1px;font-size:0.95em}</style>';
    var win = window.open('', '', 'height=700,width=1000');
    win.document.write('<html><head><title>শিক্ষক হাজিরা রিপোর্ট</title>');
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
