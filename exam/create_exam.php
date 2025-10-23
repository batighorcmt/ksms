<?php
require_once '../config.php';

// Authentication: require logged-in user (adjust role checks as needed)
if (!isAuthenticated()) {
    redirect('../login.php');
}

// Load supporting data
$classes = $pdo->query("SELECT id, name FROM classes WHERE status='active' ORDER BY numeric_value ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$types = $pdo->query("SELECT id, name FROM exam_types ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
// academic years from DB
$years = $pdo->query("SELECT id, year FROM academic_years WHERE status='active' ORDER BY year DESC")->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$edit_id = intval($_GET['id'] ?? $_GET['edit'] ?? 0);
$exam = null;
$exam_subjects = [];
$selected_tutorials = [];
if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id=?");
    $stmt->execute([$edit_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exam) {
        $stmt2 = $pdo->prepare("SELECT * FROM exam_subjects WHERE exam_id=?");
        $stmt2->execute([$edit_id]);
        $exam_subjects = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        // load linked tutorial exams (if any)
    // load linked tutorial exams (DB uses `exam_term_tutorial_links`)
    $stmt3 = $pdo->prepare("SELECT tutorial_exam_id FROM exam_term_tutorial_links WHERE term_exam_id = ?");
    $stmt3->execute([$edit_id]);
    $selected_tutorials = $stmt3->fetchAll(PDO::FETCH_COLUMN, 0);
    } else {
        $errors[] = 'Exam not found';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $academic_year_id = trim($_POST['academic_year_id'] ?? $_POST['academic_year'] ?? '');
    $class_id = intval($_POST['class_id'] ?? 0);
    $exam_type_id = intval($_POST['exam_type_id'] ?? 0);
    $result_release_date = $_POST['result_release_date'] ?: null;

    if ($name === '' || $class_id === 0 || $exam_type_id === 0) {
        $errors[] = 'নাম, শ্রেণি ও পরীক্ষার ধরন প্রয়োজন।';
    }

    // Subjects can come as parallel arrays or as JSON (client supports both)
    $sub_ids = $_POST['subject_id'] ?? [];
    $max_marks = $_POST['max_marks'] ?? [];
    $pass_marks = $_POST['pass_marks'] ?? [];
    $exam_dates = $_POST['exam_date'] ?? [];
    $exam_times = $_POST['exam_time'] ?? [];

    if (empty($sub_ids) && empty($_POST['subjects_json'])) {
        $errors[] = 'কমপক্ষে একটি বিষয় নির্বাচন করুন।';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            if ($edit_id) {
                $upd = $pdo->prepare("UPDATE exams SET name=?, academic_year_id=?, class_id=?, exam_type_id=?, result_release_date=?, updated_at=NOW() WHERE id=?");
                $upd->execute([$name, $academic_year_id, $class_id, $exam_type_id, $result_release_date, $edit_id]);
                $exam_id = $edit_id;
                $pdo->prepare("DELETE FROM exam_subjects WHERE exam_id=?")->execute([$exam_id]);
            } else {
                $ins = $pdo->prepare("INSERT INTO exams (name, academic_year_id, class_id, exam_type_id, result_release_date, created_at) VALUES (?,?,?,?,?,NOW())");
                $ins->execute([$name, $academic_year_id, $class_id, $exam_type_id, $result_release_date]);
                $exam_id = $pdo->lastInsertId();
            }

            // Insert subjects (from arrays) - DB columns are max_marks/pass_marks
            $es = $pdo->prepare("INSERT INTO exam_subjects (exam_id, subject_id, exam_date, exam_time, max_marks, pass_marks) VALUES (?,?,?,?,?,?)");
            if (!empty($sub_ids)) {
                foreach ($sub_ids as $i => $sid) {
                    $s = intval($sid);
                    $fm = intval($max_marks[$i] ?? 100);
                    $pm = intval($pass_marks[$i] ?? 33);
                    $ed = $exam_dates[$i] ?: null;
                    $et = $exam_times[$i] ?: null;
                    $es->execute([$exam_id, $s, $ed, $et, $fm, $pm]);
                }
            }

            // Or subjects might be posted as JSON (client-side save)
            if (!empty($_POST['subjects_json'])) {
                $subs = json_decode($_POST['subjects_json'], true);
                if (is_array($subs)) {
                    foreach ($subs as $s) {
                        $es->execute([$exam_id, intval($s['subject_id']), $s['exam_date'] ?: null, $s['exam_time'] ?: null, intval($s['max_marks'] ?? 100), intval($s['pass_marks'] ?? 33)]);
                    }
                }
            }

            // handle tutorial links when posted (optional) - use DB table `exam_term_tutorial_links`
            if (!empty($_POST['tutorial_links']) && is_array($_POST['tutorial_links'])) {
                $pdo->prepare("DELETE FROM exam_term_tutorial_links WHERE term_exam_id = ?")->execute([$exam_id]);
                $inslink = $pdo->prepare("INSERT INTO exam_term_tutorial_links (term_exam_id, tutorial_exam_id) VALUES (?,?)");
                foreach($_POST['tutorial_links'] as $t) {
                    $inslink->execute([$exam_id, intval($t)]);
                }
            }

            $pdo->commit();
            $_SESSION['success'] = $edit_id ? 'Exam updated successfully.' : 'Exam created successfully.';
            header('Location: exam_list.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $edit_id ? 'Edit Exam' : 'Create Exam' ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        /* Apply Bengali font throughout the page */
        body, .content-wrapper, .main-header, .main-sidebar, .brand-link, .card, .card-title, label, input, select, button, a, .nav-link, .sidebar, .navbar {
            font-family: 'SolaimanLipi', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .logo-custom { font-family: 'SolaimanLipi', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: 700; }
        .subject-row .removeRow { margin-top: 6px; }
        .card { border-radius: 8px; }
        /* Make header action button more prominent */
        .card-header .btn.btn-exam-list {
            font-weight: 700;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../admin/inc/header.php'; ?>
<?php include '../admin/inc/sidebar.php'; ?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h3 class="card-title"><?= $edit_id ? 'Edit Exam' : 'Create Exam' ?></h3>
                            <a href="exam_list.php" class="btn btn-warning btn-sm btn-exam-list">Exam List</a>
                        </div>
                        <div class="card-body">
                            <?php if(!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul>
                                </div>
                            <?php endif; ?>

                            <form method="post" id="examForm">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label>Exam Name</label>
                                        <input name="name" class="form-control" required value="<?= htmlspecialchars($exam['name'] ?? '') ?>">
                                    </div>
                                                <div class="col-md-3">
                                                    <label>Academic Year</label>
                                                    <select name="academic_year_id" class="form-control" required>
                                                        <option value="">--select--</option>
                                                        <?php foreach($years as $y): ?>
                                                            <option value="<?= $y['id'] ?>" <?= ($exam['academic_year_id'] ?? '') == $y['id'] ? 'selected' : '' ?>><?= htmlspecialchars($y['year']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                    <div class="col-md-3">
                                        <label>Class</label>
                                        <select name="class_id" id="class_id" class="form-control" required>
                                            <option value="">--select--</option>
                                            <?php foreach($classes as $c): ?>
                                                <option value="<?= $c['id'] ?>" <?= ($exam['class_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mt-2">
                                        <label>Exam Type</label>
                                        <select name="exam_type_id" id="exam_type_id" class="form-control" required>
                                            <option value="">--select--</option>
                                                <?php foreach($types as $t): ?>
                                                    <option data-name="<?= htmlspecialchars($t['name']) ?>" value="<?= $t['id'] ?>" <?= ($exam['exam_type_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                                                <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mt-2">
                                        <label>Result Release Date</label>
                                        <input type="date" name="result_release_date" class="form-control" value="<?= htmlspecialchars($exam['result_release_date'] ?? $exam['result_release_date'] ?? '') ?>">
                                    </div>
                                </div>

                                <hr>
                                <h5 class="mt-3">Subjects (subject-wise schedule & marks)</h5>
                                <div id="subjectsWrap"></div>
                                <div class="mt-2"><button class="btn btn-sm btn-secondary" type="button" id="addSubjectBtn">+ Add Subject</button></div>

                                <hr>
                                <div id="tutorialsWrap" class="mt-3" style="display:none">
                                    <h5>Link Tutorial Exams (for Term)</h5>
                                    <div id="tutorialsList"></div>
                                    <div class="mt-2"><strong>Combined Total Marks: </strong><span id="combinedTotal">0</span></div>
                                </div>

                                <div class="mt-3">
                                    <button class="btn btn-primary"><?= $edit_id ? 'Update Exam' : 'Create Exam' ?></button>
                                    <a class="btn btn-default" href="exam_list.php">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../admin/inc/footer.php'; ?>

</div><!-- /.wrapper -->

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
// Fallback for pushmenu if AdminLTE's data-widget binding misses
$(document).on('click','[data-widget="pushmenu"]',function(e){
    try { $('.sidebar-mini').length && $('body').hasClass('sidebar-collapse') ? $('body').removeClass('sidebar-collapse') : $('body').addClass('sidebar-collapse'); } catch(err) {}
});
// single declaration
let allSubjects = [];

// small helper to escape HTML for insertion
function escapeHtml(str){ return String(str).replace(/[&"'<>]/g, function(m){ return {'&':'&amp;','"':'&quot;',"'":'&#39;','<':'&lt;','>':'&gt;'}[m]; }); }

function makeRow(item={}){
    const sel = allSubjects.map(s=>`<option value="${s.id}" ${item.subject_id==s.id?'selected':''}>${s.name}</option>`).join('');
    return $(`
    <div class="subject-row row g-2 align-items-end mb-2">
        <div class="col-md-4"><label>Subject</label><select name="subject_id[]" class="form-control" required><option value="">--select--</option>${sel}</select></div>
        <div class="col-md-2"><label>Exam Date</label><input type="date" name="exam_date[]" class="form-control" value="${item.exam_date||''}"></div>
        <div class="col-md-2"><label>Exam Time</label><input type="time" name="exam_time[]" class="form-control" value="${item.exam_time||''}"></div>
        <div class="col-md-2"><label>Full Mark</label><input type="number" name="max_marks[]" class="form-control" value="${item.max_marks||100}"></div>
        <div class="col-md-2"><label>Pass Mark</label><input type="number" name="pass_marks[]" class="form-control" value="${item.pass_marks||33}"></div>
        <div class="col-12"><button type="button" class="btn btn-danger btn-sm removeRow">Remove</button></div>
    </div>`);
}

function loadSubjects(classId, cb){
    if (!classId) { allSubjects=[]; $('#subjectsWrap').empty(); return; }
    $.ajax({
        url: '../ajax/get_subjects_by_class.php',
        method: 'GET',
        data: {class_id: classId},
        dataType: 'json',
        success: function(res){ allSubjects = Array.isArray(res)?res:[]; if(cb) cb(); if(allSubjects.length===0) $('#subjectsWrap').append('<div class="alert alert-warning">এই শ্রেণির জন্য কোনো বিষয় পাওয়া যায়নি।</div>'); },
        error: function(){ allSubjects=[]; $('#subjectsWrap').empty(); $('#subjectsWrap').append('<div class="alert alert-danger">বিষয় লোড করতে সমস্যা হয়েছে!</div>'); }
    });
}

$(function(){
    $('#class_id').on('change', function(){ loadSubjects($(this).val(), function(){ /* no-op */ }); refreshTutorialsIfNeeded(); });
    $('#addSubjectBtn').on('click', function(){ if(allSubjects.length===0){ $('#subjectsWrap').append('<div class="alert alert-warning">বিষয় পাওয়া যায়নি।</div>'); return; } $('#subjectsWrap').append(makeRow()); });
    $(document).on('click', '.removeRow', function(){ $(this).closest('.subject-row').remove(); });

    // when exam type changes, show tutorial selection if it's a term type
    $('#exam_type_id').on('change', function(){ refreshTutorialsIfNeeded(); });

    function refreshTutorialsIfNeeded(){
        var sel = $('#exam_type_id option:selected');
        var tname = (sel.data('name') || sel.text() || '').toString().toLowerCase();
        var isTerm = tname.indexOf('term') !== -1 || tname.indexOf('সাম') !== -1 || tname.indexOf('semester') !== -1;
        if (!isTerm) { $('#tutorialsWrap').hide(); $('#tutorialsList').empty(); $('#combinedTotal').text('0'); return; }
    // need academic year and class
    var year = $('select[name="academic_year_id"]').val();
        var cid = $('#class_id').val();
    if (!year || !cid) { $('#tutorialsWrap').show(); $('#tutorialsList').html('<div class="alert alert-info">Academic year and Class are required to list tutorial exams.</div>'); return; }
        $('#tutorialsWrap').show();
        $('#tutorialsList').html('<div class="text-muted">Loading...</div>');
        $.ajax({ url: 'get_tutorial_exams_ajax.php', data: { year: year, class_id: cid }, dataType: 'json', success: function(rows){
            if (!Array.isArray(rows) || rows.length===0) { $('#tutorialsList').html('<div class="alert alert-warning">কোনো টিউটোরিয়াল পরীক্ষা পাওয়া যায়নি।</div>'); $('#combinedTotal').text('0'); return; }
            var html = '<div class="list-group">';
            rows.forEach(function(r){
                var checked = '';
                <?php if(!empty($selected_tutorials)): ?>
                var selIds = <?= json_encode($selected_tutorials) ?>;
                if (selIds.indexOf(String(r.id)) !== -1 || selIds.indexOf(Number(r.id)) !== -1) checked = 'checked';
                <?php endif; ?>
                html += '<label class="list-group-item"><input type="checkbox" name="tutorial_links[]" value="'+r.id+'" data-total="'+(r.total_max_marks||0)+'" '+checked+'> '+escapeHtml(r.name)+' <span class="badge bg-secondary float-end">'+(r.total_max_marks||0)+'</span></label>';
            });
            html += '</div>';
            $('#tutorialsList').html(html);
            updateCombinedTotal();
        }, error: function(){ $('#tutorialsList').html('<div class="alert alert-danger">টিউটোরিয়াল পরীক্ষা লোডে সমস্যা হয়েছে।</div>'); $('#combinedTotal').text('0'); } });
    }

    // helper to update combined total
    function updateCombinedTotal(){
        var sum = 0;
        $('#tutorialsList input[type=checkbox]:checked').each(function(){ sum += parseInt($(this).data('total')||0,10); });
        $('#combinedTotal').text(sum);
    }

    // update total when selecting tutorials
    $(document).on('change', '#tutorialsList input[type=checkbox]', function(){ updateCombinedTotal(); });

    // If edit mode, load subjects for the class and populate
    <?php if($edit_id && !empty($exam_subjects) && !empty($exam['class_id'])): ?>
        loadSubjects(<?= json_encode($exam['class_id']) ?>, function(){ const old = <?= json_encode($exam_subjects) ?>; old.forEach(s => $('#subjectsWrap').append(makeRow(s))); });
    <?php endif; ?>
    // Load tutorials block if we're editing or exam type is already set
    refreshTutorialsIfNeeded();
});
</script>
</body>
</html>