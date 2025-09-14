<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// Secure status toggle via POST (preferred). Keep legacy GET removed for safety.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status_id'])) {
    $tid = intval($_POST['toggle_status_id']);
    $sstmt = $pdo->prepare("SELECT status FROM students WHERE id = ? LIMIT 1");
    $sstmt->execute([$tid]);
    $row = $sstmt->fetch();
    if ($row) {
        $new = ($row['status'] === 'active') ? 'inactive' : 'active';
        $ust = $pdo->prepare("UPDATE students SET status = ? WHERE id = ?");
        if ($ust->execute([$new, $tid])) {
            $_SESSION['success'] = 'স্ট্যাটাস সফলভাবে পরিবর্তন করা হয়েছে।';
        } else {
            $_SESSION['error'] = 'স্ট্যাটাস পরিবর্তনে সমস্যা হয়েছে।';
        }
    }
    redirect('admin/students.php');
}

// Filters from querystring
$f_class = !empty($_GET['f_class']) ? intval($_GET['f_class']) : null;
$f_section = !empty($_GET['f_section']) ? intval($_GET['f_section']) : null;
$f_year = !empty($_GET['f_year']) ? intval($_GET['f_year']) : null;
$f_gender = !empty($_GET['f_gender']) ? $_GET['f_gender'] : null;
$f_religion = !empty($_GET['f_religion']) ? $_GET['f_religion'] : null;
$q = !empty($_GET['q']) ? trim($_GET['q']) : null;

// Build dynamic query with filters
$where = [];
$params = [];
if ($f_class) { $where[] = 's.class_id = ?'; $params[] = $f_class; }
if ($f_section) { $where[] = 's.section_id = ?'; $params[] = $f_section; }
if ($f_year) { $where[] = 'YEAR(s.admission_date) = ?'; $params[] = $f_year; }
if ($f_gender) { $where[] = 's.gender = ?'; $params[] = $f_gender; }
if ($f_religion) { $where[] = 's.religion = ?'; $params[] = $f_religion; }
if ($q) { $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR s.mobile_number LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }

