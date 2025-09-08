<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('login.php');
}

// শিক্ষকদের ডেটা লোড করুন
$teachers = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM class_teachers WHERE teacher_id = u.id) as total_classes
    FROM users u 
    WHERE u.role = 'teacher' 
    ORDER BY u.full_name ASC
")->fetchAll();

// শিক্ষক মুছুন
if (isset($_GET['delete_id'])) {
    $teacher_id = intval($_GET['delete_id']);
    
    // প্রথমে চেক করুন যে শিক্ষক কোনো ক্লাসের সাথে যুক্ত কি না
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM class_teachers WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = "এই শিক্ষককে মুছতে পারবেন না কারণ তিনি এক বা একাধিক ক্লাসের সাথে যুক্ত!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
        if ($stmt->execute([$teacher_id])) {
            $_SESSION['success'] = "শিক্ষক সফলভাবে মুছে ফেলা হয়েছে!";
        } else {
            $_SESSION['error'] = "শিক্ষক মুছতে সমস্যা occurred!";
        }
    }
    
    header("Location: teachers.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষক ব্যবস্থাপনা - কিন্ডার গার্ডেন</title>

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
        .teacher-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
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
                        <h1 class="m-0">শিক্ষক ব্যবস্থাপনা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">শিক্ষক ব্যবস্থাপনা</li>
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
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">শিক্ষকদের তালিকা</h3>
                                <div class="card-tools">
                                    <a href="add_teacher.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> নতুন শিক্ষক যোগ করুন
                                    </a>
                                </div>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <table id="teachersTable" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ছবি</th>
                                            <th>নাম</th>
                                            <th>ইমেইল</th>
                                            <th>মোবাইল</th>
                                            <th>ক্লাস সংখ্যা</th>
                                            <th>স্ট্যাটাস</th>
                                            <th>নিবন্ধন তারিখ</th>
                                            <th>অ্যাকশন</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($teachers) > 0): ?>
                                            <?php foreach($teachers as $teacher): ?>
                                                <tr>
                                                    <td class="text-center">
                                                        <?php if(!empty($teacher['photo'])): ?>
                                                            <img src="../uploads/teachers/<?php echo $teacher['photo']; ?>" class="teacher-img" alt="শিক্ষকের ছবি">
                                                        <?php else: ?>
                                                            <img src="https://via.placeholder.com/40" class="teacher-img" alt="ছবি নেই">
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $teacher['full_name']; ?></td>
                                                    <td><?php echo $teacher['email']; ?></td>
                                                    <td><?php echo $teacher['phone']; ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info"><?php echo $teacher['total_classes']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if($teacher['status'] == 1): ?>
                                                            <span class="badge badge-success">সক্রিয়</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">নিষ্ক্রিয়</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('d/m/Y', strtotime($teacher['created_at'])); ?></td>
                                                    <td>
                                                        <a href="teacher_details.php?id=<?php echo $teacher['id']; ?>" class="btn btn-info btn-sm" title="বিস্তারিত">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit_teacher.php?id=<?php echo $teacher['id']; ?>" class="btn btn-primary btn-sm" title="সম্পাদনা">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger btn-sm delete-teacher" data-id="<?php echo $teacher['id']; ?>" data-name="<?php echo $teacher['full_name']; ?>" title="মুছুন">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center">কোন শিক্ষক পাওয়া যায়নি</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->
                    </div>
                    <!-- /.col -->
                </div>
                <!-- /.row -->
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
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        // DataTable initialization
        $('#teachersTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
            "language": {
                "search": "খুঁজুন:",
                "lengthMenu": "প্রতি পৃষ্ঠায় _MENU_টি রেকর্ড দেখুন",
                "zeroRecords": "কিছু পাওয়া যায়নি",
                "info": "পৃষ্ঠা _PAGE_ এর _PAGES_",
                "infoEmpty": "কোন রেকর্ড নেই",
                "infoFiltered": "(মোট _MAX_ রেকর্ড থেকে ফিল্টার করা হয়েছে)",
                "paginate": {
                    "first": "প্রথম",
                    "last": "শেষ",
                    "next": "পরবর্তী",
                    "previous": "পূর্ববর্তী"
                }
            }
        });

        // শিক্ষক মুছার কনফার্মেশন
        $('.delete-teacher').click(function() {
            var teacherId = $(this).data('id');
            var teacherName = $(this).data('name');
            
            if (confirm('আপনি কি "' + teacherName + '" শিক্ষককে মুছতে চান?')) {
                window.location.href = 'teachers.php?delete_id=' + teacherId;
            }
        });
    });
</script>
</body>
</html>