<?php
require_once '../config.php';
require_once __DIR__ . '/inc/enrollment_helpers.php';
require_once __DIR__ . '/inc/sms_api.php';

if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../login.php');
}

// Load base data
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_value ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$sections = $pdo->query("SELECT * FROM sections ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $pdo->query("SELECT id, full_name, phone FROM users WHERE role='teacher' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$success_count = 0; $fail_count = 0; $recipients_preview = [];

// Fetch SMS balance from provider using API key stored in settings
$sms_balance = null; $sms_balance_error = null; $sms_balance_raw = null;
try {
    $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`='sms_api_key' LIMIT 1");
    $st->execute();
    $api_key = trim((string)($st->fetchColumn() ?: ''));
    if ($api_key !== '') {
        $balanceUrl = 'http://bulksmsbd.net/api/getBalanceApi?api_key=' . urlencode($api_key);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $balanceUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $sms_balance_raw = $resp;
        if ($resp === false || $http < 200 || $http >= 300) {
            $sms_balance_error = $err ? ($err) : ('HTTP ' . $http);
        } else {
            $json = json_decode($resp, true);
            if (is_array($json)) {
                if (isset($json['balance'])) { $sms_balance = (float)$json['balance']; }
                elseif (isset($json['Balance'])) { $sms_balance = (float)$json['Balance']; }
                elseif (isset($json['data']['balance'])) { $sms_balance = (float)$json['data']['balance']; }
                elseif (isset($json['sms'])) { $sms_balance = (float)$json['sms']; }
            }
            if ($sms_balance === null) {
                if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', (string)$resp, $m)) {
                    $sms_balance = (float)$m[1];
                } else {
                    $sms_balance_error = 'Unexpected response';
                }
            }
        }
    } else {
        $sms_balance_error = 'API key not configured';
    }
} catch (Exception $e) {
    $sms_balance_error = $e->getMessage();
}

// Compute how many SMS can be sent assuming per-SMS charge 0.35 BDT
$per_sms_cost = 0.35;
$sms_possible = null;
if ($sms_balance !== null && $per_sms_cost > 0) {
    $sms_possible = (int)floor($sms_balance / $per_sms_cost);
}

// Load SMS templates (from settings menu table sms_templates)
$panel_templates = [];
try {
    // Prefer settings page schema (title, content) but support legacy columns
    $cols = $pdo->query("SHOW COLUMNS FROM sms_templates")->fetchAll(PDO::FETCH_COLUMN);
    $labelCol = in_array('title', $cols, true) ? 'title' : (in_array('name', $cols, true) ? 'name' : (in_array('status', $cols, true) ? 'status' : null));
    $bodyCol  = in_array('content', $cols, true) ? 'content' : (in_array('template', $cols, true) ? 'template' : (in_array('template_text', $cols, true) ? 'template_text' : null));
    if ($bodyCol) {
        $sel = 'SELECT ' . ($labelCol ? $labelCol : "'' AS title") . ' AS label, ' . $bodyCol . ' AS body FROM sms_templates ORDER BY id DESC';
        foreach ($pdo->query($sel) as $r) {
            $label = trim((string)($r['label'] ?? ''));
            $body  = (string)($r['body'] ?? '');
            if ($body !== '') {
                if ($label === '') { $label = mb_substr($body, 0, 24) . (mb_strlen($body) > 24 ? '…' : ''); }
                $panel_templates[] = ['label' => $label, 'body' => $body];
            }
        }
    }
} catch (Exception $e) { /* ignore */ }

// Cache available sms_logs columns for dynamic INSERTs
function sms_log_available_columns(PDO $pdo) {
    static $cols = null;
    if ($cols !== null) return $cols;
    $cols = [];
    try {
        $st = $pdo->query("SHOW COLUMNS FROM sms_logs");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            if (!empty($c['Field'])) $cols[$c['Field']] = true;
        }
    } catch (Exception $e) {
        $cols = [];
    }
    return $cols;
}

// Best-effort DB log insert with extended metadata when columns exist
function insert_sms_log(PDO $pdo, array $data) {
    $colsAvail = sms_log_available_columns($pdo);
    // Base fields (expected to exist in older installs)
    $base = [
        'sent_by_user_id' => $data['sent_by_user_id'] ?? null,
        'recipient_type' => $data['recipient_type'] ?? null,
        'recipient_number' => $data['recipient_number'] ?? null,
        'message' => $data['message'] ?? null,
        'status' => $data['status'] ?? 'success',
    ];
    $extraCandidates = [
        'recipient_category' => $data['recipient_category'] ?? null,
        'recipient_id' => $data['recipient_id'] ?? null,
        'recipient_name' => $data['recipient_name'] ?? null,
        'recipient_role' => $data['recipient_role'] ?? null,
        'roll_number' => $data['roll_number'] ?? null,
        'class_name' => $data['class_name'] ?? null,
        'section_name' => $data['section_name'] ?? null,
    ];
    $fields = [];
    $values = [];
    $params = [];
    foreach ($base as $k => $v) {
        if (isset($colsAvail[$k])) { $fields[] = $k; $values[] = '?'; $params[] = $v; }
    }
    foreach ($extraCandidates as $k => $v) {
        if (isset($colsAvail[$k])) { $fields[] = $k; $values[] = '?'; $params[] = $v; }
    }
    $createdAtHandled = isset($colsAvail['created_at']);
    $sql = 'INSERT INTO sms_logs (' . implode(',', $fields) . ($createdAtHandled ? ',created_at' : '') . ') VALUES (' . implode(',', $values) . ($createdAtHandled ? ', NOW()' : '') . ')';
    $st = $pdo->prepare($sql);
    $st->execute($params);
}

// Helper: parse arbitrary numbers entered by user (comma/newline separated)
function parse_numbers($input) {
    $parts = preg_split('/[\s,;]+/', (string)$input, -1, PREG_SPLIT_NO_EMPTY);
    $out = [];
    foreach ($parts as $p) {
        $n = trim($p);
        if ($n !== '') { $out[] = $n; }
    }
    return array_values(array_unique($out));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $target = $_POST['target'] ?? '';
    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        $_SESSION['error'] = 'বার্তা লিখুন।';
        redirect('admin/sms_panel.php');
    }

    $numbers = [];
    $metaByNumber = []; // map number => metadata for logging

    // 1) Prefer aggregated recipients sent via hidden JSON
    $agg = [];
    if (!empty($_POST['recipients_json'])) {
        $dec = json_decode($_POST['recipients_json'], true);
        if (is_array($dec)) { $agg = $dec; }
    }
    if ($agg && count($agg) > 0) {
        foreach ($agg as $r) {
            $num = isset($r['number']) ? preg_replace('/[^0-9]/', '', (string)$r['number']) : '';
            if ($num === '') continue;
            $numbers[] = $num;
            $metaByNumber[$num] = [
                'recipient_category' => $r['category'] ?? null,
                'recipient_id' => isset($r['id']) ? (int)$r['id'] : null,
                'recipient_name' => $r['name'] ?? null,
                'recipient_role' => $r['role'] ?? null,
                'roll_number' => $r['roll'] ?? null,
                'class_name' => $r['class_name'] ?? null,
                'section_name' => $r['section_name'] ?? null,
            ];
        }
    } else {
        // 2) Fallback to existing target-based flows
        try {
        if ($target === 'teacher_one') {
            $tid = (int)($_POST['teacher_id'] ?? 0);
            if ($tid) {
                $st = $pdo->prepare("SELECT phone FROM users WHERE id = ? AND role='teacher' LIMIT 1");
                $st->execute([$tid]);
                $phone = (string)($st->fetchColumn() ?: '');
                if ($phone) {
                    $numbers[] = $phone; $recipients_preview[] = 'Teacher#'.$tid;
                    $metaByNumber[$phone] = [
                        'recipient_category' => 'teacher',
                        'recipient_id' => $tid,
                        'recipient_role' => 'teacher',
                    ];
                    // Try to fetch name too
                    try { $rn = $pdo->prepare('SELECT full_name FROM users WHERE id=?'); $rn->execute([$tid]); $nm = $rn->fetchColumn(); if ($nm) $metaByNumber[$phone]['recipient_name'] = $nm; } catch(Exception $e) {}
                }
            }
        } elseif ($target === 'teacher_all') {
            foreach ($teachers as $t) {
                if (!empty($t['phone'])) {
                    $num = $t['phone'];
                    $numbers[] = $num; $recipients_preview[] = (string)$t['full_name'];
                    $metaByNumber[$num] = [
                        'recipient_category' => 'teacher',
                        'recipient_id' => (int)$t['id'],
                        'recipient_name' => (string)($t['full_name'] ?? ''),
                        'recipient_role' => 'teacher',
                    ];
                }
            }
        } elseif ($target === 'teachers_selected') {
            $tids = isset($_POST['teacher_ids']) && is_array($_POST['teacher_ids']) ? array_map('intval', $_POST['teacher_ids']) : [];
            if ($tids) {
                $in = implode(',', array_fill(0, count($tids), '?'));
                $st = $pdo->prepare("SELECT id, full_name, phone FROM users WHERE role='teacher' AND id IN ($in)");
                $st->execute($tids);
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($r['phone'])) {
                        $num = $r['phone'];
                        $numbers[] = $num; $recipients_preview[] = (string)($r['full_name'] ?? ('Teacher#'.$r['id']));
                        $metaByNumber[$num] = [
                            'recipient_category' => 'teacher',
                            'recipient_id' => (int)$r['id'],
                            'recipient_name' => (string)($r['full_name'] ?? ''),
                            'recipient_role' => 'teacher',
                        ];
                    }
                }
            }
        } elseif ($target === 'student_one') {
            $sid = (int)($_POST['student_id'] ?? 0);
            if ($sid) {
                // Try to fetch student with class/section names
                $useEnroll = function_exists('enrollment_table_exists') && enrollment_table_exists($pdo);
                if ($useEnroll) {
                    $st = $pdo->prepare("SELECT s.mobile_number, s.first_name, s.last_name, c.name AS class_name, sec.name AS section_name, se.roll_number AS roll_number FROM students s JOIN students_enrollment se ON se.student_id = s.id LEFT JOIN classes c ON c.id = se.class_id LEFT JOIN sections sec ON sec.id = se.section_id WHERE s.id = ? LIMIT 1");
                } else {
                    $st = $pdo->prepare("SELECT s.mobile_number, s.first_name, s.last_name, c.name AS class_name, sec.name AS section_name, NULL AS roll_number FROM students s LEFT JOIN classes c ON c.id = s.class_id LEFT JOIN sections sec ON sec.id = s.section_id WHERE s.id = ? LIMIT 1");
                }
                $st->execute([$sid]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!empty($row['mobile_number'])) {
                    $num = $row['mobile_number'];
                    $numbers[] = $num; $recipients_preview[] = trim(($row['first_name']??'').' '.($row['last_name']??''));
                    $metaByNumber[$num] = [
                        'recipient_category' => 'student',
                        'recipient_id' => $sid,
                        'recipient_name' => trim(($row['first_name']??'').' '.($row['last_name']??'')),
                        'roll_number' => (string)($row['roll_number'] ?? ''),
                        'class_name' => (string)($row['class_name'] ?? ''),
                        'section_name' => (string)($row['section_name'] ?? ''),
                    ];
                }
            }
        } elseif ($target === 'students_all') {
            $class_id = (int)($_POST['class_id'] ?? 0);
            $section_id = isset($_POST['section_id']) && $_POST['section_id'] !== '' ? (int)$_POST['section_id'] : null;
            $useEnroll = function_exists('enrollment_table_exists') && enrollment_table_exists($pdo);
            $params = [];
            if ($useEnroll) {
                $sql = "SELECT s.id, s.mobile_number, s.first_name, s.last_name, c.name AS class_name, sec.name AS section_name FROM students s JOIN students_enrollment se ON se.student_id = s.id LEFT JOIN classes c ON c.id = se.class_id LEFT JOIN sections sec ON sec.id = se.section_id";
                    $sql = "SELECT s.id, s.mobile_number, s.first_name, s.last_name, c.name AS class_name, sec.name AS section_name, se.roll_number AS roll_number FROM students s JOIN students_enrollment se ON se.student_id = s.id LEFT JOIN classes c ON c.id = se.class_id LEFT JOIN sections sec ON sec.id = se.section_id";
                $w = [];
                if ($class_id) { $w[] = 'se.class_id = ?'; $params[] = $class_id; }
                if ($section_id) { $w[] = 'se.section_id = ?'; $params[] = $section_id; }
                if ($w) { $sql .= ' WHERE ' . implode(' AND ', $w); }
            } else {
                $sql = "SELECT s.id, s.mobile_number, s.first_name, s.last_name, c.name AS class_name, sec.name AS section_name FROM students s LEFT JOIN classes c ON c.id = s.class_id LEFT JOIN sections sec ON sec.id = s.section_id";
                    $sql = "SELECT s.id, s.mobile_number, s.first_name, s.last_name, c.name AS class_name, sec.name AS section_name, NULL AS roll_number FROM students s LEFT JOIN classes c ON c.id = s.class_id LEFT JOIN sections sec ON sec.id = s.section_id";
                $w = [];
                if ($class_id) { $w[] = 's.class_id = ?'; $params[] = $class_id; }
                if ($section_id) { $w[] = 's.section_id = ?'; $params[] = $section_id; }
                if ($w) { $sql .= ' WHERE ' . implode(' AND ', $w); }
            }
            $st = $pdo->prepare($sql);
            $st->execute($params);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($r['mobile_number'])) {
                    $num = $r['mobile_number'];
                    $numbers[] = $num; $recipients_preview[] = trim(($r['first_name']??'').' '.($r['last_name']??''));
                    $metaByNumber[$num] = [
                        'recipient_category' => 'student',
                        'recipient_id' => (int)$r['id'],
                        'recipient_name' => trim(($r['first_name']??'').' '.($r['last_name']??'')),
                        'roll_number' => (string)($r['roll_number'] ?? ''),
                        'class_name' => (string)($r['class_name'] ?? ''),
                        'section_name' => (string)($r['section_name'] ?? ''),
                    ];
                }
            }
        } elseif ($target === 'students_selected') {
            $ids = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? array_map('intval', $_POST['student_ids']) : [];
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $useEnroll = function_exists('enrollment_table_exists') && enrollment_table_exists($pdo);
                if ($useEnroll) {
                    $st = $pdo->prepare("SELECT s.id, s.mobile_number, s.first_name, s.last_name, c.name AS class_name, sec.name AS section_name FROM students s JOIN students_enrollment se ON se.student_id = s.id LEFT JOIN classes c ON c.id = se.class_id LEFT JOIN sections sec ON sec.id = se.section_id WHERE s.id IN ($in)");
                    $st = $pdo->prepare("SELECT s.id, s.mobile_number, s.first_name, s.last_name, c.name AS class_name, sec.name AS section_name, se.roll_number AS roll_number FROM students s JOIN students_enrollment se ON se.student_id = s.id LEFT JOIN classes c ON c.id = se.class_id LEFT JOIN sections sec ON sec.id = se.section_id WHERE s.id IN ($in)");
                } else {
                    $st = $pdo->prepare("SELECT s.id, s.mobile_number, s.first_name, s.last_name, c.name AS class_name, sec.name AS section_name FROM students s LEFT JOIN classes c ON c.id = s.class_id LEFT JOIN sections sec ON sec.id = s.section_id WHERE s.id IN ($in)");
                    $st = $pdo->prepare("SELECT s.id, s.mobile_number, s.first_name, s.last_name, c.name AS class_name, sec.name AS section_name, NULL AS roll_number FROM students s LEFT JOIN classes c ON c.id = s.class_id LEFT JOIN sections sec ON sec.id = s.section_id WHERE s.id IN ($in)");
                }
                $st->execute($ids);
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($r['mobile_number'])) {
                        $num = $r['mobile_number'];
                        $numbers[] = $num; $recipients_preview[] = trim(($r['first_name']??'').' '.($r['last_name']??''));
                        $metaByNumber[$num] = [
                            'recipient_category' => 'student',
                            'recipient_id' => (int)$r['id'],
                            'recipient_name' => trim(($r['first_name']??'').' '.($r['last_name']??'')),
                            'roll_number' => (string)($r['roll_number'] ?? ''),
                            'class_name' => (string)($r['class_name'] ?? ''),
                            'section_name' => (string)($r['section_name'] ?? ''),
                        ];
                    }
                }
            }
        } elseif ($target === 'custom_numbers') {
            $numbers = parse_numbers($_POST['numbers'] ?? '');
            $recipients_preview = $numbers;
            foreach ($numbers as $num) {
                $metaByNumber[$num] = [ 'recipient_category' => 'unknown' ];
            }
        }
        } catch (Exception $e) {
            // ignore; will handle as no numbers
        }
    }

    // De-duplicate and cap to a sane bulk size
    $numbers = array_values(array_unique(array_filter($numbers)));
    $maxSend = 1000; // safety cap
    if (count($numbers) > $maxSend) { $numbers = array_slice($numbers, 0, $maxSend); }

    if (empty($numbers)) {
        $_SESSION['error'] = 'কোনো প্রাপক পাওয়া যায়নি।';
        redirect('admin/sms_panel.php');
    }

    // Try to get sender id if available
    $sender_id = null;
    if (!empty($_SESSION['user']['id'])) { $sender_id = (int)$_SESSION['user']['id']; }
    elseif (!empty($_SESSION['user_id'])) { $sender_id = (int)$_SESSION['user_id']; }

    foreach ($numbers as $to) {
        $ok = @send_sms($to, $message);
        // Insert DB log (best-effort) with extended meta when supported
        try {
            $meta = $metaByNumber[$to] ?? [];
            insert_sms_log($pdo, [
                'sent_by_user_id' => $sender_id,
                'recipient_type' => $target,
                'recipient_number' => $to,
                'message' => $message,
                'status' => $ok ? 'success' : 'failed',
                'recipient_category' => $meta['recipient_category'] ?? null,
                'recipient_id' => $meta['recipient_id'] ?? null,
                'recipient_name' => $meta['recipient_name'] ?? null,
                'recipient_role' => $meta['recipient_role'] ?? null,
                'roll_number' => $meta['roll_number'] ?? null,
                'class_name' => $meta['class_name'] ?? null,
                'section_name' => $meta['section_name'] ?? null,
            ]);
        } catch (Exception $e) { /* ignore logging errors */ }
        if ($ok) $success_count++; else $fail_count++;
    }

    $_SESSION['success'] = 'মোট ' . count($numbers) . ' টি নম্বরে পাঠানোর চেষ্টা করা হয়েছে। সফল: ' . $success_count . ', বিফল: ' . $fail_count . '।';
    // Optional: store last recipients preview in session to show
    $_SESSION['sms_preview'] = implode(', ', array_slice($recipients_preview, 0, 10));
    redirect('admin/sms_panel.php');
}

// Read last 10 log lines from sms_api.log for display
$logLines = [];
$logFile = __DIR__ . '/inc/sms_api.log';
if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $logLines = array_slice($lines, -10);
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>এসএমএস প্যানেল</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        body, .main-sidebar, .nav-link, .card, .form-control, .btn { font-family: 'SolaimanLipi','Source Sans Pro',sans-serif; }
        .section { display:none; }
        .section.active { display:block; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">এসএমএস প্যানেল</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">এসএমএস প্যানেল</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">×</button><?php echo $_SESSION['success']; unset($_SESSION['success']); if(!empty($_SESSION['sms_preview'])) { echo '<br><small>উদাহরণ প্রাপক: '.htmlspecialchars($_SESSION['sms_preview']).'</small>'; unset($_SESSION['sms_preview']); } ?></div><?php endif; ?>
                <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">×</button><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>

                <!-- SMS Balance and Capacity info boxes -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-box <?php echo ($sms_balance !== null ? 'bg-gradient-success' : 'bg-gradient-warning'); ?>">
                            <span class="info-box-icon"><i class="fas fa-wallet"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">ব্যালেন্স (টাকা)</span>
                                <span class="info-box-number" style="font-size:1.6rem;font-weight:700;">
                                    <?php echo $sms_balance !== null ? '৳ ' . htmlspecialchars(number_format($sms_balance, 2)) : '—'; ?>
                                </span>
                                <div class="progress"><div class="progress-bar" style="width: 100%"></div></div>
                                <span class="progress-description">
                                    <?php
                                    if ($sms_balance !== null) {
                                        echo 'সর্বশেষ হালনাগাদ: ' . date('d M Y, h:i A');
                                    } else {
                                        echo 'ব্যালেন্স আনতে পারিনি' . ($sms_balance_error ? ' - ' . htmlspecialchars($sms_balance_error) : '');
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box <?php echo ($sms_possible !== null ? 'bg-gradient-info' : 'bg-gradient-warning'); ?>">
                            <span class="info-box-icon"><i class="fas fa-sms"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">সম্ভাব্য এসএমএস সংখ্যা</span>
                                <span class="info-box-number" style="font-size:1.6rem;font-weight:700;">
                                    <?php echo $sms_possible !== null ? htmlspecialchars(number_format($sms_possible)) : '—'; ?>
                                </span>
                                <div class="progress"><div class="progress-bar" style="width: 100%"></div></div>
                                <span class="progress-description">
                                    <?php
                                    if ($sms_possible !== null) {
                                        echo 'প্রতি এসএমএস: ৳ ' . number_format($per_sms_cost, 2);
                                    } else {
                                        echo 'এসএমএস সংখ্যা নির্ণয় করা যায়নি';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-primary text-white"><strong>বার্তা প্রেরণ</strong></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="send_sms" value="1" />
                            <input type="hidden" id="recipients_json" name="recipients_json" value="[]" />
                            <div class="form-group">
                                <label>প্রাপক নির্বাচন</label>
                                <div class="d-flex flex-wrap">
                                    <div class="mr-3"><label><input type="radio" name="target" value="teacher_one" checked> একক শিক্ষক</label></div>
                                    <div class="mr-3"><label><input type="radio" name="target" value="teachers_selected"> বাছাই করা শিক্ষক</label></div>
                                    <div class="mr-3"><label><input type="radio" name="target" value="teacher_all"> সকল শিক্ষক</label></div>
                                    <div class="mr-3"><label><input type="radio" name="target" value="student_one"> একক শিক্ষার্থী</label></div>
                                    <div class="mr-3"><label><input type="radio" name="target" value="students_all"> শ্রেণি/শাখার সকল শিক্ষার্থী</label></div>
                                    <div class="mr-3"><label><input type="radio" name="target" value="students_selected"> নির্দিষ্ট শিক্ষার্থীরা</label></div>
                                    <div class="mr-3"><label><input type="radio" name="target" value="custom_numbers"> অন্যান্য নম্বর</label></div>
                                </div>
                            </div>

                            <div id="sec-teachers-selected" class="section">
                                <div class="form-group">
                                    <label>শিক্ষক নির্বাচন করুন (একাধিক)</label>
                                    <select class="form-control" id="teachers_multi" name="teacher_ids[]" multiple size="8">
                                        <?php foreach($teachers as $t): ?>
                                            <option data-name="<?php echo htmlspecialchars(($t['full_name'] ?? '')); ?>" data-phone="<?php echo htmlspecialchars(($t['phone'] ?? '')); ?>" value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars(($t['full_name'] ?? '')) . (!empty($t['phone']) ? ' - ' . htmlspecialchars($t['phone']) : ''); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" id="btn-add-teachers-selected" class="btn btn-sm btn-outline-success mt-2"><i class="fas fa-plus"></i> তালিকায় যোগ করুন</button>
                                </div>
                            </div>

                            <div id="sec-teacher-one" class="section active">
                                <div class="form-group">
                                    <label>শিক্ষক নির্বাচন করুন</label>
                                    <select class="form-control" id="teacher_one" name="teacher_id">
                                        <option value="">-- নির্বাচন করুন --</option>
                                        <?php foreach($teachers as $t): ?>
                                            <option data-name="<?php echo htmlspecialchars(($t['full_name'] ?? '')); ?>" data-phone="<?php echo htmlspecialchars(($t['phone'] ?? '')); ?>" value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars(($t['full_name'] ?? '')) . (!empty($t['phone']) ? ' - ' . htmlspecialchars($t['phone']) : ''); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" id="btn-add-teacher-one" class="btn btn-sm btn-outline-success mt-2"><i class="fas fa-plus"></i> তালিকায় যোগ করুন</button>
                                </div>
                            </div>

                            <div id="sec-student-one" class="section">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>শ্রেণি</label>
                                        <select class="form-control" id="one_class" name="class_id_one">
                                            <option value="">-- শ্রেণি --</option>
                                            <?php foreach($classes as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>শাখা</label>
                                        <select class="form-control" id="one_section" name="section_id_one" disabled>
                                            <option value="">-- শাখা --</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>শিক্ষার্থী</label>
                                        <select class="form-control" id="one_student" name="student_id" disabled>
                                            <option value="">-- শিক্ষার্থী --</option>
                                        </select>
                                        <button type="button" id="btn-add-student-one" class="btn btn-sm btn-outline-success mt-2"><i class="fas fa-plus"></i> তালিকায় যোগ করুন</button>
                                    </div>
                                </div>
                            </div>

                            <div id="sec-students-all" class="section">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>শ্রেণি</label>
                                        <select class="form-control" id="all_class" name="class_id">
                                            <option value="">-- শ্রেণি --</option>
                                            <?php foreach($classes as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>শাখা (ঐচ্ছিক)</label>
                                        <select class="form-control" id="all_section" name="section_id" disabled>
                                            <option value="">-- সকল শাখা --</option>
                                        </select>
                                        <button type="button" id="btn-add-students-all" class="btn btn-sm btn-outline-success mt-2" disabled><i class="fas fa-plus"></i> তালিকায় যোগ করুন</button>
                                    </div>
                                </div>
                            </div>

                            <div id="sec-students-selected" class="section">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>শ্রেণি</label>
                                        <select class="form-control" id="sel_class">
                                            <option value="">-- শ্রেণি --</option>
                                            <?php foreach($classes as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>শাখা</label>
                                        <select class="form-control" id="sel_section" disabled>
                                            <option value="">-- শাখা --</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>শিক্ষার্থীরা (একাধিক)</label>
                                        <input type="text" class="form-control mb-2" id="stu_search" placeholder="নাম/আইডি/মোবাইল দিয়ে খুঁজুন">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="sel_all_toggle">
                                                <label class="custom-control-label" for="sel_all_toggle">সব নির্বাচন করুন</label>
                                            </div>
                                            <small class="text-muted ml-3" id="sel_count">0 নির্বাচিত</small>
                                        </div>
                                        <div id="sel_students_wrap" class="border rounded p-2" style="max-height:220px; overflow:auto; background:#fff;">
                                            <div class="text-muted text-center py-4">তালিকা লোড করুন বা সার্চ করুন</div>
                                        </div>
                                        <small class="text-muted d-block mt-1">সার্চ দিয়ে যেকোন শ্রেণির শিক্ষার্থী খুঁজে নিন, অথবা শ্রেণি/শাখা বাছাই করুন।</small>
                                        <button type="button" id="btn-add-students-selected" class="btn btn-sm btn-outline-success mt-2" disabled><i class="fas fa-plus"></i> তালিকায় যোগ করুন</button>
                                    </div>
                                </div>
                            </div>

                            <div id="sec-custom-numbers" class="section">
                                <div class="form-group">
                                    <label>অন্যান্য নম্বর (কমা/নতুন লাইনে আলাদা করুন)</label>
                                    <textarea class="form-control" name="numbers" rows="3" placeholder="017xxxxxxxx, 018xxxxxxxx"></textarea>
                                    <button type="button" id="btn-add-custom" class="btn btn-sm btn-outline-success mt-2"><i class="fas fa-plus"></i> তালিকায় যোগ করুন</button>
                                </div>
                            </div>

                            <!-- Aggregated recipient cart -->
                            <div class="card card-outline card-info mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <strong>নির্বাচিত প্রাপকের তালিকা</strong>
                                    <div>
                                        <span class="badge badge-primary" id="agg-count">0</span>
                                        <button type="button" id="agg-clear" class="btn btn-xs btn-outline-danger ml-2"><i class="fas fa-trash"></i> খালি করুন</button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0" id="agg-table">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th style="width:70px;">ক্রমিক</th>
                                                    <th>নাম</th>
                                                    <th>শ্রেণি</th>
                                                    <th>শাখা</th>
                                                    <th>রোল</th>
                                                    <th>বিভাগ</th>
                                                    <th>নম্বর</th>
                                                    <th>একশন</th>
                                                </tr>
                                            </thead>
                                            <tbody><tr class="text-muted"><td colspan="8" class="text-center">কোনো প্রাপক যোগ করা হয়নি</td></tr></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>বার্তা টেম্পলেট</label>
                                <select class="form-control" id="template_select">
                                    <option value="">-- টেম্পলেট নির্বাচন করুন --</option>
                                    <?php foreach($panel_templates as $tpl): ?>
                                        <option value="<?php echo htmlspecialchars($tpl['body']); ?>"><?php echo htmlspecialchars($tpl['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>বার্তা</label>
                                <textarea class="form-control" id="msg_text" name="message" rows="4" maxlength="1000" placeholder="আপনার বার্তা লিখুন"></textarea>
                                <small id="sms_counter" class="form-text text-muted">0 অক্ষর • 0 অংশ</small>
                                <small class="form-text text-muted">বাংলা/ইংরেজি উভয়ই সমর্থিত।</small>
                            </div>

                            <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> পাঠান</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-secondary text-white"><strong>সাম্প্রতিক পাঠানো এসএমএস লগ (শেষ ১০)</strong></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead><tr><th>সময়</th><th>নম্বর</th><th>HTTP</th><th>OK</th><th>রেসপন্স (সংক্ষিপ্ত)</th></tr></thead>
                                <tbody>
                                    <?php if ($logLines): ?>
                                        <?php foreach ($logLines as $line): ?>
                                            <?php
                                                // parse simple format: date | to= | http= | ok= | ...
                                                $time = $to = $http = $ok = $resp = '';
                                                $parts = explode(' | ', $line);
                                                if ($parts) {
                                                    $time = $parts[0] ?? '';
                                                    foreach ($parts as $p) {
                                                        if (strpos($p,'to=')===0) $to = substr($p,3);
                                                        if (strpos($p,'http=')===0) $http = substr($p,5);
                                                        if (strpos($p,'ok=')===0) $ok = substr($p,3);
                                                        if (strpos($p,'resp=')===0) $resp = substr($p,5);
                                                    }
                                                }
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($time) ?></td>
                                                <td><?= htmlspecialchars($to) ?></td>
                                                <td><?= htmlspecialchars($http) ?></td>
                                                <td><?= htmlspecialchars($ok) ?></td>
                                                <td style="max-width:520px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($resp) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center text-muted">কোনো লগ পাওয়া যায়নি</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include 'inc/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
$(function(){
    // --- Aggregated recipients cart ---
    var recipients = {}; // number(normalized) -> meta {number, name, category, id, role, roll, class_name, section_name}
    function normalizeNumber(n){ return (n||'').replace(/[^0-9]/g,''); }
    function refreshAggUI(){
        var $tb = $('#agg-table tbody');
        var keys = Object.keys(recipients);
        $('#agg-count').text(keys.length);
        if(keys.length===0){ $tb.html('<tr class="text-muted"><td colspan="8" class="text-center">কোনো প্রাপক যোগ করা হয়নি</td></tr>'); return; }
        var html='';
        keys.sort();
        var idx = 1;
        keys.forEach(function(k){
            var r = recipients[k];
            html += '<tr>'+
                '<td>'+ idx +'</td>'+
                '<td>'+ $('<div/>').text(r.name||'').html() +'</td>'+
                '<td>'+ $('<div/>').text(r.class_name||'').html() +'</td>'+
                '<td>'+ $('<div/>').text(r.section_name||'').html() +'</td>'+
                '<td>'+ $('<div/>').text(r.roll||'').html() +'</td>'+
                '<td>'+ $('<div/>').text(r.category||'').html() +'</td>'+
                '<td>'+ $('<div/>').text(r.number||'').html() +'</td>'+
                '<td><button type="button" class="btn btn-xs btn-outline-danger agg-remove" data-num="'+k+'"><i class="fas fa-times"></i> বাদ দিন</button></td>'+
            '</tr>';
            idx++;
        });
        $tb.html(html);
        // update hidden field
        var arr = keys.map(function(k){ return recipients[k]; });
        $('#recipients_json').val(JSON.stringify(arr));
    }
    $('#agg-table').on('click', '.agg-remove', function(){ var num=$(this).data('num'); delete recipients[num]; refreshAggUI(); });
    $('#agg-clear').on('click', function(){ recipients={}; refreshAggUI(); });

    function addRecipient(number, meta){
        var n = normalizeNumber(number);
        if(!n) return;
        if(!meta) meta={};
        meta.number = n;
        recipients[n] = {
            number: n,
            name: meta.name||meta.recipient_name||'',
            category: meta.category||meta.recipient_category||'',
            id: meta.id||meta.recipient_id||null,
            role: meta.role||meta.recipient_role||'',
            roll: meta.roll||meta.roll_number||'',
            class_name: meta.class_name||'',
            section_name: meta.section_name||''
        };
        refreshAggUI();
    }

    function showSection(val){
        $('.section').removeClass('active');
        if(val==='teacher_one') $('#sec-teacher-one').addClass('active');
        if(val==='teachers_selected') $('#sec-teachers-selected').addClass('active');
        if(val==='teacher_all') {/* no extra inputs */}
        if(val==='student_one') $('#sec-student-one').addClass('active');
        if(val==='students_all') $('#sec-students-all').addClass('active');
        if(val==='students_selected') $('#sec-students-selected').addClass('active');
        if(val==='custom_numbers') $('#sec-custom-numbers').addClass('active');
    }
    $('input[name="target"]').on('change', function(){ showSection(this.value); });
    showSection($('input[name="target"]:checked').val());

    // Sections loader reused
    function loadSections(classId, $select){
        $select.prop('disabled', true).html('<option>লোড হচ্ছে...</option>');
        if(!classId){ $select.html('<option value="">-- শাখা --</option>').prop('disabled', false); return; }
        $.get('get_sections.php', { class_id: classId }).done(function(html){
            $select.html('<option value="">-- শাখা --</option>' + html).prop('disabled', false);
        }).fail(function(){ $select.html('<option value="">-- শাখা --</option>').prop('disabled', false); });
    }

    function loadStudents(classId, sectionId, $target, multiple, q){
        if (multiple) {
            $target.html('<div class="text-center text-muted py-2">লোড হচ্ছে...</div>');
        } else {
            $target.prop('disabled', true).html('<option>লোড হচ্ছে...</option>');
        }
        var params = { class_id: classId||'', section_id: sectionId||'' };
        if (q) params.q = q;
        if(!classId && !q){
            if (multiple) { $target.html('<div class="text-muted text-center py-2">শ্রেণি/শাখা বাছাই করুন বা সার্চ করুন</div>'); }
            else { $target.html('<option value="">-- শিক্ষার্থী --</option>').prop('disabled', false); }
            return;
        }
    $.getJSON('ajax/get_students_by_class_section.php', params)
         .done(function(res){
            if (multiple) {
                var html = '';
                if(res && res.students && res.students.length){
                    res.students.forEach(function(s){
                        var label = (s.name||('ID '+s.id)) + (s.mobile? (' - '+s.mobile):'');
                        var escName = $('<div/>').text(s.name||'').html();
                        var escMob  = $('<div/>').text(s.mobile||'').html();
                        var escClass = $('<div/>').text(s.class_name||'').html();
                        var escSect  = $('<div/>').text(s.section_name||'').html();
                        var escRoll  = $('<div/>').text(s.roll_number||'').html();
                        html += '<div class="custom-control custom-checkbox">'+
                            '<input type="checkbox" class="custom-control-input sel-stu" id="stu_'+s.id+'" data-id="'+s.id+'" data-name="'+escName+'" data-mobile="'+escMob+'" data-class="'+escClass+'" data-section="'+escSect+'" data-roll="'+escRoll+'">'+
                            '<label class="custom-control-label" for="stu_'+s.id+'">'+$('<div/>').text(label).html()+'</label>'+
                        '</div>';
                    });
                } else {
                    html = '<div class="text-muted text-center py-2">কিছু পাওয়া যায়নি</div>';
                }
                $target.html(html);
                updateSelectedCount();
                updateAddButtons();
            } else {
                var opts = '<option value="">-- শিক্ষার্থী --</option>';
                if(res && res.students){
                    res.students.forEach(function(s){
                        var label = (s.name||('ID '+s.id)) + (s.mobile? (' - '+s.mobile):'');
                        var escName = $('<div/>').text(s.name||'').html();
                        var escMob  = $('<div/>').text(s.mobile||'').html();
                        opts += '<option data-name="'+escName+'" data-mobile="'+escMob+'" value="'+s.id+'">'+$('<div/>').text(label).html()+'</option>';
                    });
                }
                $target.html(opts).prop('disabled', false);
            }
         }).fail(function(){
            if (multiple) {
                $target.html('<div class="text-muted text-center py-2">লোড ব্যর্থ হয়েছে</div>');
            } else {
                $target.html('<option value="">-- শিক্ষার্থী --</option>').prop('disabled', false);
            }
         });
    }

    // One student flow
    $('#one_class').on('change', function(){ var cid=this.value; loadSections(cid, $('#one_section')); $('#one_student').html('<option value="">-- শিক্ষার্থী --</option>').prop('disabled', true); });
    $('#one_section').on('change', function(){ loadStudents($('#one_class').val(), this.value, $('#one_student'), false, null); });

    // All students flow
    $('#all_class').on('change', function(){ loadSections(this.value, $('#all_section')); });

    // Selected students flow
    $('#sel_class').on('change', function(){
        loadSections(this.value, $('#sel_section'));
        $('#sel_students_wrap').html('<div class="text-muted text-center py-2">শ্রেণি/শাখা বাছাই করুন বা সার্চ করুন</div>');
        updateSelectedCount();
        updateAddButtons();
    });
    $('#sel_section').on('change', function(){ loadStudents($('#sel_class').val(), this.value, $('#sel_students_wrap'), true, null); });

    // Live search across students for multi-select
    var searchDebounce;
    $('#stu_search').on('input', function(){
        clearTimeout(searchDebounce);
        var q = this.value.trim();
        searchDebounce = setTimeout(function(){
            loadStudents($('#sel_class').val(), $('#sel_section').val(), $('#sel_students_wrap'), true, q);
        }, 300);
    });

    // SMS character counter and parts estimator
    function isGsmChar(code){
        // Basic GSM 03.38 (approx): letters, digits, common punctuation; treat others as Unicode
        const gsmRegex = /^[\n\r\t\x20-\x7E€£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉÄÖÑÜäöñüÄÖÑÜ^{}\\\[~\]|]+$/u;
        return gsmRegex.test(String.fromCharCode(code));
    }
    function detectUnicode(str){
        for (let i=0;i<str.length;i++){
            const code = str.charCodeAt(i);
            if (!isGsmChar(code)) return true;
        }
        return false;
    }
    function computeParts(len, unicode){
        if (len===0) return {parts:0, per:unicode?70:160};
        const single = unicode?70:160;
        const multi = unicode?67:153;
        if (len<=single) return {parts:1, per:single};
        return {parts: Math.ceil(len/multi), per: multi};
    }
    function updateCounter(){
        const txt = $('#msg_text').val() || '';
        const unicode = detectUnicode(txt);
        const len = txt.length;
        const calc = computeParts(len, unicode);
        $('#sms_counter').text(len+' অক্ষর • '+calc.parts+' অংশ' + (calc.parts>1? ' (প্রতি অংশ '+calc.per+' অক্ষর)':''));
    }
    $('#msg_text').on('input', updateCounter);
    updateCounter();

    // Enable/disable add buttons based on selections
    function updateAddButtons(){
        $('#btn-add-students-selected').prop('disabled', $('#sel_students_wrap input.sel-stu:checked').length===0);
        $('#btn-add-students-all').prop('disabled', !$('#all_class').val());
    }
    function updateSelectedCount(){
        var c = $('#sel_students_wrap input.sel-stu:checked').length;
        $('#sel_count').text(c+' নির্বাচিত');
        $('#sel_all_toggle').prop('checked', c>0 && c === $('#sel_students_wrap input.sel-stu').length);
    }
    $('#sel_students_wrap').on('change', 'input.sel-stu', function(){ updateSelectedCount(); updateAddButtons(); });
    $('#sel_all_toggle').on('change', function(){ var on=this.checked; $('#sel_students_wrap input.sel-stu').prop('checked', on); updateSelectedCount(); updateAddButtons(); });
    $('#all_class, #all_section').on('change', updateAddButtons);
    updateAddButtons();

    // Add handlers
    $('#btn-add-teacher-one').on('click', function(){
        var $opt = $('#teacher_one option:selected');
        var id = $('#teacher_one').val(); if(!id) return;
        var name = $opt.data('name')||('Teacher#'+id);
        var phone = $opt.data('phone')||'';
        addRecipient(phone, {name:name, category:'teacher', id:parseInt(id,10)||null, role:'teacher'});
    });
    $('#btn-add-teachers-selected').on('click', function(){
        $('#teachers_multi option:selected').each(function(){
            var id = $(this).val(); var name=$(this).data('name')||('Teacher#'+id); var phone=$(this).data('phone')||'';
            addRecipient(phone, {name:name, category:'teacher', id:parseInt(id,10)||null, role:'teacher'});
        });
    });
    $('#btn-add-student-one').on('click', function(){
        var $opt = $('#one_student option:selected'); var sid=$('#one_student').val(); if(!sid) return;
        var name = $opt.data('name')||('ID '+sid); var phone=$opt.data('mobile')||'';
        var klass=$('#one_class option:selected').text()||''; var sect=$('#one_section option:selected').text()||'';
        addRecipient(phone, {name:name, category:'student', id:parseInt(sid,10)||null, role:'student', class_name:klass, section_name:sect});
    });
    $('#btn-add-students-selected').on('click', function(){
        $('#sel_students_wrap input.sel-stu:checked').each(function(){
            var sid=$(this).data('id'); var name=$(this).data('name')||('ID '+sid); var phone=$(this).data('mobile')||'';
            var klass=$(this).data('class')||($('#sel_class option:selected').text()||'');
            var sect=$(this).data('section')||($('#sel_section option:selected').text()||'');
            var roll=$(this).data('roll')||'';
            addRecipient(phone, {name:name, category:'student', id:parseInt(sid,10)||null, role:'student', class_name:klass, section_name:sect, roll: roll});
        });
    });
    $('#btn-add-students-all').on('click', function(){
        var cid=$('#all_class').val()||''; var sid=$('#all_section').val()||'';
        if(cid){
            $.getJSON('ajax/get_students_by_class_section.php', {class_id:cid, section_id:sid})
             .done(function(res){
                if(res && res.students){
                    var klass=$('#all_class option:selected').text()||''; var sect=$('#all_section option:selected').text()||'';
                    res.students.forEach(function(s){ addRecipient(s.mobile||'', {name:s.name||('ID '+s.id), category:'student', id:parseInt(s.id,10)||null, role:'student', class_name:(s.class_name||klass), section_name:(s.section_name||sect), roll: (s.roll_number||'')}); });
                }
             });
        } else {
            if(!confirm('সতর্কতা: পুরো প্রতিষ্ঠানের সকল শিক্ষার্থী যোগ করা হবে। চালিয়ে যেতে OK চাপুন।')) return;
            $.getJSON('ajax/get_students_all.php', {limit: 10000})
             .done(function(res){
                if(res && res.students){
                    res.students.forEach(function(s){ addRecipient(s.mobile||'', {name:s.name||('ID '+s.id), category:'student', id:parseInt(s.id,10)||null, role:'student', class_name:(s.class_name||''), section_name:(s.section_name||''), roll:(s.roll_number||'')}); });
                }
             });
        }
    });
    $('#btn-add-custom').on('click', function(){
        var txt = ($('textarea[name="numbers"]').val()||'');
        txt.split(/[\s,;]+/).forEach(function(n){ if(n){ addRecipient(n, {category:'unknown'}); } });
    });

    // Template select -> fill message box
    $('#template_select').on('change', function(){
        var val = $(this).val()||''; if(val){ $('#msg_text').val(val).trigger('input'); }
    });
});
</script>
</body>
</html>
