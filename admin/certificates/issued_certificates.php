<?php
require_once '../../config.php';
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
  redirect('../../index.php');
}

// filters
$q_student = trim($_GET['student'] ?? '');
$q_class = intval($_GET['class_id'] ?? 0);
$q_year = intval($_GET['academic_year_id'] ?? 0); // optional year filter
$q_type = trim($_GET['certificate_type'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$where = [];
$params = [];
if ($q_student !== '') { $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR se.roll_number LIKE ?)'; $like = "%$q_student%"; $params = array_merge($params, [$like,$like,$like]); }
if ($q_class) { $where[] = 'se.class_id = ?'; $params[] = $q_class; }
if ($q_type !== '') { $where[] = 'ci.certificate_type = ?'; $params[] = $q_type; }
if ($from) { $where[] = 'ci.issued_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to) { $where[] = 'ci.issued_at <= ?'; $params[] = $to . ' 23:59:59'; }

// (paginated query is executed below)

// load filters data
$classes = $pdo->query("SELECT id, name FROM classes WHERE status='active' ORDER BY numeric_value ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
// server-side pagination: read page and per_page from GET
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = max(1, intval($_GET['per_page'] ?? 25));

// Resolve target academic year: GET param -> current year -> fallback to latest enrollment per student
$targetAcademicYearId = 0;
if ($q_year > 0) {
  $targetAcademicYearId = $q_year;
} else {
  $cur = $pdo->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
  if (!empty($cur['id'])) $targetAcademicYearId = (int)$cur['id'];
}

// build base FROM/JOIN and WHERE for count and select
$joinParams = [];
$baseFrom = "FROM certificate_issues ci
  LEFT JOIN students s ON s.id = ci.student_id\n";
if ($targetAcademicYearId > 0) {
  $baseFrom .= "  LEFT JOIN students_enrollment se ON se.student_id = ci.student_id AND se.academic_year_id = ?\n";
  $joinParams[] = $targetAcademicYearId;
} else {
  // fallback to latest enrollment per student
  $baseFrom .= "  LEFT JOIN students_enrollment se ON se.student_id = ci.student_id AND se.academic_year_id = (SELECT MAX(se2.academic_year_id) FROM students_enrollment se2 WHERE se2.student_id = ci.student_id)\n";
}
$baseFrom .= "  LEFT JOIN classes c ON c.id = se.class_id\n  LEFT JOIN users u ON u.id = ci.issued_by";
$whereSql = '';
if (!empty($where)) $whereSql = ' WHERE ' . implode(' AND ', $where);

// total count
$countSql = 'SELECT COUNT(*) as cnt ' . $baseFrom . $whereSql;
$countStmt = $pdo->prepare($countSql);
$allParams = array_merge($joinParams, $params);
$countStmt->execute($allParams);
$totalRow = $countStmt->fetch(PDO::FETCH_ASSOC);
$total = $totalRow ? (int)$totalRow['cnt'] : 0;
$total_pages = $total ? (int)ceil($total / $per_page) : 1;

// fetch page rows
$offset = ($page - 1) * $per_page;
$sql = "SELECT ci.*, s.first_name, s.last_name, se.roll_number, s.id as student_id, c.name as class_name, u.full_name as issued_by_name "
  . $baseFrom . $whereSql . " ORDER BY ci.issued_at DESC LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($allParams);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>প্রদানকৃত প্রত্যয়নপত্র তালিকা</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: 'SolaimanLipi', Arial, sans-serif; background: #f5f5f5; }
        .cert-container { max-width: 720px; margin: 20px auto; background: #fff; box-shadow: 0 0 10px #ccc; padding: 20px; border-radius: 8px; }
        h2 { text-align: center; margin-bottom: 18px; color: #006400; font-size: 1.5rem; }
        label { font-weight: bold; margin-top: 8px; display: block; }
        select, button, input { width: 100%; padding: 10px; margin-top: 6px; border-radius: 5px; border: 1px solid #ccc; font-size: 16px; }
        button { background: #006400; color: #fff; border: none; cursor: pointer; margin-top: 12px; }
        button:hover { background: #004d00; }
        @media (max-width: 576px) {
            .cert-container { padding: 14px; margin: 10px; }
            h2 { font-size: 1.25rem; }
            .form-row { display: block; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include '../../admin/inc/header.php'; ?>
  <?php
  if (hasRole(['super_admin'])) {
    include '../../admin/inc/sidebar.php';
  } elseif (hasRole(['teacher'])) {
    include '../../teacher/inc/sidebar.php';
  }
  ?>
  <div class="content-wrapper">
    <div class="content-header"><div class="container-fluid d-flex align-items-center justify-content-between"><h1 class="m-0">প্রদানকৃত প্রত্যয়নপত্র সমূহ</h1>
      <div>
        <a href="print_certificate_options.php" class="btn btn-success btn-sm">নতুন প্রত্যয়নপত্র</a>
      </div>
    </div></div>
  <section class="content"><div class="container-fluid">
    <div class="card"><div class="card-body">
      <form method="get" class="row gx-2 gy-2 align-items-end mb-3">
        <div class="col-md-3"><label>Student</label><input type="text" name="student" class="form-control" value="<?= htmlspecialchars($q_student) ?>"></div>
        <div class="col-md-2"><label>Class</label><select name="class_id" class="form-control"><option value="">-- All --</option><?php foreach($classes as $c) echo '<option value="'. $c['id'] .'" '.($q_class==$c['id']?'selected':'').'>'.htmlspecialchars($c['name']).'</option>'; ?></select></div>
        <div class="col-md-2"><label>Type</label><input type="text" name="certificate_type" class="form-control" value="<?= htmlspecialchars($q_type) ?>"></div>
        <div class="col-md-2"><label>From</label><input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>"></div>
        <div class="col-md-2"><label>To</label><input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>"></div>
        <div class="col-md-1"><button class="btn btn-primary">Filter</button></div>
      </form>

        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="d-flex align-items-center">
            <label class="me-2 mb-0">প্রতি পৃষ্ঠায়:</label>
            <select id="perPageSelect" name="per_page" class="form-select form-select-sm" style="width:80px;">
              <option value="10" <?= $per_page==10 ? 'selected' : '' ?>>10</option>
              <option value="25" <?= $per_page==25 ? 'selected' : '' ?>>25</option>
              <option value="50" <?= $per_page==50 ? 'selected' : '' ?>>50</option>
              <option value="100" <?= $per_page==100 ? 'selected' : '' ?>>100</option>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-sm w-100" id="issuedTable">
            <thead><tr><th>SL</th><th>ID</th><th>Student</th><th>Roll</th><th>Class</th><th>Type</th><th>Issued By</th><th>Issued At</th><th>Certificate No</th><th>Notes</th><th>Actions</th></tr></thead>
            <tbody id="issuedTableBody">
              <!-- server-side fallback rows (AJAX will replace these) -->
      <?php if (!empty($rows)): $sl=$offset; foreach ($rows as $r): $sl++;
                    $id = (int)$r['id'];
                    $studentName = htmlspecialchars(trim(($r['first_name']??'').' '.($r['last_name']??'')));
                    $roll = htmlspecialchars($r['roll_number'] ?? '');
                    $class = htmlspecialchars($r['class_name'] ?? '');
                    $type = htmlspecialchars($r['certificate_type'] ?? '');
                    $issuedBy = htmlspecialchars($r['issued_by_name'] ?? '');
                    $issuedAt = htmlspecialchars($r['issued_at'] ?? '');
                    $notes = htmlspecialchars($r['notes'] ?? '');
                    $certNo = htmlspecialchars($r['certificate_number'] ?? '');
                    $student_id = (int)($r['student_id'] ?? $r['student_id'] ?? 0);
              ?>
              <tr>
                <td><?= $sl ?></td>
                <td><?= $id ?></td>
                <td><?= $studentName ?></td>
                <td><?= $roll ?></td>
                <td><?= $class ?></td>
                <td><?= $type ?></td>
                <td><?= $issuedBy ?></td>
                <td><?= $issuedAt ?></td>
                <td><?= $certNo ?></td>
                <td><?= $notes ?></td>
                <td class="text-center">
                  <div class="btn-group" role="group" aria-label="actions">
                    <a class="btn btn-sm btn-outline-primary" href="running_student_certificate.php?id=<?= $student_id ?>&certificate_number=<?= rawurlencode($r['certificate_number'] ?? '') ?>" target="_blank" title="View"><i class="fas fa-eye"></i></a>
                    <button data-id="<?= $id ?>" class="btn btn-sm btn-outline-danger btn-delete" title="Delete"><i class="fas fa-trash"></i></button>
                  </div>
                </td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="11" class="text-center text-muted">কোনো রেকর্ড পাওয়া যায়নি</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between mt-2">
          <div class="text-muted"><?= 'Showing ' . ($total? $offset+1 : 0) . ' to ' . min($total, $offset + $per_page) . ' of ' . $total ?></div>
          <div>
            <nav aria-label="Page navigation">
              <ul class="pagination pagination-sm mb-0">
                <?php
                  $startPage = max(1, $page - 2);
                  $endPage = min($total_pages, $page + 2);
                  $buildUrl = function($p) { $qs = $_GET; $qs['page'] = $p; return '?'.http_build_query($qs); };
                  if ($page > 1) echo '<li class="page-item"><a class="page-link" href="'.$buildUrl($page-1).'">&laquo;</a></li>';
                  if ($startPage > 1) echo '<li class="page-item"><a class="page-link" href="'.$buildUrl(1).'">1</a></li>';
                  if ($startPage > 2) echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                  for ($p = $startPage; $p <= $endPage; $p++) {
                    echo '<li class="page-item '.($p==$page?'active':'').'"> <a class="page-link" href="'.$buildUrl($p).'">'.$p.'</a></li>';
                  }
                  if ($endPage < $total_pages-1) echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                  if ($endPage < $total_pages) echo '<li class="page-item"><a class="page-link" href="'.$buildUrl($total_pages).'">'.$total_pages.'</a></li>';
                  if ($page < $total_pages) echo '<li class="page-item"><a class="page-link" href="'.$buildUrl($page+1).'">&raquo;</a></li>';
                ?>
              </ul>
            </nav>
          </div>
        </div>
    </div></div>
  </div></section>
</div>

  <?php include '../../admin/inc/footer.php'; ?>
</div>
  <!-- required scripts for AdminLTE sidebar and AJAX -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
  <script>
  // when per-page is changed, submit the filter form so server re-renders with the new per_page
  document.getElementById('perPageSelect').addEventListener('change', function(){
    const f = document.querySelector('form');
    let h = f.querySelector('input[name="per_page"]');
    if (!h) { h = document.createElement('input'); h.type='hidden'; h.name='per_page'; f.appendChild(h); }
    h.value = this.value;
    f.submit();
  });

  // attach delete handlers (POST then reload current page)
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.onclick = function(){
      if (!confirm('Are you sure to delete this certificate?')) return;
      const id = this.getAttribute('data-id');
      fetch('delete_certificate_issue.php', { method: 'POST', credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest'}, body: new URLSearchParams({id: id}) })
        .then(r => r.json()).then(j => { if (j.success) window.location.reload(); else alert('Delete failed: ' + (j.error||'unknown')); }).catch(e=>alert('Delete failed'));
    }
  });
  </script>
