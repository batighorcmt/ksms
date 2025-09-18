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

// Build query
$sql = "SELECT ta.*, u.full_name FROM teacher_attendance ta JOIN users u ON ta.teacher_id = u.id WHERE 1";
$params = [];
if ($teacher_id) {
    $sql .= " AND ta.teacher_id = ?";
    $params[] = $teacher_id;
}
if ($date) {
    $sql .= " AND ta.date = ?";
    $params[] = $date;
}
$sql .= " ORDER BY ta.date DESC, ta.check_in ASC, u.full_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

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
    <style>body { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }</style>
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
                    <div class="card-header"><b>হাজিরা তালিকা</b></div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-striped">
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
                                <?php foreach($records as $rec): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rec['date']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['check_in']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['check_out']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['status']); ?></td>
                                    <td>
                                        <?php if($rec['check_in_photo']): ?>
                                            <a href="../<?php echo htmlspecialchars($rec['check_in_photo']); ?>" target="_blank"><img src="../<?php echo htmlspecialchars($rec['check_in_photo']); ?>" width="40"></a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($rec['check_out_photo']): ?>
                                            <a href="../<?php echo htmlspecialchars($rec['check_out_photo']); ?>" target="_blank"><img src="../<?php echo htmlspecialchars($rec['check_out_photo']); ?>" width="40"></a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $locs = [];
                                        if (!empty($rec['check_in_location'])) {
                                            $loc = explode(',', $rec['check_in_location']);
                                            if(count($loc) == 2) {
                                                $lat = trim($loc[0]);
                                                $lng = trim($loc[1]);
                                                $url = "https://maps.google.com/?q=$lat,$lng";
                                                $locs[] = '<a href="'.htmlspecialchars($url).'" target="_blank">চেক-ইন</a>';
                                            }
                                        }
                                        if (!empty($rec['check_out_location'])) {
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
</body>
</html>
