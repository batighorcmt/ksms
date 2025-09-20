<?php
require_once '../../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../../login.php');
}

// Load current settings (default values)
$default_checkin_start = '08:00:00';
$default_checkin_end = '09:30:00';
$default_checkout_start = '13:00:00';
$default_checkout_end = '16:00:00';

// Try to load from DB (settings table)
$settings = $pdo->query("SELECT * FROM settings WHERE `key` LIKE 'teacher_attendance_%'")->fetchAll();
foreach ($settings as $row) {
    if ($row['key'] === 'teacher_attendance_checkin_start') $default_checkin_start = $row['value'];
    if ($row['key'] === 'teacher_attendance_checkin_end') $default_checkin_end = $row['value'];
    if ($row['key'] === 'teacher_attendance_checkout_start') $default_checkout_start = $row['value'];
    if ($row['key'] === 'teacher_attendance_checkout_end') $default_checkout_end = $row['value'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkin_start = $_POST['checkin_start'] ?? $default_checkin_start;
    $checkin_end = $_POST['checkin_end'] ?? $default_checkin_end;
    $checkout_start = $_POST['checkout_start'] ?? $default_checkout_start;
    $checkout_end = $_POST['checkout_end'] ?? $default_checkout_end;
    // Save to DB (upsert)
    $save = $pdo->prepare("REPLACE INTO settings (`key`, `value`) VALUES
        ('teacher_attendance_checkin_start', ?),
        ('teacher_attendance_checkin_end', ?),
        ('teacher_attendance_checkout_start', ?),
        ('teacher_attendance_checkout_end', ?)");
    $save->execute([$checkin_start, $checkin_end, $checkout_start, $checkout_end]);
    $_SESSION['success'] = 'Attendance settings updated!';
    header('Location: attendance_settings.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <title>Attendance Settings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>body, .main-sidebar, .nav-link { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../inc/header.php'; ?>
    <?php include '../inc/sidebar.php'; ?>
    <div class="content-wrapper p-3">
        <section class="content-header">
            <h1><i class="fas fa-cog"></i> শিক্ষক হাজিরা সেটিংস</h1>
        </section>
        <section class="content">
            <div class="container-fluid" style="max-width:500px;">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <form method="post" class="card p-4">
                    <div class="form-group">
                        <label>Check-in Start Time</label>
                        <input type="time" name="checkin_start" class="form-control" value="<?php echo htmlspecialchars($default_checkin_start); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Check-in End Time</label>
                        <input type="time" name="checkin_end" class="form-control" value="<?php echo htmlspecialchars($default_checkin_end); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Check-out Start Time</label>
                        <input type="time" name="checkout_start" class="form-control" value="<?php echo htmlspecialchars($default_checkout_start); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Check-out End Time</label>
                        <input type="time" name="checkout_end" class="form-control" value="<?php echo htmlspecialchars($default_checkout_end); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Save Settings</button>
                </form>
            </div>
        </section>
    </div>
    <?php include '../inc/footer.php'; ?>
</div>
</body>
</html>
