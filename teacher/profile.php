<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['teacher'])) {
    header('Location: ../login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// Fetch teacher info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'teacher'");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();

// Subjects taught by this teacher
$subjects = $pdo->prepare("SELECT sub.name, c.name as class_name FROM routines r JOIN subjects sub ON r.subject_id = sub.id JOIN classes c ON r.class_id = c.id WHERE r.teacher_id = ? GROUP BY sub.id, c.id");
$subjects->execute([$user_id]);
$subject_list = $subjects->fetchAll();

// Class teacher for (attendance recorder)
$class_teacher_stmt = $pdo->prepare("SELECT c.name as class_name, s.name as section_name FROM class_teachers ct JOIN classes c ON ct.class_id = c.id JOIN sections s ON ct.section_id = s.id WHERE ct.teacher_id = ?");
$class_teacher_stmt->execute([$user_id]);
$class_teacher_for = $class_teacher_stmt->fetchAll();

// Attendance summary
$att_stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(status='present') as present, SUM(status='late') as late, SUM(status='early') as early, SUM(status='absent') as absent FROM teacher_attendance WHERE teacher_id = ?");
$att_stmt->execute([$user_id]);
$att_summary = $att_stmt->fetch();

// Recent attendance records
$recent_att = $pdo->prepare("SELECT * FROM teacher_attendance WHERE teacher_id = ? ORDER BY date DESC, id DESC LIMIT 10");
$recent_att->execute([$user_id]);
$recent_attendance = $recent_att->fetchAll();

// Password change
$change_success = $change_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (!$old || !$new || !$confirm) {
        $change_error = 'সব ফিল্ড পূরণ করুন।';
    } elseif ($new !== $confirm) {
        $change_error = 'নতুন পাসওয়ার্ড মিলছে না!';
    } elseif (!password_verify($old, $teacher['password'])) {
        $change_error = 'পুরনো পাসওয়ার্ড ভুল!';
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $user_id]);
        $change_success = 'পাসওয়ার্ড পরিবর্তন হয়েছে!';
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <title>Teacher Profile</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body, .main-sidebar, .nav-link { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../admin/inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">প্রোফাইল</h1></div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header"><b>ব্যক্তিগত তথ্য</b></div>
                            <div class="card-body">
                                <p><b>নাম:</b> <?php echo htmlspecialchars($teacher['full_name']); ?></p>
                                <p><b>ইমেইল:</b> <?php echo htmlspecialchars($teacher['email']); ?></p>
                                <p><b>মোবাইল:</b> <?php echo htmlspecialchars($teacher['phone'] ?? ''); ?></p>
                                <p><b>ঠিকানা:</b> <?php echo htmlspecialchars($teacher['address'] ?? ''); ?></p>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-header"><b>পাসওয়ার্ড পরিবর্তন</b></div>
                            <div class="card-body">
                                <?php if($change_success): ?><div class="alert alert-success"><?php echo $change_success; ?></div><?php endif; ?>
                                <?php if($change_error): ?><div class="alert alert-danger"><?php echo $change_error; ?></div><?php endif; ?>
                                <form method="post">
                                    <div class="form-group">
                                        <label>পুরনো পাসওয়ার্ড</label>
                                        <input type="password" name="old_password" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>নতুন পাসওয়ার্ড</label>
                                        <input type="password" name="new_password" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>নতুন পাসওয়ার্ড (পুনরায়)</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-primary">পরিবর্তন করুন</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header"><b>যে বিষয়সমূহ পড়ান</b></div>
                            <div class="card-body">
                                <?php if($subject_list): ?>
                                    <ul>
                                    <?php foreach($subject_list as $sub): ?>
                                        <li><?php echo htmlspecialchars($sub['subject_name'] ?? $sub['name']); ?> (<?php echo htmlspecialchars($sub['class_name']); ?>)</li>
                                    <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p>কোনো বিষয় পাওয়া যায়নি।</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-header"><b>ক্লাস টিচার (হাজিরা রেকর্ডার)</b></div>
                            <div class="card-body">
                                <?php if($class_teacher_for): ?>
                                    <ul>
                                    <?php foreach($class_teacher_for as $ct): ?>
                                        <li><?php echo htmlspecialchars($ct['class_name']); ?> - <?php echo htmlspecialchars($ct['section_name']); ?></li>
                                    <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p>কোনো ক্লাস পাওয়া যায়নি।</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-header"><b>নিজস্ব হাজিরা রিপোর্ট</b></div>
                            <div class="card-body">
                                <p>মোট: <?php echo (int)$att_summary['total']; ?> | উপস্থিত: <?php echo (int)$att_summary['present']; ?> | দেরি: <?php echo (int)$att_summary['late']; ?> | আগেভাগে: <?php echo (int)$att_summary['early']; ?> | অনুপস্থিত: <?php echo (int)$att_summary['absent']; ?></p>
                                <table class="table table-bordered table-sm">
                                    <thead><tr><th>তারিখ</th><th>চেক-ইন</th><th>চেক-আউট</th><th>স্ট্যাটাস</th></tr></thead>
                                    <tbody>
                                    <?php foreach($recent_attendance as $a): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($a['date']); ?></td>
                                            <td><?php echo htmlspecialchars($a['check_in']); ?></td>
                                            <td><?php echo htmlspecialchars($a['check_out']); ?></td>
                                            <td><?php echo htmlspecialchars($a['status']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php include '../admin/inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
