<?php
// পঞ্চম শ্রেণি পাস সার্টিফিকেট জেনারেটর
require_once '../../config.php';
// Auth
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('../../index.php');
}


// Fetch all academic years
$years = $pdo->query("SELECT * FROM academic_years ORDER BY year DESC")->fetchAll();
$classes = $pdo->query("SELECT id, name FROM classes ORDER BY numeric_value ASC, name ASC")->fetchAll();
$selected_year_id = isset($_GET['year_id']) ? intval($_GET['year_id']) : null;
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 10;
$selected_year_label = '';
foreach ($years as $y) {
    if ($y['id'] == $selected_year_id) $selected_year_label = $y['year'];
}

// Student selection logic
$selected_student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
$students = [];
if ($selected_year_id && $selected_class_id) {
    if ($selected_student_id) {
        $stmt = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, s.father_name, s.mother_name, s.date_of_birth, se.roll_number, s.photo
            FROM students s
            JOIN students_enrollment se ON se.student_id = s.id
            WHERE se.class_id = ? AND se.academic_year_id = ? AND s.id = ?
              AND (se.status = 'active' OR se.status IS NULL)");
        $stmt->execute([$selected_class_id, $selected_year_id, $selected_student_id]);
        $students = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, s.father_name, s.mother_name, s.date_of_birth, se.roll_number, s.photo
            FROM students s
            JOIN students_enrollment se ON se.student_id = s.id
            WHERE se.class_id = ? AND se.academic_year_id = ?
              AND (se.status = 'active' OR se.status IS NULL)
            ORDER BY se.roll_number ASC, s.first_name ASC");
        $stmt->execute([$selected_class_id, $selected_year_id]);
        $students = $stmt->fetchAll();
    }
}


// Handle batch certificate info submission
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cert_data']) && is_array($_POST['cert_data'])) {
    $cert_data = $_POST['cert_data'];
    $errors = [];
    foreach ($cert_data as $sid => $row) {
        $gpa = trim($row['gpa'] ?? '');
        $issue_date = trim($row['issue_date'] ?? '');
        if ($gpa === '' || $issue_date === '') {
            $errors[] = "ID $sid: GPA/Issue date missing";
            continue;
        }
    // Fetch school shortcode
    $school_row = $pdo->query("SELECT short_code FROM school_info LIMIT 1")->fetch();
    $school_shortcode = $school_row && !empty($school_row['short_code']) ? $school_row['short_code'] : 'SC';
    $exam_year = $selected_year_label ?: date('Y');
    // Find serial number for this certificate in this year
    $serial_stmt = $pdo->prepare("SELECT COUNT(*)+1 AS serial_no FROM five_pass_certificate_info");
    $serial_stmt->execute();
    $serial_row = $serial_stmt->fetch();
    $serial_no = $serial_row ? $serial_row['serial_no'] : 1;
    // Generate certificate ID: shortcode-studentyear-serialno
    $certificate_id = $school_shortcode . '-' . $exam_year . '-' . $serial_no;
        // Insert or update
        $exists = $pdo->prepare("SELECT id FROM five_pass_certificate_info WHERE student_id = ?");
        $exists->execute([$sid]);
        if ($exists->fetch()) {
            $upd = $pdo->prepare("UPDATE five_pass_certificate_info SET gpa=?, exam_year=?, certificate_id=?, issue_date=? WHERE student_id=?");
            $upd->execute([$gpa, $exam_year, $certificate_id, $issue_date, $sid]);
        } else {
            $ins = $pdo->prepare("INSERT INTO five_pass_certificate_info (student_id, gpa, exam_year, certificate_id, issue_date) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$sid, $gpa, $exam_year, $certificate_id, $issue_date]);
        }
    }
    if (empty($errors)) {
        $success = 'সকল শিক্ষার্থীর তথ্য সফলভাবে সংরক্ষণ হয়েছে!';
    } else {
        $success = 'ত্রুটি: ' . implode(', ', $errors);
    }
}

