<?php
require_once '../config.php';

// Authentication
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    header('Location: ../login.php');
    exit;
}
// Load classes, sections, subjects, teachers
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();
$sections = $pdo->query("SELECT * FROM sections WHERE status='active' ORDER BY class_id, name ASC")->fetchAll();
$teachers = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('teacher','super_admin') ORDER BY full_name ASC")->fetchAll();

// JSON endpoint to get subjects for a class (from class_subjects)
if (isset($_GET['action']) && $_GET['action'] === 'get_subjects' && isset($_GET['class_id'])) {
    $cid = intval($_GET['class_id']);
    $sql = "SELECT cs.subject_id, s.name FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.id WHERE cs.class_id = ? ORDER BY cs.numeric_value ASC, s.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cid]);
    $rows = $stmt->fetchAll();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows);
    exit;
}

// JSON endpoint: get grid (all routines) for a class+section
if (isset($_GET['action']) && $_GET['action'] === 'get_grid' && isset($_GET['class_id']) && isset($_GET['section_id'])) {
    $cid = intval($_GET['class_id']);
    $sid = intval($_GET['section_id']);
    $sql = "SELECT r.*, s.name AS subject_name, u.full_name AS teacher_name FROM routines r LEFT JOIN subjects s ON r.subject_id = s.id LEFT JOIN users u ON r.teacher_id = u.id WHERE r.class_id = ? AND r.section_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cid, $sid]);
    $rows = $stmt->fetchAll();
    $grid = [];
    foreach ($rows as $r) {
        $day = $r['day_of_week'];
        $pn = intval($r['period_number']);
        if (!isset($grid[$day])) $grid[$day] = [];
        $grid[$day][$pn] = $r;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($grid);
    exit;
}

// JSON endpoint: get period count for class+section
if (isset($_GET['action']) && $_GET['action'] === 'get_period_count' && isset($_GET['class_id']) && isset($_GET['section_id'])) {
    $cid = intval($_GET['class_id']);
    $sid = intval($_GET['section_id']);
    // ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_periods (id INT AUTO_INCREMENT PRIMARY KEY, class_id INT NOT NULL, section_id INT NOT NULL, period_count INT NOT NULL DEFAULT 1, UNIQUE KEY(class_id, section_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $stmt = $pdo->prepare("SELECT period_count FROM class_periods WHERE class_id = ? AND section_id = ?");
    $stmt->execute([$cid, $sid]);
    $r = $stmt->fetchColumn();
    if (!$r) $r = 1;
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['period_count' => intval($r)]); exit;
}

// POST endpoint: set period count
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_period_count') {
    $cid = intval($_POST['class_id'] ?? 0);
    $sid = intval($_POST['section_id'] ?? 0);
    $pc = intval($_POST['period_count'] ?? 8);
    $resp = ['success' => false];
    if (!$cid || !$sid || $pc < 1) { $resp['error'] = 'Invalid'; header('Content-Type: application/json'); echo json_encode($resp); exit; }
    try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_periods (id INT AUTO_INCREMENT PRIMARY KEY, class_id INT NOT NULL, section_id INT NOT NULL, period_count INT NOT NULL DEFAULT 1, UNIQUE KEY(class_id, section_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $up = $pdo->prepare("INSERT INTO class_periods (class_id, section_id, period_count) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE period_count = VALUES(period_count)");
        $up->execute([$cid, $sid, $pc]);
        $resp['success'] = true;
    } catch (Exception $e) { $resp['error'] = 'DB'; }
    header('Content-Type: application/json'); echo json_encode($resp); exit;
}

