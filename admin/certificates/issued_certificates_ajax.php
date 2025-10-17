<?php
require_once '../../config.php';
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    http_response_code(401); echo 'Unauthorized'; exit;
}

$q_student = trim($_GET['student'] ?? '');
$q_class = intval($_GET['class_id'] ?? 0);
$q_type = trim($_GET['certificate_type'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$where = [];
$params = [];
if ($q_student !== '') { $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.roll_number LIKE ?)'; $like = "%$q_student%"; $params = array_merge($params, [$like,$like,$like]); }
if ($q_class) { $where[] = 's.class_id = ?'; $params[] = $q_class; }
if ($q_type !== '') { $where[] = 'ci.certificate_type = ?'; $params[] = $q_type; }
if ($from) { $where[] = 'ci.issued_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to) { $where[] = 'ci.issued_at <= ?'; $params[] = $to . ' 23:59:59'; }

// pagination parameters
 $page = max(1, intval($_GET['page'] ?? 1));
 $per_page = max(1, intval($_GET['per_page'] ?? 25));

 // build base SQL fragments for FROM/JOIN and WHERE
 $fromFragment = "FROM certificate_issues ci
     LEFT JOIN students s ON s.id = ci.student_id
     LEFT JOIN classes c ON c.id = s.class_id
     LEFT JOIN users u ON u.id = ci.issued_by";
 $whereFragment = '';
 if (!empty($where)) {
     $whereFragment = ' WHERE ' . implode(' AND ', $where);
 }

 // total count
 $countSql = "SELECT COUNT(*) as cnt " . $fromFragment . $whereFragment;
 $countStmt = $pdo->prepare($countSql);
 $countStmt->execute($params);
 $totalRow = $countStmt->fetch(PDO::FETCH_ASSOC);
 $total = $totalRow ? (int)$totalRow['cnt'] : 0;

 // fetch page rows
 $offset = ($page - 1) * $per_page;
 $sql = "SELECT ci.*, s.first_name, s.last_name, s.roll_number, s.id as student_id, c.name as class_name, u.full_name as issued_by_name " . $fromFragment . $whereFragment . " ORDER BY ci.issued_at DESC LIMIT ? OFFSET ?";
 $execParams = $params;
 $execParams[] = $per_page;
 $execParams[] = $offset;
 $stmt = $pdo->prepare($sql);
 $stmt->execute($execParams);
 $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

 $sl = $offset;
 $html = '';
 foreach ($rows as $r) {
    $sl++;
    $id = (int)$r['id'];
    $studentName = htmlspecialchars(trim($r['first_name'].' '.$r['last_name']));
    $roll = htmlspecialchars($r['roll_number'] ?? '');
    $class = htmlspecialchars($r['class_name'] ?? '');
    $type = htmlspecialchars($r['certificate_type'] ?? '');
    $issuedBy = htmlspecialchars($r['issued_by_name'] ?? '');
    $issuedAt = htmlspecialchars($r['issued_at'] ?? '');
    $notes = htmlspecialchars($r['notes'] ?? '');
    $certNo = htmlspecialchars($r['certificate_number'] ?? '');
    $student_id = (int)($r['student_id'] ?? 0);

    $html .= '<tr>';
    $html .= '<td>'. $sl .'</td>';
    $html .= '<td>'. $id .'</td>';
    $html .= '<td>'. $studentName .'</td>';
    $html .= '<td>'. $roll .'</td>';
    $html .= '<td>'. $class .'</td>';
    $html .= '<td>'. $type .'</td>';
    $html .= '<td>'. $issuedBy .'</td>';
    $html .= '<td>'. $issuedAt .'</td>';
    $html .= '<td>'. $certNo .'</td>';
    $html .= '<td>'. $notes .'</td>';
    // actions: view and delete
    $viewUrl = 'running_student_certificate.php?id=' . $student_id . '&certificate_number=' . rawurlencode($r['certificate_number'] ?? '');
    // action buttons in a small group so they appear on one line and same visual size
    $html .= '<td class="text-center">';
    $html .= '<div class="btn-group" role="group" aria-label="actions">';
    $html .= '<a class="btn btn-sm btn-outline-primary" href="'. htmlspecialchars($viewUrl) .'" target="_blank" title="View"><i class="fas fa-eye"></i></a>';
    $html .= '<button data-id="'. $id .'" class="btn btn-sm btn-outline-danger btn-delete" title="Delete"><i class="fas fa-trash"></i></button>';
    $html .= '</div>';
    $html .= '</td>';
    $html .= '</tr>';
}

 // prepare pagination metadata
 $totalPages = (int)ceil($total / $per_page);
 // if nothing found, show a friendly row
 if (trim($html) === '') {
      $html = '<tr><td colspan="11" class="text-center text-muted">কোনো রেকর্ড পাওয়া যায়নি</td></tr>';
 }
 header('Content-Type: application/json');
 echo json_encode([
     'success' => true,
     'html' => $html,
     'total' => $total,
     'page' => $page,
     'per_page' => $per_page,
     'total_pages' => $totalPages
 ]);
 exit;

?>
