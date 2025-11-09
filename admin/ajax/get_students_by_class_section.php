<?php
require_once '../../config.php';
require_once __DIR__ . '/../inc/enrollment_helpers.php';
header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$section_id = isset($_GET['section_id']) && $_GET['section_id'] !== '' ? (int)$_GET['section_id'] : null;
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$limit = isset($_GET['limit']) ? max(10, min(500, (int)$_GET['limit'])) : 300;

try {
    // Detect active column on students (legacy) and on enrollment (preferred)
    $studentActiveCol = null; // s.status='active' OR s.is_active=1 OR s.active=1
    $hasStudentIdCol = false; // s.student_id exists?
    try {
        $studentCols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('status', $studentCols, true)) $studentActiveCol = 'status';
        elseif (in_array('is_active', $studentCols, true)) $studentActiveCol = 'is_active';
        elseif (in_array('active', $studentCols, true)) $studentActiveCol = 'active';
        $hasStudentIdCol = in_array('student_id', $studentCols, true);
    } catch (Exception $ie) { /* ignore */ }

    // Prefer students_enrollment if available to be year-aware and to honor active enrollments
    $useEnroll = function_exists('enrollment_table_exists') && enrollment_table_exists($pdo);
    $enrollActiveFilter = '';
    $yearFilterSql = '';
    $yearParams = [];
    if ($useEnroll) {
        try {
            $enrollCols = $pdo->query("SHOW COLUMNS FROM students_enrollment")->fetchAll(PDO::FETCH_COLUMN);
            // Active enrollment column preference: status='active' else boolean flags
            if (in_array('status', $enrollCols, true)) {
                $enrollActiveFilter = "se.status = 'active'";
            } elseif (in_array('is_active', $enrollCols, true)) {
                $enrollActiveFilter = 'se.is_active = 1';
            } elseif (in_array('active', $enrollCols, true)) {
                $enrollActiveFilter = 'se.active = 1';
            }
            // Academic year filter when column exists and current year known
            if (in_array('academic_year_id', $enrollCols, true)) {
                $yearId = current_academic_year_id($pdo);
                if ($yearId) { $yearFilterSql = 'se.academic_year_id = ?'; $yearParams[] = (int)$yearId; }
            }
        } catch (Exception $e) { /* ignore */ }
    }
    $params = [];
    if ($useEnroll) {
        $sql = "SELECT s.id, s.first_name, s.last_name, s.mobile_number, se.roll_number, c.name AS class_name, sec.name AS section_name
                FROM students s
                JOIN students_enrollment se ON se.student_id = s.id
                LEFT JOIN classes c ON c.id = se.class_id
                LEFT JOIN sections sec ON sec.id = se.section_id";
        $w = [];
        if ($class_id) { $w[] = 'se.class_id = ?'; $params[] = $class_id; }
        if ($section_id) { $w[] = 'se.section_id = ?'; $params[] = $section_id; }
        // Apply active enrollment filter if detected
        if ($enrollActiveFilter !== '') { $w[] = $enrollActiveFilter; }
        // Apply current academic year filter if available
        if ($yearFilterSql !== '') { $w[] = $yearFilterSql; $params = array_merge($params, $yearParams); }
        if ($q !== '') {
            $w[] = '(
                s.first_name LIKE ? OR s.last_name LIKE ? OR
                ' . ($hasStudentIdCol ? 's.student_id LIKE ? OR' : '') . '
                CAST(s.id AS CHAR) LIKE ? OR s.mobile_number LIKE ?
            )';
            // Bindings for first_name, last_name, [student_id], id, mobile
            $params[] = "%$q%"; $params[] = "%$q%";
            if ($hasStudentIdCol) { $params[] = "%$q%"; }
            $params[] = "%$q%"; $params[] = "%$q%";
        }
        if ($w) { $sql .= ' WHERE ' . implode(' AND ', $w); }
        $sql .= ' ORDER BY se.roll_number ASC, s.first_name ASC LIMIT ' . (int)$limit;
    } else {
        $sql = "SELECT s.id, s.first_name, s.last_name, s.mobile_number, s.class_id, s.section_id, c.name AS class_name, sec.name AS section_name
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id
                LEFT JOIN sections sec ON sec.id = s.section_id";
        $w = [];
        if ($class_id) { $w[] = 's.class_id = ?'; $params[] = $class_id; }
        if ($section_id) { $w[] = 's.section_id = ?'; $params[] = $section_id; }
        if ($studentActiveCol === 'status') { $w[] = 's.status = \"active\"'; }
        elseif ($studentActiveCol === 'is_active') { $w[] = 's.is_active = 1'; }
        elseif ($studentActiveCol === 'active') { $w[] = 's.active = 1'; }
        if ($q !== '') {
            $w[] = '(
                s.first_name LIKE ? OR s.last_name LIKE ? OR
                ' . ($hasStudentIdCol ? 's.student_id LIKE ? OR' : '') . '
                CAST(s.id AS CHAR) LIKE ? OR s.mobile_number LIKE ?
            )';
            $params[] = "%$q%"; $params[] = "%$q%";
            if ($hasStudentIdCol) { $params[] = "%$q%"; }
            $params[] = "%$q%"; $params[] = "%$q%";
        }
        if ($w) { $sql .= ' WHERE ' . implode(' AND ', $w); }
        $sql .= ' ORDER BY s.first_name ASC LIMIT ' . (int)$limit;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];
    foreach ($rows as $r) {
        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $out[] = [
            'id' => (int)$r['id'],
            'name' => $name !== '' ? $name : ('ID ' . (int)$r['id']),
            'mobile' => (string)($r['mobile_number'] ?? ''),
            'class_name' => isset($r['class_name']) ? (string)$r['class_name'] : null,
            'section_name' => isset($r['section_name']) ? (string)$r['section_name'] : null,
            'roll_number' => isset($r['roll_number']) ? (string)$r['roll_number'] : null,
        ];
    }
    echo json_encode(['students' => $out]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
