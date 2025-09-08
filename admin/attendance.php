<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// Get today's date for default selection
$current_date = date('Y-m-d');

// Get classes and sections
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();

// Initialize variables
$selected_class = '';
$selected_section = '';
$selected_date = $current_date;
$attendance_data = [];
$students = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $class_id = intval($_POST['class_id']);
    $section_id = intval($_POST['section_id']);
    $date = $_POST['date'];
    
    // Check if attendance already exists for this date, class, and section
    $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE class_id = ? AND section_id = ? AND date = ?");
    $check_stmt->execute([$class_id, $section_id, $date]);
    $result = $check_stmt->fetch();
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = "এই তারিখে ইতিমধ্যেই উপস্থিতি রেকর্ড করা হয়েছে!";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Get students for the selected class and section
            $student_stmt = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND section_id = ? AND status='active'");
            $student_stmt->execute([$class_id, $section_id]);
            $student_ids = $student_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Insert attendance records
            $attendance_stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, class_id, section_id, date, status, remarks, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $recorded_by = $_SESSION['user_id'];
            
            foreach ($student_ids as $student_id) {
                $status = $_POST['attendance'][$student_id]['status'] ?? 'absent';
                $remarks = $_POST['attendance'][$student_id]['remarks'] ?? '';
                
                $attendance_stmt->execute([$student_id, $class_id, $section_id, $date, $status, $remarks, $recorded_by]);
            }
            
            $pdo->commit();
            $_SESSION['success'] = "উপস্থিতি সফলভাবে রেকর্ড করা হয়েছে!";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "উপস্থিতি রেকর্ড করতে সমস্যা হয়েছে: " . $e->getMessage();
        }
    }
    
    // Set selected values for form
    $selected_class = $class_id;
    $selected_section = $section_id;
    $selected_date = $date;
}