$sql = "SELECT s.*, c.name as class_name, sec.name as section_name, u.full_name as guardian_name 
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id 
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN users u ON s.guardian_id = u.id";
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY s.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// extra dropdown data
$years = $pdo->query("SELECT DISTINCT YEAR(admission_date) as y FROM students WHERE admission_date IS NOT NULL ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
$genders = $pdo->query("SELECT DISTINCT gender FROM students WHERE gender IS NOT NULL AND gender <> ''")->fetchAll(PDO::FETCH_COLUMN);
$religions = $pdo->query("SELECT DISTINCT religion FROM students WHERE religion IS NOT NULL AND religion <> ''")->fetchAll(PDO::FETCH_COLUMN);
$guardians = $pdo->query("SELECT * FROM users WHERE role='guardian'")->fetchAll();
$relations = $pdo->query("SELECT * FROM guardian_relations")->fetchAll();
// classes and sections for filters and add form
$classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();
$sections = $pdo->query("SELECT * FROM sections ORDER BY name ASC")->fetchAll();

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
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            cursor: pointer;
        }
        /* Enlarge photo on hover without breaking layout */
        .student-photo:hover {
            transform: scale(2.2);
            box-shadow: 0 8px 20px rgba(0,0,0,0.25);
            position: relative;
            z-index: 9999;
        }
        .action-buttons .btn {
            margin-right: 5px;
        }
    .card-header .form-inline .form-control { min-width: 140px; }
    .table td, .table th { vertical-align: middle; }
    .badge { font-size: 0.85rem; }
    .dropdown-menu { min-width: 10rem; }
        .other-relation {
            display: none;
        }
    /* Make header buttons stack on small screens and span full width */
    @media (max-width: 576px) {
        .card-header .ml-auto.d-flex { flex-direction: column; width: 100%; }
        .card-header .ml-auto.d-flex .btn { width: 100%; margin-bottom: 6px; }
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
                                                    <div class="card-header d-flex align-items-center">
                                                        <div>
                                                            <h3 class="card-title mb-0">শিক্ষার্থীদের তালিকা</h3>
                                                        </div>
                                                        <div class="ml-auto d-flex">
                                                            <a href="add_student.php" class="btn btn-primary mr-2">
                                                                <i class="fas fa-plus"></i> নতুন শিক্ষার্থী
                                                            </a>
                                                            <a href="students_print.php" class="btn btn-outline-secondary mr-2" target="_blank"><i class="fas fa-print"></i> প্রিন্ট</a>
                                                        </div>
                                                    </div>

                                                    <div class="card-body">
                                                        <!-- Filters -->
                                                        <form method="GET" class="mb-3">
                                                            <div class="form-row">
                                                                <div class="col-12 col-sm-6 col-md-2 mb-2">
                                                                    <select name="f_class" class="form-control">
                                                                        <option value="">-- ক্লাস --</option>
                                                                        <?php foreach($classes as $class): ?>
                                                                            <option value="<?php echo $class['id']; ?>" <?php if($f_class === (int)$class['id']) echo 'selected'; ?>><?php echo htmlspecialchars($class['name'] ?? ''); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-sm-6 col-md-2 mb-2">
                                                                    <select id="f_section" name="f_section" class="form-control" disabled data-selected="<?php echo (int)($f_section ?? 0); ?>">
                                                                        <option value="">-- শাখা --</option>
                                                                        <?php foreach($sections as $section): ?>
                                                                            <option value="<?php echo $section['id']; ?>" data-class="<?php echo (int)($section['class_id'] ?? 0); ?>" <?php if($f_section === (int)$section['id']) echo 'selected'; ?>><?php echo htmlspecialchars($section['name'] ?? ''); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-sm-6 col-md-2 mb-2">
                                                                    <select name="f_gender" class="form-control">
                                                                        <option value="">-- লিঙ্গ --</option>
                                                                        <?php foreach($genders as $g): $gv = (string)$g; ?>
                                                                            <option value="<?php echo htmlspecialchars($gv); ?>" <?php if($f_gender !== null && $f_gender === $gv) echo 'selected'; ?>><?php echo htmlspecialchars(ucfirst($gv)); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-sm-6 col-md-2 mb-2">
                                                                    <select name="f_religion" class="form-control">
                                                                        <option value="">-- ধর্ম --</option>
                                                                        <?php foreach($religions as $r): $rv=(string)$r; ?>
                                                                            <option value="<?php echo htmlspecialchars($rv); ?>" <?php if($f_religion !== null && $f_religion === $rv) echo 'selected'; ?>><?php echo htmlspecialchars($rv); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-sm-12 col-md-3 mb-2">
                                                                    <input type="text" name="q" value="<?php echo htmlspecialchars((string)($q ?? '')); ?>" class="form-control" placeholder="নাম/আইডি/মোবাইল খুঁজুন">
                                                                </div>
                                                                <div class="col-12 col-sm-12 col-md-1 mb-2 d-flex">
                                                                    <button type="submit" class="btn btn-success btn-block">ফিল্টার</button>
                                                                </div>
                                                                <div class="col-12 col-sm-12 col-md-12">
                                                                    <a href="students.php" class="btn btn-outline-secondary mt-2">রিসেট</a>
                                                                </div>
                                                            </div>
                                                        </form>

                                                        <div class="table-responsive" style="width:100%;overflow-x:auto">
                                                        <table id="studentsTable" class="table table-bordered table-striped table-hover" style="width:100%;min-width:1200px">
                                                            <thead>
                                                                <tr>
                                                                    <th>#</th>
                                                                    <th>ছবি</th>
                                                                    <th>আইডি</th>
                                                                    <th>নাম</th>
                                                                    <th>পিতার নাম</th>
                                                                    <th>মাতার নাম</th>
                                                                    <th>মোবাইল</th>
                                                                    <th>ক্লাস</th>
                                                                    <th>রোল</th>
                                                                    <th>বর্তমান ঠিকানা</th>
                                                                    <th>স্ট্যাটাস</th>
                                                                    <th>অ্যাকশন</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php $serial = 0; foreach($students as $student): $serial++; ?>
                                                                <tr>
                                                                    <td><?php echo $serial; ?></td>
                                                                    <td>
                                                                        <?php if(!empty($student['photo'])): ?>
                                                                            <img src="../uploads/students/<?php echo $student['photo']; ?>" class="student-photo" alt="ছবি">
                                                                        <?php else: ?>
                                                                            <img src="https://via.placeholder.com/40" class="student-photo" alt="ছবি নেই">
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td><?php echo $student['student_id']; ?></td>
                                                                    <td>
                                                                        <?php
                                                                            $data_studentid = htmlspecialchars((string)($student['student_id'] ?? ''));
                                                                            $data_name = htmlspecialchars(trim((string)($student['first_name'] ?? '') . ' ' . (string)($student['last_name'] ?? '')));
                                                                            $data_father = htmlspecialchars((string)($student['father_name'] ?? ''));
                                                                            $data_mother = htmlspecialchars((string)($student['mother_name'] ?? ''));
                                                                            $data_mobile = htmlspecialchars((string)($student['mobile_number'] ?? ''));
                                                                            $data_class = htmlspecialchars(trim((string)($student['class_name'] ?? '') . ' (' . (string)($student['section_name'] ?? '') . ')'));
                                                                            $data_roll = htmlspecialchars((string)($student['roll_number'] ?? ''));
                                                                            $data_photo = !empty($student['photo']) ? BASE_URL.'uploads/students/'.$student['photo'] : '';
                                                                            $data_address = htmlspecialchars((string)($student['present_address'] ?? ''));
                                                                        ?>
                                                                        <a href="#" class="student-link" data-id="<?php echo (int)$student['id']; ?>" data-studentid="<?php echo $data_studentid; ?>" data-name="<?php echo $data_name; ?>" data-father="<?php echo $data_father; ?>" data-mother="<?php echo $data_mother; ?>" data-mobile="<?php echo $data_mobile; ?>" data-class="<?php echo $data_class; ?>" data-roll="<?php echo $data_roll; ?>" data-photo="<?php echo $data_photo; ?>" data-address="<?php echo $data_address; ?>">
                                                                            <?php echo $data_name; ?>
                                                                        </a>
                                                                    </td>
                                                                    <td><?php echo $student['father_name']; ?></td>
                                                                    <td><?php echo $student['mother_name']; ?></td>
                                                                    <td><?php echo $student['mobile_number']; ?></td>
                                                                    <td><?php echo $student['class_name'] . ' (' . $student['section_name'] . ')'; ?></td>
                                                                    <td><?php echo $student['roll_number']; ?></td>
                                                                    <td><?php echo nl2br(htmlspecialchars((string)($student['present_address'] ?? ''))); ?></td>
                                                                    <td>
                                                                        <?php if($student['status'] == 'active'): ?>
                                                                            <span class="badge badge-success">সক্রিয়</span>
                                                                        <?php else: ?>
                                                                            <span class="badge badge-danger">নিষ্ক্রিয়</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="action-buttons">
                                                                        <div class="btn-group">
                                                                            <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                                অপশন
                                                                            </button>
                                                                            <div class="dropdown-menu dropdown-menu-right">
                                                                                <a class="dropdown-item" href="student_details.php?id=<?php echo $student['id']; ?>">বিস্তারিত</a>
                                                                                <a class="dropdown-item" href="edit_student.php?id=<?php echo $student['id']; ?>">সম্পাদনা</a>
                                                                                <form method="POST" class="d-inline toggle-status-form" style="display:inline-block;">
                                                                                    <input type="hidden" name="toggle_status_id" value="<?php echo $student['id']; ?>">
                                                                                    <button type="submit" class="dropdown-item"><?php echo ($student['status']=='active') ? 'নিষ্ক্রিয় করুন' : 'সক্রিয় করুন'; ?></button>
                                                                                </form>
                                                                                <div class="dropdown-divider"></div>
                                                                                <a class="dropdown-item text-danger" href="students.php?delete=<?php echo $student['id']; ?>" onclick="return confirm('আপনি কি নিশ্চিত যে আপনি এই শিক্ষার্থীকে মুছতে চান?')">মুছুন</a>
                                                                            </div>
                                                                        </div>
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

    <!-- Add Student moved to add_student.php -->

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

    // Dynamic sections loading when class changes
    var originalSectionOptions = $('#f_section').html();
    function loadSectionsForClass(classId, selectedSectionId) {
            var $sec = $('#f_section');
            // If no class selected, show all pre-rendered options and enable
            if (!classId) {
                $sec.find('option').show();
                $sec.val(selectedSectionId || '');
                $sec.prop('disabled', false);
                return;
            }
            $sec.prop('disabled', true);
            $sec.html('<option>লোড হচ্ছে...</option>');
            $.ajax({
                url: 'get_sections.php',
                data: { class_id: classId },
                dataType: 'html',
                method: 'GET'
            }).done(function(html){
                // get_sections.php returns <option> elements
                $sec.html(html);
                if (selectedSectionId) {
                    $sec.val(selectedSectionId);
                }
                $sec.prop('disabled', false);
            }).fail(function(jqXHR, textStatus, errorThrown){
                console.error('Failed to load sections:', textStatus, errorThrown);
                // fallback: use pre-rendered options and filter by data-class attribute
                $sec.html(originalSectionOptions);
                if (classId) {
                    $sec.find('option').each(function(){
                        var $o = $(this);
                        var oc = $o.attr('data-class') || '';
                        if ($o.val() === '') return; // keep default
                        if (parseInt(oc,10) !== parseInt(classId,10)) {
                            $o.remove();
                        }
                    });
                }
                if ($sec.find('option').length === 1) { // only default
                    $sec.html('<option value="">-- শাখা পাওয়া যায়নি --</option>');
                }
                if (selectedSectionId) {
                    $sec.val(selectedSectionId);
                }
                $sec.prop('disabled', false);
            });
        }

        // wire class select change
        $(document).on('change', 'select[name="f_class"]', function(){
            var classId = $(this).val();
            loadSectionsForClass(classId, null);
        });

        // on page load, if class filter present, load sections and preserve selected
        var initialClass = $('select[name="f_class"]').val();
        var initialSection = parseInt($('#f_section').attr('data-selected') || '0', 10) || 0;
        if (initialClass) {
            loadSectionsForClass(initialClass, initialSection ? initialSection : null);
        } else {
            // enable f_section (shows all) when no class selected
            $('#f_section').prop('disabled', false);
        }
    });
