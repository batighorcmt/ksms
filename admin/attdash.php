<?php
require_once '../config.php';

// Authentication check (আপনার সিস্টেম অনুযায়ী প্রয়োগ করুন)
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// আজকের তারিখ
$current_date = date('Y-m-d');

// ১. সারসংক্ষেপ
$summary_sql = "
    SELECT 
      COUNT(s.id) AS total_students,
      SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present_students,
      SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) AS absent_students
    FROM students s
    LEFT JOIN attendance a 
      ON s.id = a.student_id AND a.date = :today
";
$stmt = $pdo->prepare($summary_sql);
$stmt->execute(['today' => $current_date]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$total_students = $summary['total_students'] ?? 0;
$present_students = $summary['present_students'] ?? 0;
$absent_students = $summary['absent_students'] ?? 0;
$attendance_rate = $total_students > 0 ? round(($present_students / $total_students) * 100, 2) : 0;

// ২. ক্লাস–শাখাভিত্তিক রিপোর্ট
$class_section_sql = "
    SELECT 
      c.name AS class_name,
      sec.name AS section_name,
      SUM(CASE WHEN s.gender='male' THEN 1 ELSE 0 END) AS total_male,
      SUM(CASE WHEN s.gender='female' THEN 1 ELSE 0 END) AS total_female,
      SUM(CASE WHEN s.gender='male' AND a.status='present' THEN 1 ELSE 0 END) AS present_male,
      SUM(CASE WHEN s.gender='female' AND a.status='present' THEN 1 ELSE 0 END) AS present_female,
      SUM(CASE WHEN s.gender='male' AND a.status='absent' THEN 1 ELSE 0 END) AS absent_male,
      SUM(CASE WHEN s.gender='female' AND a.status='absent' THEN 1 ELSE 0 END) AS absent_female,
      COUNT(s.id) AS total_students,
      ROUND(SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) / COUNT(s.id) * 100, 2) AS attendance_rate
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN attendance a ON s.id = a.student_id AND a.date = :today
    GROUP BY c.id, sec.id
    ORDER BY c.numeric_value ASC, sec.name ASC
";
$stmt = $pdo->prepare($class_section_sql);
$stmt->execute(['today' => $current_date]);
$class_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ৩. অনুপস্থিত শিক্ষার্থী তালিকা
$absent_sql = "
    SELECT 
      CONCAT(s.first_name, ' ', s.last_name) AS student_name,
      c.name AS class_name,
      sec.name AS section_name,
      s.roll_number,
      s.mobile_number,
      s.present_address
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    JOIN attendance a ON s.id = a.student_id
    WHERE a.date = :today AND a.status='absent'
    ORDER BY c.numeric_value ASC, sec.name ASC, s.roll_number ASC
";
$stmt = $pdo->prepare($absent_sql);
$stmt->execute(['today' => $current_date]);
$absent_students_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <title>Attendance Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
<div class="container-fluid py-4">

  <!-- সারসংক্ষেপ -->
  <div class="row text-center mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="card-title">মোট শিক্ষার্থী</h5>
          <h2 class="text-primary"><?= $total_students ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="card-title">উপস্থিত</h5>
          <h2 class="text-success"><?= $present_students ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="card-title">অনুপস্থিত</h5>
          <h2 class="text-danger"><?= $absent_students ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="card-title">উপস্থিতির হার</h5>
          <h2 class="text-info"><?= $attendance_rate ?>%</h2>
        </div>
      </div>
    </div>
  </div>

  <!-- ক্লাস-শাখাভিত্তিক রিপোর্ট -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">ক্লাস–শাখাভিত্তিক উপস্থিতি</div>
    <div class="card-body table-responsive">
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>শ্রেণি</th>
            <th>শাখা</th>
            <th>মোট ছেলে</th>
            <th>মোট মেয়ে</th>
            <th>উপস্থিত ছেলে</th>
            <th>উপস্থিত মেয়ে</th>
            <th>অনুপস্থিত ছেলে</th>
            <th>অনুপস্থিত মেয়ে</th>
            <th>উপস্থিতির হার</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($class_sections as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['class_name']) ?></td>
            <td><?= htmlspecialchars($row['section_name']) ?></td>
            <td><?= $row['total_male'] ?></td>
            <td><?= $row['total_female'] ?></td>
            <td class="text-success"><?= $row['present_male'] ?></td>
            <td class="text-success"><?= $row['present_female'] ?></td>
            <td class="text-danger"><?= $row['absent_male'] ?></td>
            <td class="text-danger"><?= $row['absent_female'] ?></td>
            <td><strong><?= $row['attendance_rate'] ?>%</strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- অনুপস্থিত শিক্ষার্থী -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-danger text-white">আজকের অনুপস্থিত শিক্ষার্থী</div>
    <div class="card-body table-responsive">
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>#</th>
            <th>নাম</th>
            <th>শ্রেণি</th>
            <th>শাখা</th>
            <th>রোল</th>
            <th>মোবাইল</th>
            <th>গ্রাম/ঠিকানা</th>
          </tr>
        </thead>
        <tbody>
          <?php $i=1; foreach ($absent_students_list as $student): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($student['student_name']) ?></td>
            <td><?= htmlspecialchars($student['class_name']) ?></td>
            <td><?= htmlspecialchars($student['section_name']) ?></td>
            <td><?= $student['roll_number'] ?></td>
            <td><?= htmlspecialchars($student['mobile_number']) ?></td>
            <td><?= htmlspecialchars($student['present_address']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($absent_students_list)): ?>
          <tr><td colspan="7" class="text-center">আজ কোনো শিক্ষার্থী অনুপস্থিত নেই 🎉</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- চার্ট -->
  <div class="row">
    <div class="col-md-6">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white">উপস্থিতি বনাম অনুপস্থিতি</div>
        <div class="card-body">
          <canvas id="pieChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white">ক্লাস অনুযায়ী উপস্থিতির হার</div>
        <div class="card-body">
          <canvas id="barChart"></canvas>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
// Pie Chart Data
const pieCtx = document.getElementById('pieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'pie',
    data: {
        labels: ['উপস্থিত', 'অনুপস্থিত'],
        datasets: [{
            data: [<?= $present_students ?>, <?= $absent_students ?>],
            backgroundColor: ['#28a745', '#dc3545']
        }]
    }
});

// Bar Chart Data
const barCtx = document.getElementById('barChart').getContext('2d');
new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($class_sections, 'class_name')) ?>,
        datasets: [{
            label: 'উপস্থিতির হার (%)',
            data: <?= json_encode(array_column($class_sections, 'attendance_rate')) ?>,
            backgroundColor: '#007bff'
        }]
    },
    options: {
        scales: {
            y: { beginAtZero: true, max: 100 }
        }
    }
});
</script>

</body>
</html>
