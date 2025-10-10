<?php
require_once '../config.php';

// Auth
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('../index.php');
}


// Load helper data
$classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();
$relations = $pdo->query("SELECT * FROM guardian_relations ORDER BY id ASC")->fetchAll();
// Load academic years
$years = $pdo->query("SELECT * FROM academic_years ORDER BY year DESC")->fetchAll();
$current_year = $pdo->query("SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch();

$errors = [];
$success = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'সিকিউরিটি ভেরিফিকেশন ব্যর্থ হয়েছে। দয়া করে পুনরায় চেষ্টা করুন।';
    } else {
        // Basic sanitization
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $father_name = trim($_POST['father_name'] ?? '');
        $mother_name = trim($_POST['mother_name'] ?? '');
        $guardian_relation = trim($_POST['guardian_relation'] ?? '');
        $guardian_name = trim($_POST['guardian_name'] ?? '');
        $mobile_number = trim($_POST['mobile_number'] ?? '');
        $birth_certificate_no = trim($_POST['birth_certificate_no'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? null;
        $gender = trim($_POST['gender'] ?? '');
        $blood_group = trim($_POST['blood_group'] ?? '');
        $religion = trim($_POST['religion'] ?? '');
        $present_address = trim($_POST['present_address'] ?? '');
        $permanent_address = trim($_POST['permanent_address'] ?? '');
        $copy_address = isset($_POST['copy_address']);
        $class_id = intval($_POST['class_id'] ?? 0);
        $section_id = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
        $roll_number = trim($_POST['roll_number'] ?? '');
        $admission_date = $_POST['admission_date'] ?? null;
    // Year selection
    $year_id = !empty($_POST['year_id']) ? intval($_POST['year_id']) : ($current_year['id'] ?? null);

        // Resolve guardian_relation: it may be an id from guardian_relations table or a direct label
        $guardian_relation_label = $guardian_relation;
        if (is_numeric($guardian_relation) && intval($guardian_relation) > 0) {
            $gStmt = $pdo->prepare('SELECT * FROM guardian_relations WHERE id = ?');
            $gStmt->execute([intval($guardian_relation)]);
            $gRow = $gStmt->fetch();
            if ($gRow) {
                // prefer common name fields
                $guardian_relation_label = $gRow['name'] ?? $gRow['relation'] ?? $gRow['title'] ?? array_values($gRow)[1] ?? $guardian_relation_label;
            }
        }

        // Guardian logic validation: detect father/mother by label
        $lowerRel = mb_strtolower($guardian_relation_label ?? '');
        if (mb_stripos($lowerRel, 'পিতা') !== false || mb_stripos($lowerRel, 'father') !== false) {
            $guardian_name = $father_name;
        } elseif (mb_stripos($lowerRel, 'মাতা') !== false || mb_stripos($lowerRel, 'mother') !== false) {
            $guardian_name = $mother_name;
        }

        if (!(mb_stripos($lowerRel, 'পিতা') !== false || mb_stripos($lowerRel, 'মাতা') !== false)) {
            if ($guardian_name === '') {
                $errors[] = 'অভিভাবকের নাম প্রদান করতে হবে।';
            }
            if ($mobile_number === '') {
                $errors[] = 'অভিভাবকের মোবাইল নম্বর প্রদান করতে হবে।';
            }
        }

        // address validation
        if ($present_address === '') {
            $errors[] = 'বর্তমান ঠিকানা প্রদান করুন।';
        }
        if ($copy_address) {
            $permanent_address = $present_address;
        }

        if ($first_name === '') { $errors[] = 'শিক্ষার্থীর প্রথম নাম প্রয়োজন।'; }
        if ($class_id <= 0) { $errors[] = 'শ্রেণি নির্বাচন করুন।'; }
        if ($date_of_birth === null || $date_of_birth === '') { $errors[] = 'জন্ম তারিখ প্রদান করুন।'; }
        if ($gender === '') { $errors[] = 'জেন্ডার নির্বাচন করুন।'; }

        // Validate mobile number format
        if (!empty($mobile_number) && !preg_match('/^01[3-9]\d{8}$/', $mobile_number)) {
            $errors[] = 'সঠিক মোবাইল নম্বর প্রদান করুন (১১ ডিজিট, 01 দিয়ে শুরু)';
        }

        // Photo upload handling
        $photo_file = null;
        if (!empty($_FILES['photo']['name'])) {
            $f = $_FILES['photo'];
            if ($f['error'] === 0) {
                $allowed = ['image/jpeg','image/png','image/webp'];
                $maxFileSize = 2 * 1024 * 1024; // 2MB
                
                if ($f['size'] > $maxFileSize) {
                    $errors[] = 'ছবির আকার 2MB এর কম হতে হবে।';
                } elseif (!in_array($f['type'], $allowed)) {
                    $errors[] = 'ছবি অবশ্যই jpg/png/webp ফরম্যাটে হতে হবে।';
                } else {
                    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                    $photo_file = 'stu_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                    $dst = __DIR__ . '/../uploads/students/' . $photo_file;
                    if (!is_dir(dirname($dst))) { 
                        mkdir(dirname($dst), 0755, true); 
                    }
                    if (!move_uploaded_file($f['tmp_name'], $dst)) {
                        $errors[] = 'ছবি আপলোড করতে ব্যর্থ হয়েছে।';
                    }
                }
            } else {
                $errors[] = 'ছবি আপলোডে ত্রুটি।';
            }
        }

        if (empty($errors)) {
            // Wrap student + guardian-user creation in a transaction
            try {

                $pdo->beginTransaction();

                // Generate student id and default password (hashed)
                $student_id = 'STU' . date('Y') . str_pad(rand(0,9999),4,'0',STR_PAD_LEFT);
                $default_password = '123456';
                $password_hash = password_hash($default_password, PASSWORD_DEFAULT);

                // Insert student with year_id
                $stmt = $pdo->prepare("INSERT INTO students
                    (student_id, first_name, last_name, father_name, mother_name, guardian_relation, birth_certificate_no, date_of_birth, gender, blood_group, religion, present_address, permanent_address, mobile_number, address, city, country, photo, class_id, section_id, roll_number, admission_date, year_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $ok = $stmt->execute([
                    $student_id,
                    $first_name,
                    $last_name,
                    $father_name ?: null,
                    $mother_name ?: null,
                    $guardian_relation_label ?: null,
                    $birth_certificate_no ?: null,
                    $date_of_birth ?: null,
                    $gender,
                    $blood_group ?: null,
                    $religion ?: null,
                    $present_address ?: null,
                    $permanent_address ?: null,
                    $mobile_number ?: null,
                    null, // address
                    null, // city
                    null, // country
                    $photo_file ?: null,
                    $class_id,
                    $section_id ?: 0,
                    $roll_number !== '' ? intval($roll_number) : null,
                    $admission_date ?: null,
                    $year_id
                ]);

                if (!$ok) throw new Exception('স্টুডেন্ট তথ্য সংরক্ষণে ব্যর্থ।');

                $studentDbId = $pdo->lastInsertId();

                // create guardian user if guardian_name and mobile present
                $guardianCreated = false;
                $guardianUsername = $student_id;
                // check username collision
                $uCheck = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                $uCheck->execute([$guardianUsername]);
                if (!$uCheck->fetch()) {
                    $uInsert = $pdo->prepare("INSERT INTO users (username, password, role, email, phone, full_name, address, status) VALUES (?, ?, 'guardian', ?, ?, ?, ?, 1)");
                    $uOk = $uInsert->execute([$guardianUsername, $password_hash, '', $mobile_number ?: null, $guardian_name ?: null, $present_address ?: null]);
                    if ($uOk) {
                        $guardianCreated = true;
                        $guardianUserId = $pdo->lastInsertId();
                        // update student record with guardian_id
                        $upd = $pdo->prepare('UPDATE students SET guardian_id = ? WHERE id = ?');
                        $upd->execute([$guardianUserId, $studentDbId]);
                    }
                }

                $pdo->commit();

                $success = "শিক্ষার্থী সফলভাবে যোগ করা হয়েছে।<br>Student ID: <strong>{$student_id}</strong><br>Default password: <strong>{$default_password}</strong>";
                if ($guardianCreated) {
                    $success .= "<br>গার্ডিয়ান অ্যাকাউন্ট তৈরি হয়েছে (Username: <strong>{$guardianUsername}</strong>, Password: <strong>{$default_password}</strong>)";
                }
                // reset form values
                $_POST = [];

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'ডাটাবেসে সংরক্ষণ করতে সমস্যা হয়েছে: ' . $e->getMessage();
            }
        }
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Minimal helper to fetch sections for class (for initial page render)
$sectionsAll = $pdo->query("SELECT * FROM sections ORDER BY name ASC")->fetchAll();

?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>নতুন শিক্ষার্থী যোগ করুন</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AdminLTE style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #6f42c1;
            --success-color: #1cc88a;
        }
        body {
            font-family: SolaimanLipi, Arial, sans-serif;
            background-color: #f8f9fc;
        }
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: none;
            border-radius: 10px;
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 700;
        }
        .form-control, .form-select {
            border-radius: 6px;
            padding: 10px 15px;
            border: 1px solid #d1d3e2;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 6px;
            padding: 10px 20px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #3a5fc8;
            border-color: #3a5fc8;
        }
        .photo-preview {
            max-width: 160px;
            max-height: 160px;
            display: block;
            margin-top: 8px;
            border: 1px solid #ccc;
            padding: 3px;
            border-radius: 6px;
            object-fit: cover;
        }
        .step-progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .step-progress::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #e3e6f0;
            transform: translateY(-50%);
            z-index: 1;
        }
        .step {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e3e6f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
            z-index: 2;
        }
        .step.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .step-label {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 8px;
            white-space: nowrap;
            font-size: 0.85rem;
            color: #858796;
        }
        .form-section {
            display: none;
        }
        .form-section.active {
            display: block;
            animation: fadeIn 0.5s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .nav-tabs .nav-link {
            border: none;
            color: #858796;
            font-weight: 600;
            padding: 12px 20px;
        }
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background: transparent;
        }
        .required-label::after {
            content: '*';
            color: red;
            margin-left: 3px;
        }
        .input-group-text {
            background-color: #eaecf4;
            border: 1px solid #d1d3e2;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Navbar -->
    <?php include 'inc/header.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php
    if (hasRole(['super_admin'])) {
        include 'inc/sidebar.php';
    } elseif (hasRole(['teacher'])) {
        include '../teacher/inc/sidebar.php';
    }
    ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid py-4">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h3 class="card-title">নতুন শিক্ষার্থী যোগ করুন</h3>
                                <a href="students.php" class="btn btn-light btn-sm">
                                    <i class="fas fa-arrow-left mr-1"></i> <span style="color:#fff;background:#4e73df;padding:2px 10px;border-radius:5px;font-weight:700;box-shadow:0 2px 8px #4e73df55;"></span>শিক্ষার্থী তালিকা</span>
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if(!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                        <h5><i class="icon fas fa-exclamation-triangle"></i> কিছু ত্রুটি রয়েছে!</h5>
                                        <ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
                                    </div>
                                <?php endif; ?>

                                <?php if($success): ?>
                                    <div class="alert alert-success">
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                        <h5><i class="icon fas fa-check"></i> সফল!</h5>
                                        <?php echo $success; ?>
                                    </div>
                                <?php endif; ?>

                                <ul class="nav nav-tabs mb-4" id="studentFormTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab" aria-controls="basic" aria-selected="true">
                                            <i class="fas fa-user-graduate mr-1"></i> প্রাথমিক তথ্য
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="guardian-tab" data-bs-toggle="tab" data-bs-target="#guardian" type="button" role="tab" aria-controls="guardian" aria-selected="false">
                                            <i class="fas fa-users mr-1"></i> অভিভাবক তথ্য
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academic" type="button" role="tab" aria-controls="academic" aria-selected="false">
                                            <i class="fas fa-book mr-1"></i> একাডেমিক তথ্য
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="other-tab" data-bs-toggle="tab" data-bs-target="#other" type="button" role="tab" aria-controls="other" aria-selected="false">
                                            <i class="fas fa-info-circle mr-1"></i> অন্যান্য তথ্য
                                        </button>
                                    </li>
                                </ul>

                                <form method="post" enctype="multipart/form-data" id="addStudentForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    
                                    <div class="tab-content" id="studentFormTabContent">
                                        <!-- Basic Info Tab -->
                                        <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="basic-tab">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="required-label">প্রথম নাম</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                            <input name="first_name" class="form-control" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>শেষ নাম</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                            <input name="last_name" class="form-control" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="required-label">জন্ম তারিখ</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                                            <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>" required>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label class="required-label">লিঙ্গ</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                                                            <select name="gender" class="form-control" required>
                                                                <option value="">নির্বাচন করুন</option>
                                                                <option value="male" <?php if(!empty($_POST['gender']) && $_POST['gender']=='male') echo 'selected'; ?>>পুরুষ</option>
                                                                <option value="female" <?php if(!empty($_POST['gender']) && $_POST['gender']=='female') echo 'selected'; ?>>মহিলা</option>
                                                                <option value="other" <?php if(!empty($_POST['gender']) && $_POST['gender']=='other') echo 'selected'; ?>>অন্যান্য</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label>জন্ম সনদের নং</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                            <input name="birth_certificate_no" class="form-control" value="<?php echo htmlspecialchars($_POST['birth_certificate_no'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label>রক্তের গ্রুপ</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-tint"></i></span>
                                                            <select name="blood_group" class="form-control">
                                                                <option value="">নির্বাচন করুন</option>
                                                                <?php $bgs = ['A+','A-','B+','B-','AB+','AB-','O+','O-']; foreach($bgs as $bg): ?>
                                                                    <option value="<?php echo $bg; ?>" <?php if(!empty($_POST['blood_group']) && $_POST['blood_group']==$bg) echo 'selected'; ?>><?php echo $bg; ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label>ধর্ম</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-pray"></i></span>
                                                            <select name="religion" class="form-control">
                                                                <option value="">নির্বাচন করুন</option>
                                                                <?php $religions = ['ইসলাম','হিন্দু','বৌদ্ধ','খ্রিস্টান','অন্যান্য']; foreach($religions as $r): ?>
                                                                    <option value="<?php echo $r; ?>" <?php if(!empty($_POST['religion']) && $_POST['religion']==$r) echo 'selected'; ?>><?php echo $r; ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>ছবি আপলোড</label>
                                                        <div class="custom-file">
                                                            <input type="file" name="photo" id="photo" accept="image/*" class="custom-file-input">
                                                            <label class="custom-file-label" for="photo">ছবি নির্বাচন করুন</label>
                                                        </div>
                                                        <img id="photoPreview" class="photo-preview mt-2" style="display:none">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Guardian Info Tab -->
                                        <div class="tab-pane fade" id="guardian" role="tabpanel" aria-labelledby="guardian-tab">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>পিতার নাম</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-male"></i></span>
                                                            <input name="father_name" id="father_name" class="form-control" value="<?php echo htmlspecialchars($_POST['father_name'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>মাতার নাম</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-female"></i></span>
                                                            <input name="mother_name" id="mother_name" class="form-control" value="<?php echo htmlspecialchars($_POST['mother_name'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="required-label">অভিভাবকের সম্পর্ক</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-user-friends"></i></span>
                                                            <select name="guardian_relation" id="guardian_relation" class="form-control" required>
                                                                <option value="">নির্বাচন করুন</option>
                                                                <?php foreach($relations as $rel): ?>
                                                                    <option value="<?php echo $rel['id']; ?>" <?php if(!empty($_POST['guardian_relation']) && $_POST['guardian_relation']==$rel['id']) echo 'selected'; ?>><?php echo htmlspecialchars($rel['name'] ?? $rel['relation'] ?? $rel['title']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>অভিভাবকের নাম</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                            <input name="guardian_name" id="guardian_name" class="form-control" value="<?php echo htmlspecialchars($_POST['guardian_name'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>অভিভাবকের মোবাইল</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                                            <input name="mobile_number" id="mobile_number" class="form-control" value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>" placeholder="01xxxxxxxxx">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="required-label">বর্তমান ঠিকানা</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-home"></i></span>
                                                            <textarea name="present_address" id="present_address" class="form-control" rows="2" required><?php echo htmlspecialchars($_POST['present_address'] ?? ''); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>স্থায়ী ঠিকানা</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-home"></i></span>
                                                            <textarea name="permanent_address" id="permanent_address" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['permanent_address'] ?? ''); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="checkbox" name="copy_address" id="copy_address" <?php if(!empty($_POST['copy_address'])) echo 'checked'; ?>>
                                                        <label class="form-check-label" for="copy_address">বর্তমান ঠিকানা স্থায়ী ঠিকানায় কপি করুন</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Academic Info Tab -->
                                        <div class="tab-pane fade" id="academic" role="tabpanel" aria-labelledby="academic-tab">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="required-label">শ্রেণি</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                                                            <select name="class_id" id="class_id" class="form-control" required>
                                                                <option value="">নির্বাচন করুন</option>
                                                                <?php foreach($classes as $c): ?>
                                                                    <option value="<?php echo $c['id']; ?>" <?php if(!empty($_POST['class_id']) && $_POST['class_id']==$c['id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>শাখা</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-chalkboard"></i></span>
                                                            <select name="section_id" id="section_id" class="form-control" style="display:none;">
                                                                <option value="">নির্বাচন করুন</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>রোল নম্বর</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-sort-numeric-down"></i></span>
                                                            <input name="roll_number" class="form-control" value="<?php echo htmlspecialchars($_POST['roll_number'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>ভর্তির তারিখ</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
                                                            <input type="date" name="admission_date" class="form-control" value="<?php echo htmlspecialchars($_POST['admission_date'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="required-label">সাল (Year)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                                            <select name="year_id" id="year_id" class="form-control" required>
                                                                <option value="">নির্বাচন করুন</option>
                                                                <?php foreach($years as $y): ?>
                                                                    <option value="<?php echo $y['id']; ?>" <?php echo (!empty($year_id) && $year_id == $y['id']) ? 'selected' : ((!empty($current_year['id']) && $current_year['id'] == $y['id']) ? 'selected' : ''); ?>><?php echo htmlspecialchars($y['year']); ?><?php echo ($y['is_current'] ? ' (বর্তমান)' : ''); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Other Info Tab -->
                                        <div class="tab-pane fade" id="other" role="tabpanel" aria-labelledby="other-tab">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i> নিশ্চিত করুন যে সমস্ত তথ্য সঠিকভাবে প্রদান করা হয়েছে। তথ্য জমা দেওয়ার পরে পরিবর্তন করা কঠিন হতে পারে।
                                            </div>
                                            
                                            <div class="card bg-light">
                                                <div class="card-header">
                                                    <h5 class="card-title mb-0">তথ্য পর্যালোচনা</h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p><strong>শিক্ষার্থীর নাম:</strong> <span id="reviewFirstName"><?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?></span> <span id="reviewLastName"><?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?></span></p>
                                                            <p><strong>জন্ম তারিখ:</strong> <span id="reviewDob"><?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?></span></p>
                                                            <p><strong>লিঙ্গ:</strong> <span id="reviewGender"><?php echo !empty($_POST['gender']) ? ($_POST['gender'] == 'male' ? 'পুরুষ' : ($_POST['gender'] == 'female' ? 'মহিলা' : 'অন্যান্য')) : ''; ?></span></p>
                                                            <p><strong>শ্রেণি:</strong> <span id="reviewClass">
                                                                <?php 
                                                                if(!empty($_POST['class_id'])) {
                                                                    foreach($classes as $c) {
                                                                        if($c['id'] == $_POST['class_id']) {
                                                                            echo htmlspecialchars($c['name']);
                                                                            break;
                                                                        }
                                                                    }
                                                                }
                                                                ?>
                                                            </span></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>অভিভাবকের নাম:</strong> <span id="reviewGuardian"><?php echo htmlspecialchars($_POST['guardian_name'] ?? ''); ?></span></p>
                                                            <p><strong>সম্পর্ক:</strong> <span id="reviewRelation"><?php echo htmlspecialchars($_POST['guardian_relation'] ?? ''); ?></span></p>
                                                            <p><strong>মোবাইল নম্বর:</strong> <span id="reviewMobile"><?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?></span></p>
                                                            <p><strong>ঠিকানা:</strong> <span id="reviewAddress"><?php echo htmlspecialchars($_POST['present_address'] ?? ''); ?></span></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between mt-4">
                                        <button type="button" class="btn btn-secondary" id="prevBtn" style="display: none;">
                                            <i class="fas fa-arrow-left mr-1"></i> পূর্ববর্তী
                                        </button>
                                        <button type="button" class="btn btn-primary" id="nextBtn">
                                            পরবর্তী <i class="fas fa-arrow-right ml-1"></i>
                                        </button>
                                        <button class="btn btn-success" type="submit" id="submitBtn" style="display: none;">
                                            <i class="fas fa-check mr-1"></i> সংরক্ষণ করুন
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <p class="text-muted small mt-2">নোট: ডিফল্ট পাসওয়ার্ড হবে <strong>123456</strong></p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Main Footer -->
    <?php include 'inc/footer.php'; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
$(function(){
    // Form tabs navigation
    let currentTab = 0;
    const tabs = $('#studentFormTabs .nav-link');
    const tabPanes = $('.tab-pane');
    
    function showTab(n) {
        tabs.each(function(i) {
            if (i === n) {
                $(this).tab('show');
                $(this).removeClass('disabled');
            }
        });
        
        // Show/hide navigation buttons
        if (n === 0) {
            $('#prevBtn').hide();
        } else {
            $('#prevBtn').show();
        }
        
        if (n === (tabs.length - 1)) {
            $('#nextBtn').hide();
            $('#submitBtn').show();
        } else {
            $('#nextBtn').show();
            $('#submitBtn').hide();
        }
        
        currentTab = n;
    }
    
    $('#nextBtn').click(function() {
        // Validate current tab before proceeding
        let valid = true;
        const inputs = $(tabPanes[currentTab]).find('input, select, textarea');
        
        inputs.each(function() {
            if ($(this).prop('required') && !$(this).val()) {
                $(this).addClass('is-invalid');
                valid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (valid) {
            showTab(currentTab + 1);
            updateReview();
        } else {
            // Scroll to first error
            $('html, body').animate({
                scrollTop: $('.is-invalid').first().offset().top - 100
            }, 500);
        }
    });
    
    $('#prevBtn').click(function() {
        showTab(currentTab - 1);
    });
    
    // Initialize first tab
    showTab(0);
    
    // Update review information
    function updateReview() {
        $('#reviewFirstName').text($('input[name="first_name"]').val());
        $('#reviewLastName').text($('input[name="last_name"]').val());
        $('#reviewDob').text($('input[name="date_of_birth"]').val());
        $('#reviewGender').text($('select[name="gender"] option:selected').text());
        $('#reviewGuardian').text($('input[name="guardian_name"]').val());
        $('#reviewRelation').text($('select[name="guardian_relation"] option:selected').text());
        $('#reviewMobile').text($('input[name="mobile_number"]').val());
        $('#reviewAddress').text($('textarea[name="present_address"]').val());
        
        const classId = $('select[name="class_id"]').val();
        $('#reviewClass').text($('select[name="class_id"] option[value="' + classId + '"]').text());
    }
    
    // Guardian relation logic
    function updateGuardianFields(){
        var relText = $('#guardian_relation option:selected').text().trim();
        if(relText === 'পিতা'){
            $('#guardian_name').val($('#father_name').val()).prop('disabled', true).prop('required', false);
        } else if(relText === 'মাতা'){
            $('#guardian_name').val($('#mother_name').val()).prop('disabled', true).prop('required', false);
        } else {
            $('#guardian_name').val('').prop('disabled', false).prop('required', true);
        }
    }

    $('#guardian_relation').on('change', updateGuardianFields);
    $('#father_name,#mother_name').on('input', updateGuardianFields);
    updateGuardianFields();

    // copy present -> permanent address
    function syncAddresses(copy){
        if(copy){
            $('#permanent_address').val($('#present_address').val()).prop('readonly', true);
        } else {
            $('#permanent_address').prop('readonly', false);
        }
    }

    $('#copy_address').on('change', function(){
        syncAddresses($(this).is(':checked'));
    });
    // when present address changes and copy is checked, update permanent
    $('#present_address').on('input', function(){
        if($('#copy_address').is(':checked')) $('#permanent_address').val($(this).val());
    });
    // initialize copy state
    syncAddresses($('#copy_address').is(':checked'));

    // photo preview
    $('#photo').on('change', function(e){
        var file = this.files[0];
        if(!file) return;
        var reader = new FileReader();
        reader.onload = function(ev){
            $('#photoPreview').attr('src', ev.target.result).show();
        }
        reader.readAsDataURL(file);
        
        // Update custom file label
        $(this).next('.custom-file-label').text(file.name);
    });

    // AJAX: Show section only after class is selected
    $('#class_id').on('change', function(){
        var cid = $(this).val();
        if(cid){
            $('#section_id').show();
            $.get('get_sections.php?class_id='+cid, function(data){
                $('#section_id').html(data);
            });
        }else{
            $('#section_id').hide().html('<option value="">নির্বাচন করুন</option>');
        }
    });
    // On page load, if class is selected, trigger change
    if($('#class_id').val()) $('#class_id').trigger('change');
    
    // Real-time validation
    $('input, select, textarea').on('change input', function() {
        if ($(this).prop('required') && !$(this).val()) {
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
    
    // Mobile number validation
    $('#mobile_number').on('input', function() {
        const mobile = $(this).val();
        if (mobile && !/^01[3-9]\d{8}$/.test(mobile)) {
            $(this).addClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
            $(this).after('<div class="invalid-feedback">সঠিক মোবাইল নম্বর প্রদান করুন (১১ ডিজিট, 01 দিয়ে শুরু)</div>');
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
});
</script>
</body>
</html>