?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>পঞ্চম শ্রেণি পাস সার্টিফিকেট</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        body { font-family: 'SolaimanLipi', Arial, sans-serif; background: #f5f5f5; }
        .cert-container { max-width: 800px; margin: 40px auto; background: #fff; box-shadow: 0 0 10px #ccc; padding: 30px; border-radius: 8px; }
        h2 { text-align: center; margin-bottom: 25px; color: #006400; }
        label { font-weight: bold; margin-top: 12px; display: block; }
        select, button, input { width: 100%; padding: 10px; margin-top: 6px; border-radius: 5px; border: 1px solid #ccc; font-size: 16px; }
        button { background: #006400; color: #fff; border: none; cursor: pointer; margin-top: 18px; }
        button:hover { background: #004d00; }
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
                    <div class="col-lg-8 col-md-10 col-12">
                        <div class="cert-container">
                            <h2>পঞ্চম শ্রেণি পাস সার্টিফিকেট জেনারেটর</h2>
                            <form method="get" class="mb-4">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label for="year_id" class="form-label">শিক্ষাবর্ষ নির্বাচন করুন:</label>
                                        <select name="year_id" id="year_id" class="form-control" required onchange="this.form.submit()">
                                            <option value="">নির্বাচন করুন</option>
                                            <?php foreach ($years as $y): ?>
                                                <option value="<?php echo $y['id']; ?>" <?php echo ($selected_year_id == $y['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($y['year']); ?><?php echo ($y['is_current'] ? ' (বর্তমান)' : ''); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="class_id" class="form-label">শ্রেণি নির্বাচন করুন:</label>
                                        <select name="class_id" id="class_id" class="form-control" required onchange="this.form.submit()">
                                            <option value="">নির্বাচন করুন</option>
                                            <?php foreach ($classes as $c): ?>
                                                <option value="<?php echo $c['id']; ?>" <?php echo ($selected_class_id == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="student_id" class="form-label">শিক্ষার্থী নির্বাচন করুন:</label>
                                        <select name="student_id" id="student_id" class="form-control">
                                            <option value="">সকল শিক্ষার্থী</option>
                                            <?php
                                            if ($selected_year_id && $selected_class_id) {
                                                if (!isset($pdo) || !$pdo) {
                                                    echo '<option value="">ডাটাবেস সংযোগ নেই</option>';
                                                } else {
                                                                                                        $stuList = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, se.roll_number
                                                                                                                                                            FROM students s
                                                                                                                                                            JOIN students_enrollment se ON se.student_id = s.id
                                                                                                                                                            WHERE se.class_id = ? AND se.academic_year_id = ?
                                                                                                                                                                AND (se.status = 'active' OR se.status IS NULL)
                                                                                                                                                            ORDER BY se.roll_number ASC, s.first_name ASC");
                                                                                                        $stuList->execute([$selected_class_id, $selected_year_id]);
                                                                                                        foreach ($stuList->fetchAll() as $stuOpt) {
                                                                                                                $name = htmlspecialchars($stuOpt['first_name'].' '.$stuOpt['last_name']);
                                                                                                                $roll = htmlspecialchars($stuOpt['roll_number'] ?? '');
                                                                                                                echo '<option value="'.$stuOpt['id'].'"'.($selected_student_id==$stuOpt['id']?' selected':'').'>'.$name.' (রোল: '.$roll.')</option>';
                                                                                                        }
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-12 text-end">
                                        <button type="submit" class="btn btn-primary mt-2">সার্চ করুন</button>
                                    </div>
                                </div>
                            </form>
                            <?php if ($selected_year_id && !empty($students)): ?>
                                <?php if ($success): ?>
                                    <div class="alert alert-success"> <?php echo $success; ?> </div>
                                <?php endif; ?>
                                <form method="post">
                                    <input type="hidden" name="year_id" value="<?php echo $selected_year_id; ?>">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th>নাম</th>
                                                <th>রোল</th>
                                                <th>ফলাফল <span style="color:red">*</span></th>
                                                <th>ইস্যুর তারিখ <span style="color:red">*</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $stu):
                                                // fetch previous cert info if exists
                                                $cert_info = [];
                                                if (isset($pdo) && $pdo) {
                                                    $cert = $pdo->prepare("SELECT gpa, issue_date FROM five_pass_certificate_info WHERE student_id = ?");
                                                    $cert->execute([$stu['id']]);
                                                    $cert_info = $cert->fetch();
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($stu['first_name'].' '.$stu['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($stu['roll_number']); ?></td>
                                                <td>
                                                    <input type="text" name="cert_data[<?php echo $stu['id']; ?>][gpa]" value="<?php echo $cert_info['gpa'] ?? ''; ?>" required class="form-control">
                                                </td>
                                                <td>
                                                    <input type="date" name="cert_data[<?php echo $stu['id']; ?>][issue_date]" value="<?php echo $cert_info['issue_date'] ?? date('Y-m-d'); ?>" required class="form-control">
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <button type="submit" class="btn btn-success">সার্টিফিকেট তথ্য সংরক্ষণ করুন</button>
                                </form>
                            <?php elseif ($selected_year_id): ?>
                                <div class="alert alert-warning">এই শিক্ষাবর্ষে নির্বাচিত শ্রেণির কোনো শিক্ষার্থী পাওয়া যায়নি।</div>
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
