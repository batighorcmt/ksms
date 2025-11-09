<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../inc/finance_helpers.php';
require_once __DIR__ . '/../../inc/enrollment_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../../inc/header.php';
require_once __DIR__ . '/../../inc/sidebar.php';

// Resolve payments table
$PAY_TABLE='fee_payments'; $FC_COL_METHOD='method'; $FC_COL_REF='ref_no';
try { $cols=$pdo->query("SHOW COLUMNS FROM fee_payments")->fetchAll(PDO::FETCH_COLUMN) ?: []; if ($cols){ $hasBill=in_array('bill_id',$cols,true); $hasLegacy=in_array('student_id',$cols,true) && in_array('fee_structure_id',$cols,true); if(!$hasBill && $hasLegacy) $PAY_TABLE='fee_bill_payments'; if(!in_array('method',$cols,true) && in_array('payment_method',$cols,true)) $FC_COL_METHOD='payment_method'; if(!in_array('ref_no',$cols,true) && in_array('transaction_id',$cols,true)) $FC_COL_REF='transaction_id'; } } catch(Throwable $e) {}
if ($PAY_TABLE==='fee_bill_payments') { try { $pdo->exec("CREATE TABLE IF NOT EXISTS fee_bill_payments (id INT AUTO_INCREMENT PRIMARY KEY, bill_id INT NOT NULL, payment_date DATE NOT NULL, amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, method VARCHAR(50) NULL, ref_no VARCHAR(100) NULL, received_by_user_id INT NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_bill (bill_id), INDEX idx_date (payment_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $e) {} }

$classes = get_classes($pdo) ?? [];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$section_id = (isset($_GET['section_id']) && $_GET['section_id']!=='') ? (int)$_GET['section_id'] : null;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$student = null; $bills=[]; $payments=[];
if ($student_id) {
  $st=$pdo->prepare("SELECT id, first_name, last_name, mobile_number FROM students WHERE id=?"); $st->execute([$student_id]); $student=$st->fetch(PDO::FETCH_ASSOC) ?: null;
  if ($student) {
    $bills = $pdo->prepare("SELECT * FROM fee_bills WHERE student_id=? ORDER BY bill_year ASC, bill_month ASC, id ASC"); $bills->execute([$student_id]); $bills=$bills->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $payments = $pdo->prepare("SELECT p.*, b.bill_month, b.bill_year FROM {$PAY_TABLE} p JOIN fee_bills b ON b.id=p.bill_id WHERE b.student_id=? ORDER BY p.payment_date ASC, p.id ASC"); $payments->execute([$student_id]); $payments=$payments->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>শিক্ষার্থীভিত্তিক লেজার</title>
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
          <div class="col-sm-6"><h1 class="m-0">শিক্ষার্থীভিত্তিক লেজার</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/accounts/index.php">হিসাব</a></li><li class="breadcrumb-item active">লেজার</li></ol></div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header bg-primary text-white">শিক্ষার্থী নির্বাচন</div>
          <div class="card-body">
            <form method="get" class="form-row">
              <div class="form-group col-md-3">
                <label>শ্রেণি</label>
                <select id="class_id" class="form-control"><option value="">- সব -</option>
                  <?php foreach($classes as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo $class_id==$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label>শাখা</label>
                <select id="section_id" class="form-control"><option value="">- সব -</option></select>
              </div>
              <div class="form-group col-md-4">
                <label>শিক্ষার্থী</label>
                <select name="student_id" id="student_id" class="form-control"><option value="">প্রথমে শ্রেণি/শাখা নির্বাচন করুন</option></select>
              </div>
              <div class="form-group col-md-2 align-self-end"><button class="btn btn-info btn-block"><i class="fas fa-search"></i> দেখুন</button></div>
            </form>
          </div>
        </div>

        <?php if($student): ?>
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <?php $name = trim(($student['first_name']??'').' '.($student['last_name']??'')); ?>
              <strong><?php echo htmlspecialchars($name ?: ('ID '.$student_id)); ?></strong>
              <div class="text-muted">মোবাইল: <?php echo htmlspecialchars($student['mobile_number'] ?? '-'); ?></div>
            </div>
            <div><button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> প্রিন্ট</button></div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped mb-0">
                <thead><tr><th>#</th><th>তারিখ</th><th>ধরণ</th><th>বিবরণ</th><th class="text-right">ডেবিট</th><th class="text-right">ক্রেডিট</th><th class="text-right">ব্যালেন্স</th></tr></thead>
                <tbody>
                  <?php
                  // Build ledger rows: bills as debit (net_amount on first day of that month), payments as credit
                  $entries=[];
                  foreach($bills as $b){
                    $d = sprintf('%04d-%02d-01', (int)$b['bill_year'], (int)$b['bill_month']);
                    $entries[] = ['date'=>$d, 'type'=>'bill', 'desc'=>'মাসিক বিল ('.$b['bill_month'].'/'.$b['bill_year'].')', 'debit'=>(float)$b['net_amount'], 'credit'=>0.0];
                  }
                  foreach($payments as $p){
                    $entries[] = ['date'=>$p['payment_date'] ?? ($p['created_at'] ?? date('Y-m-d')), 'type'=>'payment', 'desc'=>'পেমেন্ট'.($p[$FC_COL_METHOD]?' - '.$p[$FC_COL_METHOD]:''), 'debit'=>0.0, 'credit'=>(float)$p['amount']];
                  }
                  usort($entries, function($a,$b){ return strcmp($a['date'],$b['date']); });
                  $bal=0.0; $i=1; if(!$entries) echo '<tr><td colspan="7" class="text-center text-muted">ডাটা নেই</td></tr>';
                  foreach($entries as $e){ $bal += $e['debit']; $bal -= $e['credit']; ?>
                    <tr>
                      <td><?php echo $i++; ?></td>
                      <td><?php echo htmlspecialchars($e['date']); ?></td>
                      <td><?php echo $e['type']==='bill'?'বিল':'পেমেন্ট'; ?></td>
                      <td><?php echo htmlspecialchars($e['desc']); ?></td>
                      <td class="text-right"><?php echo $e['debit']>0 ? ('৳ '.number_format($e['debit'],2)) : ''; ?></td>
                      <td class="text-right"><?php echo $e['credit']>0 ? ('৳ '.number_format($e['credit'],2)) : ''; ?></td>
                      <td class="text-right">৳ <?php echo number_format($bal,2); ?></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <?php elseif(isset($_GET['student_id'])): ?>
          <div class="alert alert-warning">শিক্ষার্থী পাওয়া যায়নি</div>
        <?php endif; ?>

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
  function loadStudents(){
    var cid=$('#class_id').val(); var sid=$('#section_id').val();
    if(!cid){ $('#student_id').html('<option value="">প্রথমে শ্রেণি নির্বাচন করুন</option>'); return; }
    $('#student_id').html('<option>লোড হচ্ছে...</option>');
    $.getJSON('<?php echo BASE_URL; ?>admin/ajax/get_students_by_class_section.php',{class_id: cid, section_id: sid||''}, function(resp){
      var list = (resp && Array.isArray(resp.students)) ? resp.students : [];
      if(list.length===0){ $('#student_id').html('<option value="">কোন শিক্ষার্থী নেই</option>'); return; }
      var opts = '<option value="">- নির্বাচন -</option>';
      list.forEach(function(st){ var label = (st.roll_number?('Roll '+st.roll_number+' - '):'') + st.name + (st.section_name?(' ('+st.section_name+')'):''); opts += '<option value="'+st.id+'"'+(<?php echo (int)$student_id; ?>==st.id?' selected':'')+'>'+label+'</option>'; });
      $('#student_id').html(opts);
    }).fail(function(){ $('#student_id').html('<option value="">লোড ব্যর্থ</option>'); });
  }
  var selClass = $('#class_id').val(); if(selClass){ loadSections(selClass); }
  $(document).on('change','#class_id', function(){ var v=$(this).val(); if(v){loadSections(v);} loadStudents(); });
  $(document).on('change','#section_id', function(){ loadStudents(); });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
