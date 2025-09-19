<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    header('Location: ../login.php');
    exit();
}

// Filter options
$class = isset($_GET['class']) ? $_GET['class'] : '';
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Fetch class list for filter (assuming a 'classes' table)
$classList = $pdo->query("SELECT DISTINCT class FROM exams ORDER BY class ASC")->fetchAll();
$yearList = $pdo->query("SELECT DISTINCT year FROM exams ORDER BY year DESC")->fetchAll();

// Build query
$sql = "SELECT * FROM exams WHERE 1";
$params = [];
if ($class) {
    $sql .= " AND class = ?";
    $params[] = $class;
}
if ($year) {
    $sql .= " AND year = ?";
    $params[] = $year;
}
$sql .= " ORDER BY year DESC, class ASC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$exams = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>পরীক্ষার তালিকা</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php if (hasRole(['super_admin'])) { include 'inc/sidebar.php'; } else if (hasRole(['teacher'])) { include '../teacher/inc/sidebar.php'; } ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">পরীক্ষার তালিকা</h1></div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <form class="form-inline mb-3" method="get">
                    <label class="mr-2">ক্লাস:</label>
                    <select name="class" class="form-control mr-2">
                        <option value="">সব ক্লাস</option>
                        <?php foreach($classList as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['class']); ?>" <?php if($class==$c['class']) echo 'selected'; ?>><?php echo htmlspecialchars($c['class']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="mr-2">বছর:</label>
                    <select name="year" class="form-control mr-2">
                        <option value="">সব বছর</option>
                        <?php foreach($yearList as $y): ?>
                            <option value="<?php echo htmlspecialchars($y['year']); ?>" <?php if($year==$y['year']) echo 'selected'; ?>><?php echo htmlspecialchars($y['year']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">ফিল্টার</button>
                </form>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <b>পরীক্ষার তালিকা</b>
                        <a href="add_exam.php" class="btn btn-success btn-sm"><i class="fa fa-plus"></i> নতুন পরীক্ষা</a>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-striped" id="examTable">
                            <thead>
                                <tr>
                                    <th>SL</th>
                                    <th>পরীক্ষার নাম</th>
                                    <th>ক্লাস</th>
                                    <th>বছর</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $sl=1; foreach($exams as $exam): ?>
                                <tr>
                                    <td><?php echo $sl++; ?></td>
                                    <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['class']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['year']); ?></td>
                                    <td>
                                        <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i></a>
                                        <a href="delete_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('আপনি কি নিশ্চিতভাবে মুছতে চান?');"><i class="fa fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(count($exams)==0): ?>
                                <tr><td colspan="5" class="text-center">কোনো পরীক্ষা পাওয়া যায়নি</td></tr>
                                <?php endif; ?>
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
