<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../login.php');
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
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
        }
        .card-header .card-title {
            font-weight: 600;
        }
        .btn-primary {
            background: linear-gradient(45deg, #4e73df, #224abe);
            border: none;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #224abe, #4e73df);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .table th {
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 600;
            border-top: 1px solid #e3e6f0;
        }
        .badge {
            font-size: 0.85em;
            padding: 0.4em 0.6em;
        }
        .action-buttons .btn {
            margin-right: 5px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .action-buttons .btn:last-child {
            margin-right: 0;
        }
        #teachersTable_wrapper {
            padding: 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .dataTables_filter input {
            border-radius: 4px;
            border: 1px solid #d1d3e2;
            padding: 0.375rem 0.75rem;
        }
        .pagination .page-link {
            color: #4e73df;
        }
        .pagination .page-item.active .page-link {
            background-color: #4e73df;
            border-color: #4e73df;
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
                        <h1 class="m-0 text-dark">শিক্ষক ব্যবস্থাপনা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard.php">হোম</a></li>
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
                        <div class="card shadow">
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
                                <table id="teachersTable" class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th width="30">#</th>
                                            <th>ছবি</th>
                                            <th>নাম</th>
                                            <th>ইউজারনেম</th>
                                            <th>ইমেইল</th>
                                            <th>মোবাইল</th>
                                            <th width="120">ক্লাস সংখ্যা</th>
                                            <th width="100">স্ট্যাটাস</th>
                                            <th width="120">নিবন্ধন তারিখ</th>
                                            <th width="150" class="text-center">অ্যাকশন</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($teachers) > 0): ?>
                                            <?php $sl = 1; ?>
                                            <?php foreach($teachers as $teacher): ?>
                                                <tr>
                                                    <td class="text-center"><?php echo $sl++; ?></td>
                                                    <td class="text-center">
                                                        <?php if(!empty($teacher['photo'])): ?>
                                                            <img src="../uploads/teachers/<?php echo $teacher['photo']; ?>" class="teacher-img" alt="শিক্ষকের ছবি">
                                                        <?php else: ?>
                                                            <div class="teacher-img bg-light d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-user text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $teacher['full_name']; ?></td>
                                                    <td><?php echo $teacher['username']; ?></td>
                                                    <td><?php echo $teacher['email']; ?></td>
                                                    <td><?php echo $teacher['phone']; ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info p-2"><?php echo $teacher['total_classes']; ?> ক্লাস</span>
                                                    </td>
                                                    <td>
                                                        <?php if($teacher['status'] == 1): ?>
                                                            <span class="badge badge-success p-2">সক্রিয়</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger p-2">নিষ্ক্রিয়</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('d/m/Y', strtotime($teacher['created_at'])); ?></td>
                                                    <td class="action-buttons text-center">
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
                                                <td colspan="10" class="text-center py-4">
                                                    <i class="fas fa-exclamation-circle text-muted fa-2x mb-2"></i>
                                                    <p class="text-muted">কোন শিক্ষক পাওয়া যায়নি</p>
                                                    <a href="add_teacher.php" class="btn btn-primary mt-2">
                                                        <i class="fas fa-plus"></i> নতুন শিক্ষক যোগ করুন
                                                    </a>
                                                </td>
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
            },
            "columnDefs": [
                { "orderable": false, "targets": [0, 1, 9] }
            ],
            "order": [[2, 'asc']]
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