<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// Get teacher id
$teacher_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($teacher_id <= 0) {
    $_SESSION['error'] = 'অবৈধ শিক্ষক আইডি!';
    header('Location: teachers.php');
    exit();
}

// Load teacher with profile
$tstmt = $pdo->prepare("SELECT u.*, tp.father_name, tp.mother_name, tp.date_of_birth, tp.gender, tp.blood_group, tp.religion, tp.address, tp.joining_date, tp.qualification, tp.experience FROM users u LEFT JOIN teacher_profiles tp ON u.id = tp.teacher_id WHERE u.id = ? AND u.role = 'teacher'");
$tstmt->execute([$teacher_id]);
$teacher = $tstmt->fetch(PDO::FETCH_ASSOC);
if (!$teacher) {
    $_SESSION['error'] = 'শিক্ষক পাওয়া যায়নি!';
    header('Location: teachers.php');
    exit();
}

// Routine-derived classes and subjects
$rcStmt = $pdo->prepare("SELECT DISTINCT c.id AS class_id, c.name AS class_name FROM routines r JOIN classes c ON r.class_id = c.id WHERE r.teacher_id = ? ORDER BY c.name ASC");
$rcStmt->execute([$teacher_id]);
$routineClasses = $rcStmt->fetchAll(PDO::FETCH_ASSOC);

$rsStmt = $pdo->prepare("SELECT DISTINCT sub.id AS subject_id, sub.name AS subject_name, c.name AS class_name FROM routines r JOIN subjects sub ON r.subject_id = sub.id JOIN classes c ON r.class_id = c.id WHERE r.teacher_id = ? ORDER BY sub.name ASC");
$rsStmt->execute([$teacher_id]);
$routine_subjects = $rsStmt->fetchAll(PDO::FETCH_ASSOC);

$total_classes = count($routineClasses);
$total_subjects = count($routine_subjects);

// হাজিরা সারাংশ (নির্বাচিত মাস অনুযায়ী)
$now = date('Y-m-d');
// Month selection and range
$selectedMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
$monthStart = $selectedMonth.'-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$monthEndBound = ($selectedMonth === date('Y-m')) ? min($monthEnd, $now) : $monthEnd;
// Attendance rows within month
$attStmt = $pdo->prepare("SELECT date, status, check_in, check_out FROM teacher_attendance WHERE teacher_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC");
$attStmt->execute([$teacher_id, $monthStart, $monthEndBound]);
$attRows = $attStmt->fetchAll(PDO::FETCH_ASSOC);
// Build daily map and counts
$recordsByDate = [];
foreach ($attRows as $r) { $recordsByDate[$r['date']] = $r; }
$presentDays = 0; $lateDays = 0; $earlyDays = 0;
foreach ($recordsByDate as $rec) {
    $st = strtolower($rec['status'] ?? '');
    if (in_array($st, ['present','late','early'])) $presentDays++;
    if ($st === 'late') $lateDays++;
    if ($st === 'early') $earlyDays++;
}
// Helper functions and weekly offs
function getWeeklyOffs($pdo){
    try{
        $rows = $pdo->query("SELECT day_name, day_number FROM weekly_holidays WHERE status='active'")->fetchAll(PDO::FETCH_ASSOC);
        $offs = [];
        // Name maps: prefer day_name when available to avoid numeric convention mismatches.
        $mapEn = ['sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6];
        // Bengali long and short names
        $mapBn = [
            'রবিবার'=>0,'রবি'=>0,
            'সোমবার'=>1,'সোম'=>1,
            'মঙ্গলবার'=>2,'মঙ্গল'=>2,
            'বুধবার'=>3,'বুধ'=>3,
            'বৃহস্পতিবার'=>4,'বৃহস্পতি'=>4,
            'শুক্রবার'=>5,'শুক্র'=>5,
            'শনিবার'=>6,'শনি'=>6
        ];
        foreach($rows as $r){
            $added = false;
            if (!empty($r['day_name'])) {
                $nameRaw = trim($r['day_name']);
                $nameLower = strtolower($nameRaw);
                if (isset($mapEn[$nameLower])) { $offs[] = $mapEn[$nameLower]; $added = true; }
                elseif (isset($mapBn[$nameRaw])) { $offs[] = $mapBn[$nameRaw]; $added = true; }
            }
            if (!$added && $r['day_number'] !== null && $r['day_number'] !== '' && is_numeric($r['day_number'])) {
                $dn = (int)$r['day_number'];
                // Supported conventions:
                // - PHP style: 0..6 (Sun..Sat)
                // - ISO-like: 1..7 (Mon..Sun)
                if ($dn >= 0 && $dn <= 6) {
                    $offs[] = $dn; // already PHP's numbering
                } elseif ($dn >= 1 && $dn <= 7) {
                    // Assume Monday=1..Sunday=7, convert to PHP 0..6
                    $mapMon = [1=>1,2=>2,3=>3,4=>4,5=>5,6=>6,7=>0];
                    $offs[] = $mapMon[$dn];
                }
            }
        }
        return array_values(array_unique($offs));
    }catch(Exception $e){ return []; }
}
function getHolidaySet($pdo, $start, $end){
    $stmt = $pdo->prepare("SELECT date FROM holidays WHERE status='active' AND date BETWEEN ? AND ?");
    $stmt->execute([$start, $end]);
    $set = [];
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $d){ $set[$d] = true; }
    return $set;
}
function countWorkingDays($start, $end, $weeklyOffs, $holidaySet, $joinDate=null){
    $startDate = $start;
    if ($joinDate && $joinDate > $startDate) $startDate = $joinDate;
    $cnt = 0; $cur = strtotime($startDate); $endTs = strtotime($end);
    while($cur !== false && $cur <= $endTs){
        $d = date('Y-m-d', $cur); $w = (int)date('w', $cur);
        if (!in_array($w, $weeklyOffs, true) && empty($holidaySet[$d])) { $cnt++; }
        $cur = strtotime('+1 day', $cur);
    }
    return $cnt;
}
$weeklyOffs = getWeeklyOffs($pdo);
$holidaySetMonth = getHolidaySet($pdo, $monthStart, $monthEndBound);
$joinDate = !empty($teacher['joining_date']) ? $teacher['joining_date'] : null;
$workingDaysMonth = countWorkingDays($monthStart, $monthEndBound, $weeklyOffs, $holidaySetMonth, $joinDate);
$absentDaysMonth = max(0, $workingDaysMonth - $presentDays);

