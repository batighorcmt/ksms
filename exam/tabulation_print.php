<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) redirect('../login.php');

$exam_id = intval($_GET['exam_id'] ?? 0);
if(!$exam_id) { echo "Invalid"; exit; }

$exam_stmt = $pdo->prepare("SELECT e.*, c.name class_name, t.name type_name FROM exams e JOIN classes c ON e.class_id=c.id JOIN exam_types t ON e.exam_type_id=t.id WHERE e.id=?");
$exam_stmt->execute([$exam_id]);
$exam = $exam_stmt->fetch();
if(!$exam) { echo "Exam not found"; exit; }

$subjects_stmt = $pdo->prepare("\n    SELECT es.*, sub.name subject_name, es.full_mark, es.pass_mark\n    FROM exam_subjects es\n    JOIN subjects sub ON es.subject_id=sub.id\n    JOIN class_subjects cs ON cs.subject_id = es.subject_id AND cs.class_id = ?\n    WHERE es.exam_id=?\n    ORDER BY cs.numeric_value ASC, cs.id ASC\n");
$subjects_stmt->execute([$exam['class_id'], $exam_id]);
$subjects = $subjects_stmt->fetchAll();

$students_stmt = $pdo->prepare("SELECT * FROM students WHERE class_id=? AND status='active' ORDER BY roll_number ASC");
$students_stmt->execute([$exam['class_id']]);
$students = $students_stmt->fetchAll();

$tabulation = [];
foreach ($students as $stu) {
    $total = 0; $subjects_passed = 0; $subjects_failed = 0; $marks = []; $all_passed = true;
    foreach ($subjects as $s) {
        $m = $pdo->prepare("SELECT obtained_marks FROM marks WHERE exam_subject_id=? AND student_id=?");
        $m->execute([$s['id'], $stu['id']]);
        $mr = $m->fetch();
        $obt = $mr ? (float)$mr['obtained_marks'] : 0.0;
        $marks[] = number_format($obt,2,'.','');
        $total += $obt;
        if ($obt >= (float)$s['pass_mark']) { $subjects_passed++; } else { $subjects_failed++; $all_passed = false; }
    }
    $tabulation[] = [
        'roll_number' => $stu['roll_number'],
        'first_name' => $stu['first_name'],
        'last_name' => $stu['last_name'],
        'marks' => $marks,
        'total_marks' => $total,
        'subjects_passed' => $subjects_passed,
        'subjects_failed' => $subjects_failed,
        'result_status' => $all_passed ? 'পাশ' : 'ফেল',
    ];
}
usort($tabulation, function($a,$b){ return $b['total_marks'] <=> $a['total_marks']; });
foreach($tabulation as $i=>&$r){ $r['position']=$i+1; } unset($r);

function bn($number){
    $en=['0','1','2','3','4','5','6','7','8','9'];
    $bn=['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
    return str_replace($en,$bn,$number);
}
 $school_info = $pdo->query("SELECT * FROM school_info LIMIT 1")->fetch();
 $inst_name = $school_info['name'] ?? 'আপনার প্রতিষ্ঠান';
 $inst_address = $school_info['address'] ?? 'ঠিকানা';
 $inst_contact='';
 if(!empty($school_info['phone'])) $inst_contact .='ফোন: '.$school_info['phone'];
 if(!empty($school_info['email'])) $inst_contact .= ($inst_contact?' | ':'').'ইমেল: '.$school_info['email'];
 $inst_logo = !empty($school_info['logo']) ? (BASE_URL.'uploads/logo/'.$school_info['logo']) : '';
 $class_section='শ্রেণি: '.htmlspecialchars($exam['class_name']);
 $exam_title = htmlspecialchars($exam['name']);
 $exam_type = htmlspecialchars($exam['type_name'] ?? '');
 $exam_desc_line = trim(($exam_type?($exam_type.' - '):'').$exam_title);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tabulation - <?= $exam_title ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        body { font-family: SolaimanLipi, Arial, sans-serif; background:#fff; }
        .header-area { text-align:center; margin-bottom:15px; }
        .inst-logo { max-height:80px; }
        .meta-line { font-size:14px; }
        .print-btn { position:fixed; top:15px; right:15px; z-index:999; }
        table.table { font-size:13px; }
        thead th { text-align:center; background:#f4f6f9; }
        tbody td { text-align:center; }
        .tot-col { font-weight:600; background:#fafafa; }
        @media print { .print-btn { display:none!important; } }
    </style>
</head>
<body>
<button class="btn btn-primary print-btn" onclick="window.print()">প্রিন্ট</button>
<div class="p-3">
    <div class="header-area">
        <?php if($inst_logo): ?><img src="<?= htmlspecialchars($inst_logo) ?>" class="inst-logo mb-2" alt="Logo"><?php endif; ?>
        <h3 style="margin:0;"><?= htmlspecialchars($inst_name) ?></h3>
        <div><?= htmlspecialchars($inst_address) ?></div>
        <?php if($inst_contact): ?><div class="meta-line"><?= htmlspecialchars($inst_contact) ?></div><?php endif; ?>
        <hr style="margin:8px 0 12px;">
        <h5 style="margin:0;">ট্যাবুলেশন শীট</h5>
        <div class="meta-line">পরীক্ষা: <?= $exam_desc_line ?> | <?= $class_section ?> | তারিখ: <?= date('d-m-Y') ?></div>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead>
            <tr>
                <th>মেধাক্রম</th>
                <th>রোল নং</th>
                <th>নাম</th>
                <?php foreach($subjects as $s): ?><th><?= htmlspecialchars($s['subject_name']) ?></th><?php endforeach; ?>
                <th>মোট</th>
                <th>পাস</th>
                <th>ফেল</th>
                <th>ফলাফল</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($tabulation as $row): ?>
                <tr>
                    <td><?= bn($row['position']); ?></td>
                    <td><?= bn($row['roll_number']); ?></td>
                    <td style="text-align:left;"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                    <?php foreach($row['marks'] as $obt): ?>
                        <td><?= bn(number_format((float)$obt,2)) ?></td>
                    <?php endforeach; ?>
                    <td class="tot-col"><?= bn(number_format($row['total_marks'],2)) ?></td>
                    <td><?= bn($row['subjects_passed']) ?></td>
                    <td><?= bn($row['subjects_failed']) ?></td>
                    <td><?= strtoupper($row['result_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
