<?php
// add_student.php
session_start();
require_once '../config.php';

// সেশন চেক
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ফর্ম সাবমিশন হ্যান্ডেল
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id       = trim($_POST['student_id']);
    $first_name       = trim($_POST['first_name']);
    $last_name        = trim($_POST['last_name']);
    $father_name      = trim($_POST['father_name']);
    $mother_name      = trim($_POST['mother_name']);
    $guardian_id      = intval($_POST['guardian_id']);
    $guardian_relation= trim($_POST['guardian_relation']);
    $dob              = $_POST['date_of_birth'];
    $gender           = $_POST['gender'];
    $mobile_number    = trim($_POST['mobile_number']);
    $class_id         = intval($_POST['class_id']);
    $section_id       = intval($_POST['section_id']);
    $roll_number      = intval($_POST['roll_number']);
    $admission_date   = $_POST['admission_date'];
    $status           = $_POST['status'];

    // ফটো আপলোড
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

    $sql = "INSERT INTO students (
                student_id, first_name, last_name, father_name, mother_name,
                guardian_relation, date_of_birth, gender, mobile_number,
                class_id, section_id, roll_number, guardian_id, admission_date,
                status, photo
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssssssiiisssss",
        $student_id, $first_name, $last_name, $father_name, $mother_name,
        $guardian_relation, $dob, $gender, $mobile_number,
        $class_id, $section_id, $roll_number, $guardian_id, $admission_date,
        $status, $photo
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = "✅ শিক্ষার্থী সফলভাবে যোগ হয়েছে!";
        header("Location: students_list.php");
        exit();
    } else {
        $_SESSION['error'] = "❌ কিছু সমস্যা হয়েছে: " . $stmt->error;
    }
}
?>

<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; ?>

<div class="container-fluid px-4">
    <h2 class="mt-4 mb-4">➕ নতুন শিক্ষার্থী যোগ করুন</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-lg border-0 rounded-3">
        <div class="card-body p-4">
            <form action="" method="POST" enctype="multipart/form-data" class="row g-3">

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
                    <input type="text" name="father_name" class="form-control">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Mother's Name</label>
                    <input type="text" name="mother_name" class="form-control">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Guardian ID</label>
                    <input type="number" name="guardian_id" class="form-control">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Guardian Relation</label>
                    <input type="text" name="guardian_relation" class="form-control">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select" required>
                        <option value="">Select</option>
                        <option value="male">পুরুষ</option>
                        <option value="female">মহিলা</option>
                        <option value="other">অন্যান্য</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Mobile Number</label>
                    <input type="text" name="mobile_number" class="form-control">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Class</label>
                    <select name="class_id" class="form-select" required>
                        <!-- Dynamic class list load করতে হবে -->
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Section</label>
                    <select name="section_id" class="form-select" required>
                        <!-- Dynamic section list load করতে হবে -->
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Roll Number</label>
                    <input type="number" name="roll_number" class="form-control">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Admission Date</label>
                    <input type="date" name="admission_date" class="form-control">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="graduated">Graduated</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Photo</label>
                    <input type="file" name="photo" class="form-control">
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