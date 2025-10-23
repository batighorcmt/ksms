<?php
// সার্টিফিকেট তালিকা দেখার পৃষ্ঠা
require_once '../../config.php';
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('../../index.php');
}

// Fetch academic years and classes
$years = $pdo->query("SELECT * FROM academic_years ORDER BY year DESC")->fetchAll();
$classes = $pdo->query("SELECT id, name FROM classes ORDER BY numeric_value ASC, name ASC")->fetchAll();
$selected_year_id = isset($_GET['year_id']) ? intval($_GET['year_id']) : null;
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : null;

// Fetch students with certificate info
$students = [];
if ($selected_year_id && $selected_class_id) {
    // Enrollment-aware fetch: join students_enrollment for class/year/roll
    $stmt = $pdo->prepare("SELECT 
            s.id,
            s.first_name,
            s.last_name,
            s.student_id,
            se.roll_number AS roll_number,
            s.photo,
            c.gpa,
            c.certificate_id,
            c.issue_date
        FROM students s
        JOIN students_enrollment se ON se.student_id = s.id
        JOIN five_pass_certificate_info c ON s.id = c.student_id
        WHERE se.academic_year_id = ?
          AND se.class_id = ?
          AND (se.status = 'active' OR se.status IS NULL)
        ORDER BY se.roll_number ASC, s.first_name ASC, s.last_name ASC");
    $stmt->execute([$selected_year_id, $selected_class_id]);
    $students = $stmt->fetchAll();
}

?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>সার্টিফিকেট তালিকা</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        body { font-family: 'SolaimanLipi', Arial, sans-serif; background: #f5f5f5; }
        .cert-container { max-width: 1000px; margin: 40px auto; background: #fff; box-shadow: 0 0 10px #ccc; padding: 30px; border-radius: 8px; }
        h2 { text-align: center; margin-bottom: 25px; color: #006400; }
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../../admin/inc/header.php'; ?>
    <?php include '../../admin/inc/sidebar.php'; ?>
    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid py-4">
                <div class="row justify-content-center">
                    <div class="col-12 col-md-12 col-lg-10">
                        <div class="cert-container">
                            <h2>পঞ্চম শ্রেণি পাস সার্টিফিকেট তালিকা</h2>
                            <form method="get" class="mb-4">
                                <div class="row g-2 align-items-end">
                                    <div class="col-12 col-md-5">
                                        <label for="year_id" class="form-label">শিক্ষাবর্ষ নির্বাচন করুন:</label>
                                        <select name="year_id" id="year_id" class="form-control" required>
                                            <option value="">নির্বাচন করুন</option>
                                            <?php foreach ($years as $y): ?>
                                                <option value="<?php echo $y['id']; ?>" <?php echo ($selected_year_id == $y['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($y['year']); ?><?php echo ($y['is_current'] ? ' (বর্তমান)' : ''); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label for="class_id" class="form-label">শ্রেণি নির্বাচন করুন:</label>
                                        <select name="class_id" id="class_id" class="form-control" required>
                                            <option value="">নির্বাচন করুন</option>
                                            <?php foreach ($classes as $c): ?>
                                                <option value="<?php echo $c['id']; ?>" <?php echo ($selected_class_id == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <button type="submit" class="btn btn-primary w-100">ফিল্টার করুন</button>
                                    </div>
                                </div>
                            </form>
                            <?php if ($selected_year_id && $selected_class_id && !empty($students)): ?>
                                <div class="mb-3 text-end">
                                    <a href="five_pass_certificate_print_all.php?year_id=<?php echo $selected_year_id; ?>&class_id=<?php echo $selected_class_id; ?>" target="_blank" class="btn btn-success"><i class="fa fa-print"></i> সকল শিক্ষার্থীর সার্টিফিকেট প্রিন্ট</a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>নাম</th>
                                                <th>রোল</th>
                                                <th>জিপিএ</th>
                                                <th>সার্টিফিকেট আইডি</th>
                                                <th>ইস্যুর তারিখ</th>
                                                <th>ছবি</th>
                                                <th>প্রিন্ট</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $stu): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($stu['first_name'].' '.$stu['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($stu['roll_number']); ?></td>
                                                <td><?php echo htmlspecialchars($stu['gpa']); ?></td>
                                                <td><?php echo htmlspecialchars($stu['certificate_id']); ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($stu['issue_date'])); ?></td>
                                                <td>
                                                    <?php if (!empty($stu['photo'])): ?>
                                                        <img src="../../uploads/students/<?php echo htmlspecialchars($stu['photo']); ?>" alt="ছবি" style="max-width:60px;max-height:80px;">
                                                    <?php else: ?>
                                                        <span class="text-muted">ছবি নেই</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="five_pass_certificate_print.php?id=<?php echo htmlspecialchars($stu['student_id']); ?>" target="_blank" class="btn btn-info btn-sm"><i class="fa fa-print"></i> প্রিন্ট</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($selected_year_id && $selected_class_id): ?>
                                <div class="alert alert-warning">এই শিক্ষাবর্ষ ও শ্রেণিতে কোনো সার্টিফিকেট পাওয়া যায়নি।</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php include '../../admin/inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
