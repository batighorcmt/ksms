<?php
require_once __DIR__ . '/config.php';
if (!isAuthenticated()) redirect(ADMIN_URL . 'dashboard.php');

// Inputs
$exam_id = intval($_GET['exam_id'] ?? 0);
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$student_ids_param = trim($_GET['student_ids'] ?? ''); // comma separated ids

if (!$exam_id) {
    echo '<div style="font-family: sans-serif; padding:20px; color:#b91c1c;">exam_id parameter is required.</div>';
    exit;
}

// School info
$school = $pdo->query("SELECT * FROM school_info LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$school_name = $school['name'] ?? 'বিদ্যালয় নাম';
$school_address = $school['address'] ?? '';
$school_phone = $school['phone'] ?? '';
$school_logo = !empty($school['logo']) ? (BASE_URL . 'uploads/logo/' . $school['logo']) : '';

// Exam info
$exStmt = $pdo->prepare("SELECT e.*, c.name AS class_name, c.id AS class_id FROM exams e LEFT JOIN classes c ON e.class_id = c.id WHERE e.id = ?");
$exStmt->execute([$exam_id]);
$exam = $exStmt->fetch(PDO::FETCH_ASSOC);
if (!$exam) { echo '<div style="font-family: sans-serif; padding:20px; color:#b91c1c;">Exam not found.</div>'; exit; }
$exam_name = $exam['name'] ?? 'পরীক্ষা';
$exam_class_id = intval($exam['class_id'] ?? 0);

// Exam schedule (subjects with date/time)
// Order by date asc, then by class subject order if available
$scheduleSql = "SELECT es.subject_id, s.name AS subject_name, es.exam_date, es.exam_time
                FROM exam_subjects es
                JOIN subjects s ON s.id = es.subject_id
                LEFT JOIN class_subjects cs ON cs.subject_id = es.subject_id AND cs.class_id = :cid
                WHERE es.exam_id = :eid
                ORDER BY es.exam_date IS NULL, es.exam_date ASC, cs.numeric_value ASC, s.name ASC";
$sched = $pdo->prepare($scheduleSql);
$sched->execute([':eid' => $exam_id, ':cid' => $exam_class_id]);
$schedule = $sched->fetchAll(PDO::FETCH_ASSOC);

// Students list
$students = [];
if ($student_ids_param !== '') {
    $ids = array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', $student_ids_param))));
    if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT s.*, c.name AS class_name, sec.name AS section_name
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id
                LEFT JOIN sections sec ON sec.id = s.section_id
                WHERE s.id IN ($in) AND s.status='active'
                ORDER BY s.roll_number ASC, s.id ASC";
        $st = $pdo->prepare($sql);
        $st->execute($ids);
        $students = $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
if (empty($students)) {
    // fallback: all students in exam class (optionally filter by section)
    $params = [$exam_class_id];
    $where = "s.class_id = ? AND s.status='active'";
    if ($section_id) { $where .= " AND s.section_id = ?"; $params[] = $section_id; }
    $sql = "SELECT s.*, c.name AS class_name, sec.name AS section_name
            FROM students s
            LEFT JOIN classes c ON c.id = s.class_id
            LEFT JOIN sections sec ON sec.id = s.section_id
            WHERE $where
            ORDER BY s.roll_number ASC, s.id ASC";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $students = $st->fetchAll(PDO::FETCH_ASSOC);
}

// Helper: format date/time
function fmt_date($d) { return $d ? date('d-m-Y', strtotime($d)) : '-'; }
function fmt_time($t) { return $t ? date('H:i', strtotime($t)) : '-'; }

?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>প্রবেশপত্র (Admit Card)</title>
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary:#1e5799; --accent:#ff9800; --muted:#6b7280; --border:#e5e7eb; }
    * { box-sizing: border-box; }
    body { margin:0; background:#f3f4f6; color:#111827; font-family:'Noto Sans Bengali','Hind Siliguri',system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
    .actions { text-align:center; margin:16px 0 24px; }
    .print-btn { background:var(--primary); color:#fff; border:none; border-radius:6px; padding:10px 18px; cursor:pointer; font-size:14px; }
    .print-btn:hover { background:#154274; }
    .sheet { width:210mm; margin:0 auto; padding:10mm 10mm 16mm; }
    .admit-card { background:#fff; border:1px solid var(--border); border-radius:8px; padding:8mm; margin-bottom:6mm; box-shadow:0 2px 6px rgba(0,0,0,0.04); min-height:135mm; display:flex; flex-direction:column; justify-content:space-between; page-break-inside:avoid; }
    .card-head { display:grid; grid-template-columns:28mm 1fr 30mm; align-items:center; gap:8mm; margin-bottom:4mm; }
    .logo-box { width:28mm; height:28mm; border:1px solid var(--border); border-radius:6px; display:flex; align-items:center; justify-content:center; overflow:hidden; background:#fff; }
    .logo-box img { max-width:100%; max-height:100%; }
    .school-info { text-align:center; }
    .school-name { font-weight:700; font-size:18pt; line-height:1.2; color:var(--primary); }
    .school-meta { font-size:10pt; color:var(--muted); margin-top:2mm; }
    .photo-box { width:30mm; height:36mm; border:1px dashed var(--border); border-radius:4px; overflow:hidden; display:flex; align-items:center; justify-content:center; font-size:9pt; color:#9ca3af; background:#fafafa; }
    .photo-box img { width:100%; height:100%; object-fit:cover; }
    .exam-title { text-align:center; font-weight:700; font-size:14pt; color:#0f172a; padding:3mm 0; margin-bottom:2mm; border-top:2px solid var(--border); border-bottom:2px solid var(--border); }
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:6mm; }
    .section { border:1px solid var(--border); border-radius:6px; padding:4mm; }
    .section-title { font-weight:700; color:var(--primary); margin-bottom:3mm; font-size:11pt; }
    .info-list { display:grid; grid-template-columns:1fr 1fr; gap:2mm 6mm; font-size:10pt; }
    .info-item { display:flex; gap:3mm; }
    .label { color:#374151; font-weight:600; min-width:28mm; }
    .value { color:#111827; }
    table { border-collapse:collapse; width:100%; font-size:10pt; }
    th,td { border-bottom:1px solid var(--border); padding:6px 8px; text-align:left; }
    th { background:#f9fafb; color:#111827; font-weight:700; }
    .instructions { font-size:10pt; }
    .instructions ul { margin:2mm 0 0 5mm; }
    .sign-row { display:flex; justify-content:space-between; gap:10mm; margin-top:6mm; }
    .sign-col { flex:1; text-align:center; }
    .sign-line { margin:12mm 0 2mm; border-top:1px solid #4b5563; }
    .sign-label { font-weight:600; color:#111827; }
    .branding { text-align:center; font-size:9pt; color:#6b7280; margin-top:4mm; }
    .cut-line { margin:2mm 0 6mm; position:relative; text-align:center; color:#9ca3af; font-weight:700; }
    .cut-line:before { content:""; position:absolute; top:50%; left:0; right:0; border-top:1px dashed #9ca3af; }
    .cut-line span { background:#f3f4f6; padding:0 6px; position:relative; }
    @media print { @page { size:A4 portrait; margin:10mm; } body { background:#fff; } .actions{ display:none; } .sheet{ padding:0; } .cut-line span{ background:#fff; } .admit-card{ box-shadow:none; } }
    @media (max-width: 900px) { .sheet{ width:100%; padding:10px; } .card-head{ grid-template-columns:24mm 1fr 28mm; gap:10px; } .grid-2{ grid-template-columns:1fr; } }
  </style>
</head>
<body>
  <div class="actions"><button class="print-btn" onclick="window.print()">প্রিন্ট করুন</button></div>
  <div class="sheet">
    <?php
      // chunk students into pairs to place cut-line between two cards per page height
      $count = count($students);
      foreach ($students as $idx => $stu):
        $full_name = trim(($stu['first_name'] ?? '') . ' ' . ($stu['last_name'] ?? ''));
        $father = $stu['father_name'] ?? '';
        $mother = $stu['mother_name'] ?? '';
        $class_name = $stu['class_name'] ?? ($exam['class_name'] ?? '');
        $section_name = $stu['section_name'] ?? '';
        $roll = $stu['roll_number'] ?? '';
        $sid = $stu['id'];
        $photo = $stu['photo'] ?? '';
        $photoUrl = $photo ? (BASE_URL . 'uploads/students/' . $photo) : '';
    ?>
    <div class="admit-card">
      <div>
        <div class="card-head">
          <div class="logo-box">
            <?php if ($school_logo): ?>
              <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo" onerror="this.remove()">
            <?php else: ?>
              <span style="font-size:10pt;color:#9ca3af;">লোগো</span>
            <?php endif; ?>
          </div>
          <div class="school-info">
            <div class="school-name"><?= htmlspecialchars($school_name) ?></div>
            <div class="school-meta"><?= htmlspecialchars($school_address) ?><?= $school_phone? ' | মোবাইল: '.htmlspecialchars($school_phone):'' ?></div>
          </div>
          <div class="photo-box">
            <?php if ($photoUrl): ?>
              <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Photo" onerror="this.parentNode.textContent='শিক্ষার্থীর ছবি';this.remove();">
            <?php else: ?>শিক্ষার্থীর ছবি<?php endif; ?>
          </div>
        </div>
        <div class="exam-title"><?= htmlspecialchars($exam_name) ?> - প্রবেশপত্র</div>

        <div class="grid-2">
          <div class="section">
            <div class="section-title">শিক্ষার্থীর তথ্য</div>
            <div class="info-list">
              <div class="info-item"><div class="label">নাম</div><div class="value"><?= htmlspecialchars($full_name) ?></div></div>
              <div class="info-item"><div class="label">রোল</div><div class="value"><?= htmlspecialchars((string)$roll) ?></div></div>
              <div class="info-item"><div class="label">পিতার নাম</div><div class="value"><?= htmlspecialchars($father) ?></div></div>
              <div class="info-item"><div class="label">মাতার নাম</div><div class="value"><?= htmlspecialchars($mother) ?></div></div>
              <div class="info-item"><div class="label">শ্রেণি</div><div class="value"><?= htmlspecialchars($class_name . ($section_name? (' - '.$section_name) : '')) ?></div></div>
              <div class="info-item"><div class="label">আইডি</div><div class="value"><?= htmlspecialchars((string)$sid) ?></div></div>
            </div>
          </div>
          <div class="section">
            <div class="section-title">পরীক্ষার সময়সূচী</div>
            <table>
              <thead><tr><th>তারিখ</th><th>বিষয়</th><th>সময়</th></tr></thead>
              <tbody>
                <?php if (empty($schedule)): ?>
                  <tr><td colspan="3">কোনো সময়সূচী নির্ধারিত নেই</td></tr>
                <?php else: foreach ($schedule as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars(fmt_date($row['exam_date'] ?? null)) ?></td>
                    <td><?= htmlspecialchars($row['subject_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars(fmt_time($row['exam_time'] ?? null)) ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="section instructions" style="margin-top: 4mm;">
          <div class="section-title">পরীক্ষার নির্দেশাবলী</div>
          <ul>
            <li>পরীক্ষা শুরুর ৩০ মিনিট আগে পরীক্ষা কক্ষে উপস্থিত হতে হবে।</li>
            <li>প্রবেশপত্র ছাড়া পরীক্ষা কেন্দ্রে প্রবেশ করা যাবে না।</li>
            <li>পরীক্ষার সময় মোবাইল ফোন বা ইলেকট্রনিক ডিভাইস ব্যবহার করা যাবে না।</li>
            <li>পরীক্ষার হলে নকল করা সম্পূর্ণ নিষিদ্ধ।</li>
            <li>প্রশ্নপত্র ও উত্তরপত্রে নাম, রোল নং ইত্যাদি সঠিকভাবে লিখতে হবে।</li>
          </ul>
        </div>
      </div>

      <div>
        <div class="sign-row">
          <div class="sign-col">
            <div class="sign-line"></div>
            <div class="sign-label">শ্রেণি শিক্ষক</div>
          </div>
          <div class="sign-col">
            <div class="sign-line"></div>
            <div class="sign-label">প্রধান শিক্ষক</div>
          </div>
        </div>
        <div class="branding">কারিগরি সহযোগীতায়ঃ <strong>বাতিঘর কম্পিউটার’স</strong> | মোবাইলঃ 01762396713</div>
      </div>
    </div>
    <?php if (($idx % 2) == 0 && ($idx + 1) < $count): ?>
      <div class="cut-line"><span>--- কাটার দাগ ---</span></div>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
</body>
</html>
