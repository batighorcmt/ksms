<?php
require_once '../../config.php';
if (!defined('CERTIFICATE_SECRET_KEY')) {
    define('CERTIFICATE_SECRET_KEY', 'your-strong-secret-key'); // Change to a strong secret
}
// Auth
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('../../index.php');
}

// Fetch classes
$classes = $pdo->query("SELECT id, name FROM classes ORDER BY numeric_value ASC, name ASC")->fetchAll();
// Fetch academic years (active)
$years = $pdo->query("SELECT id, year, is_current FROM academic_years WHERE status='active' ORDER BY year DESC")->fetchAll(PDO::FETCH_ASSOC);
// Fetch certificate types
$certificate_types = [
    'running' => 'বর্তমান শিক্ষার্থী',
    //'ex_student' => 'পূর্বে অধ্যয়নকৃত শিক্ষার্থী'
];

// Handle AJAX for sections
if (isset($_GET['ajax']) && $_GET['ajax'] === 'sections' && isset($_GET['class_id'])) {
    $stmt = $pdo->prepare("SELECT id, name FROM sections WHERE class_id = ? ORDER BY name");
    $stmt->execute([intval($_GET['class_id'])]);
    echo json_encode($stmt->fetchAll());
    exit;
}
// Handle AJAX for students (active by enrollment) - academic year aware
if (isset($_GET['ajax']) && $_GET['ajax'] === 'students' && isset($_GET['class_id']) && isset($_GET['section_id'])) {
    $class_id = intval($_GET['class_id']);
    $section_id = intval($_GET['section_id']);
    $year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;

    // If no year provided, try current year
    if (!$year_id) {
        $cy = $pdo->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!empty($cy['id'])) { $year_id = (int)$cy['id']; }
    }

    if ($year_id) {
        // Enrollment-first: filter by class, section, year and active enrollment
        $stmt = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, se.roll_number
                               FROM students s
                               JOIN students_enrollment se ON se.student_id = s.id
                               WHERE se.class_id = ? AND se.section_id = ? AND se.academic_year_id = ?
                                     AND (se.status = 'active' OR se.status IS NULL)
                               ORDER BY (se.roll_number IS NULL), se.roll_number ASC, s.first_name, s.last_name");
        $stmt->execute([$class_id, $section_id, $year_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    } else {
        // Fallback: use latest enrollment per student for the given class/section if no current year is set
        $stmt = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, se.roll_number
                               FROM students s
                               JOIN students_enrollment se ON se.student_id = s.id
                               WHERE se.class_id = ? AND se.section_id = ?
                                     AND (se.status = 'active' OR se.status IS NULL)
                                     AND se.academic_year_id = (
                                         SELECT MAX(se2.academic_year_id) FROM students_enrollment se2
                                         WHERE se2.student_id = s.id
                                     )
                               ORDER BY (se.roll_number IS NULL), se.roll_number ASC, s.first_name, s.last_name");
        $stmt->execute([$class_id, $section_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
}


// Removed token generation logic as per new requirements

?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>সার্টিফিকেট প্রিন্ট অপশন</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: 'SolaimanLipi', Arial, sans-serif; background: #f5f5f5; }
        .cert-container { max-width: 720px; margin: 20px auto; background: #fff; box-shadow: 0 0 10px #ccc; padding: 20px; border-radius: 8px; }
        h2 { text-align: center; margin-bottom: 18px; color: #006400; font-size: 1.5rem; }
        label { font-weight: bold; margin-top: 8px; display: block; }
        select, button, input { width: 100%; padding: 10px; margin-top: 6px; border-radius: 5px; border: 1px solid #ccc; font-size: 16px; }
        button { background: #006400; color: #fff; border: none; cursor: pointer; margin-top: 12px; }
        button:hover { background: #004d00; }
        @media (max-width: 576px) {
            .cert-container { padding: 14px; margin: 10px; }
            h2 { font-size: 1.25rem; }
            .form-row { display: block; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <!-- Navbar -->
    <?php include '../../admin/inc/header.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php
    if (hasRole(['super_admin'])) {
        include '../../admin/inc/sidebar.php';
    } elseif (hasRole(['teacher'])) {
        include '../../teacher/inc/sidebar.php';
    }
    ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid py-4">
                <div class="row justify-content-center">
                    <div class="col-lg-7 col-md-9 col-12">
                        <div class="cert-container">
                            <h2>সার্টিফিকেট প্রিন্ট অপশন</h2>
                            <form method="get" action="" id="certificateForm">
                                <div class="row gx-2 gy-2">
                                    <div class="col-12 col-md-6">
                                        <label for="certificate_type">সার্টিফিকেটের ধরন:</label>
                                        <select name="certificate_type" id="certificate_type" required>
                                            <option value="">নির্বাচন করুন</option>
                                            <?php foreach ($certificate_types as $key => $label): ?>
                                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="class_id">শ্রেণি:</label>
                                        <select name="class_id" id="class_id" required>
                                            <option value="">নির্বাচন করুন</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <label for="academic_year_id">শিক্ষাবর্ষ (বছর):</label>
                                        <select name="academic_year_id" id="academic_year_id" required class="form-control">
                                            <option value="">নির্বাচন করুন</option>
                                            <?php foreach($years as $y): ?>
                                                <option value="<?= $y['id'] ?>" <?= !empty($y['is_current']) ? 'selected' : '' ?>><?= htmlspecialchars($y['year']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <label for="section_id">শাখা:</label>
                                        <select name="section_id" id="section_id" required>
                                            <option value="">প্রথমে শ্রেণি নির্বাচন করুন</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="student_id">শিক্ষার্থীর নাম:</label>
                                        <select name="student_id" id="student_id" required>
                                            <option value="">প্রথমে শাখা নির্বাচন করুন</option>
                                        </select>
                                    </div>

                                    <div class="col-12 text-center mt-2">
                                        <button type="submit" class="btn btn-success btn-lg">সার্টিফিকেট দেখুন/প্রিন্ট করুন</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Main Footer -->
    <?php include '../../admin/inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
// AJAX for section
const classSelect = document.getElementById('class_id');
const sectionSelect = document.getElementById('section_id');
const studentSelect = document.getElementById('student_id');
classSelect.addEventListener('change', function() {
    sectionSelect.innerHTML = '<option>লোড হচ্ছে...</option>';
    fetch('?ajax=sections&class_id=' + classSelect.value)
        .then(res => res.json())
        .then(data => {
            let html = '<option value="">নির্বাচন করুন</option>';
            data.forEach(sec => {
                html += `<option value="${sec.id}">${sec.name}</option>`;
            });
            sectionSelect.innerHTML = html;
            studentSelect.innerHTML = '<option value="">প্রথমে শাখা নির্বাচন করুন</option>';
        });
});
sectionSelect.addEventListener('change', function() {
    studentSelect.innerHTML = '<option>লোড হচ্ছে...</option>';
    // include selected academic year in the request so students are filtered by year
    const yearVal = document.getElementById('academic_year_id') ? document.getElementById('academic_year_id').value : '';
    const qs = '?ajax=students&class_id=' + encodeURIComponent(classSelect.value) + '&section_id=' + encodeURIComponent(sectionSelect.value) + (yearVal ? '&academic_year_id=' + encodeURIComponent(yearVal) : '');
    fetch(qs)
        .then(res => res.json())
        .then(data => {
            let html = '<option value="">নির্বাচন করুন</option>';
            data.forEach(stu => {
                html += `<option value="${stu.id}">${stu.first_name} ${stu.last_name}</option>`;
            });
            studentSelect.innerHTML = html;
        });
});
// On submit, record certificate first (AJAX) then redirect to certificate page with certificate_number
const form = document.getElementById('certificateForm');
form.addEventListener('submit', function(e) {
    e.preventDefault();
    const type = document.getElementById('certificate_type').value;
    const studentId = document.getElementById('student_id').value;
    if (!type || !studentId) return;
    let targetPage = '';
    if (type === 'running') {
        targetPage = 'running_student_certificate.php';
    } else if (type === 'ex_student') {
        targetPage = 'ex_student_certificate.php';
    }
    if (!targetPage) return;

    // Post to record endpoint (same folder) — include credentials so session cookie is sent
    const yearId = document.getElementById('academic_year_id') ? document.getElementById('academic_year_id').value : '';
    fetch('record_certificate_issue.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: new URLSearchParams({ student_id: studentId, certificate_type: type, academic_year_id: yearId })
    }).then(r => {
        if (r.status === 401) {
            // Not authenticated — send the user to login
            window.location.href = '../../login.php';
            return Promise.reject(new Error('unauthorized'));
        }
        return r.json().catch(() => ({}));
    }).then(j => {
        if (j && j.success) {
            // Redirect to print page with student id and certificate_number so print page shows existing record
            const redirectUrl = targetPage + '?id=' + encodeURIComponent(studentId) + '&certificate_number=' + encodeURIComponent(j.certificate_number);
            window.location.href = redirectUrl;
        } else {
            // fallback: just go to page with id
            window.location.href = targetPage + '?id=' + studentId;
        }
    }).catch(err => {
        if (err && err.message === 'unauthorized') return;
        console.error(err);
        window.location.href = targetPage + '?id=' + studentId;
    });
});
</script>
</body>
</html>
