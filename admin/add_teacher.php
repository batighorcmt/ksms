<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../login.php');
}

// ক্লাস এবং বিষয় ডেটা লোড করুন
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects WHERE status='active'")->fetchAll();

// ফর্ম সাবমিট হ্যান্ডলিং
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_teacher'])) {
    // প্রাথমিক ডেটা সংগ্রহ
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $father_name = trim($_POST['father_name']);
    $mother_name = trim($_POST['mother_name']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $blood_group = $_POST['blood_group'];
    $religion = $_POST['religion'];
    $joining_date = $_POST['joining_date'];
    $qualification = trim($_POST['qualification']);
    $experience = trim($_POST['experience']);
    
    // নির্বাচিত ক্লাস এবং বিষয়
    $selected_classes = isset($_POST['classes']) ? $_POST['classes'] : [];
    $selected_subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    
    // ভ্যালিডেশন
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "ইউজারনেম প্রয়োজন";
    } else {
        // চেক করুন যে ইউজারনেম ইতিমধ্যে আছে কিনা
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "এই ইউজারনেম ইতিমধ্যে ব্যবহৃত হয়েছে";
        }
    }
    
    if (empty($password)) {
        $errors[] = "পাসওয়ার্ড প্রয়োজন";
    } elseif (strlen($password) < 6) {
        $errors[] = "পাসওয়ার্ড অন্তত ৬ অক্ষরের হতে হবে";
    } elseif ($password !== $confirm_password) {
        $errors[] = "পাসওয়ার্ড মেলে না";
    }
    
    if (empty($full_name)) {
        $errors[] = "পুরো নাম প্রয়োজন";
    }
    
    if (empty($email)) {
        $errors[] = "ইমেইল প্রয়োজন";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "সঠিক ইমেইল ঠিকানা দিন";
    } else {
        // চেক করুন যে ইমেইল ইতিমধ্যে আছে কিনা
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "এই ইমেইল ইতিমধ্যে ব্যবহৃত হয়েছে";
        }
    }
    
    if (empty($phone)) {
        $errors[] = "মোবাইল নম্বর প্রয়োজন";
    }
    
    // যদি কোনো ভুল না থাকে
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // পাসওয়ার্ড হ্যাশ করা
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // users টেবিলে ইনসার্ট
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, role, email, phone, full_name, address, status)
                VALUES (?, ?, 'teacher', ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$username, $hashed_password, $email, $phone, $full_name, $address]);
            $teacher_id = $pdo->lastInsertId();
            
            // teacher_profiles টেবিলে ইনসার্ট
            $insert_profile = $pdo->prepare("
                INSERT INTO teacher_profiles 
                (teacher_id, father_name, mother_name, date_of_birth, gender, 
                 blood_group, religion, address, joining_date, qualification, experience)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_profile->execute([
                $teacher_id, $father_name, $mother_name, $date_of_birth, $gender, 
                $blood_group, $religion, $address, $joining_date, $qualification, $experience
            ]);
            
            // ছবি আপলোড হ্যান্ডলিং
            if (!empty($_FILES['photo']['name'])) {
                $upload_dir = '../uploads/teachers/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = time() . '_' . basename($_FILES['photo']['name']);
                $target_file = $upload_dir . $file_name;
                
                // ফাইল আপলোড করুন
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                    // users টেবিলে ছবির নাম আপডেট করুন
                    $photo_stmt = $pdo->prepare("UPDATE users SET photo = ? WHERE id = ?");
                    $photo_stmt->execute([$file_name, $teacher_id]);
                }
            }
            
            // ক্লাস বরাদ্দ
            if (!empty($selected_classes)) {
                $insert_class = $pdo->prepare("INSERT INTO class_teachers (teacher_id, class_id) VALUES (?, ?)");
                foreach ($selected_classes as $class_id) {
                    $insert_class->execute([$teacher_id, $class_id]);
                }
            }
            
            // বিষয় বরাদ্দ
            if (!empty($selected_subjects)) {
                $insert_subject = $pdo->prepare("
                    INSERT INTO class_subject_teachers (teacher_id, class_id, subject_id) 
                    VALUES (?, ?, ?)
                ");
                
                foreach ($selected_subjects as $class_subject) {
                    list($class_id, $subject_id) = explode('_', $class_subject);
                    $insert_subject->execute([$teacher_id, $class_id, $subject_id]);
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success'] = "শিক্ষক সফলভাবে যোগ করা হয়েছে!";
            redirect("teacher_details.php?id=" . $teacher_id);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "শিক্ষক যোগ করতে সমস্যা হয়েছে: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>নতুন শিক্ষক যোগ - কিন্ডার গার্ডেন</title>

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
        .teacher-profile-img {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid #dee2e6;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .profile-header {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .info-card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        .info-card .card-header {
            background: linear-gradient(45deg, #f8f9fc, #e3e6f0);
            color: #4e73df;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
        }
        .checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e3e6f0;
            border-radius: 4px;
            padding: 10px;
        }
        .checkbox-group label {
            display: block;
            margin-bottom: 8px;
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
                        <h1 class="m-0 text-dark">নতুন শিক্ষক যোগ</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/teachers.php">শিক্ষক ব্যবস্থাপনা</a></li>
                            <li class="breadcrumb-item active">নতুন শিক্ষক যোগ</li>
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
                        <div class="card info-card">
                            <div class="card-header">
                                <h3 class="card-title">নতুন শিক্ষক তথ্য যোগ করুন</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group text-center">
                                                <label for="photo">ছবি</label>
                                                <div class="text-center mb-3">
                                                    <div class="teacher-profile-img bg-light d-flex align-items-center justify-content-center mb-2">
                                                        <i class="fas fa-user text-muted fa-5x"></i>
                                                    </div>
                                                </div>
                                                <div class="custom-file">
                                                    <input type="file" class="custom-file-input" id="photo" name="photo" accept="image/*">
                                                    <label class="custom-file-label" for="photo">ছবি নির্বাচন করুন</label>
                                                </div>
                                                <small class="form-text text-muted">সর্বোচ্চ সাইজ: 2MB, ফরম্যাট: JPG, PNG, GIF</small>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="username">ইউজারনেম *</label>
                                                        <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="full_name">পুরো নাম *</label>
                                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo isset($_POST['full_name']) ? $_POST['full_name'] : ''; ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="email">ইমেইল *</label>
                                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="phone">মোবাইল নম্বর *</label>
                                                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? $_POST['phone'] : ''; ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="password">পাসওয়ার্ড *</label>
                                                        <input type="password" class="form-control" id="password" name="password" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="confirm_password">পাসওয়ার্ড নিশ্চিত করুন *</label>
                                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="address">ঠিকানা</label>
                                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo isset($_POST['address']) ? $_POST['address'] : ''; ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <h4>ব্যক্তিগত তথ্য</h4>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="father_name">পিতার নাম</label>
                                                <input type="text" class="form-control" id="father_name" name="father_name" value="<?php echo isset($_POST['father_name']) ? $_POST['father_name'] : ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="mother_name">মাতার নাম</label>
                                                <input type="text" class="form-control" id="mother_name" name="mother_name" value="<?php echo isset($_POST['mother_name']) ? $_POST['mother_name'] : ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="date_of_birth">জন্ম তারিখ</label>
                                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="gender">লিঙ্গ</label>
                                                <select class="form-control" id="gender" name="gender">
                                                    <option value="">নির্বাচন করুন</option>
                                                    <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : ''; ?>>পুরুষ</option>
                                                    <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : ''; ?>>মহিলা</option>
                                                    <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'other') ? 'selected' : ''; ?>>অন্যান্য</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="blood_group">রক্তের গ্রুপ</label>
                                                <select class="form-control" id="blood_group" name="blood_group">
                                                    <option value="">নির্বাচন করুন</option>
                                                    <option value="A+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'A+') ? 'selected' : ''; ?>>A+</option>
                                                    <option value="A-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'A-') ? 'selected' : ''; ?>>A-</option>
                                                    <option value="B+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                                                    <option value="B-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'B-') ? 'selected' : ''; ?>>B-</option>
                                                    <option value="AB+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                                    <option value="AB-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                                    <option value="O+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'O+') ? 'selected' : ''; ?>>O+</option>
                                                    <option value="O-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'O-') ? 'selected' : ''; ?>>O-</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="religion">ধর্ম</label>
                                                <select class="form-control" id="religion" name="religion">
                                                    <option value="">নির্বাচন করুন</option>
                                                    <option value="Islam" <?php echo (isset($_POST['religion']) && $_POST['religion'] == 'Islam') ? 'selected' : ''; ?>>ইসলাম</option>
                                                    <option value="Hinduism" <?php echo (isset($_POST['religion']) && $_POST['religion'] == 'Hinduism') ? 'selected' : ''; ?>>হিন্দু</option>
                                                    <option value="Christianity" <?php echo (isset($_POST['religion']) && $_POST['religion'] == 'Christianity') ? 'selected' : ''; ?>>খ্রিস্টান</option>
                                                    <option value="Buddhism" <?php echo (isset($_POST['religion']) && $_POST['religion'] == 'Buddhism') ? 'selected' : ''; ?>>বৌদ্ধ</option>
                                                    <option value="Other" <?php echo (isset($_POST['religion']) && $_POST['religion'] == 'Other') ? 'selected' : ''; ?>>অন্যান্য</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="joining_date">যোগদানের তারিখ</label>
                                                <input type="date" class="form-control" id="joining_date" name="joining_date" value="<?php echo isset($_POST['joining_date']) ? $_POST['joining_date'] : ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <h4>পেশাগত তথ্য</h4>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="qualification">শিক্ষাগত যোগ্যতা</label>
                                                <input type="text" class="form-control" id="qualification" name="qualification" value="<?php echo isset($_POST['qualification']) ? $_POST['qualification'] : ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="experience">অভিজ্ঞতা</label>
                                                <input type="text" class="form-control" id="experience" name="experience" value="<?php echo isset($_POST['experience']) ? $_POST['experience'] : ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <h4>ক্লাস এবং বিষয় বরাদ্দ</h4>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="classes">ক্লাস নির্বাচন করুন</label>
                                                <div class="checkbox-group">
                                                    <?php foreach($classes as $class): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="classes[]" value="<?php echo $class['id']; ?>">
                                                            <label class="form-check-label"><?php echo $class['name']; ?></label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="subjects">বিষয় নির্বাচন করুন (ক্লাস অনুযায়ী)</label>
                                                <div class="checkbox-group">
                                                    <?php foreach($classes as $class): ?>
                                                        <h6 class="mt-2"><?php echo $class['name']; ?></h6>
                                                        <?php foreach($subjects as $subject): ?>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $class['id'] . '_' . $subject['id']; ?>">
                                                                <label class="form-check-label"><?php echo $subject['name']; ?></label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group text-center mt-4">
                                        <button type="submit" name="add_teacher" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save"></i> শিক্ষক যোগ করুন
                                        </button>
                                        <a href="teachers.php" class="btn btn-secondary btn-lg">
                                            <i class="fas fa-times"></i> বাতিল
                                        </a>
                                    </div>
                                </form>
                            </div>
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
                    $('.teacher-profile-img').attr('src', e.target.result);
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
</script>
</body>
</html>