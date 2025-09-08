<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../login.php');
}

// শ্রেণি আইডি পান
$class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// শ্রেণি ডেটা লোড করুন
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch();

if (!$class) {
    $_SESSION['error'] = "শ্রেণি পাওয়া যায়নি!";
    redirect('admin/classes.php');
}

// শিক্ষক লোড করুন
$teachers = $pdo->query("SELECT * FROM users WHERE role='teacher' AND status=1")->fetchAll();

// শ্রেণি আপডেট করুন
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_class'])) {
    $name = $_POST['name'];
    $numeric_value = $_POST['numeric_value'];
    $capacity = $_POST['capacity'];
    $room_number = $_POST['room_number'];
    $class_teacher_id = !empty($_POST['class_teacher_id']) ? $_POST['class_teacher_id'] : NULL;
    $monthly_fee = $_POST['monthly_fee'];
    $admission_fee = $_POST['admission_fee'];
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("
        UPDATE classes 
        SET name = ?, numeric_value = ?, capacity = ?, room_number = ?, 
            class_teacher_id = ?, monthly_fee = ?, admission_fee = ?, status = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([$name, $numeric_value, $capacity, $room_number, $class_teacher_id, $monthly_fee, $admission_fee, $status, $class_id])) {
        $_SESSION['success'] = "শ্রেণি সফলভাবে আপডেট করা হয়েছে!";
        redirect('admin/classes.php');
    } else {
        $_SESSION['error'] = "শ্রেণি আপডেট করতে সমস্যা occurred!";
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শ্রেণি সম্পাদনা - কিন্ডার গার্ডেন</title>

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
        .logo-custom {
            font-weight: bold;
            font-size: 22px;
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
                        <h1 class="m-0">শ্রেণি সম্পাদনা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="classes.php">শ্রেণি ব্যবস্থাপনা</a></li>
                            <li class="breadcrumb-item active">শ্রেণি সম্পাদনা</li>
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
                    <div class="col-md-8 mx-auto">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">শ্রেণি তথ্য সম্পাদনা</h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="name">শ্রেণির নাম *</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $class['name']; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="numeric_value">সংখ্যাগত মান *</label>
                                        <input type="number" class="form-control" id="numeric_value" name="numeric_value" value="<?php echo $class['numeric_value']; ?>" required>
                                        <small class="form-text text-muted">শ্রেণি বাছাই করার জন্য ব্যবহৃত হয় (যেমন: নার্সারি=0, কেজি-১=1)</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="capacity">ধারণক্ষমতা</label>
                                        <input type="number" class="form-control" id="capacity" name="capacity" value="<?php echo $class['capacity']; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="room_number">রুম নম্বর</label>
                                        <input type="text" class="form-control" id="room_number" name="room_number" value="<?php echo $class['room_number']; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="class_teacher_id">শ্রেণি শিক্ষক</label>
                                        <select class="form-control" id="class_teacher_id" name="class_teacher_id">
                                            <option value="">নির্বাচন করুন</option>
                                            <?php foreach($teachers as $teacher): ?>
                                                <option value="<?php echo $teacher['id']; ?>" <?php echo ($class['class_teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $teacher['full_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="monthly_fee">মাসিক ফি</label>
                                                <input type="number" step="0.01" class="form-control" id="monthly_fee" name="monthly_fee" value="<?php echo $class['monthly_fee']; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="admission_fee">ভর্তি ফি</label>
                                                <input type="number" step="0.01" class="form-control" id="admission_fee" name="admission_fee" value="<?php echo $class['admission_fee']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="status">স্ট্যাটাস</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="active" <?php echo ($class['status'] == 'active') ? 'selected' : ''; ?>>সক্রিয়</option>
                                            <option value="inactive" <?php echo ($class['status'] == 'inactive') ? 'selected' : ''; ?>>নিষ্ক্রিয়</option>
                                        </select>
                                    </div>
                                    <div class="form-group text-center">
                                        <button type="submit" name="update_class" class="btn btn-primary">
                                            <i class="fas fa-save"></i> আপডেট করুন
                                        </button>
                                        <a href="classes.php" class="btn btn-secondary">
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

</body>
</html>