// POST endpoint: save a single cell (upsert)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_cell') {
    $class_id = intval($_POST['class_id'] ?? 0);
    $section_id = intval($_POST['section_id'] ?? 0);
    $day = $_POST['day_of_week'] ?? '';
    $period_number = intval($_POST['period_number'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $start_time = $_POST['start_time'] !== '' ? $_POST['start_time'] : null;
    $end_time = $_POST['end_time'] !== '' ? $_POST['end_time'] : null;

    $resp = ['success' => false];
    if (!$class_id || !$section_id || !$day || !$period_number || !$subject_id || !$teacher_id) {
        $resp['error'] = 'Incomplete data';
        header('Content-Type: application/json'); echo json_encode($resp); exit;
    }

    try {
        $pdo->beginTransaction();
        // Verify subject belongs to the selected class
        $chkSub = $pdo->prepare("SELECT COUNT(*) FROM class_subjects WHERE class_id = ? AND subject_id = ?");
        $chkSub->execute([$class_id, $subject_id]);
        $allowed = intval($chkSub->fetchColumn());
        if (!$allowed) {
            $pdo->rollBack();
            $resp['error'] = 'Selected subject is not assigned to this class';
            header('Content-Type: application/json'); echo json_encode($resp); exit;
        }
        // check exists
        $chk = $pdo->prepare("SELECT id FROM routines WHERE class_id = ? AND section_id = ? AND day_of_week = ? AND period_number = ?");
        $chk->execute([$class_id, $section_id, $day, $period_number]);
        $found = $chk->fetchColumn();
        if ($found) {
            $up = $pdo->prepare("UPDATE routines SET subject_id = ?, teacher_id = ?, start_time = ?, end_time = ?, updated_at = NOW() WHERE id = ?");
            $up->execute([$subject_id, $teacher_id, $start_time, $end_time, $found]);
        } else {
            $ins = $pdo->prepare("INSERT INTO routines (class_id, section_id, day_of_week, period_number, subject_id, teacher_id, start_time, end_time, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $ins->execute([$class_id, $section_id, $day, $period_number, $subject_id, $teacher_id, $start_time, $end_time]);
        }
        $pdo->commit();
        $resp['success'] = true;
    } catch (Exception $e) {
        $pdo->rollBack();
        $resp['error'] = 'DB error';
    }
    header('Content-Type: application/json'); echo json_encode($resp); exit;
}

// POST endpoint: delete a single cell
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_cell') {
    $class_id = intval($_POST['class_id'] ?? 0);
    $section_id = intval($_POST['section_id'] ?? 0);
    $day = $_POST['day_of_week'] ?? '';
    $period_number = intval($_POST['period_number'] ?? 0);
    $resp = ['success' => false];
    if (!$class_id || !$section_id || !$day || !$period_number) {
        $resp['error'] = 'Incomplete data';
        header('Content-Type: application/json'); echo json_encode($resp); exit;
    }
    try {
        $del = $pdo->prepare("DELETE FROM routines WHERE class_id = ? AND section_id = ? AND day_of_week = ? AND period_number = ?");
        $del->execute([$class_id, $section_id, $day, $period_number]);
        $resp['success'] = true;
    } catch (Exception $e) {
        $resp['error'] = 'DB error';
    }
    header('Content-Type: application/json'); echo json_encode($resp); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
    $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
    $day_of_week = isset($_POST['day_of_week']) ? $_POST['day_of_week'] : '';

    // Arrays for multiple periods
    $period_numbers = isset($_POST['period_number']) ? $_POST['period_number'] : [];
    $subject_ids = isset($_POST['subject_id']) ? $_POST['subject_id'] : [];
    $teacher_ids = isset($_POST['teacher_id']) ? $_POST['teacher_id'] : [];
    $start_times = isset($_POST['start_time']) ? $_POST['start_time'] : [];
    $end_times = isset($_POST['end_time']) ? $_POST['end_time'] : [];

    $errors = [];
    if (!$class_id) $errors[] = 'শ্রেণি নির্বাচন করুন।';
    if (!$section_id) $errors[] = 'শাখা নির্বাচন করুন।';
    if (!$day_of_week) $errors[] = 'দিন নির্বাচন করুন।';

    // Basic per-row validation
    $rowsToInsert = [];
    $count = max(count($period_numbers), count($subject_ids), count($teacher_ids));
    for ($i = 0; $i < $count; $i++) {
        $pnum = isset($period_numbers[$i]) ? intval($period_numbers[$i]) : 0;
        $sid = isset($subject_ids[$i]) ? intval($subject_ids[$i]) : 0;
        $tid = isset($teacher_ids[$i]) ? intval($teacher_ids[$i]) : 0;
        $st = isset($start_times[$i]) && $start_times[$i] !== '' ? $start_times[$i] : null;
        $et = isset($end_times[$i]) && $end_times[$i] !== '' ? $end_times[$i] : null;

        if (!$pnum && !$sid && !$tid && !$st && !$et) {
            // empty row (skip)
            continue;
        }
        if (!$pnum) $errors[] = "পিরিয়ড নম্বর লাগে (পাংক্তি " . ($i+1) . ")।";
        if (!$sid) $errors[] = "বিষয় নির্বাচন করুন (পাংক্তি " . ($i+1) . ")।";
        if (!$tid) $errors[] = "শিক্ষক নির্বাচন করুন (পাংক্তি " . ($i+1) . ")।";

        $rowsToInsert[] = [$class_id, $section_id, $day_of_week, $pnum, $sid, $tid, $st, $et];
    }

    if (empty($errors) && !empty($rowsToInsert)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO routines (class_id, section_id, day_of_week, period_number, subject_id, teacher_id, start_time, end_time, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            foreach ($rowsToInsert as $r) {
                $stmt->execute($r);
            }
            $pdo->commit();
            $_SESSION['success'] = 'রুটিন সফলভাবে যোগ করা হয়েছে।';
            header('Location: routine_details.php?class_id=' . $class_id . '&section_id=' . $section_id);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'ডাটাবেস ত্রুটি: রুটিন সংরক্ষণ করা যায়নি।';
        }
    }
}

$day_names = [
    'saturday' => 'শনিবার',
    'sunday' => 'রবিবার',
    'monday' => 'সোমবার',
    'tuesday' => 'মঙ্গলবার',
    'wednesday' => 'বুধবার',
    'thursday' => 'বৃহস্পতিবার',
    'friday' => 'শুক্রবার'
];

?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>রুটিন যোগ করুন - কিন্ডার গার্ডেন</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body, .main-sidebar, .nav-link {font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;}</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">রুটিন যোগ করুন</h1></div>
                    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>dashboard.php">হোম</a></li><li class="breadcrumb-item active">রুটিন যোগ করুন</li></ol></div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if(!empty($errors)): ?>
                    <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="form-row mb-3">
                            <div class="form-group col-md-4">
                                <label>শ্রেণি</label>
                                <select id="class_id" class="form-control">
                                    <option value="">-- শ্রেণি নির্বাচন করুন --</option>
                                    <?php foreach($classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>শাখা</label>
                                <select id="section_id" class="form-control">
                                    <option value="">-- প্রথমে শ্রেণি নির্বাচন করুন --</option>
                                    <?php foreach($sections as $s): ?>
                                        <option data-class="<?php echo $s['class_id']; ?>" value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['class_id']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4 text-right align-self-end">
                                <a href="routine_list.php" class="btn btn-secondary">বাতিল</a>
                            </div>
                        </div>

                        <div class="d-flex mb-2" id="periodControls" style="display:none">
                            <div class="mr-2">পিরিয়ড সংখ্যা: <span id="periodCountDisplay">8</span></div>
                            <button class="btn btn-sm btn-outline-primary mr-1" id="addPeriodBtn">+ যোগ করুন</button>
                            <button class="btn btn-sm btn-outline-danger" id="removePeriodBtn">- মুছুন</button>
                        </div>

                        <div id="routineGridWrap" style="display:none">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="routineGrid">
                                    <thead>
                                        <tr>
                                            <th>পিরিয়ড \ দিন</th>
                                            <?php foreach($day_names as $d): ?>
                                                <th><?php echo $d; ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody id="routineGridBody">
                                        <!-- rows injected by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Modal for add/edit cell -->
                        <div class="modal fade" id="cellModal" tabindex="-1" role="dialog">
                          <div class="modal-dialog" role="document">
                            <div class="modal-content">
                              <div class="modal-header"><h5 class="modal-title">রুটিন যোগ/সম্পাদনা</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                              <div class="modal-body">
                                <form id="cellForm">
                                    <input type="hidden" name="action" value="save_cell" />
                                    <input type="hidden" name="class_id" id="modal_class_id" />
                                    <input type="hidden" name="section_id" id="modal_section_id" />
                                    <input type="hidden" name="day_of_week" id="modal_day" />
                                    <div class="form-group">
                                        <label>পিরিয়ড #</label>
                                        <input type="number" name="period_number" id="modal_period" class="form-control" min="1" required />
                                    </div>
                                    <div class="form-group">
                                        <label>বিষয়</label>
                                        <select name="subject_id" id="modal_subject" class="form-control"></select>
                                    </div>
                                    <div class="form-group">
                                        <label>শিক্ষক</label>
                                        <select name="teacher_id" id="modal_teacher" class="form-control">
                                            <option value="">-- নির্বাচন করুন --</option>
                                            <?php foreach($teachers as $t): ?>
                                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6"><label>শুরুর সময়</label><input type="time" name="start_time" id="modal_start" class="form-control" /></div>
                                        <div class="form-group col-md-6"><label>শেষ সময়</label><input type="time" name="end_time" id="modal_end" class="form-control" /></div>
                                    </div>
                                </form>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-danger" id="deleteCell">মুছুন</button>
                                <button type="button" class="btn btn-primary" id="saveCell">সংরক্ষণ</button>
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">বন্ধ করুন</button>
                              </div>
                            </div>
                          </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <?php include 'inc/footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
// Filter sections when class changes
$('#class_id').on('change', function(){
    var cls = $(this).val();
    $('#section_id option').each(function(){
        var optClass = $(this).data('class');
        if(!cls) {
            $(this).hide();
        } else if(optClass == cls) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
    $('#section_id').val('');
    // load subjects for this class and populate all subject selects
    if (cls) {
        $.getJSON(window.location.pathname + '?action=get_subjects&class_id=' + cls, function(data){
            $('.subject-select').each(function(){
                var sel = $(this);
                sel.empty();
                sel.append('<option value="">-- বিষয় নির্বাচন করুন --</option>');
                $.each(data, function(i, row){
                    sel.append('<option value="'+row.subject_id+'">'+row.name+'</option>');
                });
            });
        });
    } else {
        $('.subject-select').each(function(){
            $(this).empty().append('<option value="">-- প্রথমে শ্রেণি নির্বাচন করুন --</option>');
        });
    }
    // hide current grid until section is chosen
    $('#routineGridWrap').hide();
});

// On page load hide all section options until class selected
$('#section_id option').each(function(){
    if(!$(this).data('class')) return; // keep placeholder
    $(this).hide();
});

// Dynamic add/remove period rows
function makePeriodRow() {
    var row = $('.period-row').first().clone();
    row.find('input').val('');
    row.find('select.subject-select').empty().append('<option value="">-- প্রথমে শ্রেণি নির্বাচন করুন --</option>');
    return row;
}

$('#addPeriod').on('click', function(){
    var newRow = makePeriodRow();
    $('#periodRows').append(newRow);
    // If class already selected, trigger change to load subjects into the new row
    var cls = $('#class_id').val();
    if (cls) $('#class_id').trigger('change');
});

// remove period (allow removing all but at least one row)
$(document).on('click', '.remove-period', function(){
    if ($('.period-row').length <= 1) {
        // clear inputs instead
        var r = $(this).closest('.period-row');
        r.find('input').val('');
        r.find('select').val('');
        return;
    }
    $(this).closest('.period-row').remove();
});

// --- Grid + Modal JS ---
var days = <?php echo json_encode(array_keys($day_names)); ?>;

function buildGrid(periodCount) {
    var tbody = $('#routineGridBody');
    tbody.empty();
    for (var p = 1; p <= periodCount; p++) {
        var tr = $('<tr></tr>');
        tr.append('<th>পিরিয়ড ' + p + '</th>');
        for (var d = 0; d < days.length; d++) {
            var td = $('<td class="cell-td" data-day="' + days[d] + '" data-period="' + p + '"></td>');
            var btn = $('<button class="btn btn-sm btn-success add-cell">+</button>');
            td.append(btn);
            tr.append(td);
        }
        tbody.append(tr);
    }
}

function loadGrid() {
    var cls = $('#class_id').val();
    var sec = $('#section_id').val();
    if (!cls || !sec) { $('#routineGridWrap').hide(); return; }
    $('#routineGridWrap').show();
    $('#periodControls').show();
    // fetch period count first
    $.getJSON(window.location.pathname + '?action=get_period_count&class_id=' + cls + '&section_id=' + sec, function(pcdata){
        var configured = pcdata.period_count || 8;
        $('#periodCountDisplay').text(configured);
        // then fetch grid and render with configured periods
        $.getJSON(window.location.pathname + '?action=get_grid&class_id=' + cls + '&section_id=' + sec, function(grid){
            var maxP = configured;
            $.each(grid, function(day, obj){
                $.each(obj, function(p, cell){ if (parseInt(p) > maxP) maxP = parseInt(p); });
            });
            buildGrid(maxP);
            // populate cells
            $.each(grid, function(day, obj){
                $.each(obj, function(p, cell){
                    var selector = '.cell-td[data-day="'+day+'"][data-period="'+p+'"]';
                    var td = $(selector);
                    td.data('cell', cell);
                    td.find('.add-cell').text('✎').removeClass('btn-success').addClass('btn-primary');
                    td.append('<div class="small">' + (cell.subject_name?cell.subject_name:'') + '<br/>' + (cell.teacher_name?cell.teacher_name:'') + '</div>');
                });
            });
        });
    });
}

// Populate modal subject select for given class
function populateModalSubjects(classId, selected) {
    var sel = $('#modal_subject');
    sel.empty();
    if (!classId) { sel.append('<option value="">-- প্রথমে শ্রেণি নির্বাচন করুন --</option>'); return; }
    $.getJSON(window.location.pathname + '?action=get_subjects&class_id=' + classId, function(data){
        sel.append('<option value="">-- নির্বাচন করুন --</option>');
        $.each(data, function(i, row){ sel.append('<option value="'+row.subject_id+'">'+row.name+'</option>'); });
        if (selected) sel.val(selected);
    });
}

// click + in a cell

// Add / Remove period buttons
$('#addPeriodBtn').on('click', function(){
    var cls = $('#class_id').val(); var sec = $('#section_id').val();
    if (!cls || !sec) return alert('প্রথমে শ্রেণি ও শাখা নির্বাচন করুন');
    var current = parseInt($('#periodCountDisplay').text()) || 8;
    var next = current + 1;
    $.post(window.location.pathname, {action:'set_period_count', class_id:cls, section_id:sec, period_count:next}, function(resp){ if (resp.success) loadGrid(); else alert('Failed'); }, 'json');
});

$('#removePeriodBtn').on('click', function(){
    var cls = $('#class_id').val(); var sec = $('#section_id').val();
    if (!cls || !sec) return alert('প্রথমে শ্রেণি ও শাখা নির্বাচন করুন');
    var current = parseInt($('#periodCountDisplay').text()) || 8;
    if (current <= 1) return alert('কমপক্ষে 1 পিরিয়ড থাকতে হবে');
    var next = current - 1;
    $.post(window.location.pathname, {action:'set_period_count', class_id:cls, section_id:sec, period_count:next}, function(resp){ if (resp.success) loadGrid(); else alert('Failed'); }, 'json');
});
$(document).on('click', '.add-cell', function(){
    var td = $(this).closest('.cell-td');
    var day = td.data('day');
    var period = td.data('period');
    var cls = $('#class_id').val();
    var sec = $('#section_id').val();
    $('#modal_class_id').val(cls);
    $('#modal_section_id').val(sec);
    $('#modal_day').val(day);
    $('#modal_period').val(period);
    // clear
    $('#modal_subject').val(''); $('#modal_teacher').val(''); $('#modal_start').val(''); $('#modal_end').val('');
    populateModalSubjects(cls);
    // if existing data present on td, fill
    var cell = td.data('cell');
    if (cell) {
        // set after subjects loaded
        setTimeout(function(){
            $('#modal_period').val(cell.period_number);
            $('#modal_subject').val(cell.subject_id);
            $('#modal_teacher').val(cell.teacher_id);
            $('#modal_start').val(cell.start_time);
            $('#modal_end').val(cell.end_time);
        }, 200);
    }
    $('#cellModal').modal('show');
});

// save cell
$('#saveCell').on('click', function(){
    var data = $('#cellForm').serialize();
    $.post(window.location.pathname, data, function(resp){
        if (resp.success) {
            $('#cellModal').modal('hide');
            loadGrid();
        } else {
            alert(resp.error || 'সংরক্ষণে ত্রুটি');
        }
    }, 'json');
});

// delete cell
$('#deleteCell').on('click', function(){
    if (!confirm('শুধু নির্দিষ্ট পিরিয়ড রেকর্ড মুছবেন?')) return;
    var cls = $('#modal_class_id').val();
    var sec = $('#modal_section_id').val();
    var day = $('#modal_day').val();
    var period = $('#modal_period').val();
    $.post(window.location.pathname, {action:'delete_cell', class_id:cls, section_id:sec, day_of_week:day, period_number:period}, function(resp){
        if (resp.success) {
            $('#cellModal').modal('hide'); loadGrid();
        } else alert(resp.error || 'মুছতে ব্যর্থ');
    }, 'json');
});

// when section changes, load grid
$('#section_id').on('change', function(){ if ($('#class_id').val()) loadGrid(); });
</script>

</body>
</html>
