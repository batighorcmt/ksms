<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['teacher'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch teacher info
// Fetch teacher info (add all possible fields)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'teacher'");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();
 
// Subjects taught by this teacher
$subjects = $pdo->prepare("SELECT sub.name, c.name as class_name, s.name as section_name FROM routines r JOIN subjects sub ON r.subject_id = sub.id JOIN classes c ON r.class_id = c.id LEFT JOIN sections s ON r.section_id = s.id WHERE r.teacher_id = ? GROUP BY sub.id, c.id, s.id");
$subjects->execute([$user_id]);
$subject_list = $subjects->fetchAll();

// Class teacher for (attendance recorder) - only class name, as section_id does not exist
$class_teacher_stmt = $pdo->prepare("SELECT c.name as class_name FROM class_teachers ct JOIN classes c ON ct.class_id = c.id WHERE ct.teacher_id = ?");
$class_teacher_stmt->execute([$user_id]);
$class_teacher_for = $class_teacher_stmt->fetchAll();

// Attendance summary
$att_stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(status='present') as present, SUM(status='late') as late, SUM(status='early') as early, SUM(status='absent') as absent FROM teacher_attendance WHERE teacher_id = ?");
$att_stmt->execute([$user_id]);
$att_summary = $att_stmt->fetch();

// Recent attendance records
// Attendance month filter
$att_month = isset($_GET['att_month']) ? $_GET['att_month'] : date('Y-m');
$month_start = $att_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));
$recent_att = $pdo->prepare("SELECT * FROM teacher_attendance WHERE teacher_id = ? AND date BETWEEN ? AND ? ORDER BY date DESC, id DESC");
$recent_att->execute([$user_id, $month_start, $month_end]);
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Profile</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
    body, .main-sidebar, .nav-link { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }
        .card { border-radius: 18px; box-shadow: 0 4px 24px rgba(60,72,88,0.10); border: none; margin-bottom: 24px; }
        .card-header { background: linear-gradient(90deg, #4f8cff 0%, #6ed6ff 100%); color: #fff; border-radius: 18px 18px 0 0; font-size: 1.15em; font-weight: 600; letter-spacing: 0.5px; }
        .card-body { background: #fff; border-radius: 0 0 18px 18px; }
        .table { background: #fff; border-radius: 12px; overflow: hidden; }
        .table th { background: #eaf4ff; color: #2c3e50; font-weight: 600; }
        .table td { color: #34495e; }
        .table-bordered th, .table-bordered td { border: 1px solid #e0e6ed !important; }
        .btn-primary, .btn-info, .btn-success { border-radius: 8px; font-weight: 500; }
        .btn-primary { background: linear-gradient(90deg, #4f8cff 0%, #6ed6ff 100%); border: none; }
        .btn-info { background: linear-gradient(90deg, #36cfc9 0%, #6ed6ff 100%); border: none; }
        .btn-success { background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%); border: none; }
        .alert-success { background: #eafbe7; color: #27ae60; border-radius: 8px; }
        .alert-danger { background: #ffeaea; color: #e74c3c; border-radius: 8px; }
        .form-control { border-radius: 8px; border: 1px solid #e0e6ed; }
        .card-header b, .card-header { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }
        .profile-label { color: #4f8cff; font-weight: 600; }
        @media (max-width: 767.98px) {
            .content-wrapper, .container-fluid, .card { padding: 0 !important; }
            .table-responsive { overflow-x: auto; }
            .table th, .table td { font-size: 0.95em; white-space: nowrap; }
            .card { margin-bottom: 16px; }
        }
    </style>
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
                                <div class="row align-items-center mb-3">
                                    <div class="col-4 col-sm-3 text-center">
                                        <?php
                                            // Use absolute URL to uploaded user photos; fallback to UI Avatars if missing
                                            $profile_img = !empty($teacher['photo'])
                                                ? BASE_URL . 'uploads/teachers/' . $teacher['photo']
                                                : 'https://ui-avatars.com/api/?name=' . urlencode($teacher['full_name'] ?? 'Teacher') . '&background=4f8cff&color=fff&size=128';
                                        ?>
                                        <img src="<?php echo htmlspecialchars($profile_img); ?>" alt="Profile" class="img-fluid rounded-circle border" style="max-width:110px;max-height:110px;">
                                    </div>
                                    <div class="col-8 col-sm-9">
                                        <h4 style="margin-bottom:8px;font-weight:600;color:#4f8cff;"><?php echo htmlspecialchars($teacher['full_name']); ?></h4>
                                        <div style="font-size:1.05em;color:#555;">শিক্ষক আইডি: <?php echo htmlspecialchars($teacher['id']); ?></div>
                                    </div>
                                </div>
                                <div class="row mb-2"><div class="col-5 profile-label">ইমেইল:</div><div class="col-7"> <?php echo htmlspecialchars($teacher['email'] ?? ''); ?> </div></div>
                                <div class="row mb-2"><div class="col-5 profile-label">মোবাইল:</div><div class="col-7"> <?php echo htmlspecialchars($teacher['phone'] ?? ''); ?> </div></div>
                                <div class="row mb-2"><div class="col-5 profile-label">ঠিকানা:</div><div class="col-7"> <?php echo htmlspecialchars($teacher['address'] ?? ''); ?> </div></div>
                                <div class="row mb-2"><div class="col-5 profile-label">জন্ম তারিখ:</div><div class="col-7"> <?php echo htmlspecialchars($teacher['dob'] ?? ''); ?> </div></div>
                                <div class="row mb-2"><div class="col-5 profile-label">যোগদানের তারিখ:</div><div class="col-7"> <?php echo htmlspecialchars($teacher['join_date'] ?? ''); ?> </div></div>
                                <div class="row mb-2"><div class="col-5 profile-label">লিঙ্গ:</div><div class="col-7"> <?php echo htmlspecialchars($teacher['gender'] ?? ''); ?> </div></div>
                                <div class="row mb-2"><div class="col-5 profile-label">শিক্ষাগত যোগ্যতা:</div><div class="col-7"> <?php echo htmlspecialchars($teacher['education'] ?? ''); ?> </div></div>
                                <div class="row mb-2"><div class="col-5 profile-label">জাতীয় পরিচয়পত্র:</div><div class="col-7"> <?php echo htmlspecialchars($teacher['nid'] ?? ''); ?> </div></div>
                                <div class="row mb-2"><div class="col-5 profile-label">রক্তের গ্রুপ:</div><div class="col-7"> <?php echo htmlspecialchars($teacher['blood_group'] ?? ''); ?> </div></div>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-header"><b>পাসওয়ার্ড পরিবর্তন</b></div>
                            <div class="card-body">
                                <?php if($change_success): ?><div class="alert alert-success mb-2"><?php echo $change_success; ?></div><?php endif; ?>
                                <?php if($change_error): ?><div class="alert alert-danger mb-2"><?php echo $change_error; ?></div><?php endif; ?>
                                <form method="post">
                                    <div class="form-group mb-2">
                                        <label class="profile-label">পুরনো পাসওয়ার্ড</label>
                                        <input type="password" name="old_password" class="form-control" required>
                                    </div>
                                    <div class="form-group mb-2">
                                        <label class="profile-label">নতুন পাসওয়ার্ড</label>
                                        <input type="password" name="new_password" class="form-control" required>
                                    </div>
                                    <div class="form-group mb-2">
                                        <label class="profile-label">নতুন পাসওয়ার্ড (পুনরায়)</label>
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
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width:40%">বিষয়</th>
                                                    <th style="width:30%">শ্রেণি</th>
                                                    <th style="width:30%">শাখা</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($subject_list as $sub): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($sub['subject_name'] ?? $sub['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($sub['class_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($sub['section_name'] ?? ''); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
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
                                        <li><?php echo htmlspecialchars($ct['class_name']); ?></li>
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
                                <form method="get" class="form-inline mb-2">
                                    <label class="mr-2">মাস:</label>
                                    <input type="month" name="att_month" value="<?php echo htmlspecialchars($att_month); ?>" class="form-control mr-2">
                                    <button type="submit" class="btn btn-info btn-sm">দেখুন</button>
                                </form>
                                <p>মোট: <?php echo (int)$att_summary['total']; ?> | উপস্থিত: <?php echo (int)$att_summary['present']; ?> | দেরি: <?php echo (int)$att_summary['late']; ?> | আগেভাগে: <?php echo (int)$att_summary['early']; ?> | অনুপস্থিত: <?php echo (int)$att_summary['absent']; ?></p>
                                <table class="table table-bordered table-sm">
                                    <thead><tr><th>তারিখ</th><th>চেক-ইন</th><th>চেক-আউট</th><th>স্ট্যাটাস</th></tr></thead>
                                    <tbody>
                                    <?php foreach($recent_attendance as $a): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($a['date'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($a['check_in'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($a['check_out'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($a['status'] ?? ''); ?></td>
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
