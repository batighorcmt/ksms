<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ক্লাস লিস্ট লোড
$classes = [];
$class_query = $conn->query("SELECT id, name FROM classes ORDER BY numeric_value ASC");
while ($row = $class_query->fetch_assoc()) {
    $classes[] = $row;
}

// ফর্ম সাবমিশন
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id       = trim($_POST['student_id']);
    $first_name       = trim($_POST['first_name']);
    $last_name        = trim($_POST['last_name']);
    $father_name      = trim($_POST['father_name']);
    $mother_name      = trim($_POST['mother_name']);
    $guardian_relation= trim($_POST['guardian_relation']);
    $guardian_name    = trim($_POST['guardian_name']);
    $dob              = $_POST['date_of_birth'];
    $gender           = $_POST['gender'];
    $mobile_number    = trim($_POST['mobile_number']);
    $class_id         = intval($_POST['class_id']);
    $section_id       = intval($_POST['section_id']);
    $roll_number      = intval($_POST['roll_number']);
    $admission_date   = $_POST['admission_date'];
    $status           = $_POST['status'];

    // Photo Upload
    $photo = null;
    if (!empty($_FILES['photo']['name'])) {
        $upload_dir = "../uploads/students/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $photo_name = time() . "_" . basename($_FILES['photo']['name']);
        $target_file = $upload_dir . $photo_name;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            $photo = $photo_name;
        }
    }

    // Insert into students
    $sql = "INSERT INTO students (
                student_id, first_name, last_name, father_name, mother_name,
                guardian_relation, date_of_birth, gender, mobile_number,
                class_id, section_id, roll_number, admission_date, status, photo
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssssssiiissss",
        $student_id, $first_name, $last_name, $father_name, $mother_name,
        $guardian_relation, $dob, $gender, $mobile_number,
        $class_id, $section_id, $roll_number, $admission_date, $status, $photo
    );

    if ($stmt->execute()) {
        // Create default login user for student
        $password_hash = password_hash("123456", PASSWORD_BCRYPT);
        $full_name = $first_name . " " . $last_name;
        $user_sql = "INSERT INTO users (username, password, role, full_name, status) VALUES (?,?,?,?,?)";
        $user_stmt = $conn->prepare($user_sql);
        $role = "student";
        $active = "active";
        $user_stmt->bind_param("sssss", $student_id, $password_hash, $role, $full_name, $active);
        $user_stmt->execute();

        $_SESSION['success'] = "✅ শিক্ষার্থী সফলভাবে যোগ হয়েছে!";
        header("Location: students_list.php");
        exit();
    } else {
        $_SESSION['error'] = "❌ কিছু সমস্যা হয়েছে: " . $stmt->error;
    }
}
?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>নতুন শিক্ষার্থী যোগ করুন</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body, .main-sidebar, .nav-link { font-family: 'SolaimanLipi', sans-serif; }
        .no-data{color:#6b7280}
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; ?>

<div class="content-wrapper p-4">
    <h2 class="mb-4">➕ নতুন শিক্ষার্থী যোগ করুন</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow border-0 rounded-3">
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data" class="row g-3">

                <div class="col-md-4">
                    <label class="form-label">Student ID</label>
                    <input type="text" name="student_id" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Father's Name</label>
                    <input type="text" name="father_name" id="father_name" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mother's Name</label>
                    <input type="text" name="mother_name" id="mother_name" class="form-control">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Guardian Relation</label>
                    <select name="guardian_relation" id="guardian_relation" class="form-select" required>
                        <option value="">Select</option>
                        <option value="father">Father</option>
                        <option value="mother">Mother</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Guardian Name</label>
                    <input type="text" name="guardian_name" id="guardian_name" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select" required>
                        <option value="">Select</option>
                        <option value="male">পুরুষ</option>
                        <option value="female">মহিলা</option>
                        <option value="other">অন্যান্য</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Mobile Number</label>
                    <input type="text" name="mobile_number" class="form-control">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Photo</label>
                    <input type="file" name="photo" id="photo" class="form-control" accept="image/*">
                    <img id="photoPreview" src="#" alt="Preview" class="img-thumbnail mt-2" style="display:none; max-height:150px;">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Class</label>
                    <select name="class_id" id="class_id" class="form-select" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= $cls['id']; ?>"><?= htmlspecialchars($cls['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Section</label>
                    <select name="section_id" id="section_id" class="form-select" required>
                        <option value="">Select Section</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Roll Number</label>
                    <input type="number" name="roll_number" class="form-control">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Admission Date</label>
                    <input type="date" name="admission_date" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="graduated">Graduated</option>
                    </select>
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save"></i> Save Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'inc/footer.php'; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Guardian Relation অনুযায়ী Guardian Name নিয়ন্ত্রণ
document.getElementById('guardian_relation').addEventListener('change', function() {
    let relation = this.value;
    let guardianInput = document.getElementById('guardian_name');
    if (relation === 'father') {
        guardianInput.value = document.getElementById('father_name').value;
        guardianInput.setAttribute('disabled', true);
    } else if (relation === 'mother') {
        guardianInput.value = document.getElementById('mother_name').value;
        guardianInput.setAttribute('disabled', true);
    } else {
        guardianInput.value = '';
        guardianInput.removeAttribute('disabled');
    }
});

// Photo Preview
document.getElementById('photo').addEventListener('change', function(e) {
    const reader = new FileReader();
    reader.onload = function(event) {
        const img = document.getElementById('photoPreview');
        img.src = event.target.result;
        img.style.display = 'block';
    };
    reader.readAsDataURL(e.target.files[0]);
});

// AJAX দিয়ে section load
document.getElementById('class_id').addEventListener('change', function() {
    let classId = this.value;
    let sectionSelect = document.getElementById('section_id');
    sectionSelect.innerHTML = '<option value="">Loading...</option>';

    fetch('get_sections.php?class_id=' + classId)
        .then(res => res.json())
        .then(data => {
            sectionSelect.innerHTML = '<option value="">Select Section</option>';
            data.forEach(sec => {
                let opt = document.createElement('option');
                opt.value = sec.id;
                opt.textContent = sec.name;
                sectionSelect.appendChild(opt);
            });
        });
});
</script>
</body>
</html>