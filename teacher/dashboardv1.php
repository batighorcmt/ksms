<?php 
require_once '../config.php';

// Authentication check - শুধুমাত্র শিক্ষক এক্সেস করতে পারবে
if (!isAuthenticated() || !hasRole(['teacher'])) {
    redirect('login.php');
}

// বর্তমান শিক্ষকের তথ্য
$teacher_id = $_SESSION['user_id'];

// শিক্ষকের তথ্য লোড করুন
$teacher = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$teacher->execute([$teacher_id]);
$teacher = $teacher->fetch();

// শিক্ষকের ক্লাস এবং শাখা
$teacher_classes = $pdo->prepare("
    SELECT c.*, s.name as section_name 
    FROM classes c 
    LEFT JOIN sections s ON c.class_teacher_id = ? OR s.section_teacher_id = ?
    WHERE c.class_teacher_id = ? OR s.section_teacher_id = ?
    GROUP BY c.id
");
$teacher_classes->execute([$teacher_id, $teacher_id, $teacher_id, $teacher_id]);
$teacher_classes = $teacher_classes->fetchAll();

// আজকের তারিখ
$today = date('Y-m-d');

// আজকের উপস্থিতি
$attendance_today = $pdo->prepare("
    SELECT COUNT(*) as total, 
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
    FROM attendance 
    WHERE date = ? AND recorded_by = ?
");
$attendance_today->execute([$today, $teacher_id]);
$attendance_today_data = $attendance_today->fetch();

// মাসিক উপস্থিতি
$current_month = date('Y-m');
$attendance_month = $pdo->prepare("
    SELECT COUNT(*) as total, 
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
    FROM attendance 
    WHERE date LIKE ? AND recorded_by = ?
");
$attendance_month->execute([$current_month.'%', $teacher_id]);
$attendance_month_data = $attendance_month->fetch();

// সাম্প্রতিক অনুপস্থিতি
$recent_absence = $pdo->prepare("
    SELECT a.*, s.first_name, s.last_name, c.name as class_name, sec.name as section_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN classes c ON a.class_id = c.id
    JOIN sections sec ON a.section_id = sec.id
    WHERE a.recorded_by = ? AND a.status = 'absent'
    ORDER BY a.date DESC
    LIMIT 6
");
$recent_absence->execute([$teacher_id]);
$recent_absence_data = $recent_absence->fetchAll();

// ক্লাস রুটিন
$routine_stmt = $pdo->prepare("
    SELECT r.*, c.name as class_name, s.name as section_name, sub.name as subject_name
    FROM routines r
    JOIN classes c ON r.class_id = c.id
    JOIN sections s ON r.section_id = s.id
    JOIN subjects sub ON r.subject_id = sub.id
    WHERE r.teacher_id = ?
");
$routine_stmt->execute([$teacher_id]);
$period_routine = $routine_stmt->fetchAll();

// নোটিশ
$notices = $pdo->query("
    SELECT * FROM notices 
    WHERE (target_audience='teachers' OR target_audience='all')
    ORDER BY publish_date DESC 
    LIMIT 6
")->fetchAll();

// ইভেন্ট
$events = $pdo->query("
    SELECT * FROM events 
    WHERE (audience='teachers' OR audience='all')
    ORDER BY event_date ASC 
    LIMIT 6
")->fetchAll();

// মোট শিক্ষার্থী
$total_students = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE (c.class_teacher_id=? OR sec.section_teacher_id=?) AND s.status='active'
");
$total_students->execute([$teacher_id,$teacher_id]);
$total_students = $total_students->fetch()['total'];

// সাম্প্রতিক পরীক্ষা
$recent_exams = $pdo->prepare("
    SELECT e.*, c.name as class_name, sec.name as section_name
    FROM exams e
    JOIN classes c ON e.class_id = c.id
    JOIN sections sec ON e.section_id = sec.id
    WHERE c.class_teacher_id=? OR sec.section_teacher_id=?
    ORDER BY e.exam_date DESC
    LIMIT 5
");
$recent_exams->execute([$teacher_id,$teacher_id]);
$recent_exams_data = $recent_exams->fetchAll();

// সাপ্তাহিক চার্ট
$attendance_weekly = $pdo->prepare("
    SELECT DATE_FORMAT(date,'%W') as day, 
           COUNT(*) as total,
           SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present
    FROM attendance 
    WHERE recorded_by=? AND date>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY date
    ORDER BY date
");
$attendance_weekly->execute([$teacher_id]);
$attendance_weekly_data=$attendance_weekly->fetchAll();

$chart_labels=[]; $chart_data=[];
foreach($attendance_weekly_data as $d){
  $chart_labels[]=$d['day'];
  $chart_data[]= $d['total']>0 ? round(($d['present']/$d['total'])*100,2):0;
}
if(empty($chart_labels)){ $chart_labels=['কোনো ডেটা নেই']; $chart_data=[0]; }
?>

<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="utf-8">
<title>শিক্ষক ড্যাশবোর্ড</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
<style>body{font-family:'SolaimanLipi','Source Sans Pro',sans-serif;}</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../admin/inc/header.php'; ?>
<?php include 'inc/sidebar.php'; ?>

<div class="content-wrapper">
<section class="content">
<div class="container-fluid">

<!-- Welcome -->
<div class="card card-primary mb-3">
  <div class="card-body">
    <h4>প্রিয় <?php echo $teacher['full_name']; ?>, স্বাগতম</h4>
    <p><?php echo date('l, d F Y h:i A'); ?></p>
  </div>
</div>

<!-- Stats -->
<div class="row">
  <div class="col-md-3">
    <div class="info-box bg-info"><span class="info-box-icon"><i class="fas fa-school"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">মোট ক্লাস</span>
        <span class="info-box-number"><?php echo count($teacher_classes); ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box bg-success"><span class="info-box-icon"><i class="fas fa-users"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">মোট শিক্ষার্থী</span>
        <span class="info-box-number"><?php echo $total_students; ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box bg-warning"><span class="info-box-icon"><i class="fas fa-user-check"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">আজকের উপস্থিতি</span>
        <span class="info-box-number"><?php echo $attendance_today_data['present'] ?? 0; ?>/<?php echo $attendance_today_data['total'] ?? 0; ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box bg-danger"><span class="info-box-icon"><i class="fas fa-calendar-alt"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">এই মাসে উপস্থিতি</span>
        <span class="info-box-number"><?php echo $attendance_month_data['present'] ?? 0; ?>/<?php echo $attendance_month_data['total'] ?? 0; ?></span>
      </div>
    </div>
  </div>
</div>

<div class="row">
<!-- Left -->
<div class="col-lg-8">

  <!-- Chart -->
  <div class="card mb-3">
    <div class="card-header"><h5><i class="fas fa-chart-line"></i> সাপ্তাহিক উপস্থিতি</h5></div>
    <div class="card-body"><canvas id="attendanceChart" height="200"></canvas></div>
  </div>

  <!-- My Classes + Absence -->
  <div class="row">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header"><h6><i class="fas fa-chalkboard"></i> আমার ক্লাস</h6></div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
          <?php foreach($teacher_classes as $c): ?>
            <li class="list-group-item"><?php echo $c['name']; ?> (<?php echo $c['section_name'] ?? 'শাখা নেই'; ?>)</li>
          <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header"><h6><i class="fas fa-user-times"></i> সাম্প্রতিক অনুপস্থিতি</h6></div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
          <?php foreach($recent_absence_data as $a): ?>
            <li class="list-group-item text-danger"><?php echo $a['first_name']." ".$a['last_name']; ?> - <?php echo $a['class_name']; ?></li>
          <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Routine -->
  <div class="card mb-3">
    <div class="card-header"><h6><i class="fas fa-calendar-alt"></i> ক্লাস রুটিন</h6></div>
    <div class="card-body p-0">
      <table class="table table-sm table-bordered">
        <thead><tr><th>দিন</th><th>পিরিওড</th><th>ক্লাস</th><th>শাখা</th><th>বিষয়</th></tr></thead>
        <tbody>
        <?php foreach($period_routine as $r): ?>
          <tr>
            <td><?php echo $r['day']; ?></td>
            <td><?php echo $r['period']; ?></td>
            <td><?php echo $r['class_name']; ?></td>
            <td><?php echo $r['section_name']; ?></td>
            <td><?php echo $r['subject_name']; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Exams -->
  <div class="card mb-3">
    <div class="card-header"><h6><i class="fas fa-book"></i> সাম্প্রতিক পরীক্ষা</h6></div>
    <div class="card-body p-0">
      <table class="table table-hover">
        <thead><tr><th>পরীক্ষা</th><th>ক্লাস</th><th>তারিখ</th><th>নম্বর</th></tr></thead>
        <tbody>
        <?php foreach($recent_exams_data as $e): ?>
          <tr>
            <td><?php echo $e['name']; ?></td>
            <td><?php echo $e['class_name']; ?></td>
            <td><?php echo $e['exam_date']; ?></td>
            <td><?php echo $e['total_marks']; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Right -->
<div class="col-lg-4">

  <!-- Quick -->
  <div class="card mb-3">
    <div class="card-header"><h6><i class="fas fa-bolt"></i> দ্রুত অ্যাকশন</h6></div>
    <div class="card-body">
      <a href="#" class="btn btn-block btn-outline-primary mb-2"><i class="fas fa-clipboard-check"></i> উপস্থিতি নিন</a>
      <a href="#" class="btn btn-block btn-outline-success mb-2"><i class="fas fa-users"></i> শিক্ষার্থী</a>
      <a href="#" class="btn btn-block btn-outline-warning mb-2"><i class="fas fa-book"></i> পরীক্ষা</a>
      <a href="#" class="btn btn-block btn-outline-info mb-2"><i class="fas fa-chart-bar"></i> রিপোর্ট</a>
    </div>
  </div>

  <!-- Notices -->
  <div class="card mb-3">
    <div class="card-header"><h6><i class="fas fa-bullhorn"></i> নোটিশ</h6></div>
    <div class="card-body p-0">
      <ul class="list-group list-group-flush">
      <?php foreach($notices as $n): ?>
        <li class="list-group-item"><?php echo $n['title']; ?></li>
      <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <!-- Events -->
  <div class="card mb-3">
    <div class="card-header"><h6><i class="fas fa-calendar"></i> ইভেন্ট</h6></div>
    <div class="card-body p-0">
      <ul class="list-group list-group-flush">
      <?php foreach($events as $ev): ?>
        <li class="list-group-item"><?php echo $ev['title']." - ".$ev['event_date']; ?></li>
      <?php endforeach; ?>
      </ul>
    </div>
  </div>

</div>
</div>

</div>
</section>
</div>

<?php include '../admin/inc/footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
var ctx=document.getElementById('attendanceChart');
new Chart(ctx,{type:'bar',
 data:{labels:<?php echo json_encode($chart_labels); ?>,
 datasets:[{data:<?php echo json_encode($chart_data); ?>,backgroundColor:'rgba(78,115,223,.7)'}]}
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
