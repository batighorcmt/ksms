<?php
// শিক্ষাবর্ষ ব্যবস্থাপনা (Academic Year Management)
require_once '../../config.php';

// Auth: Only super_admin can access
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../../index.php');
}

// Handle add, update, delete, and set current year
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_year'])) {
        $year = intval($_POST['year']);
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        // If start_date is required, provide a default if not set
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime($year.'-01-01'));
        }
        // If end_date is required, provide a default if not set
        if (!$end_date) {
            $end_date = date('Y-m-d', strtotime($year.'-12-31'));
        }
        $stmt = $pdo->prepare("INSERT INTO academic_years (year, is_current, start_date, end_date) VALUES (?, 0, ?, ?)");
        $stmt->execute([$year, $start_date, $end_date]);
    }
    if (isset($_POST['update_year'])) {
        $id = intval($_POST['id']);
        $year = intval($_POST['year']);
        $stmt = $pdo->prepare("UPDATE academic_years SET year = ? WHERE id = ?");
        $stmt->execute([$year, $id]);
    }
    if (isset($_POST['delete_year'])) {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM academic_years WHERE id = ?");
        $stmt->execute([$id]);
    }
    if (isset($_POST['set_current'])) {
        $id = intval($_POST['id']);
        // Reset all to not current
        $pdo->query("UPDATE academic_years SET is_current = 0");
        // Set selected year as current
        $stmt = $pdo->prepare("UPDATE academic_years SET is_current = 1 WHERE id = ?");
        $stmt->execute([$id]);
    }
    header('Location: academic_year_management.php');
    exit;
}

// Fetch all academic years
$years = $pdo->query("SELECT * FROM academic_years ORDER BY year DESC")->fetchAll();
?>


<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>শিক্ষাবর্ষ ব্যবস্থাপনা</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        body { font-family: SolaimanLipi, Arial, sans-serif; background-color: #f8f9fc; }
        .card { box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); border: none; border-radius: 10px; }
        .card-header { border-radius: 10px 10px 0 0 !important; font-weight: 700; }
        .form-control, .form-select { border-radius: 6px; padding: 10px 15px; border: 1px solid #d1d3e2; }
        .form-control:focus, .form-select:focus { border-color: #4e73df; box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25); }
        .btn-primary { background-color: #4e73df; border-color: #4e73df; border-radius: 6px; padding: 10px 20px; font-weight: 600; }
        .btn-primary:hover { background-color: #3a5fc8; border-color: #3a5fc8; }
        .required-label::after { content: '*'; color: red; margin-left: 3px; }
        .input-group-text { background-color: #eaecf4; border: 1px solid #d1d3e2; }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include_once '../inc/header.php'; ?>
    <?php include_once '../inc/sidebar.php'; ?>
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>শিক্ষাবর্ষ ব্যবস্থাপনা</h1>
                    </div>
                </div>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <div class="card card-primary card-outline">
                    <div class="card-body">
                        <form method="post" class="row g-2 mb-3">
                            <div class="col-md-3 col-6">
                                <label for="year" class="form-label required-label">নতুন শিক্ষাবর্ষ (Year)</label>
                                <input type="number" name="year" id="year" class="form-control" min="1900" max="2100" required>
                            </div>
                            <div class="col-md-3 col-6">
                                <label for="start_date" class="form-label">শুরুর তারিখ</label>
                                <input type="date" name="start_date" id="start_date" class="form-control">
                            </div>
                            <div class="col-md-3 col-6">
                                <label for="end_date" class="form-label">শেষ তারিখ</label>
                                <input type="date" name="end_date" id="end_date" class="form-control">
                            </div>
                            <div class="col-md-2 col-4 d-flex align-items-end">
                                <button type="submit" name="add_year" class="btn btn-success w-100">যুক্ত করুন</button>
                            </div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>শিক্ষাবর্ষ</th>
                                        <th>বর্তমান?</th>
                                        <th>কর্ম</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($years as $y): ?>
                                    <tr>
                                        <form method="post">
                                            <td>
                                                <input type="hidden" name="id" value="<?php echo $y['id']; ?>">
                                                <input type="number" name="year" value="<?php echo $y['year']; ?>" class="form-control" min="1900" max="2100" required>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($y['is_current']): ?>
                                                    <span class="badge bg-primary">বর্তমান</span>
                                                <?php else: ?>
                                                    <button type="submit" name="set_current" class="btn btn-outline-primary btn-sm">বর্তমান করুন</button>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button type="submit" name="update_year" class="btn btn-warning btn-sm">আপডেট</button>
                                                <button type="submit" name="delete_year" class="btn btn-danger btn-sm" onclick="return confirm('আপনি কি নিশ্চিত?');">ডিলিট</button>
                                            </td>
                                        </form>
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
    <?php include_once '../inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