// Build calendar cells for the selected month
$firstDow = (int)date('w', strtotime($monthStart)); // 0=Sun
$daysInMonth = (int)date('t', strtotime($monthStart));
$calendarCells = [];
$week = [];
// leading empty cells
for ($i=0; $i<$firstDow; $i++) { $week[] = null; }
for ($d=1; $d <= $daysInMonth; $d++) {
    $dateStr = date('Y-m-', strtotime($monthStart)) . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
    $preJoin = ($joinDate && $dateStr < $joinDate);
    $isFuture = ($dateStr > $monthEndBound);
    $w = (int)date('w', strtotime($dateStr));
    $isHoliday = !empty($holidaySetMonth[$dateStr]);
    $isWeeklyOff = in_array($w, $weeklyOffs, true);
    $isWorking = (!$preJoin && !$isFuture && !$isHoliday && !$isWeeklyOff);
    $rec = $recordsByDate[$dateStr] ?? null;
    $st = $rec ? strtolower($rec['status'] ?? '') : '';
    $type = 'none';
    if ($rec) {
        if ($st === 'late') $type = 'late';
        elseif ($st === 'early') $type = 'early';
        else $type = 'present';
    } else {
        if ($isWorking) $type = 'absent';
        elseif ($preJoin) $type = 'prejoin';
        elseif ($isHoliday || $isWeeklyOff) $type = 'off';
        elseif ($isFuture) $type = 'future';
    }
    $week[] = array(
        'day' => $d,
        'date' => $dateStr,
        'type' => $type
    );
    if (count($week) === 7) { $calendarCells[] = $week; $week = []; }
}
// trailing empty cells
if (count($week) > 0) {
    while (count($week) < 7) { $week[] = null; }
    $calendarCells[] = $week;
}

