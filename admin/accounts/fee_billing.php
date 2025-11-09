<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/inc/finance_helpers.php';
require_once __DIR__ . '/../inc/enrollment_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/sidebar.php';

function fb_post($k,$d=null){ return isset($_POST[$k])?$_POST[$k]:$d; }
function fb_num($v){ return (float)($v!==''?$v:0); }

$err=''; $ok=''; $previewData=[]; $summary=null;
$classes = ($pdo instanceof PDO) ? (get_classes($pdo) ?? []) : [];

$month = isset($_REQUEST['bill_month']) ? (int)$_REQUEST['bill_month'] : (int)date('n');
$year  = isset($_REQUEST['bill_year']) ? (int)$_REQUEST['bill_year'] : (int)date('Y');
$class_id = isset($_REQUEST['class_id']) ? (int)$_REQUEST['class_id'] : 0;
$section_id = (isset($_REQUEST['section_id']) && $_REQUEST['section_id']!=='') ? (int)$_REQUEST['section_id'] : null;
$only_active = isset($_REQUEST['only_active']) ? (int)$_REQUEST['only_active'] : 1;
$overwrite = isset($_REQUEST['overwrite']) ? (int)$_REQUEST['overwrite'] : 0;

// Decide payments table for carry-forward calculations
$PAY_TABLE = 'fee_payments';
$FC_COL_METHOD = 'method'; $FC_COL_REF = 'ref_no';
if ($pdo instanceof PDO) {
  try {
    $cols__fp = $pdo->query("SHOW COLUMNS FROM fee_payments")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if ($cols__fp) {
      $hasBill = in_array('bill_id', $cols__fp, true);
      $hasLegacy = in_array('student_id', $cols__fp, true) && in_array('fee_structure_id', $cols__fp, true);
      if (!$hasBill && $hasLegacy) { $PAY_TABLE = 'fee_bill_payments'; }
      if (!in_array('method',$cols__fp,true) && in_array('payment_method',$cols__fp,true)) $FC_COL_METHOD='payment_method';
      if (!in_array('ref_no',$cols__fp,true) && in_array('transaction_id',$cols__fp,true)) $FC_COL_REF='transaction_id';
    }
  } catch (Throwable $e) { /* ignore */ }
  if ($PAY_TABLE==='fee_bill_payments') {
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS fee_bill_payments (id INT AUTO_INCREMENT PRIMARY KEY, bill_id INT NOT NULL, payment_date DATE NOT NULL, amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, method VARCHAR(50) NULL, ref_no VARCHAR(100) NULL, received_by_user_id INT NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_bill (bill_id), INDEX idx_date (payment_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $e) {}
  }
}

// Helpers to fetch students for selected class/section (year-aware, active-only)
function fb_fetch_students(PDO $pdo, $class_id, $section_id, $only_active=true){
  $students = [];
  $useEnroll = function_exists('enrollment_table_exists') && enrollment_table_exists($pdo);
  if ($useEnroll) {
    $where = ['se.class_id = ?']; $params = [$class_id];
    if ($section_id) { $where[] = 'se.section_id = ?'; $params[] = $section_id; }
    $ayid = current_academic_year_id($pdo);
    if ($ayid) { $where[] = 'se.academic_year_id = ?'; $params[] = (int)$ayid; }
    if ($only_active) {
      // Add only existing columns to avoid Unknown column errors
      try {
        $cols = $pdo->query("SHOW COLUMNS FROM students_enrollment")->fetchAll(PDO::FETCH_COLUMN);
        $actConds = [];
        if (in_array('status', $cols, true)) $actConds[] = "se.status='active'";
        if (in_array('is_active', $cols, true)) $actConds[] = 'se.is_active=1';
        if (in_array('active', $cols, true)) $actConds[] = 'se.active=1';
        if ($actConds) { $where[] = '(' . implode(' OR ', $actConds) . ')'; }
      } catch (Throwable $e) { /* ignore, fallback to no active filter */ }
    }
    $sql = "SELECT s.id, s.first_name, s.last_name, s.mobile_number, se.roll_number, c.name AS class_name, sec.name AS section_name
            FROM students s
            JOIN students_enrollment se ON se.student_id = s.id
            LEFT JOIN classes c ON c.id = se.class_id
            LEFT JOIN sections sec ON sec.id = se.section_id
            WHERE ".implode(' AND ',$where)." ORDER BY se.roll_number ASC, s.first_name ASC";
    $st = $pdo->prepare($sql); $st->execute($params); $students = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } else {
    $where = ['s.class_id = ?']; $params = [$class_id];
    if ($section_id) { $where[] = 's.section_id = ?'; $params[] = $section_id; }
    if ($only_active) {
      // try columns for legacy active status
      try { $cols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('status',$cols,true)) $where[] = "s.status='active'";
        elseif (in_array('is_active',$cols,true)) $where[] = 's.is_active=1';
        elseif (in_array('active',$cols,true)) $where[] = 's.active=1';
      } catch(Throwable $e){}
    }
    $sql = "SELECT s.id, s.first_name, s.last_name, s.mobile_number, s.roll_number, c.name AS class_name, sec.name AS section_name
            FROM students s
            LEFT JOIN classes c ON c.id = s.class_id
            LEFT JOIN sections sec ON sec.id = s.section_id
            WHERE ".implode(' AND ',$where)." ORDER BY s.first_name ASC";
    $st = $pdo->prepare($sql); $st->execute($params); $students = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
  return $students;
}

// Fetch class monthly fee items with section override preference
function fb_class_monthly_items(PDO $pdo, int $class_id, ?int $section_id): array {
  $sql = "SELECT fi.id AS fee_item_id, fi.name,
                 COALESCE(cf_sec.amount, cf_class.amount, fi.amount) AS amount
          FROM fee_items fi
          LEFT JOIN class_fees cf_class ON cf_class.fee_item_id = fi.id AND cf_class.class_id = :cid AND cf_class.section_id IS NULL
          LEFT JOIN class_fees cf_sec ON cf_sec.fee_item_id = fi.id AND cf_sec.class_id = :cid AND cf_sec.section_id = :sid
          WHERE fi.item_type='monthly' AND fi.is_active=1
          ORDER BY fi.name";
  $st=$pdo->prepare($sql); $st->execute([':cid'=>$class_id, ':sid'=>$section_id]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $out=[]; foreach($rows as $r){ $amt = fb_num($r['amount']); if($amt>0){ $out[(int)$r['fee_item_id']] = ['name'=>$r['name'],'amount'=>$amt]; } }
  return $out;
}

// Build preview or generate
$action = isset($_POST['action']) ? $_POST['action'] : '';
if ($action === 'preview' || $action === 'generate') {
  if (!$class_id || !$month || !$year) {
    $err = 'শ্রেণি, মাস ও বছর দিন';
  } elseif (!($pdo instanceof PDO)) {
    $err = 'ডাটাবেজ সংযোগ নেই';
  } else {
    // 1) Pull students
    $students = fb_fetch_students($pdo, $class_id, $section_id, $only_active==1);
    if (!$students) {
      $err = 'কোন শিক্ষার্থী পাওয়া যায়নি';
    } else {
      // 2) Determine fee items for class (with section overrides)
      $items = fb_class_monthly_items($pdo, $class_id, $section_id);
      if (!$items) {
        $err = 'এই শ্রেণির জন্য কোন মাসিক ফি নির্ধারিত নেই';
      } else {
        // 3) Preload overrides for these students & items
        $studentIds = array_map(function($s){ return (int)$s['id']; }, $students);
        $itemIds = array_keys($items);
        $overrides=[];
        if ($studentIds && $itemIds) {
          $inS = implode(',', array_fill(0,count($studentIds),'?'));
          $inI = implode(',', array_fill(0,count($itemIds),'?'));
          $st = $pdo->prepare("SELECT student_id, fee_item_id, discount_type, discount_value FROM student_fee_overrides WHERE student_id IN ($inS) AND fee_item_id IN ($inI)");
          $st->execute(array_merge($studentIds, $itemIds));
          foreach(($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r){ $overrides[(int)$r['student_id']][(int)$r['fee_item_id']]=$r; }
        }
        // 4) Compute preview per student
        $previewData=[]; $totalStudents=0; $totBase=0; $totDisc=0; $totNet=0;
        foreach($students as $s){
          $sid=(int)$s['id']; $totalStudents++;
          $rows=[]; $subBase=0; $subDisc=0; $subNet=0;
          // Carry-forward previous dues up to previous month/year
          try {
            $br = $pdo->prepare("SELECT COALESCE(SUM(net_amount),0) FROM fee_bills WHERE student_id=? AND (bill_year < ? OR (bill_year = ? AND bill_month < ?)) AND status IN ('unpaid','partial')");
            $br->execute([$sid, $year, $year, $month]);
            $prev_net = (float)($br->fetch(PDO::FETCH_COLUMN) ?: 0);
            $pr = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM {$PAY_TABLE} p JOIN fee_bills b ON b.id=p.bill_id WHERE b.student_id=? AND (b.bill_year < ? OR (b.bill_year = ? AND b.bill_month < ?)) AND b.status IN ('unpaid','partial')");
            $pr->execute([$sid, $year, $year, $month]);
            $prev_paid = (float)($pr->fetch(PDO::FETCH_COLUMN) ?: 0);
            $prev_due = max(0.0, $prev_net - $prev_paid);
            if ($prev_due > 0) {
              $rows[] = ['item_id'=>null,'item_name'=>'পূর্বের বকেয়া','amount'=>$prev_due,'discount'=>0.0,'net'=>$prev_due];
              $subBase += $prev_due; $subNet += $prev_due;
            }
          } catch (Throwable $e) { /* ignore carry-forward errors */ }
          foreach($items as $iid=>$it){
            $base = fb_num($it['amount']);
            $disc = 0.0;
            if (isset($overrides[$sid][$iid])) {
              $ov = $overrides[$sid][$iid];
              if ($ov['discount_type']==='percent') $disc = round(($base * fb_num($ov['discount_value'])/100.0), 2);
              else $disc = min(fb_num($ov['discount_value']), $base);
            }
            $net = max(0.0, $base - $disc);
            if ($base<=0 && $net<=0) continue;
            $rows[] = ['item_id'=>$iid,'item_name'=>$it['name'],'amount'=>$base,'discount'=>$disc,'net'=>$net];
            $subBase += $base; $subDisc += $disc; $subNet += $net;
          }
          if ($rows){
            $totBase += $subBase; $totDisc += $subDisc; $totNet += $subNet;
            $previewData[] = [
              'student_id'=>$sid,
              'name'=>trim(($s['first_name']??'').' '.($s['last_name']??'')),
              'roll'=>$s['roll_number']??'',
              'class_name'=>$s['class_name']??'',
              'section_name'=>$s['section_name']??'',
              'items'=>$rows,
              'total_base'=>$subBase,
              'total_discount'=>$subDisc,
              'total_net'=>$subNet
            ];
          }
        }
        $summary = ['students'=>count($previewData),'tot_base'=>$totBase,'tot_disc'=>$totDisc,'tot_net'=>$totNet,'skipped'=>($totalStudents - count($previewData))];

        // 5) If generate, persist bills and items
        if ($action==='generate' && $previewData) {
          $insBill = $pdo->prepare("INSERT INTO fee_bills(student_id, bill_month, bill_year, total_amount, total_discount, net_amount, status) VALUES(?,?,?,?,?,?, 'unpaid')");
          $selBill = $pdo->prepare("SELECT id FROM fee_bills WHERE student_id=? AND bill_year=? AND bill_month=? LIMIT 1");
          $updBill = $pdo->prepare("UPDATE fee_bills SET total_amount=?, total_discount=?, net_amount=? WHERE id=?");
          $delItems = $pdo->prepare("DELETE FROM fee_bill_items WHERE bill_id=?");
          $insItem = $pdo->prepare("INSERT INTO fee_bill_items(bill_id, fee_item_id, description, amount, discount, net_amount) VALUES(?,?,?,?,?,?)");

          $countInserted=0; $countUpdated=0; $countSkipped=0;
          foreach($previewData as $pv){
            $billId=null;
            // find existing
            $selBill->execute([$pv['student_id'],$year,$month]); $row=$selBill->fetch(PDO::FETCH_ASSOC);
            if ($row) {
              if ($overwrite===1) { $billId=(int)$row['id']; $updBill->execute([$pv['total_base'],$pv['total_discount'],$pv['total_net'],$billId]); $delItems->execute([$billId]); $countUpdated++; }
              else { $countSkipped++; continue; }
            } else {
              $insBill->execute([$pv['student_id'],$month,$year,$pv['total_base'],$pv['total_discount'],$pv['total_net']]);
              $billId=(int)$pdo->lastInsertId(); $countInserted++;
            }
            // insert items
            foreach($pv['items'] as $it){ $insItem->execute([$billId,$it['item_id'],$it['item_name'],$it['amount'],$it['discount'],$it['net']]); }
          }
          $ok = 'বিল জেনারেট সম্পন্ন: নতুন ' . $countInserted . '; হালনাগাদ ' . $countUpdated . '; বাদ গেছে ' . $countSkipped . '।';
        }
      }
    }
  }
}

$months=[1=>'জানুয়ারি',2=>'ফেব্রুয়ারি',3=>'মার্চ',4=>'এপ্রিল',5=>'মে',6=>'জুন',7=>'জুলাই',8=>'আগস্ট',9=>'সেপ্টেম্বর',10=>'অক্টোবর',11=>'নভেম্বর',12=>'ডিসেম্বর'];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>মাসিক বিল তৈরি</title>
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
          <div class="col-sm-6"><h1 class="m-0">মাসিক বিল তৈরি</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="index.php">হিসাব</a></li><li class="breadcrumb-item active">মাসিক বিল</li></ol></div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <?php if($ok) echo '<div class="alert alert-success">'.htmlspecialchars($ok).'</div>'; ?>
        <?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>

        <div class="card">
          <div class="card-header bg-primary text-white">বিলিং অপশন</div>
          <div class="card-body">
            <form method="post">
              <div class="form-row">
                <div class="form-group col-md-2">
                  <label>মাস</label>
                  <select name="bill_month" class="form-control" required>
                    <?php foreach($months as $m=>$bn): ?>
                      <option value="<?php echo $m; ?>" <?php echo ($m==$month?'selected':''); ?>><?php echo $bn; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-md-2">
                  <label>সাল</label>
                  <input type="number" name="bill_year" class="form-control" value="<?php echo htmlspecialchars($year); ?>" required>
                </div>
                <div class="form-group col-md-3">
                  <label>শ্রেণি</label>
                  <select name="class_id" id="class_id" class="form-control" required>
                    <option value="">- নির্বাচন -</option>
                    <?php foreach($classes as $c): ?>
                      <option value="<?php echo (int)$c['id']; ?>" <?php echo ($class_id==$c['id']?'selected':''); ?>><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-md-3">
                  <label>শাখা (ঐচ্ছিক)</label>
                  <select name="section_id" id="section_id" class="form-control"><option value="">- নেই -</option></select>
                </div>
                <div class="form-group col-md-2">
                  <label>অপশন</label>
                  <div class="form-check"><input type="checkbox" class="form-check-input" id="only_active" name="only_active" value="1" <?php echo $only_active? 'checked':''; ?>><label for="only_active" class="form-check-label">শুধু সক্রিয়</label></div>
                  <div class="form-check"><input type="checkbox" class="form-check-input" id="overwrite" name="overwrite" value="1" <?php echo $overwrite? 'checked':''; ?>><label for="overwrite" class="form-check-label">বিদ্যমান আপডেট</label></div>
                </div>
              </div>
              <div class="mt-2">
                <button name="action" value="preview" class="btn btn-info"><i class="fas fa-eye"></i> প্রিভিউ</button>
                <button name="action" value="generate" class="btn btn-success" onclick="return confirm('নিশ্চিতভাবে বিল তৈরি করবেন?');"><i class="fas fa-file-invoice"></i> বিল তৈরি</button>
              </div>
            </form>
          </div>
        </div>

        <?php if($previewData): ?>
        <div class="card">
          <div class="card-header">প্রিভিউ ফলাফল</div>
          <div class="card-body">
            <?php if($summary): ?>
              <div class="row mb-3">
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-users"></i></span><div class="info-box-content"><span class="info-box-text">শিক্ষার্থী</span><span class="info-box-number"><?php echo (int)$summary['students']; ?></span></div></div></div>
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-secondary"><i class="fas fa-coins"></i></span><div class="info-box-content"><span class="info-box-text">মোট</span><span class="info-box-number">৳ <?php echo number_format($summary['tot_base'],2); ?></span></div></div></div>
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-percentage"></i></span><div class="info-box-content"><span class="info-box-text">ছাড়</span><span class="info-box-number">৳ <?php echo number_format($summary['tot_disc'],2); ?></span></div></div></div>
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-equals"></i></span><div class="info-box-content"><span class="info-box-text">নেট</span><span class="info-box-number">৳ <?php echo number_format($summary['tot_net'],2); ?></span></div></div></div>
              </div>
            <?php endif; ?>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead><tr><th>#</th><th>শিক্ষার্থী</th><th>রোল</th><th>শাখা</th><th class="text-right">মোট</th><th class="text-right">ছাড়</th><th class="text-right">নেট</th><th>ডিটেইলস</th></tr></thead>
                <tbody>
                  <?php $i=1; foreach($previewData as $pv): ?>
                    <tr>
                      <td><?php echo $i++; ?></td>
                      <td><?php echo htmlspecialchars($pv['name']); ?></td>
                      <td><?php echo htmlspecialchars($pv['roll']); ?></td>
                      <td><?php echo htmlspecialchars($pv['section_name']); ?></td>
                      <td class="text-right">৳ <?php echo number_format($pv['total_base'],2); ?></td>
                      <td class="text-right">৳ <?php echo number_format($pv['total_discount'],2); ?></td>
                      <td class="text-right">৳ <?php echo number_format($pv['total_net'],2); ?></td>
                      <td>
                        <details>
                          <summary>আইটেম</summary>
                          <ul class="mb-0">
                            <?php foreach($pv['items'] as $row): ?>
                              <li><?php echo htmlspecialchars($row['item_name']); ?>: ৳ <?php echo number_format($row['amount'],2); ?><?php if($row['discount']>0): ?> (-<?php echo number_format($row['discount'],2); ?>)<?php endif; ?> = ৳ <?php echo number_format($row['net'],2); ?></li>
                            <?php endforeach; ?>
                          </ul>
                        </details>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </section>
  </div>
  <?php include __DIR__ . '/../inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function(){
  function loadSections(cid){
    $('#section_id').html('<option value="">লোড হচ্ছে...</option>');
    $.get('<?php echo BASE_URL; ?>admin/get_sections.php',{class_id: cid}, function(html){
      $('#section_id').html('<option value="">- নেই -</option>'+(html||''));
    }).fail(function(){ $('#section_id').html('<option value="">- নেই -</option>'); });
  }
  var selClass = $('#class_id').val(); if(selClass){ loadSections(selClass); }
  $(document).on('change','#class_id', function(){ var v=$(this).val(); if(v){loadSections(v);} else { $('#section_id').html('<option value="">- নেই -</option>'); } });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
