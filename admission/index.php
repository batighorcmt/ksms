<?php
require_once '../config.php';
require_once __DIR__ . '/../admin/inc/enrollment_helpers.php';
// SMS sender helper
@require_once __DIR__ . '/../admin/inc/sms_api.php';

// Lightweight helpers to log SMS like the SMS panel (only define if missing)
if (!function_exists('sms_log_available_columns')) {
    function sms_log_available_columns(PDO $pdo) {
        static $cols = null;
        if ($cols !== null) return $cols;
        $cols = [];
        try {
            $st = $pdo->query("SHOW COLUMNS FROM sms_logs");
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
                if (!empty($c['Field'])) $cols[$c['Field']] = true;
            }
        } catch (Exception $e) { $cols = []; }
        return $cols;
    }
}
if (!function_exists('insert_sms_log')) {
    function insert_sms_log(PDO $pdo, array $data) {
        $colsAvail = sms_log_available_columns($pdo);
        $base = [
            'sent_by_user_id' => $data['sent_by_user_id'] ?? null,
            'recipient_type' => $data['recipient_type'] ?? null,
            'recipient_number' => $data['recipient_number'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => $data['status'] ?? 'success',
        ];
        $extra = [
            'recipient_category' => $data['recipient_category'] ?? null,
            'recipient_id' => $data['recipient_id'] ?? null,
            'recipient_name' => $data['recipient_name'] ?? null,
            'recipient_role' => $data['recipient_role'] ?? null,
            'roll_number' => $data['roll_number'] ?? null,
            'class_name' => $data['class_name'] ?? null,
            'section_name' => $data['section_name'] ?? null,
        ];
        $fields = []; $values = []; $params = [];
        foreach ($base as $k=>$v) { if (isset($colsAvail[$k])) { $fields[]=$k; $values[]='?'; $params[]=$v; } }
        foreach ($extra as $k=>$v) { if (isset($colsAvail[$k])) { $fields[]=$k; $values[]='?'; $params[]=$v; } }
        $createdAtHandled = isset($colsAvail['created_at']);
        if (!$fields) return; // nothing to insert
        $sql = 'INSERT INTO sms_logs (' . implode(',', $fields) . ($createdAtHandled ? ',created_at' : '') . ') VALUES (' . implode(',', $values) . ($createdAtHandled ? ', NOW()' : '') . ')';
        $st = $pdo->prepare($sql);
        $st->execute($params);
    }
}

// Fetch admission template body from sms_templates; fallback if not found
if (!function_exists('fetch_admission_sms_template')) {
    function fetch_admission_sms_template(PDO $pdo) {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM sms_templates")->fetchAll(PDO::FETCH_COLUMN);
            if (!$cols) return null;
            $labelCol = in_array('title', $cols, true) ? 'title' : (in_array('name', $cols, true) ? 'name' : (in_array('status', $cols, true) ? 'status' : null));
            $bodyCol  = in_array('content', $cols, true) ? 'content' : (in_array('template', $cols, true) ? 'template' : (in_array('template_text', $cols, true) ? 'template_text' : null));
            if (!$bodyCol) return null;
            $sql = 'SELECT ' . ($labelCol ? $labelCol : "''") . ' AS label, ' . $bodyCol . ' AS body FROM sms_templates ORDER BY id DESC';
            $stmt = $pdo->query($sql);
            $candidates = [];
            foreach ($stmt as $r) {
                $label = strtolower(trim((string)($r['label'] ?? '')));
                $body  = (string)($r['body'] ?? '');
                if ($body === '') continue;
                // Prefer rows with label/body mentioning 'admission' or 'ভর্তি'
                $score = 0;
                if ($label !== '') {
                    if (strpos($label, 'admission') !== false) $score += 3;
                    if (strpos($label, 'ভর্তি') !== false) $score += 3;
                }
                $lbody = mb_strtolower($body);
                if (strpos($lbody, 'admission') !== false) $score += 2;
                if (strpos($lbody, 'ভর্তি') !== false) $score += 2;
                if ($score > 0) $candidates[] = ['score'=>$score,'body'=>$body];
            }
            if ($candidates) {
                usort($candidates, function($a,$b){ return $b['score'] <=> $a['score']; });
                return $candidates[0]['body'];
            }
        } catch (Exception $e) { /* ignore */ }
        return null;
    }
}

