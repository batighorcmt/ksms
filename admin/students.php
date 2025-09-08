<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// শিক্ষার্থী ডেটা লোড করুন
$stmt = $pdo->query("
    SELECT s.*, c.name as class_name, sec.name as section_name, u.full_name as guardian_name 
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id 
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN users u ON s.guardian_id = u.id
    ORDER BY s.id DESC
");
$students = $stmt->fetchAll();

// ক্লাস, শাখা এবং সম্পর্ক লোড করুন
$classes = $pdo->query("SELECT * FROM classes")->fetchAll();
$sections = $pdo->query("SELECT * FROM sections")->fetchAll();
$guardians = $pdo->query("SELECT * FROM users WHERE role='guardian'")->fetchAll();
$relations = $pdo->query("SELECT * FROM guardian_relations")->fetchAll();

// নতুন শিক্ষার্থী যোগ করুন
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
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
    $admission_date = $_POST['admission_date'];
    
    // যদি অন্যান্য সম্পর্ক নির্বাচন করা হয়
    if ($guardian_relation == 'other' && !empty($other_relation)) {
        $guardian_relation = $other_relation;
    }
    
    // শিক্ষার্থী আইডি জেনারেট করুন
    $student_id = 'STU' . date('Y') . rand(1000, 9999);
    
    $stmt = $pdo->prepare("
        INSERT INTO students 
        (student_id, first_name, last_name, father_name, mother_name, guardian_relation, 
         birth_certificate_no, date_of_birth, gender, blood_group, religion, 
         present_address, permanent_address, mobile_number, 
         class_id, section_id, roll_number, guardian_id, admission_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([
        $student_id, $first_name, $last_name, $father_name, $mother_name, $guardian_relation,
        $birth_certificate_no, $date_of_birth, $gender, $blood_group, $religion,
        $present_address, $permanent_address, $mobile_number,
        $class_id, $section_id, $roll_number, $guardian_id, $admission_date
    ])) {
        $_SESSION['success'] = "শিক্ষার্থী সফলভাবে যোগ করা হয়েছে!";
        redirect('admin/students.php');
    } else {
        $_SESSION['error'] = "শিক্ষার্থী যোগ করতে সমস্যা occurred!";
    }
}

// শিক্ষার্থী মুছুন
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['success'] = "শিক্ষার্থী সফলভাবে মুছে ফেলা হয়েছে!";
        redirect('admin/students.php');
    } else {
        $_SESSION['error'] = "শিক্ষার্থী মুছতে সমস্যা occurred!";
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষার্থী ব্যবস্থাপনা - কিন্ডার গার্ডেন</title>

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
        .student-photo {
            width: 60px;
            height: 80px;
            object-fit: cover;
            border-radius: 10%;
        }
        .action-buttons .btn {
            margin-right: 5px;
        }
        .other-relation {
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
                        <h1 class="m-0">শিক্ষার্থী ব্যবস্থাপনা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">শিক্ষার্থী ব্যবস্থাপনা</li>
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
                                <h3 class="card-title">শিক্ষার্থীদের তালিকা</h3>
                                <button type="button" class="btn btn-primary float-right" data-toggle="modal" data-target="#addStudentModal">
                                    <i class="fas fa-plus"></i> নতুন শিক্ষার্থী যোগ করুন
                                </button>
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <table id="studentsTable" class="table table-bordered table-striped table-hover table-responsive">
                                    <thead>
                                        <tr>
                                            <th>ছবি</th>
                                            <th>আইডি</th>
                                            <th>নাম</th>
                                            <th>পিতার নাম</th>
                                            <th>মাতার নাম</th>
                                            <th>মোবাইল</th>
                                            <th>ক্লাস</th>
                                            <th>রোল</th>
                                            <th>স্ট্যাটাস</th>
                                            <th>অ্যাকশন</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($students as $student): ?>
                                        <tr>
                                            <td>
                                                <?php if(!empty($student['photo'])): ?>
                                                    <img src="../uploads/students/<?php echo $student['photo']; ?>" class="student-photo" alt="ছবি">
                                                <?php else: ?>
                                                    <img src="https://via.placeholder.com/40" class="student-photo" alt="ছবি নেই">
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $student['student_id']; ?></td>
                                            <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                                            <td><?php echo $student['father_name']; ?></td>
                                            <td><?php echo $student['mother_name']; ?></td>
                                            <td><?php echo $student['mobile_number']; ?></td>
                                            <td><?php echo $student['class_name'] . ' (' . $student['section_name'] . ')'; ?></td>
                                            <td><?php echo $student['roll_number']; ?></td>
                                            <td>
                                                <?php if($student['status'] == 'active'): ?>
                                                    <span class="badge badge-success">সক্রিয়</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">নিষ্ক্রিয়</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="student_details.php?id=<?php echo $student['id']; ?>" class="btn btn-info btn-sm" title="বিস্তারিত">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-primary btn-sm" title="সম্পাদনা">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="students.php?delete=<?php echo $student['id']; ?>" class="btn btn-danger btn-sm" title="মুছুন" onclick="return confirm('আপনি কি নিশ্চিত যে আপনি এই শিক্ষার্থীকে মুছতে চান?')">
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

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" role="dialog" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStudentModalLabel">নতুন শিক্ষার্থী যোগ করুন</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name">শিক্ষার্থীর নামের প্রথম অংশ *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="last_name">শিক্ষার্থীর নামের শেষ অংশ *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="father_name">পিতার নাম *</label>
                                    <input type="text" class="form-control" id="father_name" name="father_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mother_name">মাতার নাম *</label>
                                    <input type="text" class="form-control" id="mother_name" name="mother_name" required>
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
                                            <option value="<?php echo $relation['name']; ?>"><?php echo $relation['name']; ?></option>
                                        <?php endforeach; ?>
                                        <option value="other">অন্যান্য</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 other-relation" id="other_relation_field">
                                <div class="form-group">
                                    <label for="other_relation">অন্যান্য সম্পর্ক উল্লেখ করুন</label>
                                    <input type="text" class="form-control" id="other_relation" name="other_relation">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="birth_certificate_no">জন্ম নিবন্ধন নম্বর</label>
                                    <input type="text" class="form-control" id="birth_certificate_no" name="birth_certificate_no">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date_of_birth">জন্ম তারিখ *</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="gender">লিঙ্গ *</label>
                                    <select class="form-control" id="gender" name="gender" required>
                                        <option value="">নির্বাচন করুন</option>
                                        <option value="male">ছেলে</option>
                                        <option value="female">মেয়ে</option>
                                        <option value="other">অন্যান্য</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="blood_group">রক্তের গ্রুপ</label>
                                    <select class="form-control" id="blood_group" name="blood_group">
                                        <option value="">নির্বাচন করুন</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="religion">ধর্ম</label>
                                    <select class="form-control" id="religion" name="religion">
                                        <option value="">নির্বাচন করুন</option>
                                        <option value="Islam">ইসলাম</option>
                                        <option value="Hinduism">হিন্দু</option>
                                        <option value="Christianity">খ্রিস্টান</option>
                                        <option value="Buddhism">বৌদ্ধ</option>
                                        <option value="Other">অন্যান্য</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="present_address">বর্তমান ঠিকানা *</label>
                                    <textarea class="form-control" id="present_address" name="present_address" rows="3" required></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="permanent_address">স্থায়ী ঠিকানা *</label>
                                    <textarea class="form-control" id="permanent_address" name="permanent_address" rows="3" required></textarea>
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
                                    <input type="text" class="form-control" id="mobile_number" name="mobile_number" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="guardian_id">অভিভাবক (ঐচ্ছিক)</label>
                                    <select class="form-control" id="guardian_id" name="guardian_id">
                                        <option value="">নির্বাচন করুন</option>
                                        <?php foreach($guardians as $guardian): ?>
                                            <option value="<?php echo $guardian['id']; ?>"><?php echo $guardian['full_name']; ?> (<?php echo $guardian['phone']; ?>)</option>
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
                                            <option value="<?php echo $class['id']; ?>"><?php echo $class['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="section_id">শাখা </label>
                                    <select class="form-control" id="section_id" name="section_id" >
                                        <option value="">নির্বাচন করুন</option>
                                        <?php foreach($sections as $section): ?>
                                            <option value="<?php echo $section['id']; ?>"><?php echo $section['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="roll_number">রোল নম্বর *</label>
                                    <input type="number" class="form-control" id="roll_number" name="roll_number" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="admission_date">ভর্তির তারিখ *</label>
                                    <input type="date" class="form-control" id="admission_date" name="admission_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="photo">ছবি আপলোড</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="photo" name="photo">
                                        <label class="custom-file-label" for="photo">ছবি নির্বাচন করুন</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">বাতিল</button>
                        <button type="submit" name="add_student" class="btn btn-primary">সংরক্ষণ করুন</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
        $('#studentsTable').DataTable({
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

        // Custom file input
        $('.custom-file-input').on('change', function() {
            let fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').addClass("selected").html(fileName);
        });

        // অন্যান্য সম্পর্ক ফিল্ড দেখান/লুকান
        $('#guardian_relation').change(function() {
            if ($(this).val() === 'other') {
                $('#other_relation_field').show();
            } else {
                $('#other_relation_field').hide();
            }
        });

        // ঠিকানা অনুলিপি করুন
        $('#copyAddress').click(function() {
            $('#permanent_address').val($('#present_address').val());
        });
    });
</script>
</body>
</html>