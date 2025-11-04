<?php
require_once '../config.php';

// Auth: super admin and teacher can view
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
  redirect('login.php');
}

// Detect students table columns to optionally map admission to existing student profile via mobile
$studentMobileCol = null;
try {
  $scols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
  if (in_array('mobile_number', $scols, true)) { $studentMobileCol = 'mobile_number'; }
  elseif (in_array('mobile', $scols, true)) { $studentMobileCol = 'mobile'; }
} catch (Exception $e) { $studentMobileCol = null; }

// Build SQL from admissions; left join students by mobile when possible to enable profile link
$join = '';
if ($studentMobileCol) {
  // Join a deduped subquery to avoid row multiplication when multiple students share the same mobile
  $join = " LEFT JOIN (SELECT MIN(id) AS id, $studentMobileCol AS mobile_key FROM students WHERE $studentMobileCol IS NOT NULL AND $studentMobileCol <> '' GROUP BY $studentMobileCol) s ON s.mobile_key = a.mobile";
}
$sql = "SELECT a.id, a.admission_id, a.student_name, a.father_name, a.mobile, a.village, a.para_moholla, a.upazila, a.district" . ($studentMobileCol ? ", s.id AS student_db_id" : ", NULL AS student_db_id") .
     " FROM admissions a" . $join . " ORDER BY a.id DESC";
$stmt = $pdo->query($sql);
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

function full_name_row($r){
  $fn = trim($r['student_name'] ?? '');
  return $fn !== '' ? $fn : 'নাম নেই';
}

function short_address($r){
  $parts = array_filter([$r['village'] ?? '', $r['para_moholla'] ?? '', $r['upazila'] ?? '', $r['district'] ?? '']);
  return implode(', ', $parts);
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>শিক্ষার্থী সংক্ষিপ্ত তালিকা</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
  <style>
    body{font-family:'SolaimanLipi','Source Sans Pro',sans-serif}
    .table td, .table th{vertical-align:middle}
    /* Mobile-first tweaks */
    @media (max-width: 576px){
      .table thead{display:none}
      .table tbody tr{display:block; margin-bottom:10px; border:1px solid #e5e7eb; border-radius:6px; overflow:hidden}
      .table tbody td{display:flex; justify-content:space-between; padding:8px 12px}
      .table tbody td::before{content:attr(data-label); font-weight:600; color:#374151; margin-right:10px}
    }
    .truncate-1{display:-webkit-box; -webkit-line-clamp:1; line-clamp:1; -webkit-box-orient:vertical; overflow:hidden}
    /* Keep tel link looking like regular text */
    a.tel-link{color:inherit; text-decoration:none}
    a.tel-link:hover{ text-decoration:underline }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include __DIR__ . '/../admin/inc/header.php'; ?>
  <?php include __DIR__ . '/../admin/inc/sidebar.php'; ?>

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">শিক্ষার্থী সংক্ষিপ্ত তালিকা</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/dashboard.php">হোম</a></li><li class="breadcrumb-item active">সংক্ষিপ্ত তালিকা</li></ol></div>
        </div>
      </div>
    </div>
    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">সংক্ষেপ তালিকা</h3>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table id="quickStudents" class="table table-striped mb-0">
                <thead>
                  <tr>
                    <th style="width:70px">ক্রমিক</th>
                    <th>শিক্ষার্থীর নাম</th>
                    <th>পিতার নাম</th>
                    <th>ঠিকানা</th>
                    <th>মোবাইল</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i=1; foreach($rows as $s): ?>
                    <?php $name = full_name_row($s); $addr = short_address($s); $mobile = $s['mobile'] ?? ''; ?>
                    <tr>
                      <td data-label="ক্রমিক"><?php echo $i++; ?></td>
                      <td data-label="শিক্ষার্থীর নাম">
                        <a href="<?php echo BASE_URL; ?>admission/add_profile.php?id=<?php echo (int)$s['id']; ?>" class="text-primary">
                          <?php echo htmlspecialchars($name); ?>
                        </a>
                        <?php if (empty($s['student_db_id'])): ?>
                          <a href="<?php echo BASE_URL; ?>admission/add_profile.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-xs btn-outline-primary ml-2" title="প্রোফাইল তৈরি">
                            <i class="fas fa-user-plus"></i>
                          </a>
                        <?php endif; ?>
                      </td>
                      <td data-label="পিতার নাম"><?php echo htmlspecialchars($s['father_name'] ?? ''); ?></td>
                      <td data-label="ঠিকানা"><span class="truncate-1" title="<?php echo htmlspecialchars($addr); ?>"><?php echo htmlspecialchars($addr); ?></span></td>
                      <td data-label="মোবাইল">
                        <?php if(!empty($mobile)): ?>
                          <div class="d-inline-flex align-items-center">
                            <a href="tel:<?php echo preg_replace('/[^0-9+]/','', $mobile); ?>" class="btn btn-sm btn-outline-success mr-2" title="কল করুন">
                              <i class="fas fa-phone"></i>
                            </a>
                            <a href="tel:<?php echo preg_replace('/[^0-9+]/','', $mobile); ?>" class="tel-link"><?php echo htmlspecialchars($mobile); ?></a>
                          </div>
                        <?php else: ?>
                          <span class="text-muted">নেই</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <?php include __DIR__ . '/../admin/inc/footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
$(function(){
  $('#quickStudents').DataTable({
    paging: true,
    lengthChange: true,
    searching: true,
    ordering: true,
    order: [[0,'asc']],
    info: true,
    autoWidth: false,
    responsive: true,
    language: {
      search: 'খুঁজুন:',
      lengthMenu: 'প্রতি পৃষ্ঠায় _MENU_ এন্ট্রি',
      info: 'মোট _TOTAL_ রেকর্ডের মধ্যে _START_ থেকে _END_ দেখানো হচ্ছে',
      paginate: { previous: 'পূর্ববর্তী', next: 'পরবর্তী' }
    }
  });
});
</script>
</body>
</html>