// Yearly summary (academic year: from start_date to today/end)
$ayCols = [];
try { $ayCols = $pdo->query("SHOW COLUMNS FROM academic_years")->fetchAll(PDO::FETCH_COLUMN); } catch(Exception $e) { $ayCols = []; }
$hasAyRange = in_array('start_date', $ayCols) && in_array('end_date', $ayCols);
if ($hasAyRange) {
    $ayStmt = $pdo->prepare("SELECT * FROM academic_years WHERE start_date <= ? AND (end_date IS NULL OR end_date >= ?) ORDER BY id DESC LIMIT 1");
    $ayStmt->execute([$now, $now]);
    $ay = $ayStmt->fetch(PDO::FETCH_ASSOC);
} else {
    $ayStmt = $pdo->query("SELECT * FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1");
    $ay = $ayStmt ? $ayStmt->fetch(PDO::FETCH_ASSOC) : null;
}
$yearStart = null;
$yearEndBound = $now;
if (!empty($ay)) {
    if ($hasAyRange && !empty($ay['start_date'])) $yearStart = $ay['start_date'];
    if ($hasAyRange && !empty($ay['end_date']) && $ay['end_date'] < $yearEndBound) $yearEndBound = $ay['end_date'];
}
if (!$yearStart) {
    $y = (int)substr($selectedMonth, 0, 4);
    $yearStart = $y.'-01-01';
}
if ($joinDate && $joinDate > $yearStart) { $yearStart = $joinDate; }
if (strtotime($yearStart) > strtotime($yearEndBound)) {
    $y = (int)substr($selectedMonth, 0, 4);
    $yearStart = $y.'-01-01';
    if ($joinDate && strtotime($joinDate) < strtotime($now) && strtotime($joinDate) > strtotime($yearStart)) {
        $yearStart = $joinDate;
    }
    $yearEndBound = $now;
}

// Yearly attendance: distinct days with any record
$presentYearStmt = $pdo->prepare("SELECT COUNT(DISTINCT date) FROM teacher_attendance WHERE teacher_id=? AND date BETWEEN ? AND ?");
$presentYearStmt->execute([$teacher_id, $yearStart, $yearEndBound]);
$presentYear = (int)$presentYearStmt->fetchColumn();
$lateYearStmt = $pdo->prepare("SELECT COUNT(DISTINCT date) FROM teacher_attendance WHERE teacher_id=? AND date BETWEEN ? AND ? AND status='late'");
$lateYearStmt->execute([$teacher_id, $yearStart, $yearEndBound]);
$lateYear = (int)$lateYearStmt->fetchColumn();
$holidaySetYear = getHolidaySet($pdo, $yearStart, $yearEndBound);
$workingDaysYear = countWorkingDays($yearStart, $yearEndBound, $weeklyOffs, $holidaySetYear, $joinDate);
$absentYear = max(0, $workingDaysYear - $presentYear);

