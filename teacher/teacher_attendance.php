<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['teacher'])) {
    redirect('../login.php');
}

$teacher_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// আজকের রেকর্ড আছে কিনা
$stmt = $pdo->prepare("SELECT * FROM teacher_attendance WHERE teacher_id=? AND date=?");
$stmt->execute([$teacher_id, $today]);
$record = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষক ড্যাশবোর্ড - কিন্ডার গার্ডেন</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Bengali Font -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php include '../admin/inc/header.php'; ?>
<?php include 'inc/sidebar.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <h1><i class="fas fa-user-check"></i> আমার হাজিরা</h1>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <!-- Attendance Card -->
      <div class="card card-outline card-success">
        <div class="card-header">
          <h3 class="card-title">আজকের হাজিরা - <?= date('d-m-Y') ?></h3>
        </div>
        <div class="card-body text-center">

          <?php if(!$record): ?>
            <p class="text-muted">আজকের জন্য এখনো হাজিরা হয়নি।</p>
            <video id="video" width="320" height="240" autoplay></video>
            <canvas id="canvas" width="320" height="240" style="display:none;"></canvas>
            <form id="attendanceForm" method="POST" action="teacher_attendance_action.php">
              <input type="hidden" name="photo" id="photoInput">
              <input type="hidden" name="lat" id="lat">
              <input type="hidden" name="lng" id="lng">
                <input type="hidden" name="action" value="check_in">
                <button type="button" onclick="capture('check_in')" class="btn btn-success mt-2">📸 ছবি তুলে চেক-ইন করুন</button>
            </form>

          <?php elseif($record && !$record['check_out']): ?>
            <p>✅ চেক-ইন হয়েছে: <b><?= $record['check_in'] ?></b></p>
            <video id="video" width="320" height="240" autoplay></video>
            <canvas id="canvas" width="320" height="240" style="display:none;"></canvas>
            <form id="attendanceForm" method="POST" action="teacher_attendance_action.php">
              <input type="hidden" name="photo" id="photoInput">
              <input type="hidden" name="lat" id="lat">
              <input type="hidden" name="lng" id="lng">
                <input type="hidden" name="action" value="check_out">
                <button type="button" onclick="capture('check_out')" class="btn btn-danger mt-2">📸 ছবি তুলে চেক-আউট দিন</button>
            </form>

          <?php else: ?>
            <p class="text-success">✅ আজকের হাজিরা সম্পন্ন!</p>
            <p>চেক-ইন: <?= $record['check_in'] ?> | চেক-আউট: <?= $record['check_out'] ?></p>
          <?php endif; ?>

        </div>
      </div>

    </div>
  </section>
</div>

<script>
  // Camera start
  if(navigator.mediaDevices && navigator.mediaDevices.getUserMedia){
    navigator.mediaDevices.getUserMedia({ video: true })
      .then(stream => { document.getElementById('video').srcObject = stream });
  }

  function capture(){
    function capture(type){
      let video = document.getElementById('video');
      let canvas = document.getElementById('canvas');
      let ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, 320, 240);
      let data = canvas.toDataURL('image/jpeg', 0.3); // low resolution
      if(type === 'check_in') {
        document.getElementById('photoInput').value = data;
        navigator.geolocation.getCurrentPosition(function(pos){
          document.getElementById('lat').value = pos.coords.latitude;
          document.getElementById('lng').value = pos.coords.longitude;
          document.getElementById('attendanceForm').submit();
        }, function(){
          alert("⚠ লোকেশন পাওয়া যায়নি। দয়া করে Location চালু করুন।");
        });
      } else if(type === 'check_out') {
        document.getElementById('photoInput').value = data;
        navigator.geolocation.getCurrentPosition(function(pos){
          document.getElementById('lat').value = pos.coords.latitude;
          document.getElementById('lng').value = pos.coords.longitude;
          document.getElementById('attendanceForm').submit();
        }, function(){
          alert("⚠ লোকেশন পাওয়া যায়নি। দয়া করে Location চালু করুন।");
        });
      }

    // Location capture
    navigator.geolocation.getCurrentPosition(function(pos){
      document.getElementById('lat').value = pos.coords.latitude;
      document.getElementById('lng').value = pos.coords.longitude;
      document.getElementById('attendanceForm').submit();
    }, function(){
      alert("⚠ লোকেশন পাওয়া যায়নি। দয়া করে Location চালু করুন।");
    });
  }
</script>

<?php include '../admin/inc/footer.php'; ?>

    <!-- REQUIRED SCRIPTS -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE App -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html> 
