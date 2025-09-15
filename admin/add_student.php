<?php
// add_student.php
session_start();
require_once '../config.php';

// শুধুমাত্র লগইন করা ব্যবহারকারীরা অ্যাক্সেস করতে পারবে
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ফর্ম সাবমিট হলে ডাটা প্রসেস
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ইনপুট স্যানিটাইজ করা
    $student_id     = trim($_POST['student_id']);
    $first_name     = trim($_POST['first_name']);
    $last_name      = trim($_POST['last_name']);
    $father_name    = trim($_POST['father_name']);
    $mother_name    = trim($_POST['mother_name']);
    $guardian_id    = intval($_POST['guardian_id']);
    $guardian_relation = trim($_POST['guardian_relation']);
    $birth_cert_no  = trim($_POST['birth_certificate_no']);
    $dob            = $_POST['date_of_birth'];
    $gender         = $_POST['gender'];
    $blood_group    = $_POST['blood_group'];
    $religion       = $_POST['religion'];
    $present_address = trim($_POST['present_address']);
    $permanent_address = trim($_POST['permanent_address']);
    $mobile_number  = trim($_POST['mobile_number']);
    $city           = trim($_POST['city']);
    $country        = trim($_POST['country']);
    $class_id       = intval($_POST['class_id']);
    $section_id     = intval($_POST['section_id']);
    $roll_number    = intval($_POST['roll_number']);
    $admission_date = $_POST['admission_date'];
    $status         = $_POST['status'];

    // ফটো আপলোড
    $photo = null;
    if (!empty($_FILES['photo']['name'])) {
        $upload_dir = "../uploads/students/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $photo_name = time() . "_" . basename($_FILES['photo']['name']);
        $target_file = $upload_dir . $photo_name;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            $photo = $photo_name;
        }
    }

    // ডাটাবেজ ইনসার্ট (prepared statement ব্যবহার করে নিরাপদ)
    $sql = "INSERT INTO students (
                student_id, first_name, last_name, father_name, mother_name, guardian_relation,
                birth_certificate_no, date_of_birth, gender, blood_group, religion,
                present_address, permanent_address, mobile_number, city, country, photo,
                class_id, section_id, roll_number, guardian_id, admission_date, status
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssss ssssss sssiiiss",
        $student_id, $first_name, $last_name, $father_name, $mother_name, $guardian_relation,
        $birth_cert_no, $dob, $gender, $blood_group, $religion,
        $present_address, $permanent_address, $mobile_number, $city, $country, $photo,
        $class_id, $section_id, $roll_number, $guardian_id, $admission_date, $status
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

<!-- HTML ফর্ম -->
<?php include '../includes/header.php'; ?>

<div class="container mt-4">
    <h2>নতুন শিক্ষার্থী যোগ করুন</h2>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-6">
            <label>Student ID</label>
            <input type="text" name="student_id" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label>First Name</label>
            <input type="text" name="first_name" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label>Last Name</label>
            <input type="text" name="last_name" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label>Father's Name</label>
            <input type="text" name="father_name" class="form-control">
        </div>
        <div class="col-md-6">
            <label>Mother's Name</label>
            <input type="text" name="mother_name" class="form-control">
        </div>
        <div class="col-md-6">
            <label>Guardian ID</label>
            <input type="number" name="guardian_id" class="form-control">
        </div>
        <div class="col-md-6">
            <label>Guardian Relation</label>
            <input type="text" name="guardian_relation" class="form-control">
        </div>
        <div class="col-md-6">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label>Gender</label>
            <select name="gender" class="form-control" required>
                <option value="">Select</option>
                <option value="male">পুরুষ</option>
                <option value="female">মহিলা</option>
                <option value="other">অন্যান্য</option>
            </select>
        </div>
        <div class="col-md-6">
            <label>Photo</label>
            <input type="file" name="photo" class="form-control">
        </div>
        <!-- আরও ফিল্ডগুলো একইভাবে এখানে রাখা হবে -->
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Save Student</button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>