?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষক বিবরণ - কিন্ডার গার্ডেন</title>

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
        .teacher-profile-img {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid #dee2e6;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .profile-header {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .info-card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        .info-card .card-header {
            background: linear-gradient(45deg, #f8f9fc, #e3e6f0);
            color: #4e73df;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
        }
        .info-item {
            padding: 10px 0;
            border-bottom: 1px solid #e3e6f0;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #4e73df;
        }
        .badge-custom {
            font-size: 0.9em;
            padding: 0.5em 0.8em;
            border-radius: 4px;
        }
        .stat-card {
            border-left: .25rem solid #4e73df;
        }
        .stat-card .card-body { padding:.85rem 1rem; }
        .stat-value { font-size: 1.3rem; font-weight: 700; }
        .stat-label { font-size: .85rem; color:#6c757d; }
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
                        <h1 class="m-0 text-dark">শিক্ষক বিবরণ</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/teachers.php">শিক্ষক ব্যবস্থাপনা</a></li>
                            <li class="breadcrumb-item active">শিক্ষক বিবরণ</li>
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
                    <!-- Summary stats -->
                    <div class="col-12 mb-3">
                        <div class="row">
                            <div class="col-sm-6 col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="stat-label">ক্লাস সংখ্যা</div>
                                        <div class="stat-value text-primary"><?php echo $total_classes; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="card stat-card" style="border-left-color:#1cc88a;">
                                    <div class="card-body">
                                        <div class="stat-label">বিষয় সংখ্যা</div>
                                        <div class="stat-value text-success"><?php echo $total_subjects; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="card stat-card" style="border-left-color:#36b9cc;">
                                    <div class="card-body">
                                        <div class="stat-label">এই মাসে উপস্থিত</div>
                                        <div class="stat-value text-info"><?php echo $presentDays; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="card stat-card" style="border-left-color:#f6c23e;">
                                    <div class="card-body">
                                        <div class="stat-label">এই মাসে দেরি</div>
                                        <div class="stat-value" style="color:#f6c23e;"><?php echo $lateDays; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Yearly totals row -->
                        <div class="row mt-2">
                            <div class="col-sm-6 col-md-3">
                                <div class="card stat-card" style="border-left-color:#28a745;">
                                    <div class="card-body">
                                        <div class="stat-label">বছরে উপস্থিত</div>
                                        <div class="stat-value text-success"><?php echo $presentYear; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="card stat-card" style="border-left-color:#e74a3b;">
                                    <div class="card-body">
                                        <div class="stat-label">বছরে অনুপস্থিত</div>
                                        <div class="stat-value" style="color:#e74a3b;">&nbsp;<?php echo $absentYear; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- শিক্ষক প্রোফাইল কার্ড -->
                        <div class="card info-card">
                            <div class="card-body text-center">
                                <?php if(!empty($teacher['photo'])): ?>
                                    <img src="../uploads/teachers/<?php echo $teacher['photo']; ?>" class="teacher-profile-img mb-3" alt="শিক্ষকের ছবি">
                                <?php else: ?>
                                    <div class="teacher-profile-img bg-light d-flex align-items-center justify-content-center mb-3">
                                        <i class="fas fa-user text-muted fa-5x"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <h3><?php echo $teacher['full_name']; ?></h3>
                                <p class="text-muted"><?php echo $teacher['username']; ?></p>
                                
                                <div class="mt-3">
                                    <?php if($teacher['status'] == 'active'): ?>
                                        <span class="badge badge-success badge-custom">সক্রিয়</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger badge-custom">নিষ্ক্রিয়</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="edit_teacher.php?id=<?php echo $teacher_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> সম্পাদনা
                                    </a>
                                    <a href="teachers.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> ফিরে যান
                                    </a>
                                    <a href="teacher_attendance_report.php?id=<?php echo $teacher_id; ?>" class="btn btn-info mt-2">
                                        <i class="fas fa-user-check"></i> হাজিরা রিপোর্ট
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- যোগাযোগের তথ্য -->
                        <div class="card info-card">
                            <div class="card-header">
                                <h3 class="card-title">যোগাযোগের তথ্য</h3>
                            </div>
                            <div class="card-body">
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-envelope mr-2"></i>ইমেইল:</span>
                                    <p class="mb-0"><?php echo $teacher['email'] ?? 'নেই'; ?></p>
                                </div>
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-phone mr-2"></i>মোবাইল:</span>
                                    <p class="mb-0"><?php echo $teacher['phone'] ?? 'নেই'; ?></p>
                                </div>
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-map-marker-alt mr-2"></i>ঠিকানা:</span>
                                    <p class="mb-0"><?php echo $teacher['address'] ?? 'নেই'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <!-- ব্যক্তিগত তথ্য -->
                        <div class="card info-card">
                            <div class="card-header">
                                <h3 class="card-title">ব্যক্তিগত তথ্য</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">পিতার নাম:</span>
                                            <p class="mb-0"><?php echo $teacher['father_name'] ?? 'নেই'; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">মাতার নাম:</span>
                                            <p class="mb-0"><?php echo $teacher['mother_name'] ?? 'নেই'; ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">জন্ম তারিখ:</span>
                                            <p class="mb-0"><?php echo $teacher['date_of_birth'] ? date('d/m/Y', strtotime($teacher['date_of_birth'])) : 'নেই'; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">লিঙ্গ:</span>
                                            <p class="mb-0">
                                                <?php 
                                                if(isset($teacher['gender'])) {
                                                    if($teacher['gender'] == 'male') echo 'পুরুষ';
                                                    elseif($teacher['gender'] == 'female') echo 'মহিলা';
                                                    else echo $teacher['gender'];
                                                } else {
                                                    echo 'নেই';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">রক্তের গ্রুপ:</span>
                                            <p class="mb-0"><?php echo $teacher['blood_group'] ?? 'নেই'; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">ধর্ম:</span>
                                            <p class="mb-0"><?php echo $teacher['religion'] ?? 'নেই'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- পেশাগত তথ্য -->
                        <div class="card info-card">
                            <div class="card-header">
                                <h3 class="card-title">পেশাগত তথ্য</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">যোগদানের তারিখ:</span>
                                            <p class="mb-0"><?php echo $teacher['joining_date'] ? date('d/m/Y', strtotime($teacher['joining_date'])) : 'নেই'; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">ক্লাস সংখ্যা:</span>
                                            <p class="mb-0"><span class="badge bg-info"><?php echo $total_classes; ?></span></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">শিক্ষাগত যোগ্যতা:</span>
                                    <p class="mb-0"><?php echo $teacher['qualification'] ?? 'নেই'; ?></p>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">অভিজ্ঞতা:</span>
                                    <p class="mb-0"><?php echo $teacher['experience'] ?? 'নেই'; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- হাজিরা (চলতি মাস) -->
                        <div class="card info-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title">হাজিরা</h3>
                                <form method="get" class="d-flex align-items-center" id="monthFilterForm">
                                    <input type="hidden" name="id" value="<?php echo (int)$teacher_id; ?>">
                                    <div class="btn-group btn-group-sm mr-2" role="group">
                                        <button type="button" id="prevMonthBtn" class="btn btn-outline-secondary" title="আগের মাস">&laquo;</button>
                                        <button type="button" id="nextMonthBtn" class="btn btn-outline-secondary" title="পরের মাস">&raquo;</button>
                                    </div>
                                    <label for="monthPicker" class="mr-2 mb-0 small text-muted">মাস</label>
                                    <input type="month" name="month" id="monthPicker" value="<?php echo htmlspecialchars($selectedMonth); ?>" class="form-control form-control-sm" style="width:160px;">
                                </form>
                            </div>
                            <div class="card-body">
                                <div class="mb-2 text-muted small">
                                    <span>কার্যদিবস: <?php echo $workingDaysMonth; ?></span>
                                    <span class="mx-2">|</span>
                                    <span>উপস্থিত: <?php echo $presentDays; ?></span>
                                    <span class="mx-2">|</span>
                                    <span>অনুপস্থিত: <?php echo $absentDaysMonth; ?></span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered text-center calendar-table">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="py-2">রবি</th>
                                                <th class="py-2">সোম</th>
                                                <th class="py-2">মঙ্গল</th>
                                                <th class="py-2">বুধ</th>
                                                <th class="py-2">বৃহ</th>
                                                <th class="py-2">শুক্র</th>
                                                <th class="py-2">শনি</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($calendarCells as $week): ?>
                                                <tr>
                                                    <?php foreach ($week as $cell): ?>
                                                        <?php if ($cell === null): ?>
                                                            <td class="align-middle" style="height:64px; background:#fafafa"></td>
                                                        <?php else: ?>
                                                            <?php 
                                                                $icon = '';
                                                                $cls = '';
                                                                $title = '';
                                                                switch ($cell['type']) {
                                                                    case 'present': $icon = '<i class="fa-solid fa-check text-success"></i>'; $title='উপস্থিত'; break;
                                                                    case 'late': $icon = '<i class="fa-regular fa-clock text-warning"></i>'; $title='দেরি'; break;
                                                                    case 'early': $icon = '<i class="fa-solid fa-bolt text-info"></i>'; $title='আগে'; break;
                                                                    case 'absent': $icon = '<i class="fa-solid fa-xmark text-danger"></i>'; $title='অনুপস্থিত'; break;
                                                                    case 'off': $icon = '<span class="text-muted">ছুটি</span>'; $cls='bg-light'; $title='ছুটি/সাপ্তাহিক ছুটি'; break;
                                                                    case 'future': $icon = '<span class="text-muted">—</span>'; $title='ভবিষ্যৎ'; break;
                                                                    case 'prejoin': $icon = '<span class="text-muted">—</span>'; $title='যোগদানের পূর্বে'; break;
                                                                    default: $icon = ''; $title='';
                                                                }
                                                            ?>
                                                            <td class="align-middle <?php echo $cls; ?>" style="height:64px;">
                                                                <div class="small text-muted mb-1"><?php echo $cell['day']; ?></div>
                                                                <div title="<?php echo $title; ?>"><?php echo $icon; ?></div>
                                                            </td>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2 small text-muted">
                                    <span class="mr-3"><i class="fa-solid fa-check text-success"></i> উপস্থিত</span>
                                    <span class="mr-3"><i class="fa-regular fa-clock text-warning"></i> দেরি</span>
                                    <span class="mr-3"><i class="fa-solid fa-bolt text-info"></i> আগে</span>
                                    <span class="mr-3"><i class="fa-solid fa-xmark text-danger"></i> অনুপস্থিত</span>
                                    <span class="mr-3"><span class="badge badge-light">ছুটি</span> ছুটি</span>
                                </div>
                                <hr>
                                <h6 class="mb-2">সাম্প্রতিক হাজিরা</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>তারিখ</th>
                                                <th>স্ট্যাটাস</th>
                                                <th>চেক-ইন</th>
                                                <th>চেক-আউট</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($attRows)): ?>
                                                <?php foreach (array_reverse($attRows) as $row): // latest first ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                                                        <td>
                                                            <?php $st = strtolower($row['status'] ?? '');
                                                                $badge = 'secondary';
                                                                if ($st==='present') $badge='success';
                                                                elseif ($st==='late') $badge='warning';
                                                                elseif ($st==='early') $badge='info';
                                                            ?>
                                                            <span class="badge badge-<?php echo $badge; ?> text-uppercase"><?php echo $st ?: 'N/A'; ?></span>
                                                        </td>
                                                        <td><?php echo $row['check_in'] ? date('h:i A', strtotime($row['check_in'])) : '-'; ?></td>
                                                        <td><?php echo $row['check_out'] ? date('h:i A', strtotime($row['check_out'])) : '-'; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="4" class="text-center text-muted">এই মাসে কোনো হাজিরার রেকর্ড নেই</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- ক্লাস এবং বিষয় তালিকা -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card info-card">
                                    <div class="card-header">
                                        <h3 class="card-title">ক্লাস তালিকা</h3>
                                    </div>
                                    <div class="card-body">
                                        <?php if($total_classes > 0): ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach($routineClasses as $class): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?php echo $class['class_name']; ?>
                                                        <a href="class_details.php?id=<?php echo $class['class_id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-muted text-center">কোন ক্লাস বরাদ্দ নেই</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card info-card">
                                    <div class="card-header">
                                        <h3 class="card-title">বিষয় তালিকা</h3>
                                    </div>
                                    <div class="card-body">
                                        <?php if($total_subjects > 0): ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach($routine_subjects as $subject): ?>
                                                    <li class="list-group-item">
                                                        <?php echo $subject['subject_name']; ?>
                                                        <small class="text-muted">(<?php echo $subject['class_name']; ?>)</small>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-muted text-center">কোন বিষয় বরাদ্দ নেই</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
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
// Month navigation and auto-submit
document.addEventListener('DOMContentLoaded', function(){
    function changeMonthOffset(offset){
        var mp = document.getElementById('monthPicker');
        if (!mp || !mp.value) return;
        var parts = mp.value.split('-');
        var y = parseInt(parts[0],10);
        var m = parseInt(parts[1],10);
        m += offset;
        while (m < 1) { m += 12; y -= 1; }
        while (m > 12) { m -= 12; y += 1; }
        var mm = String(m).padStart(2,'0');
        mp.value = y + '-' + mm;
        document.getElementById('monthFilterForm').submit();
    }
    var prev = document.getElementById('prevMonthBtn');
    var next = document.getElementById('nextMonthBtn');
    if (prev) prev.addEventListener('click', function(){ changeMonthOffset(-1); });
    if (next) next.addEventListener('click', function(){ changeMonthOffset(1); });
    var mp = document.getElementById('monthPicker');
    if (mp) mp.addEventListener('change', function(){ document.getElementById('monthFilterForm').submit(); });
});
</script>

</body>
</html>