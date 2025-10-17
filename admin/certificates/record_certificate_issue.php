<?php
require_once '../../config.php';
if (!isAuthenticated()) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json', true, 401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    header('HTTP/1.1 401 Unauthorized'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/certificates/print_certificate_options.php');
}

$student_id = intval($_POST['student_id'] ?? 0);
$type = trim($_POST['certificate_type'] ?? 'running');
$notes = trim($_POST['notes'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;

if (!$student_id) {
    $msg = 'শিক্ষার্থী আইডি পাওয়া যায়নি।';
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $msg]); exit;
    }
    $_SESSION['error'] = $msg; redirect('admin/certificates/print_certificate_options.php');
    exit;
}

try {
    // Determine current year (calendar year). This is used to avoid duplicate per-year issues.
    // allow client to pass academic_year_id for certificate numbering; fall back to current calendar year if not provided
    $academic_year_id = intval($_POST['academic_year_id'] ?? 0);
    if ($academic_year_id) {
        $ay = $pdo->prepare('SELECT year FROM academic_years WHERE id = ? LIMIT 1');
        $ay->execute([$academic_year_id]);
        $ayRow = $ay->fetch(PDO::FETCH_ASSOC);
        $year = $ayRow ? (int)$ayRow['year'] : (int)date('Y');
    } else {
        $year = (int)date('Y');
    }

    // NOTE: Previously we prevented creating a new certificate if one existed for the
    // same student/type/year. Per new requirements we always create a new record
    // even if prior records exist. This keeps historical issuance intact.

    // Need to create a new certificate record
    // Fetch school short code
    $schoolRow = $pdo->query("SELECT short_code FROM school_info LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $short = ($schoolRow && !empty($schoolRow['short_code'])) ? $schoolRow['short_code'] : 'SC';

    // Compute serial for this year and type
    $countStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM certificate_issues WHERE certificate_type = ? AND YEAR(issued_at) = ?");
    $countStmt->execute([$type, $year]);
    $cntRow = $countStmt->fetch(PDO::FETCH_ASSOC);
    $serial = (int)$cntRow['cnt'] + 1;

    // Certificate number format: {SHORT}/প্রত্যয়নপত্র/{YEAR}/{SERIAL}
    $certificate_number = sprintf('%s/প্রত্যয়নপত্র/%s/%d', $short, $year, $serial);

    // Insert new record (include certificate_number if column exists)
    $pdo->beginTransaction();
    // Try inserting with certificate_number column - if it fails because column missing, fall back
    $insertSql = "INSERT INTO certificate_issues (student_id, issued_by, certificate_type, notes, certificate_number, issued_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([$student_id, $user_id, $type, $notes, $certificate_number]);
    $newId = $pdo->lastInsertId();
    $pdo->commit();

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'id' => (int)$newId, 'certificate_number' => $certificate_number, 'issued_at' => date('Y-m-d H:i:s')]);
        exit;
    } else {
        // non-AJAX fallback: redirect to print page with certificate_number
        $redirect = $_POST['print_url'] ?? 'running_student_certificate.php?id=' . $student_id;
        $sep = (parse_url($redirect, PHP_URL_QUERY) ? '&' : '?');
        header('Location: ' . $redirect . $sep . 'certificate_number=' . urlencode($certificate_number));
        exit;
    }

} catch (Exception $e) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'ডাটাবেজ ত্রুটি: ' . $e->getMessage()]);
        exit;
    }
        $_SESSION['error'] = 'ডাটাবেজ ত্রুটি: ' . $e->getMessage();
    redirect('admin/certificates/print_certificate_options.php');
    exit;
}

?>
