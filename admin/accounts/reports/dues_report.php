<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../inc/finance_helpers.php';
require_once __DIR__ . '/../../inc/enrollment_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../../inc/header.php';
require_once __DIR__ . '/../../inc/sidebar.php';

$err=''; $ok='';

// Determine payments table (reuse logic)
$PAY_TABLE = 'fee_payments';
$isLegacyPayments = false; $FC_COL_METHOD='method'; $FC_COL_REF='ref_no';
if ($pdo instanceof PDO) {
  try {
    $cols__fp = $pdo->query("SHOW COLUMNS FROM fee_payments")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if ($cols__fp) {
      $hasBill = in_array('bill_id', $cols__fp, true);
      $hasLegacy = in_array('student_id', $cols__fp, true) && in_array('fee_structure_id', $cols__fp, true);
      if (!$hasBill && $hasLegacy) { $isLegacyPayments = true; }
      if (!in_array('method',$cols__fp,true) && in_array('payment_method',$cols__fp,true)) $FC_COL_METHOD='payment_method';
      if (!in_array('ref_no',$cols__fp,true) && in_array('transaction_id',$cols__fp,true)) $FC_COL_REF='transaction_id';
    }
  } catch (Throwable $e) {}
}
if ($isLegacyPayments) {
  $PAY_TABLE = 'fee_bill_payments';
  try { $pdo->exec("CREATE TABLE IF NOT EXISTS fee_bill_payments (id INT AUTO_INCREMENT PRIMARY KEY, bill_id INT NOT NULL, payment_date DATE NOT NULL, amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, method VARCHAR(50) NULL, ref_no VARCHAR(100) NULL, received_by_user_id INT NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_bill (bill_id), INDEX idx_date (payment_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $e) {}
}

$classes = get_classes($pdo) ?? [];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$section_id = (isset($_GET['section_id']) && $_GET['section_id']!=='') ? (int)$_GET['section_id'] : null;

// SMS to selected
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_sms'])) {
  $ids = isset($_POST['sid']) && is_array($_POST['sid']) ? array_map('intval', $_POST['sid']) : [];
  $template = trim($_POST['template'] ?? 'আপনার ফি বকেয়া রয়েছে, অনুগ্রহ করে পরিশোধ করুন।');
  if ($ids) {
    include __DIR__ . '/../../inc/sms_api.php';
    // Fetch mobiles and dues
    $in = implode(',', array_fill(0,count($ids),'?'));
    $st = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, s.mobile_number FROM students s WHERE s.id IN ($in)");
    $st->execute($ids); $rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $sent=0; $fail=0;
    foreach($rows as $r){
      $sid=(int)$r['id'];
      // Compute due: all unpaid/partial bills
      $duer = $pdo->prepare("SELECT COALESCE(SUM(b.net_amount),0) AS bill_net FROM fee_bills b WHERE b.student_id=? AND b.status IN ('unpaid','partial')");
      $duer->execute([$sid]); $bill_net = (float)($duer->fetch(PDO::FETCH_COLUMN) ?: 0);
      $payr = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM {$PAY_TABLE} p JOIN fee_bills b ON b.id=p.bill_id WHERE b.student_id=? AND b.status IN ('unpaid','partial')");
      $payr->execute([$sid]); $paid = (float)($payr->fetch(PDO::FETCH_COLUMN) ?: 0);
      $due = max(0.0, $bill_net - $paid);
      if ($due <= 0) continue;
      $name = trim(($r['first_name']??'').' '.($r['last_name']??''));
      $msg = str_replace(['{name}','{due}'], [$name ?: ('ID '.$sid), number_format($due,2)], $template);
      $okone = send_sms((string)($r['mobile_number'] ?? ''), $msg);
      if ($okone) $sent++; else $fail++;
    }
    $ok = "SMS পাঠানো হয়েছে: {$sent}, ব্যর্থ: {$fail}";
  } else {
    $err = 'কোন শিক্ষার্থী নির্বাচন করা হয়নি';
  }
}

// Build dues list
$where = []; $params = [];
$useEnroll = function_exists('enrollment_table_exists') && enrollment_table_exists($pdo);
if ($useEnroll) {
  $sql = "SELECT s.id, s.first_name, s.last_name, s.mobile_number, se.roll_number, c.name AS class_name, sec.name AS section_name
          FROM students s JOIN students_enrollment se ON se.student_id=s.id
          LEFT JOIN classes c ON c.id=se.class_id LEFT JOIN sections sec ON sec.id=se.section_id";
  if ($class_id) { $where[]='se.class_id=?'; $params[]=$class_id; }
  if ($section_id) { $where[]='se.section_id=?'; $params[]=$section_id; }
  // current academic year filter if exists
  try { $enCols=$pdo->query("SHOW COLUMNS FROM students_enrollment")->fetchAll(PDO::FETCH_COLUMN) ?: []; if (in_array('academic_year_id',$enCols,true)) { $ay=current_academic_year_id($pdo); if ($ay) { $where[]='se.academic_year_id=?'; $params[]=(int)$ay; } } } catch(Throwable $e) {}
  // active filter if column exists
  try { $enCols=$pdo->query("SHOW COLUMNS FROM students_enrollment")->fetchAll(PDO::FETCH_COLUMN) ?: []; if (in_array('status',$enCols,true)) { $where[]="se.status='active'"; } } catch(Throwable $e) {}
} else {
  $sql = "SELECT s.id, s.first_name, s.last_name, s.mobile_number, s.roll_number, c.name AS class_name, sec.name AS section_name FROM students s LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN sections sec ON sec.id=s.section_id";
  if ($class_id) { $where[]='s.class_id=?'; $params[]=$class_id; }
  if ($section_id) { $where[]='s.section_id=?'; $params[]=$section_id; }
}
if ($where) $sql .= ' WHERE '.implode(' AND ',$where);
$sql .= ' ORDER BY c.numeric_value ASC, se.roll_number ASC, s.first_name ASC';

$st = $pdo->prepare($sql); $st->execute($params); $students = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Compute dues per student
$rows=[]; $total_due=0.0; $defCnt=0;
foreach($students as $s){
  $sid=(int)$s['id'];
  // sum unpaid/partial bills and payments
  $br = $pdo->prepare("SELECT COALESCE(SUM(net_amount),0) FROM fee_bills WHERE student_id=? AND status IN ('unpaid','partial')");
  $br->execute([$sid]); $net=(float)($br->fetch(PDO::FETCH_COLUMN) ?: 0);
  $pr = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM {$PAY_TABLE} p JOIN fee_bills b ON b.id=p.bill_id WHERE b.student_id=? AND b.status IN ('unpaid','partial')");
  $pr->execute([$sid]); $paid=(float)($pr->fetch(PDO::FETCH_COLUMN) ?: 0);
  $due = max(0.0, $net - $paid);
  if ($due>0) { $defCnt++; $total_due += $due; $rows[] = $s + ['due'=>$due]; }
}

?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>বকেয়া রিপোর্ট</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <style>body{font-family:'SolaimanLipi','Source Sans Pro',sans-serif}</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">বকেয়া রিপোর্ট</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/accounts/index.php">হিসাব</a></li><li class="breadcrumb-item active">বকেয়া</li></ol></div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <?php if($ok) echo '<div class="alert alert-success">'.htmlspecialchars($ok).'</div>'; ?>
        <?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>

        <div class="card">
          <div class="card-header bg-primary text-white">ফিল্টার</div>
          <div class="card-body">
            <form method="get" class="form-row">
              <div class="form-group col-md-3">
                <label>শ্রেণি</label>
                <select name="class_id" id="class_id" class="form-control">
                  <option value="">- সব -</option>
                  <?php foreach($classes as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>" <?php echo $class_id==$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label>শাখা</label>
                <select name="section_id" id="section_id" class="form-control"><option value="">- সব -</option></select>
              </div>
              <div class="form-group col-md-2 align-self-end">
                <button class="btn btn-info btn-block"><i class="fas fa-search"></i> দেখুন</button>
              </div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>বকেয়া তালিকা</div>
            <div>
              <span class="mr-3">মোট বকেয়া: <strong>৳ <?php echo number_format($total_due,2); ?></strong> (<?php echo (int)$defCnt; ?> জন)</span>
              <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> প্রিন্ট</button>
            </div>
          </div>
          <div class="card-body p-0">
            <form method="post">
              <input type="hidden" name="send_sms" value="1">
              <div class="table-responsive">
                <table class="table table-striped mb-0">
                  <thead><tr><th class="no-print"><input type="checkbox" id="selAll"></th><th>#</th><th>নাম</th><th>রোল</th><th>শ্রেণি/শাখা</th><th>মোবাইল</th><th class="text-right">বকেয়া</th></tr></thead>
                  <tbody>
                    <?php if(!$rows): ?><tr><td colspan="7" class="text-center text-muted">কোন বকেয়া নেই</td></tr><?php endif; ?>
                    <?php $i=1; foreach($rows as $r): ?>
                      <tr>
                        <td class="no-print"><input type="checkbox" name="sid[]" value="<?php echo (int)$r['id']; ?>"></td>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars(trim(($r['first_name']??'').' '.($r['last_name']??''))); ?></td>
                        <td><?php echo htmlspecialchars($r['roll_number'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars(($r['class_name']??'').($r['section_name']?(' - '.$r['section_name']):'')); ?></td>
                        <td><?php echo htmlspecialchars($r['mobile_number'] ?? ''); ?></td>
                        <td class="text-right">৳ <?php echo number_format($r['due'],2); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div class="no-print p-3 d-flex align-items-center">
                <label class="mb-0 mr-2">SMS টেমপ্লেট:</label>
                <input type="text" name="template" class="form-control mr-2" value="{name} - আপনার বকেয়া ৳{due}। অনুগ্রহ করে পরিশোধ করুন।" style="max-width:480px">
                <button class="btn btn-success"><i class="fas fa-paper-plane"></i> SMS পাঠান</button>
              </div>
            </form>
          </div>
        </div>

      </div>
    </section>
  </div>
  <?php include __DIR__ . '/../../inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function(){
  function loadSections(cid){
    $('#section_id').html('<option value="">লোড হচ্ছে...</option>');
    $.get('<?php echo BASE_URL; ?>admin/get_sections.php',{class_id: cid}, function(html){
      $('#section_id').html('<option value="">- সব -</option>'+(html||''));
    }).fail(function(){ $('#section_id').html('<option value="">- সব -</option>'); });
  }
  var selClass = $('#class_id').val(); if(selClass){ loadSections(selClass); }
  $(document).on('change','#class_id', function(){ var v=$(this).val(); if(v){loadSections(v);} else { $('#section_id').html('<option value="">- সব -</option>'); } });
  $('#selAll').on('change', function(){ $('input[name="sid[]"]').prop('checked', this.checked); });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
