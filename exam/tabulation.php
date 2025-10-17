<?php
require_once '../config.php';
if (!isAuthenticated()) redirect('../login.php');
if (!hasRole(['super_admin'])) redirect('403.php');

// load helper lists
$classes = $pdo->query("SELECT id, name FROM classes WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT id, year FROM academic_years WHERE status='active' ORDER BY year DESC")->fetchAll(PDO::FETCH_ASSOC);

$selected_year = intval($_GET['year'] ?? 0);
$selected_class = intval($_GET['class_id'] ?? 0);
$exam_id = intval($_GET['exam_id'] ?? 0);
$printTab = $_GET['print'] ?? '';
$printMode = in_array($printTab, ['single','combined','stats']);

$exams = [];
if ($selected_year && $selected_class) {
    $stmt = $pdo->prepare("SELECT e.*, et.name as exam_type_name FROM exams e LEFT JOIN exam_types et ON et.id = e.exam_type_id WHERE e.academic_year_id = ? AND e.class_id = ? ORDER BY e.created_at DESC");
    $stmt->execute([$selected_year, $selected_class]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$exam = null;
$linked_tutorials = [];
if ($exam_id) {
    $stmt = $pdo->prepare("SELECT e.*, et.name as exam_type_name FROM exams e LEFT JOIN exam_types et ON et.id = e.exam_type_id WHERE e.id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    // get linked tutorials if any (fetch id and name)
    $lt = $pdo->prepare("SELECT l.tutorial_exam_id as id, e.name FROM exam_term_tutorial_links l JOIN exams e ON e.id = l.tutorial_exam_id WHERE l.term_exam_id = ?");
    $lt->execute([$exam_id]);
    $ltRows = $lt->fetchAll(PDO::FETCH_ASSOC);
    $linked_tutorials = [];
    $linked_tutorials_map = []; // id => name
    foreach ($ltRows as $r) { $linked_tutorials[] = $r['id']; $linked_tutorials_map[$r['id']] = $r['name']; }
}

// students in class
$students = [];
if ($selected_class) {
    // roll_number is the actual column name in the students table; alias as `roll` for template compatibility
    $st = $pdo->prepare("SELECT id, first_name, last_name, roll_number AS roll FROM students WHERE class_id = ? AND status='active' ORDER BY roll_number, id");
    $st->execute([$selected_class]);
    $students = $st->fetchAll(PDO::FETCH_ASSOC);
}

// helper: fetch subjects for an exam, ordered by class subject order when available
function getExamSubjectsOrdered($pdo, $examId, $classId = null) {
    if ($classId) {
        $s = $pdo->prepare("SELECT es.subject_id, sub.name as subject_name, es.max_marks, es.pass_marks, COALESCE(cs.numeric_value, 9999) AS sort_key
            FROM exam_subjects es 
            JOIN subjects sub ON sub.id = es.subject_id 
            LEFT JOIN class_subjects cs ON cs.subject_id = es.subject_id AND cs.class_id = ?
            WHERE es.exam_id = ?
            ORDER BY cs.numeric_value ASC, es.id ASC");
        $s->execute([$classId, $examId]);
    } else {
        $s = $pdo->prepare("SELECT es.subject_id, sub.name as subject_name, es.max_marks, es.pass_marks, es.id AS sort_key FROM exam_subjects es JOIN subjects sub ON sub.id = es.subject_id WHERE es.exam_id = ? ORDER BY es.id");
        $s->execute([$examId]);
    }
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

// Preload data for single exam
$single_subjects = [];
$marks_map = [];
if ($exam && !empty($students)) {
    // subjects ordered by class subject order
    $single_subjects = getExamSubjectsOrdered($pdo, $exam_id, $selected_class);
    // fetch marks for this exam for all students
    $studentIds = array_column($students, 'id');
    if (!empty($studentIds)) {
        $in = implode(',', array_fill(0, count($studentIds), '?'));
        $sql = "SELECT student_id, subject_id, marks_obtained FROM marks WHERE exam_id = ? AND student_id IN ($in)";
        $params = array_merge([$exam_id], $studentIds);
        $ms = $pdo->prepare($sql);
        $ms->execute($params);
        while ($r = $ms->fetch(PDO::FETCH_ASSOC)) {
            $marks_map[$r['student_id']][$r['subject_id']] = $r['marks_obtained'];
        }
    }
}

// Populate subjects for dropdown regardless of student presence
$subjects_for_dropdown = [];
if ($exam) {
    $subjects_for_dropdown = getExamSubjectsOrdered($pdo, $exam_id, $selected_class);
}

// Preload data for combined (term + tutorials)
$combined_exams = [];
$combined_subjects = []; // union of subjects across exams => subject_id => name
$combined_subject_ids = []; // ordered list per class order
$combined_marks = []; // [exam_id][student_id][subject_id]
$pass_marks_map = []; // [exam_id][subject_id] => pass_marks
// class subject order map
$class_subject_order = [];
if ($selected_class) {
    $cso = $pdo->prepare("SELECT subject_id, numeric_value FROM class_subjects WHERE class_id = ? ORDER BY numeric_value ASC");
    $cso->execute([$selected_class]);
    while ($r = $cso->fetch(PDO::FETCH_ASSOC)) { $class_subject_order[$r['subject_id']] = intval($r['numeric_value']); }
}
if ($exam) {
    $combined_exams = [$exam_id];
    if (!empty($linked_tutorials)) $combined_exams = array_merge($combined_exams, $linked_tutorials);
    // gather subjects and marks across these exams
    $allExamIds = $combined_exams;
    if (!empty($allExamIds) && !empty($students)) {
        // subjects per exam (ordered) and pass marks
        foreach ($allExamIds as $eid) {
            $subs = getExamSubjectsOrdered($pdo, $eid, $selected_class);
            foreach ($subs as $s) {
                $combined_subjects[$s['subject_id']] = $s['subject_name'];
                $pass_marks_map[$eid][$s['subject_id']] = intval($s['pass_marks'] ?? 0);
            }
        }
        // determine ordered subject id list by class_subjects order
        $combined_subject_ids = array_keys($combined_subjects);
        usort($combined_subject_ids, function($a, $b) use ($class_subject_order) {
            $oa = $class_subject_order[$a] ?? PHP_INT_MAX;
            $ob = $class_subject_order[$b] ?? PHP_INT_MAX;
            if ($oa === $ob) return $a <=> $b;
            return ($oa < $ob) ? -1 : 1;
        });
        // fetch marks for all exam_ids and students
        $examIn = implode(',', array_fill(0, count($allExamIds), '?'));
        $studentIds = array_column($students, 'id');
        $studentIn = implode(',', array_fill(0, count($studentIds), '?'));
        $sql = "SELECT exam_id, student_id, subject_id, marks_obtained FROM marks WHERE exam_id IN ($examIn) AND student_id IN ($studentIn)";
        $params = array_merge($allExamIds, $studentIds);
        $ms = $pdo->prepare($sql);
        $ms->execute($params);
        while ($r = $ms->fetch(PDO::FETCH_ASSOC)) {
            $combined_marks[$r['exam_id']][$r['student_id']][$r['subject_id']] = $r['marks_obtained'];
        }
    }
}

// Stats (per subject for selected exam)
$subject_stats = [];
if ($exam && !empty($single_subjects)) {
    foreach ($single_subjects as $sub) {
        $sid = $sub['subject_id'];
        $vals = [];
        foreach ($students as $stu) {
            $v = $marks_map[$stu['id']][$sid] ?? null;
            if ($v !== null) $vals[] = floatval($v);
        }
        if (!empty($vals)) {
            $subject_stats[$sid] = [
                'name' => $sub['subject_name'],
                'avg' => array_sum($vals)/count($vals),
                'max' => max($vals),
                'min' => min($vals),
                'count' => count($vals)
            ];
        } else {
            $subject_stats[$sid] = ['name'=>$sub['subject_name'],'avg'=>0,'max'=>0,'min'=>0,'count'=>0];
        }
    }
}

?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tabulation - পরীক্ষার টেবুলেশন</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body, .main-sidebar, .nav-link { font-family: 'SolaimanLipi','Source Sans Pro',sans-serif; }</style>
    <?php if ($printMode): ?>
    <style>
        /* Print mode: hide navigation and show only selected tab content */
        body{background:#fff;color:#000}
        .tab-content .tab-pane{display:none !important}
        #<?php echo htmlspecialchars($printTab); ?>{display:block !important}
        .nav, .nav-tabs, .card .card-header, .breadcrumb, .sidebar, .main-header, .btn, .main-footer{display:none !important}
        .container-fluid, .content-wrapper{padding:0;margin:0}
        table{font-size:12px}
        @page { size: A4; margin: 15mm; }
        /* ensure page breaks after large tables when printing multiple sections */
        table { page-break-inside: auto }
        tr    { page-break-inside: avoid; page-break-after: auto }
    </style>
    <?php endif; ?>
</head>
<body class="hold-transition sidebar-mini">
<?php if (!$printMode) { include '../admin/inc/header.php'; include '../admin/inc/sidebar.php'; } else { include '../admin/print_common.php'; echo print_header($pdo, ($exam['name'] ?? 'টেবুলেশন')); } ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">টেবুলেশন</h1></div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body">
                    <?php if (!$printMode): ?>
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label>Academic Year</label>
                            <select name="year" class="form-control">
                                <option value="">-- select --</option>
                                <?php foreach($years as $y): ?>
                                    <option value="<?= $y['id'] ?>" <?= $selected_year==$y['id']? 'selected':'' ?>><?= htmlspecialchars($y['year']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Class</label>
                            <select name="class_id" class="form-control" onchange="this.form.submit()">
                                <option value="">-- select --</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $selected_class==$c['id']? 'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Exam</label>
                            <select name="exam_id" class="form-control" onchange="this.form.submit()">
                                <option value="">-- select --</option>
                                <?php foreach($exams as $ex): ?>
                                    <option value="<?= $ex['id'] ?>" <?= $exam_id==$ex['id']? 'selected':'' ?>><?= htmlspecialchars($ex['name'].' ('.$ex['exam_type_name'].')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <?php endif; ?>

                    <?php if ($printMode && $exam): ?>
                        <div style="text-align:center;margin:12px 0 18px 0;">
                            <div style="font-weight:800;font-size:20px;">টেবুলেশন শীট - <?= htmlspecialchars($exam['name']) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!$exam): ?>
                        <div class="alert alert-info mt-3">একটি ক্লাস ও পরীক্ষা নির্বাচন করুন টেবুলেশন দেখার জন্য।</div>
                    <?php else: ?>
                        <!-- per-tab print buttons are rendered inside each tab pane -->
                        <hr>
                        <ul class="nav nav-tabs" id="tabulationTabs" role="tablist">
                            <li class="nav-item" role="presentation"><button class="nav-link active" id="admit-tab" data-bs-toggle="tab" data-bs-target="#admit" type="button" role="tab">প্রবেশপত্র</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" id="markentry-tab" data-bs-toggle="tab" data-bs-target="#markentry" type="button" role="tab">মার্ক এন্ট্রি</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab">মার্কশীট (একক)</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" id="combined-tab" data-bs-toggle="tab" data-bs-target="#combined" type="button" role="tab">মার্কশীট (যুক্ত)</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">পরিসংখ্যান</button></li>
                        </ul>
                        <div class="tab-content mt-3">
                            <div class="tab-pane fade show active" id="admit" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="m-0">প্রবেশপত্র</h5>
                                </div>
                                <div class="alert alert-secondary">বর্তমান নির্বাচিত পরীক্ষার জন্য প্রবেশপত্র প্রিন্ট করতে নিচের বাটন ব্যবহার করুন।</div>
                                <a class="btn btn-primary" target="_blank" href="admit.php?<?= http_build_query(['exam_id'=>$exam_id]) ?>">প্রবেশপত্র খুলুন</a>
                                <p class="text-muted mt-2" style="font-size: 0.9rem;">নোট: প্রবেশপত্র পাতায় সেকশন বা নির্দিষ্ট শিক্ষার্থীর আইডি দিয়ে আরো নির্দিষ্টভাবে প্রিন্ট করা যাবে।</p>
                            </div>
                            <div class="tab-pane fade" id="markentry" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="m-0">মার্ক এন্ট্রি</h5>
                                </div>
                                <?php if (!$selected_class || !$exam_id): ?>
                                    <div class="alert alert-warning">মার্ক এন্ট্রি করার আগে উপরে থেকে ক্লাস ও পরীক্ষা নির্বাচন করুন।</div>
                                <?php else: ?>
                                    <div class="row g-2 align-items-end mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">বিষয় নির্বাচন</label>
                                            <select id="me-subject" class="form-control">
                                                <option value="">-- বিষয় নির্বাচন করুন --</option>
                                                <?php foreach($subjects_for_dropdown as $s): ?>
                                                    <option value="<?= $s['subject_id'] ?>" data-max="<?= htmlspecialchars($s['max_marks'] ?? 0) ?>"><?= htmlspecialchars($s['subject_name']) ?> (<?= htmlspecialchars($s['max_marks'] ?? 0) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">সর্বোচ্চ নম্বর</label>
                                            <input id="me-max" type="text" class="form-control" value="" readonly>
                                        </div>
                                    </div>
                                    <div id="me-holder">
                                        <div class="alert alert-info">উপরের ড্রপডাউন থেকে একটি বিষয় নির্বাচন করুন।</div>
                                    </div>
                                    <script>
                                        (function initMarkEntry(){
                                            function startWhenReady(){
                                                if (!window.jQuery) { return setTimeout(startWhenReady, 50); }
                                                jQuery(function($){
                                                    const examId = <?= json_encode($exam_id) ?>;
                                                    const classId = <?= json_encode($selected_class) ?>;
                                                    const $sub = $('#me-subject');
                                                    const $holder = $('#me-holder');
                                                    const $max = $('#me-max');

                                                    function renderTable(data){
                                                        const maxMarks = Number($max.val()) || 0;
                                                        let html = '<div class="table-responsive"><table class="table table-bordered table-sm align-middle">';
                                                        html += '<thead><tr><th style="width:90px">রোল</th><th>শিক্ষার্থী</th><th style="width:130px">নম্বর</th><th style="width:120px">স্ট্যাটাস</th></tr></thead><tbody>';
                                                        if (!data.students || data.students.length===0){
                                                            html += '<tr><td colspan="4" class="text-center">কোনো শিক্ষার্থী পাওয়া যায়নি</td></tr>';
                                                        } else {
                                                            for (const st of data.students){
                                                                const val = (st.mark!==null && st.mark!==undefined) ? st.mark : '';
                                                                html += '<tr>'+
                                                                    '<td>'+ (st.roll ?? '-') +'</td>'+
                                                                    '<td>'+ $('<div>').text(st.name).html() +'</td>'+
                                                                    '<td><input type="text" inputmode="decimal" pattern="[0-9০-৯.,٫،।]*" class="form-control form-control-sm me-input" data-stu="'+st.id+'" data-max="'+maxMarks+'" value="'+ val +'" placeholder="0"></td>'+
                                                                    '<td><span class="me-status text-muted">&nbsp;</span></td>'+
                                                                '</tr>';
                                                            }
                                                        }
                                                        html += '</tbody></table></div>';
                                                        $holder.html(html);

                                                        // attach listeners
                                                        let typingTimers = {};
                                                        function normalizeRawToEnNumber(raw){
                                                            if (raw === null || raw === undefined) return '';
                                                            let s = String(raw).trim();
                                                            if (!s) return '';
                                                            const map = { '০':'0','১':'1','২':'2','৩':'3','৪':'4','৫':'5','৬':'6','৭':'7','৮':'8','৯':'9' };
                                                            s = s.replace(/[০-৯]/g, ch => map[ch] || ch);
                                                            s = s.replace(/[٬,٫،.,،،]/g, '.');
                                                            s = s.replace(/[^0-9.\-]/g, '');
                                                            const parts = s.split('.');
                                                            if (parts.length > 2) s = parts.shift() + '.' + parts.join('');
                                                            return s;
                                                        }
                                                        $holder.find('.me-input').on('input', function(){
                                                            const $inp = $(this);
                                                            const stuId = $inp.data('stu');
                                                            const subjectId = Number($sub.val());
                                                            let raw = $inp.val();
                                                            raw = normalizeRawToEnNumber(raw);
                                                            $inp.val(raw);
                                                            if (raw === '') {
                                                                queueSave('');
                                                                return;
                                                            }
                                                            let v = parseFloat(raw);
                                                            if (isNaN(v)) { queueSave(''); return; }
                                                            const mx = Number($inp.attr('max') || $inp.data('max') || 0) || 0;
                                                            if (v < 0) v = 0;
                                                            if (mx > 0 && v > mx) v = mx;
                                                            $inp.val(v);
                                                            queueSave(v);

                                                            function queueSave(val){
                                                                const $status = $inp.closest('tr').find('.me-status');
                                                                $status.removeClass('text-success text-danger').addClass('text-muted').text('সেভ হচ্ছে…');
                                                                clearTimeout(typingTimers[stuId]);
                                                                typingTimers[stuId] = setTimeout(function(){
                                                                    saveMark(stuId, subjectId, val, function(ok,msg){
                                                                        if (ok){ $status.removeClass('text-muted text-danger').addClass('text-success').text('সেভ হয়েছে'); }
                                                                        else { $status.removeClass('text-muted text-success').addClass('text-danger').text(msg||'ত্রুটি'); }
                                                                    });
                                                                }, 400);
                                                            }
                                                        });
                                                    }

                                                    function loadStudents(subjectId){
                                                        $holder.html('<div class="alert alert-info">তথ্য লোড হচ্ছে…</div>');
                                                        $.getJSON('get_students_marks_ajax.php', { exam_id: examId, class_id: classId, subject_id: subjectId })
                                                            .done(function(res){ renderTable(res); })
                                                            .fail(function(){ $holder.html('<div class="alert alert-danger">ডাটা লোড করা যায়নি</div>'); });
                                                    }

                                                    function saveMark(studentId, subjectId, mark, cb){
                                                        $.ajax({
                                                            url: 'ajax_save_mark.php', method: 'POST', dataType: 'json',
                                                            data: { exam_id: examId, subject_id: subjectId, student_id: studentId, mark: mark }
                                                        }).done(function(res){ cb(!!res.success, res.message); })
                                                          .fail(function(){ cb(false, 'সেভ ব্যর্থ'); });
                                                    }

                                                    $sub.on('change', function(){
                                                        const raw = $(this).val();
                                                        const sid = (raw === null) ? '' : String(raw);
                                                        const max = $(this).find('option:selected').data('max');
                                                        $max.val(max || '');
                                                        if (sid === ''){
                                                            $holder.html('<div class="alert alert-info">উপরের ড্রপডাউন থেকে একটি বিষয় নির্বাচন করুন।</div>');
                                                        } else {
                                                            loadStudents(parseInt(sid,10));
                                                        }
                                                    });
                                                    // auto load when only one real option exists
                                                    if ($sub.find('option').length === 2 && !$sub.val()) {
                                                        const v = $sub.find('option').eq(1).attr('value');
                                                        $sub.val(v).trigger('change');
                                                    }
                                                });
                                            }
                                            startWhenReady();
                                        })();
                                    </script>
                                <?php endif; ?>
                            </div>
                            <div class="tab-pane fade" id="single" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="m-0">মার্কশীট (একক): <?= htmlspecialchars($exam['name']) ?></h5>
                                    <?php if (!$printMode): ?>
                                        <a class="btn btn-sm btn-outline-primary" target="_blank" href="tabulation_print_single.php?<?= http_build_query(['year'=>$selected_year,'class_id'=>$selected_class,'exam_id'=>$exam_id]) ?>">Print This Tab</a>
                                    <?php endif; ?>
                                </div>
                                <?php if (empty($single_subjects)): ?>
                                    <div class="alert alert-warning">এই পরীক্ষার জন্য কোনো বিষয় নির্ধারণ করা হয়নি।</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>রোল</th>
                                                    <th>শিক্ষার্থী</th>
                                                    <?php foreach($single_subjects as $s): ?><th><?= htmlspecialchars($s['subject_name']) ?></th><?php endforeach; ?>
                                                    <th>মোট</th>
                                                    <th>গড়</th>
                                                    <th>ফেল</th>
                                                    <th>স্ট্যাটাস</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($students as $stu):
                                                    $total=0; $has=0; $failCount=0;
                                                ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($stu['roll'] ?? $stu['id']) ?></td>
                                                        <td><?= htmlspecialchars(trim($stu['first_name'].' '.$stu['last_name'])) ?></td>
                                                        <?php foreach($single_subjects as $s):
                                                            $val = $marks_map[$stu['id']][$s['subject_id']] ?? null;
                                                            if ($val !== null) {
                                                                $fv = floatval($val);
                                                                $total += $fv; $has++;
                                                                $pm = floatval($s['pass_marks'] ?? 0);
                                                                if ($pm > 0 && $fv < $pm) { $failCount++; }
                                                            }
                                                        ?>
                                                            <td><?= $val === null ? '-' : htmlspecialchars($val) ?></td>
                                                        <?php endforeach; ?>
                                                        <td><?= $has? number_format($total,2) : '-' ?></td>
                                                        <td><?= $has? number_format($total/$has,2) : '-' ?></td>
                                                        <td><?= $has? htmlspecialchars($failCount) : '-' ?></td>
                                                        <td><?= $has? ($failCount>0? 'ফেল' : 'পাস') : '-' ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade" id="combined" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="m-0">মার্কশীট (যুক্ত): <?= htmlspecialchars($exam['name']) ?> (Term + Tutorials)</h5>
                                    <?php if (!$printMode): ?>
                                        <a class="btn btn-sm btn-outline-primary" target="_blank" href="tabulation_print_combined.php?<?= http_build_query(['year'=>$selected_year,'class_id'=>$selected_class,'exam_id'=>$exam_id]) ?>">Print This Tab</a>
                                    <?php endif; ?>
                                </div>
                                <?php if (empty($combined_exams) || count($combined_exams)===1): ?>
                                    <div class="alert alert-info">এই পরীক্ষার সাথে কোনো টিউটোরিয়াল পরীক্ষার লিংক করা হয়নি।</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>রোল</th>
                                                    <th>শিক্ষার্থী</th>
                                                    <?php
                                                    // header: for each subject, show main exam column then per-tutorial columns
                                                    $subIdList = empty($combined_subject_ids) ? array_keys($combined_subjects) : $combined_subject_ids;
                                                    foreach ($subIdList as $sid) {
                                                        $sname = $combined_subjects[$sid];
                                                        echo '<th colspan="'.(1+count($linked_tutorials)).'">'.htmlspecialchars($sname).'</th>';
                                                    }
                                                    ?>
                                                    <th>সর্বমোট</th>
                                                    <th>গড়</th>
                                                    <th>ফেল</th>
                                                    <th>স্ট্যাটাস</th>
                                                </tr>
                                                <tr>
                                                    <th></th><th></th>
                                                    <?php foreach ($subIdList as $sid) {
                                                        // main exam column
                                                        echo '<th>'.htmlspecialchars($exam['name']).'</th>';
                                                        // tutorials
                                                        foreach ($linked_tutorials as $ltid) {
                                                            $tn = $linked_tutorials_map[$ltid] ?? 'Tutorial';
                                                            echo '<th>'.htmlspecialchars($tn).'</th>';
                                                        }
                                                    } ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($students as $stu):
                                                    $combinedTotal=0; $failCount=0; $hasAny=false; $subjectsCountWithMarks = 0;
                                                ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($stu['roll'] ?? $stu['id']) ?></td>
                                                        <td><?= htmlspecialchars(trim($stu['first_name'].' '.$stu['last_name'])) ?></td>
                                                        <?php 
                                                        foreach ($subIdList as $sid) {
                                                            $subjectSum = 0; $subjectHas = false; $subjectPassThreshold = 0;
                                                            // main exam mark
                                                            $m = $combined_marks[$exam_id][$stu['id']][$sid] ?? null;
                                                            if ($m !== null) { $combinedTotal += floatval($m); $subjectSum += floatval($m); $subjectHas = true; $hasAny = true; }
                                                            echo '<td>'.($m===null?'-':htmlspecialchars($m)).'</td>';
                                                            $subjectPassThreshold += floatval($pass_marks_map[$exam_id][$sid] ?? 0);
                                                            // each tutorial
                                                            foreach ($linked_tutorials as $ltid) {
                                                                $tm = $combined_marks[$ltid][$stu['id']][$sid] ?? null;
                                                                if ($tm !== null) { $combinedTotal += floatval($tm); $subjectSum += floatval($tm); $subjectHas = true; $hasAny = true; }
                                                                echo '<td>'.($tm===null?'-':htmlspecialchars($tm)).'</td>';
                                                                $subjectPassThreshold += floatval($pass_marks_map[$ltid][$sid] ?? 0);
                                                            }
                                                            if ($subjectHas) { $subjectsCountWithMarks++; }
                                                            if ($subjectHas && $subjectPassThreshold > 0 && $subjectSum < $subjectPassThreshold) { $failCount++; }
                                                        } ?>
                                                        <td><?= number_format($combinedTotal,2) ?></td>
                                                        <td><?= $subjectsCountWithMarks? number_format($combinedTotal/$subjectsCountWithMarks,2) : '-' ?></td>
                                                        <td><?= $hasAny? htmlspecialchars($failCount) : '-' ?></td>
                                                        <td><?= $hasAny? ($failCount>0? 'ফেল' : 'পাস') : '-' ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade" id="stats" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="m-0">পরিসংখ্যান</h5>
                                    <?php if (!$printMode): ?>
                                        <a class="btn btn-sm btn-outline-primary" target="_blank" href="tabulation_print_stats.php?<?= http_build_query(['year'=>$selected_year,'class_id'=>$selected_class,'exam_id'=>$exam_id]) ?>">Print This Tab</a>
                                    <?php endif; ?>
                                </div>
                                <?php if (empty($subject_stats)): ?>
                                    <div class="alert alert-info">কোনো পরিসংখ্যান তৈরি করা যাচ্ছে না (নির্বাচিত পরীক্ষায় নম্বর নেই)।</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead><tr><th>Subject</th><th>Average</th><th>Highest</th><th>Lowest</th><th>Count</th></tr></thead>
                                            <tbody>
                                                <?php foreach($subject_stats as $stat): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($stat['name']) ?></td>
                                                        <td><?= number_format($stat['avg'],2) ?></td>
                                                        <td><?= htmlspecialchars($stat['max']) ?></td>
                                                        <td><?= htmlspecialchars($stat['min']) ?></td>
                                                        <td><?= htmlspecialchars($stat['count']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<?php if ($printMode) {
    // print footer helper and stop
    echo print_footer();
    echo "</body></html>";
    exit;
} else {
    include '../admin/inc/footer.php';
}

?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