// Handle view attendance request
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['view_attendance'])) {
    $selected_class = intval($_GET['class_id']);
    $selected_section = intval($_GET['section_id']);
    $selected_date = $_GET['date'];
    
    // Get attendance data for the selected date, class, and section
    $attendance_stmt = $pdo->prepare("
        SELECT a.*, s.first_name, s.last_name, s.roll_number 
        FROM attendance a 
        JOIN students s ON a.student_id = s.id 
        WHERE a.class_id = ? AND a.section_id = ? AND a.date = ?
        ORDER BY s.roll_number ASC
    ");
    $attendance_stmt->execute([$selected_class, $selected_section, $selected_date]);
    $attendance_data = $attendance_stmt->fetchAll();
    
    // Get students list for the selected class and section
    $student_stmt = $pdo->prepare("
        SELECT id, first_name, last_name, roll_number 
        FROM students 
        WHERE class_id = ? AND section_id = ? AND status='active'
        ORDER BY roll_number ASC
    ");
    $student_stmt->execute([$selected_class, $selected_section]);
    $students = $student_stmt->fetchAll();
}

// Get sections based on selected class
$sections = [];
if ($selected_class) {
    $section_stmt = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? AND status='active'");
    $section_stmt->execute([$selected_class]);
    $sections = $section_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>উপস্থিতি ব্যবস্থাপনা - কিন্ডার গার্ডেন</title>

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
        .attendance-card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        .attendance-card .card-header {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
        }
        .attendance-status {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .attendance-status label {
            margin-bottom: 0;
            cursor: pointer;
        }
        .attendance-table th {
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 600;
        }
        .badge-attendance {
            font-size: 0.85em;
            padding: 0.4em 0.8em;
            border-radius: 4px;
        }
        .present {
            background-color: #28a745;
            color: white;
        }
        .absent {
            background-color: #dc3545;
            color: white;
        }
        .late {
            background-color: #ffc107;
            color: #212529;
        }
        .half_day {
            background-color: #17a2b8;
            color: white;
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
                        <h1 class="m-0 text-dark">উপস্থিতি ব্যবস্থাপনা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">উপস্থিতি ব্যবস্থাপনা</li>
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
                    <div class="col-md-12">
                        <div class="card attendance-card">
                            <div class="card-header">
                                <h3 class="card-title">উপস্থিতি রেকর্ড/দেখুন</h3>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="" class="mb-4">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="class_id">ক্লাস নির্বাচন করুন</label>
                                                <select class="form-control" id="class_id" name="class_id" required>
                                                    <option value="">নির্বাচন করুন</option>
                                                    <?php foreach($classes as $class): ?>
                                                        <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class == $class['id']) ? 'selected' : ''; ?>>
                                                            <?php echo $class['name']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="section_id">শাখা নির্বাচন করুন</label>
                                                <select class="form-control" id="section_id" name="section_id" required>
                                                    <option value="">নির্বাচন করুন</option>
                                                    <?php foreach($sections as $section): ?>
                                                        <option value="<?php echo $section['id']; ?>" <?php echo ($selected_section == $section['id']) ? 'selected' : ''; ?>>
                                                            <?php echo $section['name']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="date">তারিখ</label>
                                                <input type="date" class="form-control" id="date" name="date" value="<?php echo $selected_date; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group" style="margin-top: 32px;">
                                                <button type="submit" name="view_attendance" class="btn btn-primary">
                                                    <i class="fas fa-eye"></i> দেখুন
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <?php if(!empty($students) || !empty($attendance_data)): ?>
                                    <hr>
                                    
                                    <?php if(empty($attendance_data)): ?>
                                        <!-- Mark Attendance Form -->
                                        <form method="POST" action="">
                                            <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                                            <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
                                            <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h4>উপস্থিতি রেকর্ড করুন</h4>
                                                <button type="submit" name="mark_attendance" class="btn btn-success">
                                                    <i class="fas fa-save"></i> উপস্থিতি সাবমিট করুন
                                                </button>
                                            </div>
                                            
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-striped attendance-table">
                                                    <thead>
                                                        <tr>
                                                            <th width="50">রোল</th>
                                                            <th>শিক্ষার্থীর নাম</th>
                                                            <th width="250">উপস্থিতি অবস্থা</th>
                                                            <th width="300">মন্তব্য</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach($students as $student): ?>
                                                            <tr>
                                                                <td><?php echo $student['roll_number']; ?></td>
                                                                <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                                                                <td>
                                                                    <div class="attendance-status">
                                                                        <div class="form-check form-check-inline">
                                                                            <input class="form-check-input" type="radio" name="attendance[<?php echo $student['id']; ?>][status]" id="present_<?php echo $student['id']; ?>" value="present" checked>
                                                                            <label class="form-check-label" for="present_<?php echo $student['id']; ?>">উপস্থিত</label>
                                                                        </div>
                                                                        <div class="form-check form-check-inline">
                                                                            <input class="form-check-input" type="radio" name="attendance[<?php echo $student['id']; ?>][status]" id="absent_<?php echo $student['id']; ?>" value="absent">
                                                                            <label class="form-check-label" for="absent_<?php echo $student['id']; ?>">অনুপস্থিত</label>
                                                                        </div>
                                                                        <div class="form-check form-check-inline">
                                                                            <input class="form-check-input" type="radio" name="attendance[<?php echo $student['id']; ?>][status]" id="late_<?php echo $student['id']; ?>" value="late">
                                                                            <label class="form-check-label" for="late_<?php echo $student['id']; ?>">দেরী</label>
                                                                        </div>
                                                                        <div class="form-check form-check-inline">
                                                                            <input class="form-check-input" type="radio" name="attendance[<?php echo $student['id']; ?>][status]" id="half_day_<?php echo $student['id']; ?>" value="half_day">
                                                                            <label class="form-check-label" for="half_day_<?php echo $student['id']; ?>">অর্ধদিবস</label>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control form-control-sm" name="attendance[<?php echo $student['id']; ?>][remarks]" placeholder="মন্তব্য (ঐচ্ছিক)">
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <!-- View Attendance Data -->
                                        <h4>উপস্থিতি বিবরণ (<?php echo date('d/m/Y', strtotime($selected_date)); ?>)</h4>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped attendance-table">
                                                <thead>
                                                    <tr>
                                                        <th width="50">রোল</th>
                                                        <th>শিক্ষার্থীর নাম</th>
                                                        <th width="150">উপস্থিতি অবস্থা</th>
                                                        <th>মন্তব্য</th>
                                                        <th width="120">রেকর্ড করার সময়</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($attendance_data as $record): ?>
                                                        <tr>
                                                            <td><?php echo $record['roll_number']; ?></td>
                                                            <td><?php echo $record['first_name'] . ' ' . $record['last_name']; ?></td>
                                                            <td>
                                                                <?php 
                                                                $status_class = '';
                                                                $status_text = '';
                                                                switch($record['status']) {
                                                                    case 'present':
                                                                        $status_class = 'present';
                                                                        $status_text = 'উপস্থিত';
                                                                        break;
                                                                    case 'absent':
                                                                        $status_class = 'absent';
                                                                        $status_text = 'অনুপস্থিত';
                                                                        break;
                                                                    case 'late':
                                                                        $status_class = 'late';
                                                                        $status_text = 'দেরী';
                                                                        break;
                                                                    case 'half_day':
                                                                        $status_class = 'half_day';
                                                                        $status_text = 'অর্ধদিবস';
                                                                        break;
                                                                }
                                                                ?>
                                                                <span class="badge badge-attendance <?php echo $status_class; ?>">
                                                                    <?php echo $status_text; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo $record['remarks']; ?></td>
                                                            <td><?php echo date('H:i', strtotime($record['created_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <a href="attendance.php" class="btn btn-primary">
                                                <i class="fas fa-arrow-left"></i> নতুন অনুসন্ধান
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif($selected_class && $selected_section): ?>
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle"></i> এই ক্লাস এবং শাখায় কোনো শিক্ষার্থী নেই।
                                    </div>
                                <?php endif; ?>
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

<script>
    $(document).ready(function() {
        // Load sections when class is selected
        $('#class_id').change(function() {
            var class_id = $(this).val();
            if (class_id) {
                $.ajax({
                    url: 'get_sections.php',
                    type: 'GET',
                    data: {class_id: class_id},
                    success: function(data) {
                        $('#section_id').html(data);
                    }
                });
            } else {
                $('#section_id').html('<option value="">নির্বাচন করুন</option>');
            }
        });
    });
</script>
</body>
</html>