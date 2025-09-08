<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('login.php');
}

// শিক্ষার্থী আইডি পান
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// শিক্ষার্থী ডেটা লোড করুন
$stmt = $pdo->prepare("
    SELECT s.*, 
           c.name as class_name, 
           sec.name as section_name,
           u.full_name as guardian_name
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id 
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN users u ON s.guardian_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = "শিক্ষার্থী পাওয়া যায়নি!";
    header("Location: students.php");
    exit();
}

// ক্লাস, শাখা এবং সম্পর্ক লোড করুন
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();
$sections = $pdo->query("SELECT * FROM sections WHERE status='active'")->fetchAll();
$guardians = $pdo->query("SELECT * FROM users WHERE role='guardian' AND status=1")->fetchAll();
$relations = $pdo->query("SELECT * FROM guardian_relations")->fetchAll();

// AJAX রিকোয়েস্ট হ্যান্ডলিং - ক্লাস আইডি অনুযায়ী শাখা লোড করার জন্য
if (isset($_GET['class_id'])) {
    $class_id = intval($_GET['class_id']);
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? AND status='active'");
    $stmt->execute([$class_id]);
    $sections = $stmt->fetchAll();
    
    echo json_encode($sections);
    exit;
}

// শিক্ষার্থী আপডেট করুন
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_student'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $father_name = $_POST['father_name'];
    $mother_name = $_POST['mother_name'];
    $guardian_relation = $_POST['guardian_relation'];
    $other_relation = $_POST['other_relation'];
    $birth_certificate_no = $_POST['birth_certificate_no'];
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $blood_group = $_POST['blood_group'];
    $religion = $_POST['religion'];
    $present_address = $_POST['present_address'];
    $permanent_address = $_POST['permanent_address'];
    $mobile_number = $_POST['mobile_number'];
    $class_id = $_POST['class_id'];
    $section_id = $_POST['section_id'];
    $roll_number = $_POST['roll_number'];
    $guardian_id = !empty($_POST['guardian_id']) ? $_POST['guardian_id'] : NULL;
    $status = $_POST['status'];
    
    // যদি অন্যান্য সম্পর্ক নির্বাচন করা হয়
    if ($guardian_relation == 'other' && !empty($other_relation)) {
        $guardian_relation = $other_relation;
    }
    
    // অভিভাবক নাম ভ্যালিডেশন
    if ($guardian_relation == 'পিতা' || $guardian_relation == 'মাতা') {
        $guardian_name_field = ($guardian_relation == 'পিতা') ? 'father_name' : 'mother_name';
        $guardian_name = $$guardian_name_field;
    } else {
        $guardian_name = $_POST['guardian_name'] ?? '';
        if (empty($guardian_name)) {
            $_SESSION['error'] = "অভিভাবকের নাম বাধ্যতামূলক!";
            header("Location: edit_student.php?id=" . $student_id);
            exit();
        }
    }
    
    // লোগো আপলোড হ্যান্ডলিং
    $photo = $student['photo']; // ডিফল্টভাবে পুরানো ছবি রাখুন
    
    if (!empty($_FILES['photo']['name'])) {
        $upload_dir = '../uploads/students/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['photo']['name']);
        $target_file = $upload_dir . $file_name;
        
        // ফাইল আপলোড করুন
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            $photo = $file_name;
            
            // পুরানো ছবি ডিলিট করুন (যদি থাকে)
            if (!empty($student['photo']) && file_exists($upload_dir . $student['photo'])) {
                unlink($upload_dir . $student['photo']);
            }
        }
    }
    
    $stmt = $pdo->prepare("
        UPDATE students 
        SET first_name = ?, last_name = ?, father_name = ?, mother_name = ?, 
            guardian_relation = ?, birth_certificate_no = ?, date_of_birth = ?, 
            gender = ?, blood_group = ?, religion = ?, present_address = ?, 
            permanent_address = ?, mobile_number = ?, photo = ?, class_id = ?, 
            section_id = ?, roll_number = ?, guardian_id = ?, status = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([
        $first_name, $last_name, $father_name, $mother_name, $guardian_relation,
        $birth_certificate_no, $date_of_birth, $gender, $blood_group, $religion,
        $present_address, $permanent_address, $mobile_number, $photo,
        $class_id, $section_id, $roll_number, $guardian_id, $status, $student_id
    ])) {
        $_SESSION['success'] = "শিক্ষার্থীর তথ্য সফলভাবে আপডেট করা হয়েছে!";
        header("Location: student_details.php?id=" . $student_id);
        exit();
    } else {
        $_SESSION['error'] = "শিক্ষার্থীর তথ্য আপডেট করতে সমস্যা হয়েছে!";
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষার্থী সম্পাদনা - কিন্ডার গার্ডেন</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Bengali Font -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    
    <style>
        body, .main-sidebar, .nav-link {
            font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;
        }
        .student-photo {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .other-relation, .guardian-name-field {
            display: none;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Navbar -->
    <?php include 'inc/header.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php include 'inc/sidebar.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">শিক্ষার্থী সম্পাদনা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/students.php">শিক্ষার্থী ব্যবস্থাপনা</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/student_details.php?id=<?php echo $student_id; ?>">শিক্ষার্থী বিস্তারিত</a></li>
                            <li class="breadcrumb-item active">শিক্ষার্থী সম্পাদনা</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Notification Alerts -->
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-check"></i> সফল!</h5>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-ban"></i> ত্রুটি!</h5>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">শিক্ষার্থী তথ্য সম্পাদনা</h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="first_name">শিক্ষার্থীর নামের প্রথম অংশ *</label>
                                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $student['first_name']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="last_name">শিক্ষার্থীর নামের শেষ অংশ *</label>
                                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $student['last_name']; ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="father_name">পিতার নাম *</label>
                                                        <input type="text" class="form-control" id="father_name" name="father_name" value="<?php echo $student['father_name']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="mother_name">মাতার নাম *</label>
                                                        <input type="text" class="form-control" id="mother_name" name="mother_name" value="<?php echo $student['mother_name']; ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="guardian_relation">অভিভাবকের সম্পর্ক *</label>
                                                        <select class="form-control" id="guardian_relation" name="guardian_relation" required>
                                                            <option value="">নির্বাচন করুন</option>
                                                            <?php foreach($relations as $relation): ?>
                                                                <option value="<?php echo $relation['name']; ?>" <?php echo ($student['guardian_relation'] == $relation['name']) ? 'selected' : ''; ?>>
                                                                    <?php echo $relation['name']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                            <option value="other" <?php echo (!in_array($student['guardian_relation'], array_column($relations, 'name'))) ? 'selected' : ''; ?>>অন্যান্য</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 other-relation" id="other_relation_field" style="<?php echo (!in_array($student['guardian_relation'], array_column($relations, 'name'))) ? 'display:block;' : 'display:none;'; ?>">
                                                    <div class="form-group">
                                                        <label for="other_relation">অন্যান্য সম্পর্ক উল্লেখ করুন</label>
                                                        <input type="text" class="form-control" id="other_relation" name="other_relation" value="<?php echo (!in_array($student['guardian_relation'], array_column($relations, 'name'))) ? $student['guardian_relation'] : ''; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- অভিভাবক নাম ফিল্ড (পিতা/মাতা ছাড়া অন্য সম্পর্কের জন্য) -->
                                            <div class="row guardian-name-field" id="guardian_name_field">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label for="guardian_name">অভিভাবকের নাম *</label>
                                                        <input type="text" class="form-control" id="guardian_name" name="guardian_name" value="">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="birth_certificate_no">জন্ম নিবন্ধন নম্বর</label>
                                                        <input type="text" class="form-control" id="birth_certificate_no" name="birth_certificate_no" value="<?php echo $student['birth_certificate_no']; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="date_of_birth">জন্ম তারিখ *</label>
                                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo $student['date_of_birth']; ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="gender">লিঙ্গ *</label>
                                                        <select class="form-control" id="gender" name="gender" required>
                                                            <option value="">নির্বাচন করুন</option>
                                                            <option value="male" <?php echo ($student['gender'] == 'male') ? 'selected' : ''; ?>>ছেলে</option>
                                                            <option value="female" <?php echo ($student['gender'] == 'female') ? 'selected' : ''; ?>>মেয়ে</option>
                                                            <option value="other" <?php echo ($student['gender'] == 'other') ? 'selected' : ''; ?>>অন্যান্য</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="blood_group">রক্তের গ্রুপ</label>
                                                        <select class="form-control" id="blood_group" name="blood_group">
                                                            <option value="">নির্বাচন করুন</option>
                                                            <option value="A+" <?php echo ($student['blood_group'] == 'A+') ? 'selected' : ''; ?>>A+</option>
                                                            <option value="A-" <?php echo ($student['blood_group'] == 'A-') ? 'selected' : ''; ?>>A-</option>
                                                            <option value="B+" <?php echo ($student['blood_group'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                                                            <option value="B-" <?php echo ($student['blood_group'] == 'B-') ? 'selected' : ''; ?>>B-</option>
                                                            <option value="AB+" <?php echo ($student['blood_group'] == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                                            <option value="AB-" <?php echo ($student['blood_group'] == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                                            <option value="O+" <?php echo ($student['blood_group'] == 'O+') ? 'selected' : ''; ?>>O+</option>
                                                            <option value="O-" <?php echo ($student['blood_group'] == 'O-') ? 'selected' : ''; ?>>O-</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="religion">ধর্ম</label>
                                                        <select class="form-control" id="religion" name="religion">
                                                            <option value="">নির্বাচন করুন</option>
                                                            <option value="Islam" <?php echo ($student['religion'] == 'Islam') ? 'selected' : ''; ?>>ইসলাম</option>
                                                            <option value="Hinduism" <?php echo ($student['religion'] == 'Hinduism') ? 'selected' : ''; ?>>হিন্দু</option>
                                                            <option value="Christianity" <?php echo ($student['religion'] == 'Christianity') ? 'selected' : ''; ?>>খ্রিস্টান</option>
                                                            <option value="Buddhism" <?php echo ($student['religion'] == 'Buddhism') ? 'selected' : ''; ?>>বৌদ্ধ</option>
                                                            <option value="Other" <?php echo ($student['religion'] == 'Other') ? 'selected' : ''; ?>>অন্যান্য</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="present_address">বর্তমান ঠিকানা *</label>
                                                        <textarea class="form-control" id="present_address" name="present_address" rows="3" required><?php echo $student['present_address']; ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="permanent_address">স্থায়ী ঠিকানা *</label>
                                                        <textarea class="form-control" id="permanent_address" name="permanent_address" rows="3" required><?php echo $student['permanent_address']; ?></textarea>
                                                    </div>
                                                    <button type="button" class="btn btn-secondary btn-sm" id="copyAddress">
                                                        <i class="fas fa-copy"></i> বর্তমান ঠিকানা অনুলিপি করুন
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="mobile_number">মোবাইল নম্বর *</label>
                                                        <input type="text" class="form-control" id="mobile_number" name="mobile_number" value="<?php echo $student['mobile_number']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="guardian_id">অভিভাবক (ঐচ্ছিক)</label>
                                                        <select class="form-control" id="guardian_id" name="guardian_id">
                                                            <option value="">নির্বাচন করুন</option>
                                                            <?php foreach($guardians as $guardian): ?>
                                                                <option value="<?php echo $guardian['id']; ?>" <?php echo ($student['guardian_id'] == $guardian['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo $guardian['full_name']; ?> (<?php echo $guardian['phone']; ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="class_id">ক্লাস *</label>
                                                        <select class="form-control" id="class_id" name="class_id" required>
                                                            <option value="">নির্বাচন করুন</option>
                                                            <?php foreach($classes as $class): ?>
                                                                <option value="<?php echo $class['id']; ?>" <?php echo ($student['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo $class['name']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="section_id">শাখা *</label>
                                                        <select class="form-control" id="section_id" name="section_id" required>
                                                            <option value="">নির্বাচন করুন</option>
                                                            <?php 
                                                            // নির্বাচিত ক্লাসের শাখাগুলো লোড করুন
                                                            $class_sections = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? AND status='active'");
                                                            $class_sections->execute([$student['class_id']]);
                                                            $sections = $class_sections->fetchAll();
                                                            
                                                            foreach($sections as $section): ?>
                                                                <option value="<?php echo $section['id']; ?>" <?php echo ($student['section_id'] == $section['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo $section['name']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="roll_number">রোল নম্বর *</label>
                                                        <input type="number" class="form-control" id="roll_number" name="roll_number" value="<?php echo $student['roll_number']; ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="status">স্ট্যাটাস</label>
                                                <select class="form-control" id="status" name="status">
                                                    <option value="active" <?php echo ($student['status'] == 'active') ? 'selected' : ''; ?>>সক্রিয়</option>
                                                    <option value="inactive" <?php echo ($student['status'] == 'inactive') ? 'selected' : ''; ?>>নিষ্ক্রিয়</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="form-group text-center">
                                                <label for="photo">ছবি</label>
                                                <div class="text-center mb-3">
                                                    <?php if(!empty($student['photo'])): ?>
                                                        <img src="../uploads/students/<?php echo $student['photo']; ?>" class="student-photo mb-2" alt="শিক্ষার্থীর ছবি">
                                                    <?php else: ?>
                                                        <img src="https://via.placeholder.com/150" class="student-photo mb-2" alt="ছবি নেই">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="custom-file">
                                                    <input type="file" class="custom-file-input" id="photo" name="photo" accept="image/*">
                                                    <label class="custom-file-label" for="photo">নতুন ছবি নির্বাচন করুন</label>
                                                </div>
                                                <small class="form-text text-muted">সর্বোচ্চ সাইজ: 2MB, ফরম্যাট: JPG, PNG, GIF</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group text-center mt-4">
                                        <button type="submit" name="update_student" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save"></i> তথ্য আপডেট করুন
                                        </button>
                                        <a href="<?php echo BASE_URL; ?>students.php" class="btn btn-secondary btn-lg">
                                            <i class="fas fa-times"></i> বাতিল
                                        </a>
                                    </div>
                                </form>
                            </div>
                            <!-- /.card-body -->
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Main Footer -->
    <?php include 'inc/footer.php'; ?>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
    $(document).ready(function() {
        // Custom file input
        $('.custom-file-input').on('change', function() {
            let fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').addClass("selected").html(fileName);
            
            // ছবি প্রিভিউ
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    $('.student-photo').attr('src', e.target.result);
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });

        // অন্যান্য সম্পর্ক ফিল্ড দেখান/লুকান
        $('#guardian_relation').change(function() {
            if ($(this).val() === 'other') {
                $('#other_relation_field').show();
            } else {
                $('#other_relation_field').hide();
            }
            
            // অভিভাবক নাম ফিল্ড দেখান/লুকান
            if ($(this).val() === 'পিতা' || $(this).val() === 'মাতা') {
                $('#guardian_name_field').hide();
                $('#guardian_name').removeAttr('required');
            } else if ($(this).val() !== '' && $(this).val() !== 'other') {
                $('#guardian_name_field').show();
                $('#guardian_name').attr('required', 'required');
            } else {
                $('#guardian_name_field').hide();
                $('#guardian_name').removeAttr('required');
            }
        });
        
        // পৃষ্ঠা লোড হওয়ার সময় সম্পর্ক ফিল্ড চেক করুন
        if ($('#guardian_relation').val() !== 'পিতা' && $('#guardian_relation').val() !== 'মাতা' && $('#guardian_relation').val() !== '' && $('#guardian_relation').val() !== 'other') {
            $('#guardian_name_field').show();
            $('#guardian_name').attr('required', 'required');
        }

        // ঠিকানা অনুলিপি করুন
        $('#copyAddress').click(function() {
            $('#permanent_address').val($('#present_address').val());
        });
        
        // ক্লাস পরিবর্তন হলে শাখা লোড করুন
        $('#class_id').change(function() {
            var class_id = $(this).val();
            if (class_id) {
                $.ajax({
                    url: 'edit_student.php',
                    type: 'GET',
                    data: {class_id: class_id},
                    success: function(data) {
                        var sections = JSON.parse(data);
                        $('#section_id').empty();
                        $('#section_id').append('<option value="">নির্বাচন করুন</option>');
                        
                        $.each(sections, function(key, value) {
                            $('#section_id').append('<option value="'+ value.id +'">'+ value.name +'</option>');
                        });
                    }
                });
            } else {
                $('#section_id').empty();
                $('#section_id').append('<option value="">নির্বাচন করুন</option>');
            }
        });
    });
</script>
</body>
</html>