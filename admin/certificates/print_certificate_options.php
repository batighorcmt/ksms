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
$classes = $pdo->query("SELECT id, name FROM classes ORDER BY id")->fetchAll();
// Fetch certificate types
$certificate_types = [
    'running' => 'বর্তমান শিক্ষার্থী',
    'ex_student' => 'পূর্বে অধ্যয়নকৃত শিক্ষার্থী'
];

// Handle AJAX for sections
if (isset($_GET['ajax']) && $_GET['ajax'] === 'sections' && isset($_GET['class_id'])) {
    $stmt = $pdo->prepare("SELECT id, name FROM sections WHERE class_id = ? ORDER BY name");
    $stmt->execute([intval($_GET['class_id'])]);
    echo json_encode($stmt->fetchAll());
    exit;
}
// Handle AJAX for students
if (isset($_GET['ajax']) && $_GET['ajax'] === 'students' && isset($_GET['class_id']) && isset($_GET['section_id'])) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM students WHERE class_id = ? AND section_id = ? ORDER BY first_name, last_name");
    $stmt->execute([intval($_GET['class_id']), intval($_GET['section_id'])]);
    echo json_encode($stmt->fetchAll());
    exit;
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
    <style>
        body { font-family: 'SolaimanLipi', Arial, sans-serif; background: #f5f5f5; }
    .cert-container { max-width: 600px; margin: 40px auto; background: #fff; box-shadow: 0 0 10px #ccc; padding: 30px; border-radius: 8px; }
        h2 { text-align: center; margin-bottom: 25px; color: #006400; }
        label { font-weight: bold; margin-top: 12px; display: block; }
        select, button { width: 100%; padding: 10px; margin-top: 6px; border-radius: 5px; border: 1px solid #ccc; font-size: 16px; }
        button { background: #006400; color: #fff; border: none; cursor: pointer; margin-top: 18px; }
        button:hover { background: #004d00; }
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
                                <label for="certificate_type">সার্টিফিকেটের ধরন:</label>
                                <select name="certificate_type" id="certificate_type" required>
                                    <option value="">নির্বাচন করুন</option>
                                    <?php foreach ($certificate_types as $key => $label): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <label for="class_id">শ্রেণি:</label>
                                <select name="class_id" id="class_id" required>
                                    <option value="">নির্বাচন করুন</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <label for="section_id">শাখা:</label>
                                <select name="section_id" id="section_id" required>
                                    <option value="">প্রথমে শ্রেণি নির্বাচন করুন</option>
                                </select>

                                <label for="student_id">শিক্ষার্থীর নাম:</label>
                                <select name="student_id" id="student_id" required>
                                    <option value="">প্রথমে শাখা নির্বাচন করুন</option>
                                </select>

                                <button type="submit">সার্টিফিকেট দেখুন/প্রিন্ট করুন</button>
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
    fetch('?ajax=students&class_id=' + classSelect.value + '&section_id=' + sectionSelect.value)
        .then(res => res.json())
        .then(data => {
            let html = '<option value="">নির্বাচন করুন</option>';
            data.forEach(stu => {
                html += `<option value="${stu.id}">${stu.first_name} ${stu.last_name}</option>`;
            });
            studentSelect.innerHTML = html;
        });
});
// On submit, redirect to correct certificate page
const form = document.getElementById('certificateForm');
form.addEventListener('submit', function(e) {
    e.preventDefault();
    const type = document.getElementById('certificate_type').value;
    const studentId = document.getElementById('student_id').value;
    if (!type || !studentId) return;
    let url = '';
    if (type === 'running') {
        url = 'running_student_certificate.php?id=' + studentId;
    } else if (type === 'ex_student') {
        url = 'ex_student_certificate.php?id=' + studentId;
    }
    if (url) window.location.href = url;
});
</script>
</body>
</html>
