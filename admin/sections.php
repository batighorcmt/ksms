<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../login.php');
}

// শ্রেণি আইডি পান
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// শ্রেণি ডেটা লোড করুন
$class = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$class->execute([$class_id]);
$class = $class->fetch();

if (!$class) {
    $_SESSION['error'] = "শ্রেণি পাওয়া যায়নি!";
    redirect('admin/classes.php');
}

// শাখা ডেটা লোড করুন
$sections = $pdo->prepare("
    SELECT s.*, u.full_name as teacher_name 
    FROM sections s 
    LEFT JOIN users u ON s.section_teacher_id = u.id 
    WHERE s.class_id = ? 
    ORDER BY s.name ASC
");
$sections->execute([$class_id]);
$sections = $sections->fetchAll();

// শিক্ষক লোড করুন
$teachers = $pdo->query("SELECT * FROM users WHERE role='teacher' AND status=1")->fetchAll();

// নতুন শাখা যোগ করুন
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_section'])) {
    $name = $_POST['name'];
    $capacity = $_POST['capacity'];
    $room_number = $_POST['room_number'];
    $section_teacher_id = !empty($_POST['section_teacher_id']) ? $_POST['section_teacher_id'] : NULL;
    
    $stmt = $pdo->prepare("
        INSERT INTO sections 
        (class_id, name, capacity, room_number, section_teacher_id) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$class_id, $name, $capacity, $room_number, $section_teacher_id])) {
        $_SESSION['success'] = "শাখা সফলভাবে যোগ করা হয়েছে!";
        redirect('admin/sections.php?class_id=' . $class_id);
    } else {
        $_SESSION['error'] = "শাখা যোগ করতে সমস্যা occurred!";
    }
}

// শাখা মুছুন
if (isset($_GET['delete_section'])) {
    $id = $_GET['delete_section'];
    $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['success'] = "শাখা সফলভাবে মুছে ফেলা হয়েছে!";
        redirect('admin/sections.php?class_id=' . $class_id);
    } else {
        $_SESSION['error'] = "শাখা মুছতে সমস্যা occurred!";
    }
}

// শাখা স্ট্যাটাস পরিবর্তন করুন
if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    $stmt = $pdo->prepare("UPDATE sections SET status = IF(status='active', 'inactive', 'active') WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['success'] = "শাখা স্ট্যাটাস সফলভাবে পরিবর্তন করা হয়েছে!";
        redirect('admin/sections.php?class_id=' . $class_id);
    } else {
        $_SESSION['error'] = "স্ট্যাটাস পরিবর্তন করতে সমস্যা occurred!";
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শাখা ব্যবস্থাপনা - কিন্ডার গার্ডেন</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Bengali Font -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    
    <style>
        body, .main-sidebar, .nav-link {
            font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;
        }
        .logo-custom {
            font-weight: bold;
            font-size: 22px;
        }
        .action-buttons .btn {
            margin-right: 5px;
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
                        <h1 class="m-0">শাখা ব্যবস্থাপনা - <?php echo $class['name']; ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="classes.php">শ্রেণি ব্যবস্থাপনা</a></li>
                            <li class="breadcrumb-item active">শাখা ব্যবস্থাপনা</li>
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
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">নতুন শাখা যোগ করুন</h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="name">শাখার নাম *</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="capacity">ধারণক্ষমতা</label>
                                        <input type="number" class="form-control" id="capacity" name="capacity">
                                    </div>
                                    <div class="form-group">
                                        <label for="room_number">রুম নম্বর</label>
                                        <input type="text" class="form-control" id="room_number" name="room_number">
                                    </div>
                                    <div class="form-group">
                                        <label for="section_teacher_id">শাখা শিক্ষক</label>
                                        <select class="form-control" id="section_teacher_id" name="section_teacher_id">
                                            <option value="">নির্বাচন করুন</option>
                                            <?php foreach($teachers as $teacher): ?>
                                                <option value="<?php echo $teacher['id']; ?>"><?php echo $teacher['full_name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="add_section" class="btn btn-primary">সংরক্ষণ করুন</button>
                                </form>
                            </div>
                            <!-- /.card-body -->
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><?php echo $class['name']; ?> শ্রেণির শাখা তালিকা</h3>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <table id="sectionsTable" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>শাখার নাম</th>
                                            <th>ধারণক্ষমতা</th>
                                            <th>রুম নম্বর</th>
                                            <th>শিক্ষক</th>
                                            <th>স্ট্যাটাস</th>
                                            <th>অ্যাকশন</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($sections as $section): ?>
                                        <tr>
                                            <td><?php echo $section['name']; ?></td>
                                            <td><?php echo $section['capacity']; ?></td>
                                            <td><?php echo $section['room_number']; ?></td>
                                            <td><?php echo $section['teacher_name'] ?? 'নির্ধারিত হয়নি'; ?></td>
                                            <td>
                                                <?php if($section['status'] == 'active'): ?>
                                                    <span class="badge badge-success">সক্রিয়</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">নিষ্ক্রিয়</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="edit_section.php?id=<?php echo $section['id']; ?>" class="btn btn-primary btn-sm" title="সম্পাদনা">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="sections.php?class_id=<?php echo $class_id; ?>&toggle_status=<?php echo $section['id']; ?>" class="btn btn-warning btn-sm" title="স্ট্যাটাস পরিবর্তন">
                                                    <i class="fas fa-power-off"></i>
                                                </a>
                                                <a href="sections.php?class_id=<?php echo $class_id; ?>&delete_section=<?php echo $section['id']; ?>" class="btn btn-danger btn-sm" title="মুছুন" onclick="return confirm('আপনি কি নিশ্চিত যে আপনি এই শাখাটি মুছতে চান?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        // DataTable initialization
        $('#sectionsTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
            "language": {
                "search": "খুঁজুন:",
                "lengthMenu": "প্রতি পৃষ্ঠায় _MENU_ এন্ট্রি দেখুন",
                "info": "পৃষ্ঠা _PAGE_ এর _PAGES_ থেকে দেখানো হচ্ছে",
                "infoEmpty": "কোন এন্ট্রি পাওয়া যায়নি",
                "infoFiltered": "(মোট _MAX_ এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
                "paginate": {
                    "previous": "পূর্ববর্তী",
                    "next": "পরবর্তী"
                }
            }
        });
    });
</script>
</body>
</html>