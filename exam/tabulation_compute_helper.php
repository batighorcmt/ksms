<?php
// compute_tabulation_for_student(PDO $pdo, int $exam_id, int $student_id)
function compute_tabulation_for_student($pdo, $exam_id, $student_id) {
    // fetch exam subjects
    $es = $pdo->prepare("SELECT id, subject_id, full_mark, pass_mark FROM exam_subjects WHERE exam_id=?");
    $es->execute([$exam_id]);
    $subjects = $es->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    $subjects_passed = 0;
    $subjects_failed = 0;

    foreach($subjects as $s) {
        $mstm = $pdo->prepare("SELECT obtained_marks FROM marks WHERE exam_subject_id=? AND student_id=? LIMIT 1");
        $mstm->execute([$s['id'], $student_id]);
        $markRow = $mstm->fetch();
        $obt = $markRow ? floatval($markRow['obtained_marks']) : 0;
        $total += $obt;
        if($obt >= $s['pass_mark']) $subjects_passed++;
        else $subjects_failed++;
    }

    $result_status = ($subjects_failed==0) ? 'pass' : 'fail';

    // upsert into tabulation_cache
    $u = $pdo->prepare("SELECT id FROM tabulation_cache WHERE exam_id=? AND student_id=?");
    $u->execute([$exam_id, $student_id]);
    $r = $u->fetch();
    if($r) {
        $pdo->prepare("UPDATE tabulation_cache SET total_marks=?, subjects_passed=?, subjects_failed=?, result_status=?, computed_at=NOW() WHERE id=?")
            ->execute([$total, $subjects_passed, $subjects_failed, $result_status, $r['id']]);
    } else {
        $pdo->prepare("INSERT INTO tabulation_cache (exam_id, student_id, total_marks, subjects_passed, subjects_failed, result_status) VALUES (?,?,?,?,?,?)")
            ->execute([$exam_id, $student_id, $total, $subjects_passed, $subjects_failed, $result_status]);
    }

    // after updating this student, recompute positions for the exam:
    recompute_positions_for_exam($pdo, $exam_id);
}

function recompute_positions_for_exam($pdo, $exam_id) {
    // fetch all students' totals and sort by: subjects_failed asc, total_marks desc
    $rows = $pdo->prepare("SELECT * FROM tabulation_cache WHERE exam_id=? ORDER BY subjects_failed ASC, total_marks DESC");
    $rows->execute([$exam_id]);
    $list = $rows->fetchAll(PDO::FETCH_ASSOC);
    $pos = 0;
    $last_total = null;
    $last_failed = null;
    foreach($list as $i => $r) {
        // positions: if same failed count and same total -> same position (tie)
        if($i==0) {
            $pos = 1;
            $last_total = $r['total_marks'];
            $last_failed = $r['subjects_failed'];
            $pdo->prepare("UPDATE tabulation_cache SET position=? WHERE id=?")->execute([$pos, $r['id']]);
        } else {
            if($r['subjects_failed']==$last_failed && floatval($r['total_marks'])==floatval($last_total)) {
                // same position
                $pdo->prepare("UPDATE tabulation_cache SET position=? WHERE id=?")->execute([$pos, $r['id']]);
            } else {
                $pos = $i+1;
                $last_failed = $r['subjects_failed'];
                $last_total = $r['total_marks'];
                $pdo->prepare("UPDATE tabulation_cache SET position=? WHERE id=?")->execute([$pos, $r['id']]);
            }
        }
    }
}