if (!function_exists('render_sms_template')) {
    function render_sms_template($tpl, array $vars) {
        $repl = [];
        foreach ($vars as $k=>$v) { $repl['{'.$k.'}'] = (string)$v; }
        // also support uppercase tokens
        foreach ($vars as $k=>$v) { $repl['{'.strtoupper($k).'}'] = (string)$v; }
        return strtr($tpl, $repl);
    }
}

// Auth: teachers and super admins can add
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('login.php');
}

// Ensure admissions table exists (soft-guard). Admins should also run database/admissions_schema.sql
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admissions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      admission_id VARCHAR(32) NOT NULL,
      academic_year_id INT NOT NULL,
      serial_no INT NOT NULL,
      student_name VARCHAR(150) NOT NULL,
      father_name VARCHAR(150) NULL,
      mother_name VARCHAR(150) NULL,
      mobile VARCHAR(20) NULL,
      village VARCHAR(120) NULL,
      para_moholla VARCHAR(120) NULL,
      upazila VARCHAR(120) NULL,
      district VARCHAR(120) NULL,
      currently_studying TINYINT(1) NOT NULL DEFAULT 0,
      current_school_name VARCHAR(200) NULL,
      current_class VARCHAR(60) NULL,
      added_by_user_id INT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_admission (admission_id),
      UNIQUE KEY uniq_year_serial (academic_year_id, serial_no),
      INDEX idx_year (academic_year_id),
      INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) { /* ignore */ }

// Ensure optional 'reference' column exists (best-effort)
try {
    $col = $pdo->query("SHOW COLUMNS FROM admissions LIKE 'reference'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        $pdo->exec("ALTER TABLE admissions ADD COLUMN reference TEXT NULL");
    }
} catch (Exception $e) { /* ignore */ }

// Load academic years
$years = [];
try {
    $years = $pdo->query("SELECT id, year, is_current FROM academic_years ORDER BY is_current DESC, start_date DESC, id DESC")
                 ->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $years = [];
}
$current_year_id = function_exists('current_academic_year_id') ? current_academic_year_id($pdo) : null;

$success = false; $error = '';
$newAdmissionId = ''; $newStudentName = '';
$addedByName = ''; $createdAtDisp = '';

// Form field defaults (for initial render and sticky values)
$academic_year_id = null;
$student_name = '';
$father_name = '';
$mother_name = '';
$mobile = '';
$village = '';
$para_moholla = '';
$upazila = 'গাংনী'; // default value, editable
$district = 'মেহেরপুর'; // default value, editable
$currently_studying = 0;
$current_school_name = '';
$current_class = '';
$reference = '';

