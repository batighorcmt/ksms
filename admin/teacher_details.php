<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// শিক্ষক আইডি পান
$teacher_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// শিক্ষকের ডেটা লোড করুন
$stmt = $pdo->prepare("
    SELECT u.*, 
           tp.father_name, tp.mother_name, tp.date_of_birth, tp.gender, 
           tp.blood_group, tp.religion, tp.address, tp.joining_date, 
           tp.qualification, tp.experience
    FROM users u 
    LEFT JOIN teacher_profiles tp ON u.id = tp.teacher_id
    WHERE u.id = ? AND u.role = 'teacher'
");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    $_SESSION['error'] = "শিক্ষক পাওয়া যায়নি!";
    header('Location: teachers.php');
    exit();
}

// শিক্ষকের ক্লাসগুলো লোড করুন
$classes_stmt = $pdo->prepare("
    SELECT c.name as class_name, c.id as class_id
    FROM class_teachers ct
    JOIN classes c ON ct.class_id = c.id
    WHERE ct.teacher_id = ?
");
$classes_stmt->execute([$teacher_id]);
$teacher_classes = $classes_stmt->fetchAll();

// শিক্ষকের বিষয়গুলো লোড করুন
$subjects_stmt = $pdo->prepare("
    SELECT s.name as subject_name, c.name as class_name
    FROM class_subject_teachers cst
    JOIN subjects s ON cst.subject_id = s.id
    JOIN classes c ON cst.class_id = c.id
    WHERE cst.teacher_id = ?
");
$subjects_stmt->execute([$teacher_id]);
$teacher_subjects = $subjects_stmt->fetchAll();

// শিক্ষকের মোট ক্লাস এবং বিষয় সংখ্যা
$total_classes = count($teacher_classes);
$total_subjects = count($teacher_subjects);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষক বিবরণ - কিন্ডার গার্ডেন</title>

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
        .info-item {
            padding: 10px 0;
            border-bottom: 1px solid #e3e6f0;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #4e73df;
        }
        .badge-custom {
            font-size: 0.9em;
            padding: 0.5em 0.8em;
            border-radius: 4px;
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
                        <h1 class="m-0 text-dark">শিক্ষক বিবরণ</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/teachers.php">শিক্ষক ব্যবস্থাপনা</a></li>
                            <li class="breadcrumb-item active">শিক্ষক বিবরণ</li>
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
                    <div class="col-md-4">
                        <!-- শিক্ষক প্রোফাইল কার্ড -->
                        <div class="card info-card">
                            <div class="card-body text-center">
                                <?php if(!empty($teacher['photo'])): ?>
                                    <img src="../uploads/teachers/<?php echo $teacher['photo']; ?>" class="teacher-profile-img mb-3" alt="শিক্ষকের ছবি">
                                <?php else: ?>
                                    <div class="teacher-profile-img bg-light d-flex align-items-center justify-content-center mb-3">
                                        <i class="fas fa-user text-muted fa-5x"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <h3><?php echo $teacher['full_name']; ?></h3>
                                <p class="text-muted"><?php echo $teacher['username']; ?></p>
                                
                                <div class="mt-3">
                                    <?php if($teacher['status'] == 'active'): ?>
                                        <span class="badge badge-success badge-custom">সক্রিয়</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger badge-custom">নিষ্ক্রিয়</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="edit_teacher.php?id=<?php echo $teacher_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> সম্পাদনা
                                    </a>
                                    <a href="teachers.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> ফিরে যান
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- যোগাযোগের তথ্য -->
                        <div class="card info-card">
                            <div class="card-header">
                                <h3 class="card-title">যোগাযোগের তথ্য</h3>
                            </div>
                            <div class="card-body">
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-envelope mr-2"></i>ইমেইল:</span>
                                    <p class="mb-0"><?php echo $teacher['email'] ?? 'নেই'; ?></p>
                                </div>
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-phone mr-2"></i>মোবাইল:</span>
                                    <p class="mb-0"><?php echo $teacher['phone'] ?? 'নেই'; ?></p>
                                </div>
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-map-marker-alt mr-2"></i>ঠিকানা:</span>
                                    <p class="mb-0"><?php echo $teacher['address'] ?? 'নেই'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <!-- ব্যক্তিগত তথ্য -->
                        <div class="card info-card">
                            <div class="card-header">
                                <h3 class="card-title">ব্যক্তিগত তথ্য</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">পিতার নাম:</span>
                                            <p class="mb-0"><?php echo $teacher['father_name'] ?? 'নেই'; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">মাতার নাম:</span>
                                            <p class="mb-0"><?php echo $teacher['mother_name'] ?? 'নেই'; ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">জন্ম তারিখ:</span>
                                            <p class="mb-0"><?php echo $teacher['date_of_birth'] ? date('d/m/Y', strtotime($teacher['date_of_birth'])) : 'নেই'; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">লিঙ্গ:</span>
                                            <p class="mb-0">
                                                <?php 
                                                if(isset($teacher['gender'])) {
                                                    if($teacher['gender'] == 'male') echo 'পুরুষ';
                                                    elseif($teacher['gender'] == 'female') echo 'মহিলা';
                                                    else echo $teacher['gender'];
                                                } else {
                                                    echo 'নেই';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">রক্তের গ্রুপ:</span>
                                            <p class="mb-0"><?php echo $teacher['blood_group'] ?? 'নেই'; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">ধর্ম:</span>
                                            <p class="mb-0"><?php echo $teacher['religion'] ?? 'নেই'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- পেশাগত তথ্য -->
                        <div class="card info-card">
                            <div class="card-header">
                                <h3 class="card-title">পেশাগত তথ্য</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">যোগদানের তারিখ:</span>
                                            <p class="mb-0"><?php echo $teacher['joining_date'] ? date('d/m/Y', strtotime($teacher['joining_date'])) : 'নেই'; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">ক্লাস সংখ্যা:</span>
                                            <p class="mb-0"><span class="badge bg-info"><?php echo $total_classes; ?></span></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">শিক্ষাগত যোগ্যতা:</span>
                                    <p class="mb-0"><?php echo $teacher['qualification'] ?? 'নেই'; ?></p>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">অভিজ্ঞতা:</span>
                                    <p class="mb-0"><?php echo $teacher['experience'] ?? 'নেই'; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- ক্লাস এবং বিষয় তালিকা -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card info-card">
                                    <div class="card-header">
                                        <h3 class="card-title">ক্লাস তালিকা</h3>
                                    </div>
                                    <div class="card-body">
                                        <?php if($total_classes > 0): ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach($teacher_classes as $class): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?php echo $class['class_name']; ?>
                                                        <a href="class_details.php?id=<?php echo $class['class_id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-muted text-center">কোন ক্লাস বরাদ্দ নেই</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card info-card">
                                    <div class="card-header">
                                        <h3 class="card-title">বিষয় তালিকা</h3>
                                    </div>
                                    <div class="card-body">
                                        <?php if($total_subjects > 0): ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach($teacher_subjects as $subject): ?>
                                                    <li class="list-group-item">
                                                        <?php echo $subject['subject_name']; ?>
                                                        <small class="text-muted">(<?php echo $subject['class_name']; ?>)</small>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-muted text-center">কোন বিষয় বরাদ্দ নেই</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
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

</body>
</html>