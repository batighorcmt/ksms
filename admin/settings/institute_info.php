<?php
require_once '../../config.php';

// Authentication check - শুধুমাত্র সুপার অ্যাডমিন এক্সেস করতে পারবে
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('login.php');
}

// প্রতিষ্ঠানের তথ্য লোড করুন
$school_info = $pdo->query("SELECT * FROM school_info LIMIT 1")->fetch();
// Ensure all keys exist to avoid undefined key warnings
$school_info_defaults = [
    'name' => '',
    'address' => '',
    'phone' => '',
    'email' => '',
    'established_year' => '',
    'principal_name' => '',
    'principal_designation' => '',
    'short_code' => '',
    'website' => '',
    'logo' => ''
];
if (!$school_info) {
    $school_info = $school_info_defaults;
} else {
    // Fill missing keys if any
    foreach ($school_info_defaults as $k => $v) {
        if (!array_key_exists($k, $school_info)) $school_info[$k] = $v;
    }
}

// ফর্ম সাবমিট হলে তথ্য আপডেট করুন
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_info'])) {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $established_year = $_POST['established_year'];
    $principal_name = $_POST['principal_name'];
    $website = $_POST['website'];
    $principal_designation = $_POST['principal_designation'] ?? '';
    $short_code = $_POST['short_code'] ?? '';

    // লোগো আপলোড হ্যান্ডলিং
    $logo = $school_info['logo']; // ডিফল্টভাবে পুরানো লোগো রাখুন

    if (!empty($_FILES['logo']['name'])) {
        $upload_dir = BASE_URL . 'uploads/logo/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . '_' . basename($_FILES['logo']['name']);
        $target_file = $upload_dir . $file_name;

        // ফাইল আপলোড করুন
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
            $logo = $file_name;

            // পুরানো লোগো ডিলিট করুন (যদি থাকে)
            if (!empty($school_info['logo']) && file_exists($upload_dir . $school_info['logo'])) {
                unlink($upload_dir . $school_info['logo']);
            }
        }
    }

    // ডেটাবেসে তথ্য আপডেট বা ইনসার্ট করুন
    if ($school_info) {
        // আপডেট করুন
        $stmt = $pdo->prepare("
            UPDATE school_info 
            SET name = ?, address = ?, phone = ?, email = ?, 
                established_year = ?, principal_name = ?, principal_designation = ?, short_code = ?, website = ?, logo = ?
            WHERE id = ?
        ");
        $result = $stmt->execute([
            $name, $address, $phone, $email, 
            $established_year, $principal_name, $principal_designation, $short_code, $website, $logo,
            $school_info['id']
        ]);
    } else {
        // নতুন এন্ট্রি তৈরি করুন
        $stmt = $pdo->prepare("
            INSERT INTO school_info 
            (name, address, phone, email, established_year, principal_name, principal_designation, short_code, website, logo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $name, $address, $phone, $email, 
            $established_year, $principal_name, $principal_designation, $short_code, $website, $logo
        ]);
    }
    
    if ($result) {
        $success_message = "প্রতিষ্ঠানের তথ্য সফলভাবে আপডেট করা হয়েছে!";
    } else {
        $error_message = "তথ্য আপডেট করতে সমস্যা হয়েছে!";
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>প্রতিষ্ঠানের তথ্য - কিন্ডার গার্ডেন</title>

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
        .logo-preview {
            max-width: 200px;
            max-height: 200px;
            border: 1px solid #ddd;
            padding: 5px;
            margin-top: 10px;
        }
        .logo-container {
            text-align: center;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Navbar -->
    <?php include '../inc/header.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php include '../inc/sidebar.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">প্রতিষ্ঠানের তথ্য</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/settings.php">সেটিংস</a></li>
                            <li class="breadcrumb-item active">প্রতিষ্ঠানের তথ্য</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Notification Alerts -->
                <?php if(!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-check"></i> সফল!</h5>
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if(!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-ban"></i> ত্রুটি!</h5>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">প্রতিষ্ঠানের তথ্য সম্পাদনা</h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="name">প্রতিষ্ঠানের নাম *</label>
                                                <input type="text" class="form-control" id="name" name="name" 
                                                       value="<?php echo $school_info['name']; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="established_year">স্থাপিত সাল</label>
                                                <input type="number" class="form-control" id="established_year" 
                                                       name="established_year" value="<?php echo $school_info['established_year']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="address">ঠিকানা *</label>
                                        <textarea class="form-control" id="address" name="address" rows="3" required><?php echo $school_info['address']; ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="phone">ফোন নম্বর</label>
                                                <input type="text" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo $school_info['phone']; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="email">ইমেইল</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo $school_info['email']; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="website">ওয়েবসাইট</label>
                                                <input type="url" class="form-control" id="website" name="website" 
                                                       value="<?php echo $school_info['website']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="principal_name">প্রধান শিক্ষকের নাম</label>
                                                <input type="text" class="form-control" id="principal_name" 
                                                       name="principal_name" value="<?php echo $school_info['principal_name']; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="principal_designation">প্রধান শিক্ষকের পদবী</label>
                                                <input type="text" class="form-control" id="principal_designation" 
                                                       name="principal_designation" value="<?php echo $school_info['principal_designation'] ?? ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="short_code">প্রতিষ্ঠানের সংক্ষিপ্ত নাম (Short Code)</label>
                                                <input type="text" class="form-control" id="short_code" 
                                                       name="short_code" value="<?php echo $school_info['short_code'] ?? ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="logo">লোগো</label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="logo" name="logo" accept="image/*">
                                            <label class="custom-file-label" for="logo">লোগো নির্বাচন করুন</label>
                                        </div>
                                        <small class="form-text text-muted">সর্বোচ্চ সাইজ: 2MB, ফরম্যাট: JPG, PNG, GIF</small>
                                        
                                        <?php if(!empty($school_info['logo'])): ?>
                                        <div class="logo-container mt-3">
                                            <p>বর্তমান লোগো:</p>
                                            <img src="<?php echo BASE_URL . 'uploads/logo/' . $school_info['logo']; ?>" 
                                                 class="logo-preview" alt="প্রতিষ্ঠানের লোগো">
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="form-group text-center mt-4">
                                        <button type="submit" name="update_info" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save"></i> তথ্য আপডেট করুন
                                        </button>
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
    <?php include '../inc/footer.php'; ?>
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
        });
        
        // লোগো প্রিভিউ ফাংশন (ঐচ্ছিক)
        $('#logo').on('change', function() {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    // যদি আগে থেকে কোনো প্রিভিউ থাকে তাহলে সরান
                    $('.logo-preview').remove();
                    
                    // নতুন প্রিভিউ যোগ করুন
                    $('.logo-container').html(
                        '<p>নতুন লোগো প্রিভিউ:</p>' +
                        '<img src="' + e.target.result + '" class="logo-preview" alt="লোগো প্রিভিউ">'
                    );
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
</script>
</body>
</html>