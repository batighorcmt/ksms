<?php
require_once '../config.php';
require_once __DIR__ . '/inc/enrollment_helpers.php';
require_once __DIR__ . '/print_common.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// Fetch classes from the database
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_value ASC, name ASC")->fetchAll();

// Load academic years with runtime label guard and current marker
$academic_years = [];
$current_ac_year_id = current_academic_year_id($pdo);
$ay_has_name = false; $ay_has_year = false; $ay_has_start = false; $ay_has_end = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM academic_years")->fetchAll(PDO::FETCH_COLUMN);
    $ay_has_name = in_array('name', $cols);
    $ay_has_year = in_array('year', $cols);
    $ay_has_start = in_array('start_date', $cols) || in_array('start_year', $cols);
    $ay_has_end = in_array('end_date', $cols) || in_array('end_year', $cols);
} catch (Exception $e) {
    // ignore
}
try {
    $academic_years = $pdo->query("SELECT * FROM academic_years ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $academic_years = [];
}

// Helper to format academic year label
$format_academic_year_label = function(array $row) use ($ay_has_name, $ay_has_year, $ay_has_start, $ay_has_end): string {
    if ($ay_has_name && !empty($row['name'])) return (string)$row['name'];
    if ($ay_has_year && !empty($row['year'])) return (string)$row['year'];
    // Try start/end year or date fields
    $start = $row['start_year'] ?? $row['start_date'] ?? '';
    $end = $row['end_year'] ?? $row['end_date'] ?? '';
    if ($start && $end) return sprintf('%s - %s', (string)$start, (string)$end);
    return 'Year #' . ($row['id'] ?? '');
};

// Initialize variables
$students = [];
$filter_applied = false;
$selected_academic_year_id = $current_ac_year_id; // default to current
$selected_academic_year_label = '';

// Available columns and default selection (order matters)
$available_columns = [
    'serial' => 'ক্রম',
    'photo' => 'ছবি',
    'id' => 'আইডি',
    'name' => 'শিক্ষার্থীর নাম',
    'father' => 'পিতার নাম',
    'mother' => 'মাতার নাম',
    'guardian' => 'অভিভাবক',
    'relation' => 'সম্পর্ক',
    'roll' => 'রোল',
    'class' => 'শ্রেণি',
    'section' => 'শাখা',
    'dob' => 'জন্ম তারিখ',
    'admission_date' => 'ভর্তির তারিখ',
    'gender' => 'লিঙ্গ',
    'religion' => 'ধর্ম',
    'status' => 'স্ট্যাটাস',
    'mobile' => 'মোবাইল',
    'address' => 'ঠিকানা',
    'action' => 'অ্যাকশন',
];

$selected_columns = array_keys($available_columns); // default: all
// Default column order (1-based) for display and POST persistence
$column_orders = [];
foreach (array_keys($available_columns) as $idx => $k) { $column_orders[$k] = $idx + 1; }

// Helper: Convert English digits to Bangla digits
if (!function_exists('bn_digits')) {
    function bn_digits($input): string {
        $en = ['0','1','2','3','4','5','6','7','8','9'];
        $bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
        return str_replace($en, $bn, (string)$input);
    }
}

// Helper: Format date (d/m/Y) and convert digits to Bangla
if (!function_exists('bn_date')) {
    function bn_date(?string $dateStr): string {
        if (empty($dateStr)) return '';
        $formatted = date('d/m/Y', strtotime($dateStr));
        return bn_digits($formatted);
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_report'])) {
    $filter_applied = true;
    
    // Sanitize and get form data
    $class_id = filter_var($_POST['class_id'], FILTER_SANITIZE_NUMBER_INT);
    $section_id = filter_var($_POST['section_id'], FILTER_SANITIZE_NUMBER_INT);
    // Academic year (selected); fallback to current
    $selected_academic_year_id = isset($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : $current_ac_year_id;
    $gender = filter_var($_POST['gender'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $gender_l = $gender !== null ? strtolower(trim($gender)) : '';
    $religion = filter_var($_POST['religion'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $status = filter_var($_POST['status'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    // Selected columns (checkboxes)
    if (isset($_POST['columns']) && is_array($_POST['columns'])) {
        $selected_columns = array_values(array_intersect(array_keys($available_columns), array_map('strval', $_POST['columns'])));
        if (empty($selected_columns)) { $selected_columns = ['serial','id','name','roll','class','section','mobile']; }
    }
    // Column order mapping (from numeric inputs)
    $posted_orders = isset($_POST['order']) && is_array($_POST['order']) ? $_POST['order'] : [];
    // Initialize default orders
    $baseIndex = array_flip(array_keys($available_columns));
    foreach ($selected_columns as $k) {
        $column_orders[$k] = isset($posted_orders[$k]) ? max(1, (int)$posted_orders[$k]) : ($baseIndex[$k] + 1);
    }
    // Sort selected columns by user-provided order, tie-breaker by base index
    usort($selected_columns, function($a,$b) use ($column_orders, $baseIndex){
        $oa = $column_orders[$a] ?? PHP_INT_MAX; $ob = $column_orders[$b] ?? PHP_INT_MAX;
        if ($oa === $ob) { return ($baseIndex[$a] ?? 0) <=> ($baseIndex[$b] ?? 0); }
        return $oa <=> $ob;
    });

    // Build the WHERE clause for the SQL query
    $conditions = [];
    $params = [];

    // Use enrollment filters by academic year/class/section
    $conditions[] = "se.academic_year_id = ?"; $params[] = $selected_academic_year_id;
    if (!empty($class_id)) { $conditions[] = "se.class_id = ?"; $params[] = $class_id; }
    if (!empty($section_id)) { $conditions[] = "se.section_id = ?"; $params[] = $section_id; }
    if (!empty($gender_l)) {
        $conditions[] = "LOWER(s.gender) = ?";
        $params[] = $gender_l;
    }
    if (!empty($religion)) {
        $conditions[] = "s.religion = ?";
        $params[] = $religion;
    }
    if (!empty($status)) {
        $conditions[] = "COALESCE(se.status, s.status) = ?";
        $params[] = $status;
    }

    // Detect guardian_id column and users name column
    $has_guardian_id = false; $users_name_col = 'full_name';
    try {
        $scols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
        $has_guardian_id = in_array('guardian_id', $scols);
        // Also detect if guardian_name and guardian_relation exist; safely rely on s.* otherwise
    } catch (Exception $e) { $has_guardian_id = false; }
    try {
        $ucols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('full_name', $ucols) && in_array('name', $ucols)) { $users_name_col = 'name'; }
    } catch (Exception $e) { $users_name_col = 'full_name'; }

    $sql = "
        SELECT 
            s.id AS s_id,
            s.student_id AS public_id,
            s.first_name, s.last_name,
            s.father_name, s.mother_name,
            s.guardian_name AS s_guardian_name,
            s.guardian_relation,
            s.admission_date,
            s.date_of_birth,
            s.photo,
            s.gender, s.religion, s.mobile_number, s.present_address,
            s.status AS student_status,
            se.roll_number,
            COALESCE(se.status, s.status) AS combined_status,
            c.name as class_name,
            sec.name as section_name";
    if ($has_guardian_id) {
        $sql .= ", COALESCE(s.guardian_name, u.$users_name_col) AS guardian_name";
    } else {
        $sql .= ", s.guardian_name AS guardian_name";
    }
    $sql .= "
        FROM students s
        JOIN students_enrollment se ON se.student_id = s.id
        LEFT JOIN classes c ON c.id = se.class_id
        LEFT JOIN sections sec ON sec.id = se.section_id";
    if ($has_guardian_id) {
        $sql .= " LEFT JOIN users u ON u.id = s.guardian_id";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY c.numeric_value ASC, sec.name ASC, se.roll_number ASC, s.first_name ASC";

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
        .no-print { display: block; }
        .print-only { display: none; }
        .table-sm td, .table-sm th { padding: .35rem; }
        .table thead th { position: sticky; top: 0; background:#f1f5f9; z-index:1; }
        .report-meta { font-size: 0.95rem; color:#374151; }
        .form-card .card-header {
            background: linear-gradient(45deg, #28a745, #1d8236);
        }
        .stu-photo { width: 42px; height: 52px; object-fit: cover; border:1px solid #ddd; border-radius: 2px; }
        .table td, .table th { vertical-align: middle; }
        .text-nowrap { white-space: nowrap; }
        @media (max-width: 768px){
            .table-wrap { overflow-x:auto; }
        }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { background-color: #fff; }
            .main-header, .main-sidebar, .control-sidebar, .navbar, .sidebar, .breadcrumb, .main-footer { display:none !important; }
            .content-wrapper { margin-left:0 !important; }
            .card, .card-body { box-shadow:none !important; }
            .table { font-size: 12px; }
            .table th, .table td { padding: 6px !important; }
            .table thead th { background:#e5e7eb !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
        }

        /* Default print orientation: portrait */
        @media print {
            @page { size: A4 portrait; margin: 12mm; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Navbar -->
    <?php include 'inc/header.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php
    if (hasRole(['super_admin'])) {
        include 'inc/sidebar.php';
    } elseif (hasRole(['teacher'])) {
        include '../teacher/inc/sidebar.php';
    }
    ?>

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
                                        <select class="form-control" name="class_id" id="class_id" >
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
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="academic_year_id">শিক্ষাবর্ষ</label>
                                        <select class="form-control" name="academic_year_id" id="academic_year_id">
                                            <?php foreach ($academic_years as $ay): ?>
                                                <?php 
                                                    $label = $format_academic_year_label($ay);
                                                    $isCurrent = isset($ay['is_current']) && (int)$ay['is_current'] === 1;
                                                    if ($selected_academic_year_id == null && $isCurrent) { $selected_academic_year_id = (int)$ay['id']; }
                                                    if ($selected_academic_year_id == $ay['id']) { $selected_academic_year_label = $label; }
                                                ?>
                                                <option value="<?= (int)$ay['id'] ?>" <?= ($selected_academic_year_id == (int)$ay['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label) ?><?= $isCurrent ? ' (বর্তমান)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted">বর্তমান শিক্ষাবর্ষ: 
                                            <?php 
                                                $currLabel = '';
                                                foreach ($academic_years as $ay) { if (!empty($ay['is_current'])) { $currLabel = $format_academic_year_label($ay); break; } }
                                                echo htmlspecialchars($currLabel ?: 'অনির্ধারিত');
                                            ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="gender">লিঙ্গ (ঐচ্ছিক)</label>
                                        <select class="form-control" name="gender" id="gender">
                                            <option value="">সকল</option>
                                            <option value="male" <?= (isset($gender_l) && $gender_l == 'male') ? 'selected' : '' ?>>পুরুষ</option>
                                            <option value="female" <?= (isset($gender_l) && $gender_l == 'female') ? 'selected' : '' ?>>মহিলা</option>
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
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group mb-1"><label>কলাম নির্বাচন করুন</label></div>
                                </div>
                                <?php foreach ($available_columns as $key => $label): ?>
                                    <div class="col-md-2 col-sm-3 col-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[]" id="col_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($key) ?>" <?= in_array($key, $selected_columns, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="col_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>
                                            <div>
                                                <small class="text-muted">ক্রম</small>
                                                <input type="number" min="1" class="form-control form-control-sm" style="width:80px; display:inline-block;" name="order[<?= htmlspecialchars($key) ?>]" id="order_<?= htmlspecialchars($key) ?>" value="<?= isset($column_orders[$key]) ? (int)$column_orders[$key] : 99 ?>">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <button type="submit" name="generate_report" class="btn btn-primary"><i class="fas fa-search"></i> অনুসন্ধান করুন</button>
                                    <button type="button" class="btn btn-success ml-2" onclick="window.print()"><i class="fas fa-print"></i> পোর্ট্রেটে প্রিন্ট</button>
                                    <button type="button" class="btn btn-secondary ml-2" onclick="printWithOrientation('landscape')"><i class="fas fa-print"></i> ল্যান্ডস্কেপে প্রিন্ট</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Student List Section -->
                <?php if ($filter_applied): ?>
                    <div class="print-only my-2">
                        <?php echo print_header($pdo, 'শিক্ষার্থী তালিকা'); ?>
                        <div class="text-center">
                        <p class="text-muted report-meta">
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
                            <?php 
                                $selAYLabel = '';
                                foreach ($academic_years as $ay) { if ((int)$ay['id'] === (int)$selected_academic_year_id) { $selAYLabel = $format_academic_year_label($ay); break; } }
                                echo "শিক্ষাবর্ষ: " . ($selAYLabel ? bn_digits($selAYLabel) : 'বর্তমান');
                            ?> |
                            <?php 
                                $gLabel = 'সকল';
                                if (!empty($gender_l)) {
                                    if ($gender_l === 'male') $gLabel = 'পুরুষ';
                                    elseif ($gender_l === 'female') $gLabel = 'মহিলা';
                                    else $gLabel = 'অন্যান্য';
                                }
                                echo "লিঙ্গ: " . $gLabel; 
                            ?> |
                            <?php echo "ধর্ম: " . ($religion ?? 'সকল'); ?> |
                            <?php echo "স্ট্যাটাস: " . ($status ?? 'সকল'); ?>
                        </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($students)): ?>
                        <div class="card">
                            <div class="card-header no-print">
                                <h3 class="card-title">ফলাফল: মোট <?= bn_digits(count($students)) ?> জন</h3>
                            </div>
                            <div class="card-body table-responsive p-0 table-wrap">
                                <table class="table table-bordered table-striped table-hover table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <?php foreach ($selected_columns as $colKey): ?>
                                                <?php 
                                                    $thClasses = ['text-center','align-middle'];
                                                    if ($colKey === 'action') { $thClasses[] = 'no-print'; }
                                                    $style = ($colKey === 'serial') ? ' style="width:60px"' : '';
                                                    $thAttr = ' class="' . implode(' ', $thClasses) . '"';
                                                ?>
                                                <th<?= $thAttr ?><?= $style ?>><?= htmlspecialchars($available_columns[$colKey] ?? $colKey) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i=1; foreach ($students as $student): ?>
                                            <tr>
                                                <?php foreach ($selected_columns as $colKey): ?>
                                                    <?php 
                                                        $leftCols = ['name','father','mother','guardian','address'];
                                                        $classes = in_array($colKey, $leftCols, true) ? ['text-left'] : ['text-center'];
                                                        if ($colKey === 'action') { $classes[] = 'no-print'; }
                                                        $tdAttr = ' class="' . implode(' ', $classes) . '"';
                                                    ?>
                                                    <?php switch ($colKey) {
                                                        case 'serial': ?>
                                                            <td<?= $tdAttr ?>><?= bn_digits((string)$i++) ?></td>
                                                        <?php break; case 'photo': ?>
                                                            <td<?= $tdAttr ?>>
                                                                <?php if (!empty($student['photo'])): ?>
                                                                    <img class="stu-photo" src="../uploads/students/<?= htmlspecialchars($student['photo']) ?>" alt="ছবি">
                                                                <?php else: ?>
                                                                    <img class="stu-photo" src="https://via.placeholder.com/42x52?text=%E0%A6%9B%E0%A6%AC%E0%A6%BF" alt="ছবি নেই">
                                                                <?php endif; ?>
                                                            </td>
                                                        <?php break; case 'id': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars((string)($student['public_id'] ?: $student['s_id'])) ?></td>
                                                        <?php break; case 'name': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))) ?></td>
                                                        <?php break; case 'father': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars($student['father_name'] ?? '') ?></td>
                                                        <?php break; case 'mother': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars($student['mother_name'] ?? '') ?></td>
                                                        <?php break; case 'guardian': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars($student['guardian_name'] ?? $student['s_guardian_name'] ?? '') ?></td>
                                                        <?php break; case 'relation': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars($student['guardian_relation'] ?? '') ?></td>
                                                        <?php break; case 'roll': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars(bn_digits((string)($student['roll_number'] ?? ''))) ?></td>
                                                        <?php break; case 'class': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars($student['class_name'] ?? '') ?></td>
                                                        <?php break; case 'section': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars($student['section_name'] ?? '') ?></td>
                                                        <?php break; case 'admission_date': ?>
                                                            <td<?= $tdAttr ?>><?= !empty($student['admission_date']) ? htmlspecialchars(bn_date($student['admission_date'])) : '' ?></td>
                                                        <?php break; case 'dob': ?>
                                                            <td<?= $tdAttr ?>><?= !empty($student['date_of_birth']) ? htmlspecialchars(bn_date($student['date_of_birth'])) : '' ?></td>
                                                        <?php break; case 'gender': ?>
                                                            <td<?= $tdAttr ?>>
                                                                <?php 
                                                                    $g = strtolower(trim($student['gender'] ?? ''));
                                                                    if ($g === 'male') echo 'পুরুষ';
                                                                    elseif ($g === 'female') echo 'মহিলা';
                                                                    else echo 'অন্যান্য';
                                                                ?>
                                                            </td>
                                                        <?php break; case 'religion': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars($student['religion'] ?? '') ?></td>
                                                        <?php break; case 'status': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars(($student['combined_status'] ?? $student['student_status'] ?? '') === 'active' ? 'সক্রিয়' : 'নিষ্ক্রিয়') ?></td>
                                                        <?php break; case 'mobile': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars(bn_digits($student['mobile_number'] ?? '')) ?></td>
                                                        <?php break; case 'address': ?>
                                                            <td<?= $tdAttr ?>><?= htmlspecialchars($student['present_address'] ?? '') ?></td>
                                                        <?php break; case 'action': ?>
                                                            <td<?= $tdAttr ?>>
                                                                <a class="btn btn-xs btn-outline-primary" href="<?= BASE_URL; ?>admin/student_details.php?id=<?= (int)$student['s_id'] ?>" target="_blank"><i class="fas fa-eye"></i></a>
                                                            </td>
                                                        <?php break; default: ?>
                                                            <td<?= $tdAttr ?>></td>
                                                    <?php } ?>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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

    // Print helper: temporarily set print orientation and trigger print
    function printWithOrientation(orientation) {
        try {
            var css = '@media print{ @page { size: A4 ' + (orientation === 'landscape' ? 'landscape' : 'portrait') + '; margin: 12mm; } }';
            var style = document.createElement('style');
            style.setAttribute('id', 'print-orientation-style');
            style.type = 'text/css';
            style.appendChild(document.createTextNode(css));
            document.head.appendChild(style);
            var cleanup = function(){
                var el = document.getElementById('print-orientation-style');
                if (el && el.parentNode) el.parentNode.removeChild(el);
                window.removeEventListener('afterprint', cleanup);
            };
            window.addEventListener('afterprint', cleanup);
        } catch (e) {
            // ignore
        } finally {
            window.print();
        }
    }
</script>
<?php // Ensure print footer behaves exactly like other print pages
echo print_footer(); ?>
</body>
</html>