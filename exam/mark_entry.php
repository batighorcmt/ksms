<?php
require_once __DIR__ . '/../config.php';
if (!isAuthenticated()) redirect('../login.php');
// Only teachers can access this page
if (!hasRole(['teacher'])) { redirect('403.php'); }

// Load filters
$classes = $pdo->query("SELECT id, name FROM classes WHERE status='active' ORDER BY numeric_value ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT id, year FROM academic_years WHERE status='active' ORDER BY year DESC")->fetchAll(PDO::FETCH_ASSOC);

$selected_year = intval($_GET['year'] ?? 0);
$selected_class = intval($_GET['class_id'] ?? 0);
$exam_id = intval($_GET['exam_id'] ?? 0);

$exams = [];
if ($selected_year && $selected_class) {
    $stmt = $pdo->prepare("SELECT e.*, et.name as exam_type_name FROM exams e LEFT JOIN exam_types et ON et.id = e.exam_type_id WHERE e.academic_year_id = ? AND e.class_id = ? ORDER BY e.created_at DESC");
    $stmt->execute([$selected_year, $selected_class]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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

$subjects_for_dropdown = [];
if ($exam_id) {
    $subjects_for_dropdown = getExamSubjectsOrdered($pdo, $exam_id, $selected_class ?: null);
}

// Filter subjects by teacher's routine assignments (only allow subjects assigned to this teacher for the selected class)
$teacher_id = intval($_SESSION['user_id'] ?? 0);
if ($teacher_id && $selected_class && !empty($subjects_for_dropdown)) {
  $rs = $pdo->prepare("SELECT DISTINCT subject_id FROM routines WHERE class_id = ? AND teacher_id = ?");
  $rs->execute([$selected_class, $teacher_id]);
  $allowed = $rs->fetchAll(PDO::FETCH_COLUMN, 0);
  if (!empty($allowed)) {
    $subjects_for_dropdown = array_values(array_filter($subjects_for_dropdown, function($row) use ($allowed){
      return in_array($row['subject_id'], array_map('intval', $allowed));
    }));
  } else {
    // no allowed subjects; empty the list
    $subjects_for_dropdown = [];
  }
}
?>
<!doctype html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>মার্ক এন্ট্রি</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <style>body { font-family:'SolaimanLipi','Source Sans Pro',sans-serif; }</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include '../admin/inc/header.php'; ?>
  <?php include '../teacher/inc/sidebar.php'; ?>

  <div class="content-wrapper">
    <div class="content">
      <div class="container mt-3">
        <div class="card">
          <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
              <div class="col-md-3">
                <label>Academic Year</label>
                <select name="year" class="form-control" onchange="this.form.submit()">
                  <option value="">-- select --</option>
                  <?php foreach($years as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= $selected_year==$y['id']? 'selected':'' ?>><?= htmlspecialchars($y['year']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label>Class</label>
                <select name="class_id" class="form-control" onchange="this.form.submit()">
                  <option value="">-- select --</option>
                  <?php foreach($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selected_class==$c['id']? 'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-5">
                <label>Exam</label>
                <select name="exam_id" class="form-control" onchange="this.form.submit()">
                  <option value="">-- select --</option>
                  <?php foreach($exams as $ex): ?>
                    <option value="<?= $ex['id'] ?>" <?= $exam_id==$ex['id']? 'selected':'' ?>><?= htmlspecialchars($ex['name'].' ('.$ex['exam_type_name'].')') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </form>

            <hr>
            <?php if (!$selected_class || !$exam_id): ?>
              <div class="alert alert-warning">মার্ক এন্ট্রি করতে উপরে থেকে শ্রেণি ও পরীক্ষা নির্বাচন করুন।</div>
            <?php else: ?>
              <?php if (empty($subjects_for_dropdown)): ?>
                <div class="alert alert-danger">আপনার রুটিনে এই শ্রেণির কোনো বিষয় বরাদ্দ নেই — তাই নম্বর প্রদান সম্ভব নয়।</div>
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
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php include '../admin/inc/footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
              '<td><input type="text" inputmode="decimal" pattern="[0-9০-৯.]*" class="form-control form-control-sm me-input" data-stu="'+st.id+'" data-max="'+maxMarks+'" value="'+ val +'" placeholder="0"></td>'+
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
          let s = String(raw);
          const map = { '০':'0','১':'1','২':'2','৩':'3','৪':'4','৫':'5','৬':'6','৭':'7','৮':'8','৯':'9' };
          // map Bangla digits to English; do not modify any other characters here
          s = s.replace(/[০-৯]/g, ch => map[ch] || ch);
          return s;
        }

        $holder.find('.me-input').on('input', function(){
          const $inp = $(this);
          const stuId = $inp.data('stu');
          const subjectId = Number($sub.val());
          const $status = $inp.closest('tr').find('.me-status');
          const mx = Number($inp.attr('max') || $inp.data('max') || 0) || 0;
          const typed = String($inp.val());
          const mapped = normalizeRawToEnNumber(typed).trim(); // only digit mapping

          // user truly cleared the field => delete
          if (typed.trim() === ''){
            $inp.removeClass('is-invalid');
            queueSave('');
            return;
          }

          // if mapping results in empty (all characters invalid), treat as invalid (don't delete/save)
          if (mapped === ''){
            $inp.addClass('is-invalid');
            $status.removeClass('text-success text-muted').addClass('text-danger').text('অকার্যকর নম্বর');
            clearTimeout(typingTimers[stuId]);
            return;
          }

          // only digits and a single '.' are allowed
          if (/[^0-9.]/.test(mapped)){
            $inp.addClass('is-invalid');
            $status.removeClass('text-success text-muted').addClass('text-danger').text('শুধু সংখ্যা ও "." ব্যবহারযোগ্য');
            clearTimeout(typingTimers[stuId]);
            return;
          }
          if ((mapped.match(/\./g) || []).length > 1){
            $inp.addClass('is-invalid');
            $status.removeClass('text-success text-muted').addClass('text-danger').text('একটির বেশি দশমিক চিহ্ন নয়');
            clearTimeout(typingTimers[stuId]);
            return;
          }

          const v = parseFloat(mapped);
          if (isNaN(v)) {
            $inp.addClass('is-invalid');
            $status.removeClass('text-success text-muted').addClass('text-danger').text('অকার্যকর নম্বর');
            clearTimeout(typingTimers[stuId]);
            return;
          }

          if (v < 0){
            $inp.addClass('is-invalid');
            $status.removeClass('text-success text-muted').addClass('text-danger').text('০-এর নিচে নয়');
            clearTimeout(typingTimers[stuId]);
            return; // don't save
          }
          if (mx > 0 && v > mx){
            $inp.addClass('is-invalid');
            $status.removeClass('text-success text-muted').addClass('text-danger').text('সর্বোচ্চ '+mx+'-এর বেশি — সেভ হবে না');
            clearTimeout(typingTimers[stuId]);
            return; // don't save
          }

          // valid value: remove warning and save (do not auto-replace input)
          $inp.removeClass('is-invalid');
          queueSave(v);

          function queueSave(val){
            $status.removeClass('text-success text-danger').addClass('text-muted').text('সেভ হচ্ছে…');
            clearTimeout(typingTimers[stuId]);
            typingTimers[stuId] = setTimeout(function(){
              saveMark(stuId, subjectId, val, function(ok,msg){
                if (ok){
                  $status.removeClass('text-muted text-danger').addClass('text-success').text('সেভ হয়েছে');
                  if (window && typeof window.updateAfterSave === 'function') {
                    window.updateAfterSave(stuId, subjectId, val);
                  }
                }
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
      if ($sub.find('option').length === 2 && !$sub.val()) {
        const v = $sub.find('option').eq(1).attr('value');
        $sub.val(v).trigger('change');
      }
    });
  }
  startWhenReady();
})();
</script>
</body>
</html>
