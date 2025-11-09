<?php
require_once __DIR__ . '/../../config.php';
@header('Content-Type: application/json; charset=utf-8');

// Optional: include enrollment helpers for future use
@include_once __DIR__ . '/../inc/enrollment_helpers.php';

// Basic auth check (super_admin page context assumed, but keep it light)
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$limit = isset($_GET['limit']) ? max(1, min(10000, (int)$_GET['limit'])) : 5000;

try {
    $useEnroll = function_exists('enrollment_table_exists') && enrollment_table_exists($pdo);
    // Detect active column
    $activeCol = null;
    try {
        $studentCols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('status', $studentCols, true)) $activeCol = 'status';
        elseif (in_array('is_active', $studentCols, true)) $activeCol = 'is_active';
        elseif (in_array('active', $studentCols, true)) $activeCol = 'active';
    } catch (Exception $ie) {}
    if ($useEnroll) {
        $sql = "SELECT s.id,
                       CONCAT(COALESCE(s.first_name,''),' ',COALESCE(s.last_name,'')) AS name,
                       s.mobile_number AS mobile,
                       se.roll_number,
                       c.name AS class_name,
                       sec.name AS section_name
                FROM students s
                JOIN students_enrollment se ON se.student_id = s.id
                LEFT JOIN classes c ON c.id = se.class_id
                LEFT JOIN sections sec ON sec.id = se.section_id";
        $w = [];
        if ($activeCol === 'status') { $w[] = "s.status='active'"; }
        elseif ($activeCol === 'is_active') { $w[] = 's.is_active=1'; }
        elseif ($activeCol === 'active') { $w[] = 's.active=1'; }
        if ($w) { $sql .= ' WHERE ' . implode(' AND ', $w); }
        $sql .= " ORDER BY se.roll_number ASC, s.id DESC LIMIT :lim";
    } else {
        $sql = "SELECT s.id,
                       CONCAT(COALESCE(s.first_name,''),' ',COALESCE(s.last_name,'')) AS name,
                       s.mobile_number AS mobile,
                       NULL AS roll_number,
                       c.name AS class_name,
                       sec.name AS section_name
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id
                LEFT JOIN sections sec ON sec.id = s.section_id";
        $w = [];
        if ($activeCol === 'status') { $w[] = "s.status='active'"; }
        elseif ($activeCol === 'is_active') { $w[] = 's.is_active=1'; }
        elseif ($activeCol === 'active') { $w[] = 's.active=1'; }
        if ($w) { $sql .= ' WHERE ' . implode(' AND ', $w); }
        $sql .= " ORDER BY s.id DESC LIMIT :lim";
    }
    $st = $pdo->prepare($sql);
    $st->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['students' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
}
?>