</script>

<!-- Student Summary Modal -->
<div class="modal fade" id="studentSummaryModal" tabindex="-1" role="dialog" aria-labelledby="studentSummaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(90deg,#4e73df,#6f42c1);color:#fff">
                <h5 class="modal-title" id="studentSummaryModalLabel">শিক্ষার্থী সংক্ষিপ্ত বিবরণ</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <img id="modalPhoto" src="" alt="ছবি" style="width:120px;height:160px;object-fit:cover;border-radius:6px;background:#f3f4f6">
                        <div style="margin-top:8px">
                            <a href="#" id="profileLinkUnderPhoto" class="btn btn-outline-primary btn-sm">পুরো প্রোফাইল দেখুন</a>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h5 id="modalName" style="margin-top:0"></h5>
                        <p id="modalClass" class="mb-1 text-muted"></p>
                        <p class="mb-1"><strong>আইডি:</strong> <span id="modalStudentId"></span></p>
                        <p class="mb-1"><strong>রোল:</strong> <span id="modalRoll"></span></p>
                        <p class="mb-1"><strong>পিতা:</strong> <span id="modalFather"></span></p>
                        <p class="mb-1"><strong>মাতা:</strong> <span id="modalMother"></span></p>
                        <p class="mb-1"><strong>মোবাইল:</strong> <span id="modalMobile"></span></p>
                        <p class="mb-1"><strong>বর্তমান ঠিকানা:</strong> <span id="modalAddress"></span></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="callBtn" class="btn btn-success">কল করুন</a>
                <a href="#" id="smsBtn" class="btn btn-info">মেসেজ পাঠান</a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">বন্ধ করুন</button>
            </div>
        </div>
    </div>