// Determine default selected academic year: prefer 2026, else current year
$selected_year_id = null;
foreach ($years as $y) {
    if (trim((string)$y['year']) === '2026') { $selected_year_id = (int)$y['id']; break; }
}
if ($selected_year_id === null) { $selected_year_id = $current_year_id ?: null; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_admission'])) {
    $academic_year_id = isset($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : 0;
    $selected_year_id = $academic_year_id ?: $selected_year_id;
    $student_name = trim($_POST['student_name'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $mother_name = trim($_POST['mother_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $village = trim($_POST['village'] ?? '');
    $para_moholla = trim($_POST['para_moholla'] ?? '');
    $upazila = trim($_POST['upazila'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $currently_studying = isset($_POST['currently_studying']) ? 1 : 0;
    $current_school_name = $currently_studying ? trim($_POST['current_school_name'] ?? '') : null;
    $current_class = $currently_studying ? trim($_POST['current_class'] ?? '') : null;
    $reference = trim($_POST['reference'] ?? '');
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$academic_year_id || $student_name === '') {
        $error = 'শিক্ষাবর্ষ এবং শিক্ষার্থীর নাম প্রয়োজন।';
    } elseif ($mobile !== '' && !preg_match('/^01\d{9}$/', $mobile)) {
        $error = 'মোবাইল নম্বর 01 দিয়ে শুরু এবং ১১ সংখ্যার হতে হবে অথবা ঘরটি ফাঁকা রাখুন।';
    } else {
        try {
            $pdo->beginTransaction();
            // Read academic year text
            $yrStmt = $pdo->prepare('SELECT year FROM academic_years WHERE id = ?');
            $yrStmt->execute([$academic_year_id]);
            $yr = $yrStmt->fetch(PDO::FETCH_ASSOC);
            $yearText = $yr['year'] ?? date('Y');

            // Compute next serial for this year
            // Try to lock related rows to avoid race
            $maxStmt = $pdo->prepare('SELECT MAX(serial_no) AS max_serial FROM admissions WHERE academic_year_id = ? FOR UPDATE');
            try { $maxStmt->execute([$academic_year_id]); } catch (Exception $ie) {
                // Some MySQL configs might not allow FOR UPDATE in this context; fallback without lock
                $maxStmt = $pdo->prepare('SELECT MAX(serial_no) AS max_serial FROM admissions WHERE academic_year_id = ?');
                $maxStmt->execute([$academic_year_id]);
            }
            $max = $maxStmt->fetch(PDO::FETCH_ASSOC);
            $nextSerial = (int)($max['max_serial'] ?? 0) + 1;

            // Build admission id: ADD + Year + 3-digit serial
            $admission_id = 'ADD' . preg_replace('/\D+/','', $yearText) . str_pad((string)$nextSerial, 3, '0', STR_PAD_LEFT);

            $ins = $pdo->prepare('INSERT INTO admissions (admission_id, academic_year_id, serial_no, student_name, father_name, mother_name, mobile, village, para_moholla, upazila, district, currently_studying, current_school_name, current_class, added_by_user_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())');
            $ok = $ins->execute([
                $admission_id, $academic_year_id, $nextSerial, $student_name, $father_name, $mother_name, $mobile, $village, $para_moholla, $upazila, $district, $currently_studying, $current_school_name, $current_class, $user_id
            ]);
            if (!$ok) throw new Exception('Insert failed');

            // Save reference if provided and column exists (best-effort inside txn)
            if ($reference !== '') {
                try {
                    $col = $pdo->query("SHOW COLUMNS FROM admissions LIKE 'reference'")->fetch(PDO::FETCH_ASSOC);
                    if ($col) {
                        $newPk = (int)$pdo->lastInsertId();
                        $stRef = $pdo->prepare('UPDATE admissions SET reference = ? WHERE id = ?');
                        $stRef->execute([$reference, $newPk]);
                    }
                } catch (Exception $e) { /* ignore */ }
            }
            $pdo->commit();

            $success = true;
            $newAdmissionId = $admission_id;
            $newStudentName = $student_name;
            // fetch user name
            $un = $pdo->prepare('SELECT full_name FROM users WHERE id = ?');
            if ($user_id) { $un->execute([$user_id]); $row = $un->fetch(PDO::FETCH_ASSOC); $addedByName = $row['full_name'] ?? 'অজানা'; }
            $createdAtDisp = date('d M Y, h:i A');

            // After successful admission, send SMS (best-effort)
            try {
                $mobileTo = trim((string)$mobile);
                if ($mobileTo !== '' && function_exists('send_sms')) {
                    $tpl = fetch_admission_sms_template($pdo);
                    if (!$tpl) {
                        $tpl = 'প্রিয় {student_name}, আপনার ভর্তি আবেদন গ্রহণ করা হয়েছে। ভর্তি আইডি: {admission_id}. ধন্যবাদ।';
                    }
                    $msg = render_sms_template($tpl, [
                        'student_name' => $student_name,
                        'admission_id' => $admission_id,
                        'father_name' => $father_name,
                        'mobile' => $mobileTo,
                        'year' => isset($yearText)?$yearText:date('Y'),
                    ]);
                    $ok = @send_sms($mobileTo, $msg);
                    // Log to DB if table exists
                    try {
                        $sender_id = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : (!empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
                        insert_sms_log($pdo, [
                            'sent_by_user_id' => $sender_id,
                            'recipient_type' => 'admission_new',
                            'recipient_number' => $mobileTo,
                            'message' => $msg,
                            'status' => $ok ? 'success' : 'failed',
                            'recipient_category' => 'admission',
                            'recipient_name' => $student_name,
                            'recipient_role' => 'student',
                        ]);
                    } catch (Exception $e) { /* ignore log errors */ }
                }
            } catch (Exception $e) { /* ignore sms errors */ }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            // If duplicate due to race, try once more with fresh serial
            try {
                $pdo->beginTransaction();
                $maxStmt = $pdo->prepare('SELECT MAX(serial_no) AS max_serial FROM admissions WHERE academic_year_id = ?');
                $maxStmt->execute([$academic_year_id]);
                $max = $maxStmt->fetch(PDO::FETCH_ASSOC);
                $nextSerial = (int)($max['max_serial'] ?? 0) + 1;
                $yrStmt = $pdo->prepare('SELECT year FROM academic_years WHERE id = ?');
                $yrStmt->execute([$academic_year_id]);
                $yr = $yrStmt->fetch(PDO::FETCH_ASSOC);
                $yearText = $yr['year'] ?? date('Y');
                $admission_id = 'ADD' . preg_replace('/\D+/','', $yearText) . str_pad((string)$nextSerial, 3, '0', STR_PAD_LEFT);
                $ins = $pdo->prepare('INSERT INTO admissions (admission_id, academic_year_id, serial_no, student_name, father_name, mother_name, mobile, village, para_moholla, upazila, district, currently_studying, current_school_name, current_class, added_by_user_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())');
                $ok = $ins->execute([$admission_id, $academic_year_id, $nextSerial, $student_name, $father_name, $mother_name, $mobile, $village, $para_moholla, $upazila, $district, $currently_studying, $current_school_name, $current_class, $user_id]);
                if (!$ok) throw new Exception('Insert retry failed');

                // Save reference if provided on retry
                if ($reference !== '') {
                    try {
                        $col = $pdo->query("SHOW COLUMNS FROM admissions LIKE 'reference'")->fetch(PDO::FETCH_ASSOC);
                        if ($col) {
                            $newPk = (int)$pdo->lastInsertId();
                            $stRef = $pdo->prepare('UPDATE admissions SET reference = ? WHERE id = ?');
                            $stRef->execute([$reference, $newPk]);
                        }
                    } catch (Exception $e) { /* ignore */ }
                }
                $pdo->commit();
                $success = true; $newAdmissionId = $admission_id; $newStudentName = $student_name; $createdAtDisp = date('d M Y, h:i A');
                if ($user_id) { $un = $pdo->prepare('SELECT full_name FROM users WHERE id = ?'); $un->execute([$user_id]); $row = $un->fetch(PDO::FETCH_ASSOC); $addedByName = $row['full_name'] ?? 'অজানা'; }

                // Send SMS on retry success as well
                try {
                    $mobileTo = trim((string)$mobile);
                    if ($mobileTo !== '' && function_exists('send_sms')) {
                        $tpl = fetch_admission_sms_template($pdo);
                        if (!$tpl) { $tpl = 'প্রিয় {student_name}, আপনার ভর্তি আবেদন গ্রহণ করা হয়েছে। ভর্তি আইডি: {admission_id}. ধন্যবাদ।'; }
                        $msg = render_sms_template($tpl, [
                            'student_name' => $student_name,
                            'admission_id' => $admission_id,
                            'father_name' => $father_name,
                            'mobile' => $mobileTo,
                            'year' => isset($yearText)?$yearText:date('Y'),
                        ]);
                        $ok = @send_sms($mobileTo, $msg);
                        try {
                            $sender_id = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : (!empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
                            insert_sms_log($pdo, [
                                'sent_by_user_id' => $sender_id,
                                'recipient_type' => 'admission_new',
                                'recipient_number' => $mobileTo,
                                'message' => $msg,
                                'status' => $ok ? 'success' : 'failed',
                                'recipient_category' => 'admission',
                                'recipient_name' => $student_name,
                                'recipient_role' => 'student',
                            ]);
                        } catch (Exception $e) { /* ignore log errors */ }
                    }
                } catch (Exception $e) { /* ignore sms errors */ }
            } catch (Exception $e2) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = 'তথ্য সংরক্ষণে সমস্যা হয়েছে।';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ভর্তি আবেদন</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        body{font-family:'SolaimanLipi','Source Sans Pro',sans-serif}
        .required:after{content:' *';color:#dc3545}
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include __DIR__ . '/../admin/inc/header.php'; ?>
    <?php include __DIR__ . '/../admin/inc/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">ভর্তি আবেদন</h1></div>
                    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/dashboard.php">হোম</a></li><li class="breadcrumb-item active">ভর্তি আবেদন</li></ol></div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">×</button><?php echo htmlspecialchars($error,ENT_QUOTES,'UTF-8'); ?></div>
                <?php endif; ?>

                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title mb-0">শিক্ষার্থীর বেসিক তথ্য</h3>
                        <div class="card-tools">
                            <a class="btn btn-secondary btn-sm" href="<?php echo BASE_URL; ?>admission/list.php"><i class="fas fa-list mr-1"></i> ভর্তি তালিকা</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="required">ভর্তির বছর</label>
                                        <select name="academic_year_id" class="form-control" required>
                                            <option value="">নির্বাচন করুন</option>
                                            <?php foreach($years as $y): $yid=(int)$y['id']; $isSel = ($selected_year_id && $selected_year_id===$yid); ?>
                                                <option value="<?php echo $yid; ?>" <?php echo $isSel ? 'selected' : ''; ?>><?php echo htmlspecialchars($y['year']); ?><?php echo ($y['is_current']? ' (বর্তমান)':''); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="required">শিক্ষার্থীর নাম</label>
                                        <input type="text" name="student_name" class="form-control" value="<?php echo htmlspecialchars($student_name); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="required">পিতার নাম</label>
                                        <input type="text" name="father_name" class="form-control" value="<?php echo htmlspecialchars($father_name); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="required">মাতার নাম</label>
                                        <input type="text" name="mother_name" class="form-control" value="<?php echo htmlspecialchars($mother_name); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>মোবাইল নম্বর</label>
                                        <input type="text" name="mobile" class="form-control" value="<?php echo htmlspecialchars($mobile); ?>" inputmode="numeric" pattern="01[0-9]{9}" maxlength="11" title="01 দিয়ে শুরু এবং মোট ১১ সংখ্যার হতে হবে (অথবা ফাঁকা রাখুন)">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>গ্রাম</label>
                                        <input type="text" name="village" class="form-control" value="<?php echo htmlspecialchars($village); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>পাড়া/মহল্লা</label>
                                        <input type="text" name="para_moholla" class="form-control" value="<?php echo htmlspecialchars($para_moholla); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>উপজেলা</label>
                                        <input type="text" name="upazila" class="form-control" value="<?php echo htmlspecialchars($upazila); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>জেলা</label>
                                        <input type="text" name="district" class="form-control" value="<?php echo htmlspecialchars($district); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="form-check mt-4">
                                            <input type="checkbox" class="form-check-input" id="chkStudying" name="currently_studying" value="1" <?php echo $currently_studying ? 'checked' : ''; ?>>
                                            <label for="chkStudying" class="form-check-label">বর্তমানে অন্য কোনো বিদ্যালয়ে পড়ে কি না?</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 d-none" id="grpSchool">
                                    <div class="form-group">
                                        <label>বিদ্যালয়ের নাম</label>
                                        <input type="text" name="current_school_name" class="form-control" value="<?php echo htmlspecialchars($current_school_name); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 d-none" id="grpClass">
                                    <div class="form-group">
                                        <label>শ্রেণি</label>
                                        <input type="text" name="current_class" class="form-control" placeholder="উদাহরণ: ষষ্ঠ" value="<?php echo htmlspecialchars($current_class); ?>">
                                </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>রেফারেন্স</label>
                                        <textarea name="reference" class="form-control" rows="2" placeholder="রেফারেন্স থাকলে বিস্তারিত লিখুন (ঐচ্ছিক)"><?php echo htmlspecialchars($reference); ?></textarea>
                                    </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <button type="submit" name="save_admission" class="btn btn-primary"><i class="fas fa-save mr-1"></i> সংরক্ষণ</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include __DIR__ . '/../admin/inc/footer.php'; ?>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
    $(function(){
        function togglePrev(){
            var on = $('#chkStudying').is(':checked');
            $('#grpSchool, #grpClass').toggleClass('d-none', !on);
        }
        $('#chkStudying').on('change', togglePrev);
        togglePrev();
        <?php if($success): ?>
            $('#successModal').modal('show');
        <?php endif; ?>
    });
</script>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">সফল</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>আইডি নম্বর <strong><?php echo htmlspecialchars($newAdmissionId); ?></strong> সহ "<?php echo htmlspecialchars($newStudentName); ?>" সফলভাবে যুক্ত হয়েছে।</p>
        <?php if($addedByName || $createdAtDisp): ?>
            <small class="text-muted d-block">যোগ করেছেন: <?php echo htmlspecialchars($addedByName ?: ''); ?> | সময়: <?php echo htmlspecialchars($createdAtDisp ?: ''); ?></small>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <a href="<?php echo BASE_URL; ?>admission/list.php" class="btn btn-secondary">তালিকা দেখুন</a>
        <button type="button" class="btn btn-primary" data-dismiss="modal">ঠিক আছে</button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
