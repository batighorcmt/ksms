<?php
require_once '../config.php';
require_once __DIR__ . '/../admin/print_common.php';

if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('login.php');
}

// Optional ordering via GET (whitelist)
$allowed = [
  'admission_id' => 'a.admission_id',
  'year' => 'ay.year',
  'student_name' => 'a.student_name',
  'created_at' => 'a.created_at'
];
$order_by = isset($_GET['order_by']) && isset($allowed[$_GET['order_by']]) ? $allowed[$_GET['order_by']] : 'a.created_at';
$order_dir = (isset($_GET['order_dir']) && strtolower($_GET['order_dir'])==='asc') ? 'ASC' : 'DESC';

$sql = "SELECT a.*, ay.year, u.full_name AS added_by_name
        FROM admissions a
        LEFT JOIN academic_years ay ON ay.id = a.academic_year_id
        LEFT JOIN users u ON u.id = a.added_by_user_id
        ORDER BY $order_by $order_dir";
$stmt = $pdo->query($sql);
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ভর্তি আবেদন তালিকা (প্রিন্ট)</title>
  <style>
    body{font-family:'SolaimanLipi','Source Sans Pro',sans-serif}
    .table{width:100%;border-collapse:collapse}
    .table th,.table td{border:1px solid #ddd;padding:6px;text-align:left}
    .no-print{margin:10px 0}
    @media print{ .no-print{ display:none; } }
  </style>
</head>
<body>
<?php echo print_header($pdo, 'ভর্তি আবেদন তালিকা'); ?>
<div class="no-print">
  <a href="<?php echo BASE_URL; ?>admission/list.php" class="btn">ফিরে যান</a>
  <button onclick="window.print()">প্রিন্ট</button>
</div>
<table class="table">
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
      <th>যিনি যুক্ত করেছেন</th>
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
  <td><?php $addr = array_filter([$r['village']??'', $r['para_moholla']??'', $r['upazila']??'', $r['district']??'']); echo htmlspecialchars(implode(', ', $addr)); ?></td>
  <td><?php echo ((int)$r['currently_studying']===1) ? htmlspecialchars($r['current_school_name'] ?: '') : '-'; ?></td>
  <td><?php echo ((int)$r['currently_studying']===1) ? htmlspecialchars($r['current_class'] ?: '') : '-'; ?></td>
      <td><?php echo htmlspecialchars($r['reference'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars($r['added_by_name'] ?? ''); ?></td>
      <td><?php echo date('d M Y, h:i A', strtotime($r['created_at'])); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php echo print_footer(); ?>
<script>
  // Auto open print dialog after small delay
  setTimeout(function(){ window.print(); }, 300);
</script>
</body>
</html>