</div>

<script>
        (function($){
                $(document).ready(function(){
                        // When clicking student name, populate and show modal
                        $(document).on('click', '.student-link', function(e){
                                e.preventDefault();
                                var el = $(this);
                                var id = el.data('id');
                                var sid = el.data('studentid');
                                var name = el.data('name');
                                var father = el.data('father');
                                var mother = el.data('mother');
                                var mobile = el.data('mobile');
                                var cls = el.data('class');
                                var roll = el.data('roll');
                                var photo = el.data('photo');

                                $('#modalName').text(name);
                                $('#modalStudentId').text(sid || 'N/A');
                                $('#modalFather').text(father || '-');
                                $('#modalMother').text(mother || '-');
                                $('#modalMobile').text(mobile || '-');
                                $('#modalClass').text(cls || '-');
                                $('#modalRoll').text(roll || '-');
                                $('#modalAddress').text(el.data('address') || '-');
                                if (photo) {
                                        $('#modalPhoto').attr('src', photo);
                                } else {
                                        $('#modalPhoto').attr('src', 'https://via.placeholder.com/120x160');
                                }

                                // Call and SMS buttons
                                $('#callBtn').attr('href', 'tel:' + (mobile || ''));
                                $('#smsBtn').attr('href', 'sms:' + (mobile || ''));
                                $('#profileLink').attr('href', 'student_details.php?id=' + id);
                                $('#profileLinkUnderPhoto').attr('href', 'student_details.php?id=' + id);

                                $('#studentSummaryModal').modal('show');
                        });
            // Ensure toggle-status forms submit via POST and then reload
            $(document).on('submit', '.toggle-status-form', function(e){
                // allow normal submit; optionally could do AJAX
                // add a tiny confirmation for destructive actions
                // no-op here to keep UX simple
            });
                });
        })(jQuery);
</script>
</body>
</html>