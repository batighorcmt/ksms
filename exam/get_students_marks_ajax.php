<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin/inc/enrollment_helpers.php';
header('Content-Type: application/json; charset=utf-8');
if (!isAuthenticated()) { echo json_encode(['error'=>'unauthenticated']); exit; }

$exam_id = intval($_GET['exam_id'] ?? 0);
$class_id = intval($_GET['class_id'] ?? 0);
$subject_id = intval($_GET['subject_id'] ?? 0);

if (!$exam_id || !$subject_id) { echo json_encode(['students'=>[]]); exit; }

try {
    // Resolve class_id and academic_year_id from exam to avoid mismatch
    $ex = $pdo->prepare("SELECT class_id, academic_year_id FROM exams WHERE id=? LIMIT 1");
    $ex->execute([$exam_id]);
    $examRow = $ex->fetch(PDO::FETCH_ASSOC);
    $resolvedClassId = intval($examRow['class_id'] ?? 0) ?: $class_id;
    $resolvedYearId = intval($examRow['academic_year_id'] ?? 0);
    if (!$resolvedClassId) { echo json_encode(['students'=>[]]); exit; }

    // students in class (year-aware via students_enrollment when available)
    if ($resolvedYearId && function_exists('enrollment_table_exists') && enrollment_table_exists($pdo)) {
        // Determine if we should restrict by student_subjects (elective handling)
        $hasStudentSubjects = false; $hasSsYear = false; $isElective = false;
        try {
            $chk = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_subjects'");
            $hasStudentSubjects = intval($chk->fetchColumn()) > 0;
            if ($hasStudentSubjects) {
                $col = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_subjects' AND COLUMN_NAME = 'academic_year_id'");
                $hasSsYear = intval($col->fetchColumn()) > 0;
                // Count rows for this subject in this class/year to decide elective mode
                if ($hasSsYear) {
                    $q = $pdo->prepare("SELECT COUNT(*) FROM student_subjects ss JOIN students_enrollment se ON se.student_id = ss.student_id AND se.academic_year_id = ? AND se.class_id = ? WHERE ss.subject_id = ? AND (ss.academic_year_id = ? OR ss.academic_year_id IS NULL)");
                    $q->execute([$resolvedYearId, $resolvedClassId, $subject_id, $resolvedYearId]);
                } else {
                    $q = $pdo->prepare("SELECT COUNT(*) FROM student_subjects ss JOIN students_enrollment se ON se.student_id = ss.student_id AND se.academic_year_id = ? AND se.class_id = ? WHERE ss.subject_id = ?");
                    $q->execute([$resolvedYearId, $resolvedClassId, $subject_id]);
                }
                $isElective = intval($q->fetchColumn()) > 0;
            }
        } catch (Exception $e) { /* ignore and treat as compulsory */ }

        if ($isElective) {
            // Restrict roster to only students who have this subject assigned (by year if column exists)
            if ($hasSsYear) {
                $sql = "SELECT s.id, s.first_name, s.last_name, se.roll_number AS roll
                        FROM students s
                        JOIN students_enrollment se ON se.student_id = s.id
                        JOIN student_subjects ss ON ss.student_id = s.id AND ss.subject_id = ? AND (ss.academic_year_id = ? OR ss.academic_year_id IS NULL)
                        WHERE se.academic_year_id = ? AND se.class_id = ?
                          AND (se.status='active' OR se.status IS NULL OR se.status='Active' OR se.status=1 OR se.status='1')
                        ORDER BY se.roll_number ASC, s.id ASC";
                $st = $pdo->prepare($sql);
                $st->execute([$subject_id, $resolvedYearId, $resolvedYearId, $resolvedClassId]);
            } else {
                $sql = "SELECT s.id, s.first_name, s.last_name, se.roll_number AS roll
                        FROM students s
                        JOIN students_enrollment se ON se.student_id = s.id
                        JOIN student_subjects ss ON ss.student_id = s.id AND ss.subject_id = ?
                        WHERE se.academic_year_id = ? AND se.class_id = ?
                          AND (se.status='active' OR se.status IS NULL OR se.status='Active' OR se.status=1 OR se.status='1')
                        ORDER BY se.roll_number ASC, s.id ASC";
                $st = $pdo->prepare($sql);
                $st->execute([$subject_id, $resolvedYearId, $resolvedClassId]);
            }
            $students = $st->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // No per-student restriction detected: include all enrolled students (treat as compulsory)
            $sql = "SELECT s.id, s.first_name, s.last_name, se.roll_number AS roll
                    FROM students s
                    JOIN students_enrollment se ON se.student_id = s.id
                    WHERE se.academic_year_id = ? AND se.class_id = ?
                      AND (se.status='active' OR se.status IS NULL OR se.status='Active' OR se.status=1 OR se.status='1')
                    ORDER BY se.roll_number ASC, s.id ASC";
            $st = $pdo->prepare($sql);
            $st->execute([$resolvedYearId, $resolvedClassId]);
            $students = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Legacy fallback without enrollment
        $baseSql = "SELECT id, first_name, last_name, roll_number AS roll FROM students WHERE class_id=? AND (status='active' OR status='Active' OR status=1 OR status='1' OR status IS NULL)";
        // If student_subjects exists (no year), we can still restrict roster to assigned students
        try {
            $chk = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_subjects'");
            $hasStudentSubjects = intval($chk->fetchColumn()) > 0;
        } catch (Exception $e) { $hasStudentSubjects = false; }
        if ($hasStudentSubjects) {
            $st = $pdo->prepare($baseSql . " AND id IN (SELECT student_id FROM student_subjects WHERE subject_id = ?) ORDER BY roll_number, id");
            $st->execute([$resolvedClassId, $subject_id]);
        } else {
            $st = $pdo->prepare($baseSql . " ORDER BY roll_number, id");
            $st->execute([$resolvedClassId]);
        }
        $students = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // existing marks for this exam/subject
    $mm = $pdo->prepare("SELECT student_id, marks_obtained FROM marks WHERE exam_id=? AND subject_id=?");
    $mm->execute([$exam_id, $subject_id]);
    $markMap = [];
    while ($r=$mm->fetch(PDO::FETCH_ASSOC)) { $markMap[$r['student_id']] = $r['marks_obtained']; }

    $out = ['students'=>[]];
    foreach ($students as $s) {
        $out['students'][] = [
            'id' => intval($s['id']),
            'roll' => $s['roll'],
            'name' => trim(($s['first_name']??'').' '.($s['last_name']??'')),
            'mark' => $markMap[$s['id']] ?? null,
        ];
    }
    echo json_encode($out);
} catch (Exception $e) {
    echo json_encode(['students'=>[], 'error'=>'exception']);
}
