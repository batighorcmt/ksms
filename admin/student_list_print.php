<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// Fetch classes from the database
$classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();

// Initialize variables
$students = [];
$filter_applied = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_report'])) {
    $filter_applied = true;
    
    // Sanitize and get form data
    $class_id = filter_var($_POST['class_id'], FILTER_SANITIZE_NUMBER_INT);
    $section_id = filter_var($_POST['section_id'], FILTER_SANITIZE_NUMBER_INT);
    $year = filter_var($_POST['year'], FILTER_SANITIZE_NUMBER_INT);
    $gender = filter_var($_POST['gender'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $religion = filter_var($_POST['religion'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $status = filter_var($_POST['status'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Build the WHERE clause for the SQL query
    $conditions = [];
    $params = [];

    if (!empty($class_id)) {
        $conditions[] = "s.class_id = ?";
        $params[] = $class_id;
    }
    if (!empty($section_id)) {
        $conditions[] = "s.section_id = ?";
        $params[] = $section_id;
    }
    if (!empty($year)) {
        $conditions[] = "YEAR(s.admission_date) = ?";
        $params[] = $year;
    }
    if (!empty($gender)) {
        $conditions[] = "s.gender = ?";
        $params[] = $gender;
    }
    if (!empty($religion)) {
        $conditions[] = "s.religion = ?";
        $params[] = $religion;
    }
    if (!empty($status)) {
        $conditions[] = "s.status = ?";
        $params[] = $status;
    }

    $sql = "
        SELECT 
            s.*,
            c.name as class_name,
            sec.name as section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
    ";

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY s.roll_number ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষার্থী তালিকা - কিন্ডার গার্ডেন</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Bengali Font -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    
    <style>
        body, .main-sidebar, .nav-link, .card, .form-control, .btn {
            font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;
        }
        .student-card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            background-color: #f8f9fa;
            border: none;
        }
        .student-card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
            border-radius: 12px 12px 0 0;
            font-weight: 600;
        }
        .student-info p {
            margin-bottom: 5px;
            color: #5a5c69;
            font-size: 1rem;
        }
        .student-info b {
            color: #2c3e50;
        }
        .no-print {
            display: block;
        }
        .print-only {
            display: none;
        }
        .form-card .card-header {
            background: linear-gradient(45deg, #28a745, #1d8236);
        }
        @media print {
            .no-print {
                display: none;
            }
            .print-only {
                display: block;
            }
            body {
                background-color: #fff;
            }
            .student-card {
                box-shadow: none;
                border: 1px solid #ccc;
                margin-bottom: 10px;
                page-break-inside: avoid;
            }
            .card-header {
                background-color: #4e73df !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
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
        <div class="content-header no-print">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">শিক্ষার্থী তালিকা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">শিক্ষার্থী তালিকা</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Filter Form Card -->
                <div class="card shadow-sm form-card no-print">
                    <div class="card-header">
                        <h4 class="card-title">ফিল্টার নির্বাচন করুন</h4>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <input type="hidden" name="generate_report" value="1">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="class_id">শ্রেণি</label>
                                        <select class="form-control" name="class_id" id="class_id" required>
                                            <option value="">নির্বাচন করুন</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?= htmlspecialchars($class['id']) ?>" <?= (isset($class_id) && $class_id == $class['id']) ? 'selected' : '' ?>><?= htmlspecialchars($class['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="section_id">শাখা (ঐচ্ছিক)</label>
                                        <select class="form-control" name="section_id" id="section_id">
                                            <option value="">সকল শাখা</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="year">বছর (ঐচ্ছিক)</label>
                                        <select class="form-control" name="year" id="year">
                                            <option value="">সকল বছর</option>
                                            <?php for ($y = date('Y'); $y >= 2010; $y--): ?>
                                                <option value="<?= $y ?>" <?= (isset($year) && $year == $y) ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="gender">লিঙ্গ (ঐচ্ছিক)</label>
                                        <select class="form-control" name="gender" id="gender">
                                            <option value="">সকল</option>
                                            <option value="Male" <?= (isset($gender) && $gender == 'Male') ? 'selected' : '' ?>>ছেলে</option>
                                            <option value="Female" <?= (isset($gender) && $gender == 'Female') ? 'selected' : '' ?>>মেয়ে</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="religion">ধর্ম (ঐচ্ছিক)</label>
                                        <select class="form-control" name="religion" id="religion">
                                            <option value="">সকল</option>
                                            <option value="Islam" <?= (isset($religion) && $religion == 'Islam') ? 'selected' : '' ?>>ইসলাম</option>
                                            <option value="Hinduism" <?= (isset($religion) && $religion == 'Hinduism') ? 'selected' : '' ?>>হিন্দু</option>
                                            <option value="Christianity" <?= (isset($religion) && $religion == 'Christianity') ? 'selected' : '' ?>>খ্রিস্টান</option>
                                            <option value="Buddhism" <?= (isset($religion) && $religion == 'Buddhism') ? 'selected' : '' ?>>বৌদ্ধ</option>
                                            <option value="Others" <?= (isset($religion) && $religion == 'Others') ? 'selected' : '' ?>>অন্যান্য</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="status">স্ট্যাটাস (ঐচ্ছিক)</label>
                                        <select class="form-control" name="status" id="status">
                                            <option value="">সকল</option>
                                            <option value="active" <?= (isset($status) && $status == 'active') ? 'selected' : '' ?>>সক্রিয়</option>
                                            <option value="inactive" <?= (isset($status) && $status == 'inactive') ? 'selected' : '' ?>>নিষ্ক্রিয়</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <button type="submit" name="generate_report" class="btn btn-primary"><i class="fas fa-search"></i> অনুসন্ধান করুন</button>
                                    <button type="button" class="btn btn-success ml-2" onclick="window.print()"><i class="fas fa-print"></i> প্রিন্ট করুন</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Student List Section -->
                <?php if ($filter_applied): ?>
                    <div class="print-only text-center my-4">
                        <h3 class="font-weight-bold">শিক্ষার্থী তালিকা</h3>
                        <p class="text-muted">
                            <?php 
                                $class_name = '';
                                foreach ($classes as $class) {
                                    if ($class['id'] == $class_id) {
                                        $class_name = $class['name'];
                                        break;
                                    }
                                }
                            ?>
                            <?php echo "শ্রেণি: " . ($class_name ? $class_name : 'সকল'); ?> |
                            <?php echo "শাখা: " . (!empty($section_id) ? 'নির্বাচিত শাখা' : 'সকল'); ?> |
                            <?php echo "বছর: " . ($year ?? 'সকল'); ?> |
                            <?php echo "লিঙ্গ: " . ($gender ?? 'সকল'); ?> |
                            <?php echo "ধর্ম: " . ($religion ?? 'সকল'); ?> |
                            <?php echo "স্ট্যাটাস: " . ($status ?? 'সকল'); ?>
                        </p>
                    </div>
                    
                    <?php if (!empty($students)): ?>
                        <div class="row">
                            <?php foreach ($students as $student): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card student-card">
                                        <div class="card-header py-2">
                                            <h5 class="card-title text-center my-0"><b><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></b></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="student-info">
                                                <p><b>রোল:</b> <?= htmlspecialchars($student['roll_number']) ?></p>
                                                <p><b>শ্রেণি:</b> <?= htmlspecialchars($student['class_name']) ?></p>
                                                <p><b>শাখা:</b> <?= htmlspecialchars($student['section_name'] ?? 'N/A') ?></p>
                                                <p><b>লিঙ্গ:</b> <?= htmlspecialchars($student['gender']) ?></p>
                                                <p><b>ধর্ম:</b> <?= htmlspecialchars($student['religion']) ?></p>
                                                <p><b>স্ট্যাটাস:</b> <?= htmlspecialchars($student['status'] == 'active' ? 'সক্রিয়' : 'নিষ্ক্রিয়') ?></p>
                                                <p><b>ভর্তির বছর:</b> <?= htmlspecialchars(date('Y', strtotime($student['admission_date']))) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning text-center">
                            কোনো শিক্ষার্থী পাওয়া যায়নি।
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Main Footer -->
    <?php include 'inc/footer.php'; ?>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
    $(document).ready(function() {
        // Load sections on class change
        $('#class_id').change(function() {
            var classId = $(this).val();
            if (classId) {
                $.ajax({
                    url: 'get_sections.php',
                    type: 'GET',
                    data: {class_id: classId},
                    success: function(data) {
                        $('#section_id').html(data);
                    }
                });
            } else {
                $('#section_id').html('<option value="">সকল শাখা</option>');
            }
        });
        
        // Load sections on page load if class_id is pre-selected
        var preSelectedClassId = $('#class_id').val();
        if (preSelectedClassId) {
            $.ajax({
                url: 'get_sections.php',
                type: 'GET',
                data: {class_id: preSelectedClassId},
                success: function(data) {
                    $('#section_id').html(data);
                    // Retain pre-selected section
                    var preSelectedSectionId = '<?= isset($section_id) ? $section_id : '' ?>';
                    if (preSelectedSectionId) {
                        $('#section_id').val(preSelectedSectionId);
                    }
                }
            });
        }
    });
</script>
</body>
</html>