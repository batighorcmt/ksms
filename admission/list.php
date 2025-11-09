<?php
require_once '../config.php';

if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('login.php');
}

// Fetch admissions
$sql = "SELECT a.*, ay.year, u.full_name AS added_by_name
        FROM admissions a
        LEFT JOIN academic_years ay ON ay.id = a.academic_year_id
        LEFT JOIN users u ON u.id = a.added_by_user_id
        ORDER BY a.id DESC";
$stmt = $pdo->query($sql);
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ভর্তি আবেদন তালিকা</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
  <style> body{font-family:'SolaimanLipi','Source Sans Pro',sans-serif} </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include __DIR__ . '/../admin/inc/header.php'; ?>
  <?php include __DIR__ . '/../admin/inc/sidebar.php'; ?>

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">ভর্তি আবেদন তালিকা</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/dashboard.php">হোম</a></li><li class="breadcrumb-item active">ভর্তি তালিকা</li></ol></div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">সব রেকর্ড</h3>
            <div>
              <a href="<?php echo BASE_URL; ?>admission/index.php" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i> নতুন ভর্তি</a>
              <a href="<?php echo BASE_URL; ?>admission/list_print.php" target="_blank" class="btn btn-secondary btn-sm"><i class="fas fa-print mr-1"></i> প্রিন্ট</a>
            </div>
          </div>
          <div class="card-body">
            <table id="admissionsTable" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>আইডি</th>
                  <th>শিক্ষাবর্ষ</th>
                  <th>শিক্ষার্থীর নাম</th>
                  <th>পিতা</th>
                  <th>মাতা</th>
                  <th>মোবাইল</th>
                  <th>ঠিকানা</th>
                  <th>বর্তমান বিদ্যালয়</th>
                  <th>শ্রেণি</th>
                  <th>রেফারেন্স</th>
                  <th>যুক্তকারী</th>
                  <th>তারিখ/সময়</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($rows as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['admission_id']); ?></td>
                  <td><?php echo htmlspecialchars($r['year']); ?></td>
                  <td><?php echo htmlspecialchars($r['student_name']); ?></td>
                  <td><?php echo htmlspecialchars($r['father_name']); ?></td>
                  <td><?php echo htmlspecialchars($r['mother_name']); ?></td>
                  <td><?php echo htmlspecialchars($r['mobile']); ?></td>
                  <td>
                      <?php 
                        $addr = array_filter([$r['village'] ?? '', $r['para_moholla'] ?? '', $r['upazila'] ?? '', $r['district'] ?? '']);
                        echo htmlspecialchars(implode(', ', $addr));
                      ?>
                  </td>
                  <td><?php echo (int)$r['currently_studying']===1 ? htmlspecialchars($r['current_school_name'] ?: '') : '-'; ?></td>
                  <td><?php echo (int)$r['currently_studying']===1 ? htmlspecialchars($r['current_class'] ?: '') : '-'; ?></td>
                  <td><?php echo htmlspecialchars($r['reference'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($r['added_by_name'] ?? ''); ?></td>
                  <td><?php echo date('d M Y, h:i A', strtotime($r['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
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
  $('#admissionsTable').DataTable({
    paging: true,
    lengthChange: true,
    searching: true,
    ordering: true,
  order: [[10,'desc']],
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
