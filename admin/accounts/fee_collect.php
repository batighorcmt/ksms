<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/inc/finance_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../inc/enrollment_helpers.php';
require_once __DIR__ . '/../inc/sms_api.php';

function fc_post($k,$d=null){ return isset($_POST[$k])?$_POST[$k]:$d; }
function fc_num($v){ return (float)($v!==''?$v:0); }

// Basic CSRF & double-submit protection
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
function fc_check_csrf(){
  if ($_SERVER['REQUEST_METHOD']==='POST'){
    $tok = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
    if (!$tok || !hash_equals($_SESSION['csrf_token'], $tok)) {
      throw new Exception('অবৈধ অনুরোধ (CSRF)');
    }
  }
}

$err=''; $ok='';
$classes = ($pdo instanceof PDO) ? (get_classes($pdo) ?? []) : [];

// Decide payments table based on existing schema: if legacy fee_payments (student_id/fee_structure_id and no bill_id) exists, use dedicated fee_bill_payments
$PAY_TABLE = 'fee_payments';
$isLegacyPayments = false;
$FP_HAS_STUDENT = false; // whether fee_payments has a student_id column (legacy/CI schema)
if ($pdo instanceof PDO) {
  try {
    $cols__fp = $pdo->query("SHOW COLUMNS FROM fee_payments")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if ($cols__fp) {
      $hasBill = in_array('bill_id', $cols__fp, true);
      $FP_HAS_STUDENT = in_array('student_id', $cols__fp, true);
      $hasLegacyStruct = in_array('fee_structure_id', $cols__fp, true);
      // If the legacy fee_structure_id column exists at all, treat fee_payments as legacy and DO NOT write into it
      if ($hasLegacyStruct) {
        $isLegacyPayments = true;
      } elseif (!$hasBill && $FP_HAS_STUDENT) {
        // Older schemas with only student_id and no bill_id are also legacy
        $isLegacyPayments = true;
      }
    }
  } catch (Throwable $e) { /* table may not exist */ }
}
if ($isLegacyPayments) { $PAY_TABLE = 'fee_bill_payments'; }

// Ensure fee_payments table has the columns we need; add if missing (non-destructive)
if ($pdo instanceof PDO && !$isLegacyPayments) {
  try {
    // Create if not exists (no-op when exists)
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_payments (
      id INT AUTO_INCREMENT PRIMARY KEY,
      bill_id INT NOT NULL,
      payment_date DATE NOT NULL,
      amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      method VARCHAR(50) NULL,
      ref_no VARCHAR(100) NULL,
      received_by_user_id INT NULL,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_bill (bill_id),
      INDEX idx_date (payment_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Introspect and add missing columns for legacy tables
  $cols = $pdo->query("SHOW COLUMNS FROM fee_payments")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $alter = [];
    if (!in_array('bill_id',$cols,true)) $alter[] = 'ADD COLUMN bill_id INT NOT NULL DEFAULT 0 AFTER id';
    if (!in_array('payment_date',$cols,true)) $alter[] = 'ADD COLUMN payment_date DATE NULL AFTER bill_id';
    if (!in_array('amount',$cols,true)) $alter[] = 'ADD COLUMN amount DECIMAL(10,2) NOT NULL DEFAULT 0.00';
    if (!in_array('method',$cols,true) && !in_array('payment_method',$cols,true)) $alter[] = 'ADD COLUMN method VARCHAR(50) NULL';
    if (!in_array('ref_no',$cols,true) && !in_array('transaction_id',$cols,true)) $alter[] = 'ADD COLUMN ref_no VARCHAR(100) NULL';
  if (!in_array('received_by_user_id',$cols,true)) $alter[] = 'ADD COLUMN received_by_user_id INT NULL';
  if (!in_array('note',$cols,true)) $alter[] = 'ADD COLUMN note VARCHAR(255) NULL';
    if (!in_array('created_at',$cols,true)) $alter[] = 'ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP';
    if ($alter) { $pdo->exec('ALTER TABLE fee_payments '.implode(', ',$alter)); }

    // Add index on bill if missing
    // MySQL lacks simple IF NOT EXISTS for indexes pre-8.0; try-catch
    try { $pdo->exec('CREATE INDEX idx_bill ON fee_payments(bill_id)'); } catch(Throwable $e) {}
    try { $pdo->exec('CREATE INDEX idx_date ON fee_payments(payment_date)'); } catch(Throwable $e) {}
  } catch (Throwable $e) {
    // ignore; fallback logic below will still try to run
  }
}

// Determine compatible column names for legacy vs new schema
$FC_COL_METHOD = 'method';
$FC_COL_REF = 'ref_no';
if ($pdo instanceof PDO && $PAY_TABLE==='fee_payments') {
  try {
    $cols = $pdo->query("SHOW COLUMNS FROM fee_payments")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (!in_array('method', $cols, true) && in_array('payment_method', $cols, true)) {
      $FC_COL_METHOD = 'payment_method';
    }
    if (!in_array('ref_no', $cols, true) && in_array('transaction_id', $cols, true)) {
      $FC_COL_REF = 'transaction_id';
    }
  } catch (Throwable $e) { /* ignore */ }
}

// If using separate table for Accounts payments, ensure it exists
if ($pdo instanceof PDO && $PAY_TABLE==='fee_bill_payments') {
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_bill_payments (
      id INT AUTO_INCREMENT PRIMARY KEY,
      bill_id INT NOT NULL,
      payment_date DATE NOT NULL,
      amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      method VARCHAR(50) NULL,
      ref_no VARCHAR(100) NULL,
      note VARCHAR(255) NULL,
      received_by_user_id INT NULL,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_bill (bill_id),
      INDEX idx_date (payment_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Ensure note exists
    try { $cols = $pdo->query("SHOW COLUMNS FROM fee_bill_payments")->fetchAll(PDO::FETCH_COLUMN) ?: []; if (!in_array('note',$cols,true)) { $pdo->exec("ALTER TABLE fee_bill_payments ADD COLUMN note VARCHAR(255) NULL AFTER ref_no"); } } catch(Throwable $e){}
  } catch (Throwable $e) { /* ignore */ }
}

// Ensure event tables exist for ad-hoc fees
if ($pdo instanceof PDO) {
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_events (
      id INT AUTO_INCREMENT PRIMARY KEY,
      academic_year_id INT NULL,
      name VARCHAR(150) NOT NULL,
      class_id INT NULL,
      section_id INT NULL,
      amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      due_date DATE NULL,
      fine_enabled TINYINT(1) NOT NULL DEFAULT 0,
      fine_type ENUM('flat','percent','per_day') DEFAULT 'flat',
      fine_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_year (academic_year_id),
      INDEX idx_class_section (class_id, section_id),
      INDEX idx_due (due_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Throwable $e) { /* ignore */ }
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_event_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      event_id INT NOT NULL,
      student_id INT NOT NULL,
      bill_id INT NOT NULL,
      amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_event_student (event_id, student_id),
      INDEX idx_bill (bill_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Throwable $e) { /* ignore */ }
}

$bill_id = isset($_REQUEST['bill_id']) ? (int)$_REQUEST['bill_id'] : 0;
$month = isset($_REQUEST['bill_month']) ? (int)$_REQUEST['bill_month'] : (int)date('n');
$year  = isset($_REQUEST['bill_year']) ? (int)$_REQUEST['bill_year'] : (int)date('Y');
$class_id = isset($_REQUEST['class_id']) ? (int)$_REQUEST['class_id'] : 0;
$section_id = (isset($_REQUEST['section_id']) && $_REQUEST['section_id']!=='') ? (int)$_REQUEST['section_id'] : null;
$student_id = isset($_REQUEST['student_id']) ? (int)$_REQUEST['student_id'] : 0;

// Convenience: load latest bill for a student
if (isset($_GET['latest']) && $student_id>0 && ($pdo instanceof PDO)) {
  try {
    $st = $pdo->prepare("SELECT id FROM fee_bills WHERE student_id=? ORDER BY bill_year DESC, bill_month DESC, id DESC LIMIT 1");
    $st->execute([$student_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) { $bill_id = (int)$row['id']; }
  } catch (Throwable $e) { /* ignore */ }
}

$months=[1=>'জানুয়ারি',2=>'ফেব্রুয়ারি',3=>'মার্চ',4=>'এপ্রিল',5=>'মে',6=>'জুন',7=>'জুলাই',8=>'আগস্ট',9=>'সেপ্টেম্বর',10=>'অক্টোবর',11=>'নভেম্বর',12=>'ডিসেম্বর'];

$bill=null; $bill_items=[]; $payments=[]; $tot_paid=0.0; $due=0.0; $student=null;

function fc_load_bill(PDO $pdo, $bill_id, $student_id, $year, $month){
  if ($bill_id>0) {
    $st=$pdo->prepare("SELECT * FROM fee_bills WHERE id=?"); $st->execute([$bill_id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }
  if ($student_id>0 && $year>0 && $month>0){
    $st=$pdo->prepare("SELECT * FROM fee_bills WHERE student_id=? AND bill_year=? AND bill_month=? LIMIT 1");
    $st->execute([$student_id,$year,$month]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }
  return null;
}

function fc_bill_items(PDO $pdo, $bill_id){
  $st=$pdo->prepare("SELECT * FROM fee_bill_items WHERE bill_id=? ORDER BY id ASC");
  $st->execute([$bill_id]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fc_payments(PDO $pdo, $bill_id){
  global $PAY_TABLE;
  $st=$pdo->prepare("SELECT * FROM {$PAY_TABLE} WHERE bill_id=? ORDER BY COALESCE(created_at, payment_date) DESC, id DESC");
  $st->execute([$bill_id]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fc_student(PDO $pdo, $sid){
  // Select only columns guaranteed to exist in legacy schemas
  $st=$pdo->prepare("SELECT id, first_name, last_name, mobile_number FROM students WHERE id=?");
  $st->execute([$sid]);
  return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function fc_student_class_section(PDO $pdo, int $sid): array {
  // Returns ['class_id'=>?, 'section_id'=>?]
  // Prefer enrollment table for current year
  try {
    $enCols = $pdo->query("SHOW TABLES LIKE 'students_enrollment'")->fetch(PDO::FETCH_NUM);
    if ($enCols) {
      $ay = null;
      try { $row = $pdo->query("SELECT id FROM academic_years WHERE is_current=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC); $ay = $row? (int)$row['id'] : null; } catch(Throwable $e){}
      if ($ay) {
        $st=$pdo->prepare("SELECT class_id, section_id FROM students_enrollment WHERE student_id=? AND academic_year_id=? ORDER BY id DESC LIMIT 1");
        $st->execute([$sid,$ay]); $r=$st->fetch(PDO::FETCH_ASSOC);
        if ($r) return ['class_id'=> (int)$r['class_id'], 'section_id'=> isset($r['section_id'])? (int)$r['section_id'] : null];
      } else {
        $st=$pdo->prepare("SELECT class_id, section_id FROM students_enrollment WHERE student_id=? ORDER BY id DESC LIMIT 1");
        $st->execute([$sid]); $r=$st->fetch(PDO::FETCH_ASSOC);
        if ($r) return ['class_id'=> (int)$r['class_id'], 'section_id'=> isset($r['section_id'])? (int)$r['section_id'] : null];
      }
    }
  } catch (Throwable $e) { /* ignore */ }
  // Fallback to students table
  try {
    $st=$pdo->prepare("SELECT class_id, section_id FROM students WHERE id=?");
    $st->execute([$sid]); $r=$st->fetch(PDO::FETCH_ASSOC);
    if ($r) return ['class_id'=> (int)($r['class_id'] ?? 0), 'section_id'=> isset($r['section_id'])? (int)$r['section_id'] : null];
  } catch (Throwable $e) {}
  return ['class_id'=>0,'section_id'=>null];
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
if ($pdo instanceof PDO) {
  // Handle AJAX-like quick actions (single event add, remove item) and guard with CSRF
  if ($action==='add_event_item_quick') {
    try { fc_check_csrf(); } catch(Throwable $e){ $err=$e->getMessage(); }
    if (!$err) {
      $evId = (int)fc_post('event_id',0);
      $target_bill_id = (int)fc_post('pay_bill_id',0);
      if ($evId<=0 || $target_bill_id<=0) { $err='ডাটা অসম্পূর্ণ'; }
    }
    if (!$err) {
      // Reuse existing multi-add logic with a single selected event
      $_POST['event_ids'] = [$evId];
      $_POST['action'] = 'add_events_to_bill';
      // fall-through to handler below after this block
    }
  }
  if ($action==='remove_bill_item') {
    try { fc_check_csrf(); } catch(Throwable $e){ $err=$e->getMessage(); }
    $item_id = (int)fc_post('item_id',0);
    $pbid = (int)fc_post('pay_bill_id',0);
    if (!$err && ($item_id<=0 || $pbid<=0)) { $err='ডাটা অসম্পূর্ণ'; }
    if (!$err) {
      try {
        $pdo->beginTransaction();
        // Ensure item belongs to the bill
        $st = $pdo->prepare('SELECT id FROM fee_bill_items WHERE id=? AND bill_id=?');
        $st->execute([$item_id,$pbid]);
        $exists = $st->fetch(PDO::FETCH_ASSOC);
        if (!$exists) { throw new Exception('আইটেম পাওয়া যায়নি'); }
        // Delete the item
        $pdo->prepare('DELETE FROM fee_bill_items WHERE id=?')->execute([$item_id]);
        // Recompute totals from items
        $stt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) a, COALESCE(SUM(net_amount),0) n FROM fee_bill_items WHERE bill_id=?');
        $stt->execute([$pbid]); $t = $stt->fetch(PDO::FETCH_ASSOC) ?: ['a'=>0,'n'=>0];
        $pdo->prepare('UPDATE fee_bills SET total_amount=?, net_amount=? WHERE id=?')->execute([ (float)$t['a'], (float)$t['n'], $pbid ]);
        // Update status based on payments
        $stp = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM {$PAY_TABLE} WHERE bill_id=?");
        $stp->execute([$pbid]); $paid=(float)($stp->fetch(PDO::FETCH_ASSOC)['s'] ?? 0);
        $stb = $pdo->prepare('SELECT net_amount FROM fee_bills WHERE id=?'); $stb->execute([$pbid]); $net=(float)($stb->fetch(PDO::FETCH_ASSOC)['net_amount'] ?? 0);
        $status = ($paid <= 0) ? 'unpaid' : (($paid + 0.0001 < $net) ? 'partial' : 'paid');
        $pdo->prepare('UPDATE fee_bills SET status=? WHERE id=?')->execute([$status, $pbid]);
        $pdo->commit();
        // Redirect (PRG) to avoid resubmission
        header('Location: '.ADMIN_URL.'accounts/fee_collect.php?bill_id='.$pbid.'&msg=removed');
        exit;
      } catch(Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = 'আইটেম বাদ ব্যর্থ: '.$e->getMessage();
      }
    }
  }
  // Handle event additions
  if ($action==='add_events_to_bill' || $action==='create_events_bill') {
    try { fc_check_csrf(); } catch(Throwable $e){ $err=$e->getMessage(); }
    $sel = isset($_POST['event_ids']) && is_array($_POST['event_ids']) ? array_map('intval', $_POST['event_ids']) : [];
    $target_bill_id = ($action==='add_events_to_bill') ? (int)($_POST['pay_bill_id'] ?? 0) : 0;
    $student_for_events = (int)($_POST['student_id'] ?? 0);
    if (!$sel) { $err='কোন ইভেন্ট নির্বাচন করা হয়নি'; }
    if (!$err && $action==='add_events_to_bill' && $target_bill_id<=0) { $err='বিল আইডি পাওয়া যায়নি'; }
    if (!$err) {
      try {
        $pdo->beginTransaction();
        // Load events
        $in = implode(',', array_fill(0, count($sel), '?'));
        $st = $pdo->prepare("SELECT * FROM fee_events WHERE id IN ($in) AND is_active=1");
        $st->execute($sel);
        $events = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$events) { throw new Exception('ইভেন্ট পাওয়া যায়নি'); }
        // Determine student id and month/year context
        $effective_student_id = $student_for_events;
        $bill_month_ctx = (int)date('n');
        $bill_year_ctx = (int)date('Y');
        if ($action==='add_events_to_bill') {
          $b = fc_load_bill($pdo, $target_bill_id, 0, 0, 0);
          if (!$b) throw new Exception('বিল পাওয়া যায়নি');
          $effective_student_id = (int)$b['student_id'];
          $bill_month_ctx = (int)$b['bill_month'];
          $bill_year_ctx = (int)$b['bill_year'];
        } else {
          if ($effective_student_id<=0) throw new Exception('শিক্ষার্থী নির্ধারণ হয়নি');
        }

        // Check duplicates and compute fines
        $already = [];
        $dupSt = $pdo->prepare("SELECT event_id FROM fee_event_items WHERE event_id IN ($in) AND student_id = ?");
        $dupParams = $sel; $dupParams[] = $effective_student_id; $dupSt->execute($dupParams);
        foreach(($dupSt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r){ $already[(int)$r['event_id']] = true; }

        // Create target bill if needed (respect unique (student_id,bill_year,bill_month))
        $targetBillId = $target_bill_id;
        $reusedExistingBill = false;
        if ($action==='create_events_bill') {
          // If a monthly bill already exists for this student and month-year, reuse it instead of creating a duplicate (unique constraint)
          $stExist = $pdo->prepare("SELECT id FROM fee_bills WHERE student_id=? AND bill_year=? AND bill_month=? LIMIT 1");
          $stExist->execute([$effective_student_id, $bill_year_ctx, $bill_month_ctx]);
          $exist = $stExist->fetch(PDO::FETCH_ASSOC);
          if ($exist) {
            $targetBillId = (int)$exist['id'];
            $reusedExistingBill = true;
          } else {
            try {
              $insBill = $pdo->prepare("INSERT INTO fee_bills(student_id, bill_month, bill_year, total_amount, total_discount, net_amount, status) VALUES(?,?,?,?,?,?, 'unpaid')");
              $insBill->execute([$effective_student_id, $bill_month_ctx, $bill_year_ctx, 0, 0, 0]);
              $targetBillId = (int)$pdo->lastInsertId();
            } catch (Throwable $eIns) {
              // Handle potential duplicate due to race condition: fetch existing and reuse
              if (strpos($eIns->getMessage(), 'Duplicate entry') !== false) {
                $stExist2 = $pdo->prepare("SELECT id FROM fee_bills WHERE student_id=? AND bill_year=? AND bill_month=? LIMIT 1");
                $stExist2->execute([$effective_student_id, $bill_year_ctx, $bill_month_ctx]);
                $exist2 = $stExist2->fetch(PDO::FETCH_ASSOC);
                if (!$exist2) { throw $eIns; }
                $targetBillId = (int)$exist2['id'];
                $reusedExistingBill = true;
              } else { throw $eIns; }
            }
          }
        }

        // Prepare item insert and bill update
        $insItem = $pdo->prepare("INSERT INTO fee_bill_items(bill_id, fee_item_id, description, amount, discount, net_amount) VALUES(?,?,?,?,?,?)");
        $updBill = $pdo->prepare("UPDATE fee_bills SET total_amount = total_amount + ?, net_amount = net_amount + ? WHERE id = ?");
        $insEvItem = $pdo->prepare("INSERT INTO fee_event_items(event_id, student_id, bill_id, amount, fine_amount) VALUES(?,?,?,?,?)");

        $sumAmt = 0.0; $sumNet = 0.0;
        $today = date('Y-m-d');
        foreach ($events as $ev) {
          $eid = (int)$ev['id'];
          if (!empty($already[$eid])) { continue; }
          $base = (float)$ev['amount'];
          // compute fine
          $fine = 0.0;
          if ((int)$ev['fine_enabled'] === 1 && !empty($ev['due_date']) && $today > $ev['due_date']) {
            if ($ev['fine_type']==='flat') { $fine = (float)$ev['fine_value']; }
            elseif ($ev['fine_type']==='percent') { $fine = round($base * ((float)$ev['fine_value'])/100.0, 2); }
            elseif ($ev['fine_type']==='per_day') {
              $days = (int)floor((strtotime($today) - strtotime($ev['due_date']))/86400);
              if ($days > 0) $fine = round($days * (float)$ev['fine_value'], 2);
            }
          }
          // Insert bill items
          $desc = 'ইভেন্ট: ' . ($ev['name'] ?? ('#'.$eid));
          $insItem->execute([$targetBillId, null, $desc, $base, 0, $base]);
          $sumAmt += $base; $sumNet += $base;
          if ($fine > 0) {
            $insItem->execute([$targetBillId, null, 'ইভেন্ট জরিমানা: '.($ev['name'] ?? ('#'.$eid)), $fine, 0, $fine]);
            $sumAmt += $fine; $sumNet += $fine;
          }
          // Link event-student-bill
          $insEvItem->execute([$eid, $effective_student_id, $targetBillId, $base, $fine]);
        }
        if ($sumNet <= 0) { throw new Exception('নতুন কোন আইটেম যোগ হয়নি অথবা আগে যোগ করা হয়েছে'); }
        $updBill->execute([$sumAmt, $sumNet, $targetBillId]);

        // Recompute bill status
        $stp=$pdo->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM {$PAY_TABLE} WHERE bill_id=?");
        $stp->execute([$targetBillId]); $paid = (float)($stp->fetch(PDO::FETCH_ASSOC)['s'] ?? 0);
        $rowB=$pdo->prepare("SELECT net_amount FROM fee_bills WHERE id=?"); $rowB->execute([$targetBillId]); $net=(float)($rowB->fetch(PDO::FETCH_ASSOC)['net_amount'] ?? 0);
        $status = ($paid <= 0) ? 'unpaid' : (($paid + 0.0001 < $net) ? 'partial' : 'paid');
        $pdo->prepare("UPDATE fee_bills SET status=? WHERE id=?")->execute([$status, $targetBillId]);

        $pdo->commit();
        // Redirect after success to avoid duplicate submit on reload
        $redirMsg = ($action==='add_events_to_bill') ? 'added' : ($reusedExistingBill ? 'reused' : 'created');
        header('Location: '.ADMIN_URL.'accounts/fee_collect.php?bill_id='.$targetBillId.'&msg='.$redirMsg);
        exit;
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = 'অপারেশন ব্যর্থ: '.$e->getMessage();
      }
    }
  }

  if ($action==='pay') {
    try { fc_check_csrf(); } catch(Throwable $e){ $err=$e->getMessage(); }
    $pay_bill_id = (int)fc_post('pay_bill_id',0);
    $amount = fc_num(fc_post('amount','0'));
    $method = trim((string)fc_post('method','cash'));
    $reference = trim((string)fc_post('reference',''));
  $note = trim((string)fc_post('note',''));
  $send_sms = isset($_POST['send_sms']);
  $sms_template = isset($_POST['sms_template']) ? (string)$_POST['sms_template'] : 'default';
  $sms_custom = isset($_POST['sms_custom']) ? (string)$_POST['sms_custom'] : '';
    $paid_date = trim((string)fc_post('paid_date', date('Y-m-d')));
    if ($pay_bill_id<=0) $err='অবৈধ বিল আইডি';
    if ($amount<=0) $err='সঠিক পরিমাণ লিখুন';

    if (!$err) {
      // Load bill to compute remaining due
      $bill = fc_load_bill($pdo, $pay_bill_id, 0, 0, 0);
      if (!$bill) { $err='বিল পাওয়া যায়নি'; }
    }

    if (!$err && $bill) {
      // recompute paid in two steps
  global $PAY_TABLE;
  $stp=$pdo->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM {$PAY_TABLE} WHERE bill_id=?");
      $stp->execute([$pay_bill_id]);
      $row=$stp->fetch(PDO::FETCH_ASSOC); $tot_paid_before=(float)($row['s'] ?? 0);
      $due_before = max(0.0, (float)$bill['net_amount'] - $tot_paid_before);
      if ($amount > $due_before) { $amount = $due_before; }
      if ($amount<=0){ $err='এই বিলের কোন বাকি নেই'; }

      if (!$err) {
        try {
          global $PAY_TABLE;
          if ($PAY_TABLE==='fee_payments') {
            if ($FP_HAS_STUDENT) {
              $sqlIns = "INSERT INTO fee_payments(student_id, bill_id, payment_date, amount, {$FC_COL_METHOD}, {$FC_COL_REF}, note, received_by_user_id) VALUES(?,?,?,?,?,?,?,?)";
              $params = [ (int)$bill['student_id'], $pay_bill_id, ($paid_date ?: date('Y-m-d')), $amount, $method ?: 'cash', $reference ?: null, ($note !== '' ? $note : null), null ];
            } else {
              $sqlIns = "INSERT INTO fee_payments(bill_id, payment_date, amount, {$FC_COL_METHOD}, {$FC_COL_REF}, note, received_by_user_id) VALUES(?,?,?,?,?,?,?)";
              $params = [$pay_bill_id, ($paid_date ?: date('Y-m-d')), $amount, $method ?: 'cash', $reference ?: null, ($note !== '' ? $note : null), null];
            }
          } else {
            $sqlIns = "INSERT INTO fee_bill_payments(bill_id, payment_date, amount, method, ref_no, note, received_by_user_id) VALUES(?,?,?,?,?,?,?)";
            $params = [$pay_bill_id, ($paid_date ?: date('Y-m-d')), $amount, $method ?: 'cash', $reference ?: null, ($note !== '' ? $note : null), null];
          }
          $ins=$pdo->prepare($sqlIns);
          $ins->execute($params);

          // Recompute total paid and update status
          $stp2=$pdo->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM {$PAY_TABLE} WHERE bill_id=?");
          $stp2->execute([$pay_bill_id]);
          $row2=$stp2->fetch(PDO::FETCH_ASSOC); $tot_paid = (float)($row2['s'] ?? 0);
          $net = (float)$bill['net_amount'];
          $status = ($tot_paid <= 0) ? 'unpaid' : (($tot_paid + 0.0001 < $net) ? 'partial' : 'paid');
          $up=$pdo->prepare("UPDATE fee_bills SET status=? WHERE id=?");
          $up->execute([$status, $pay_bill_id]);

          $ok = 'পেমেন্ট সংরক্ষণ হয়েছে';
          // SMS notify (optional)
          if ($send_sms && function_exists('send_sms')) {
            try {
              $stu = $student ?: fc_student($pdo, (int)$bill['student_id']);
              $mobile = $stu['mobile_number'] ?? '';
              if ($mobile) {
                $remain = max(0.0, (float)$bill['net_amount'] - ($tot_paid_before + $amount));
                // Build message from template
                $student_name = trim(($stu['first_name']??'').' '.($stu['last_name']??'')) ?: ('ID#'.(int)$bill['student_id']);
                $vars = [
                  '{student_name}' => $student_name,
                  '{bill_id}' => (string)(int)$bill['id'],
                  '{amount}' => number_format((float)$amount,2),
                  '{due}' => number_format($remain,2),
                  '{date}' => ($paid_date ?: date('Y-m-d')),
                ];
                $templates = [
                  'default' => 'আপনার বিল #{bill_id} এ ৳{amount} পরিশোধ হয়েছে। বাকি ৳{due}। ধন্যবাদ।',
                  'short'   => 'বিল#{bill_id}: ৳{amount} পেমেন্ট। বাকি ৳{due}.',
                  'bn_full' => '{student_name}, আপনার বিল #{bill_id} তারিখ {date} এ ৳{amount} পরিশোধ হয়েছে। বকেয়া ৳{due}।',
                ];
                $tmpl = $sms_custom !== '' ? $sms_custom : ($templates[$sms_template] ?? $templates['default']);
                $msg = strtr($tmpl, $vars);
                @send_sms($mobile, $msg);
              }
            } catch (Throwable $e) { /* ignore */ }
          }
          // Redirect to avoid resubmission on reload
          header('Location: '.ADMIN_URL.'accounts/fee_collect.php?bill_id='.$pay_bill_id.'&msg=paid');
          exit;
        } catch(Throwable $e){ $err='সংরক্ষণ ব্যর্থ: '.$e->getMessage(); }
      }
    }
  }

  // Load bill for display (after load action, or after payment)
  if (!$bill) {
    $bill = fc_load_bill($pdo, $bill_id, $student_id, $year, $month);
  }
  if ($bill) {
    $bill_items = fc_bill_items($pdo, (int)$bill['id']);
    $payments = fc_payments($pdo, (int)$bill['id']);
  global $PAY_TABLE;
  $stp=$pdo->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM {$PAY_TABLE} WHERE bill_id=?");
    $stp->execute([(int)$bill['id']]);
    $row=$stp->fetch(PDO::FETCH_ASSOC); $tot_paid=(float)($row['s'] ?? 0);
    $due = max(0.0, (float)$bill['net_amount'] - $tot_paid);
    $student = fc_student($pdo, (int)$bill['student_id']);
  }
}
// After handling any POST actions and potential redirects, load header & sidebar
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/sidebar.php';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ফি সংগ্রহ</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <style>
    body{font-family:'SolaimanLipi','Source Sans Pro',sans-serif}
    .receipt-area{border:1px solid #ddd;padding:16px;background:#fff}
    @media print{
      body{background:#fff}
      .no-print{display:none!important}
      .receipt-area{border:none;padding:0}
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">ফি সংগ্রহ</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="index.php">হিসাব</a></li><li class="breadcrumb-item active">ফি সংগ্রহ</li></ol></div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <?php if(isset($_GET['msg'])){
          $m = $_GET['msg'];
          $texts = [
            'added' => 'ইভেন্ট বিলে যোগ হয়েছে',
            'reused'=> 'এই মাসের বিল আগেই ছিল; ইভেন্টগুলো সেই বিলে যোগ হয়েছে',
            'created'=>'ইভেন্টের নতুন বিল তৈরি হয়েছে',
            'paid'  => 'পেমেন্ট সংরক্ষণ হয়েছে',
            'removed'=>'আইটেম বিল থেকে বাদ দেয়া হয়েছে',
          ];
          if(isset($texts[$m])) echo '<div class="alert alert-success">'.htmlspecialchars($texts[$m]).'</div>';
        } ?>
        <?php if($ok) echo '<div class="alert alert-success">'.htmlspecialchars($ok).'</div>'; ?>
        <?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>

        <div class="card">
          <div class="card-header bg-primary text-white">বিল খুঁজুন</div>
          <div class="card-body">
            <form method="get" class="mb-3">
                <div class="form-row align-items-end">
                <div class="form-group col-md-2">
                  <label>বিল আইডি</label>
                  <input type="number" name="bill_id" class="form-control" value="<?php echo (int)$bill_id; ?>" placeholder="সরাসরি বিল আইডি">
                </div>
                  <div class="form-group col-md-2">
                    <label>শিক্ষার্থী আইডি</label>
                    <input type="number" name="student_id" class="form-control" value="<?php echo (int)$student_id; ?>" placeholder="যেমন: 1023">
                    <small><button name="latest" value="1" class="btn btn-link p-0">সর্বশেষ বিল লোড</button></small>
                  </div>
                <div class="col-md-1 text-center">অথবা</div>
                <div class="form-group col-md-2">
                  <label>মাস</label>
                  <select name="bill_month" class="form-control">
                    <?php foreach($months as $m=>$bn): ?>
                      <option value="<?php echo $m; ?>" <?php echo ($m==$month?'selected':''); ?>><?php echo $bn; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-md-2">
                  <label>সাল</label>
                  <input type="number" name="bill_year" class="form-control" value="<?php echo htmlspecialchars($year); ?>">
                </div>
                <div class="form-group col-md-2">
                  <label>শ্রেণি</label>
                  <select name="class_id" id="class_id" class="form-control">
                    <option value="">- নির্বাচন -</option>
                    <?php foreach($classes as $c): ?>
                      <option value="<?php echo (int)$c['id']; ?>" <?php echo ($class_id==$c['id']?'selected':''); ?>><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-md-2">
                  <label>শাখা</label>
                  <select name="section_id" id="section_id" class="form-control"><option value="">- সব -</option></select>
                </div>
                <div class="form-group col-md-3">
                  <label>শিক্ষার্থী</label>
                  <select name="student_id" id="student_id" class="form-control">
                    <option value="">প্রথমে শ্রেণি/শাখা নির্বাচন করুন</option>
                  </select>
                </div>
                <div class="form-group col-md-2">
                  <button class="btn btn-info btn-block"><i class="fas fa-search"></i> লোড</button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <?php if($bill): $status=$bill['status']; ?>
        <div class="row">
          <div class="col-md-12">
            <div class="card">
              <div class="card-header">ফিস সংগ্রহ</div>
              <div class="card-body">
                <div class="row mb-3">
                  <div class="col-7">
                    <div><strong>শিক্ষার্থী:</strong> <?php echo htmlspecialchars(trim(($student['first_name']??'').' '.($student['last_name']??'')) ?: ('ID#'.$bill['student_id'])); ?></div>
                    <div><strong>যোগাযোগ:</strong> <?php echo htmlspecialchars($student['mobile_number'] ?? '-'); ?></div>
                  </div>
                  <div class="col-5 text-right">
                    <div><strong>বিল:</strong> #<?php echo (int)$bill['id']; ?> (<?php echo htmlspecialchars($months[(int)$bill['bill_month']] ?? ''); ?>, <?php echo (int)$bill['bill_year']; ?>)</div>
                    <div><strong>স্ট্যাটাস:</strong> <span class="badge badge-<?php echo $status==='paid'?'success':($status==='partial'?'warning':'secondary'); ?>"><?php echo htmlspecialchars($status); ?></span></div>
                  </div>
                </div>
                <div class="d-flex justify-content-between mb-3">
                  <div>মোট বিল</div><div>৳ <?php echo number_format((float)$bill['net_amount'],2); ?></div>
                </div>
                <div class="d-flex justify-content-between mb-3">
                  <div>পরিশোধিত</div><div>৳ <?php echo number_format($tot_paid,2); ?></div>
                </div>
                <div class="d-flex justify-content-between mb-3">
                  <div>বাকি</div><div><strong>৳ <?php echo number_format($due,2); ?></strong></div>
                </div>
                <form method="post" class="no-print">
                  <input type="hidden" name="action" value="pay">
                  <input type="hidden" name="pay_bill_id" value="<?php echo (int)$bill['id']; ?>">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                  <div class="form-group">
                    <label>পরিমাণ (৳)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?php echo htmlspecialchars($due>0?number_format($due,2,'.',''):''); ?>" required>
                    <small class="text-muted">বাকি: ৳ <?php echo number_format($due,2); ?></small>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label>পদ্ধতি</label>
                      <select name="method" class="form-control">
                        <option value="cash">ক্যাশ</option>
                        <option value="bkash">বিকাশ</option>
                        <option value="nagad">নগদ</option>
                        <option value="bank">ব্যাংক</option>
                      </select>
                    </div>
                    <div class="form-group col-md-6">
                      <label>তারিখ</label>
                      <input type="date" name="paid_date" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                    </div>
                  </div>
                  <div class="form-group">
                    <label>রেফারেন্স</label>
                    <input type="text" name="reference" class="form-control" placeholder="TXN/চালান/চেক নং">
                  </div>
                  <div class="form-group">
                    <label>নোট</label>
                    <input type="text" name="note" class="form-control" placeholder="ঐচ্ছিক">
                  </div>
                  <div class="form-group">
                    <label>এসএমএস টেমপ্লেট</label>
                    <select name="sms_template" class="form-control">
                      <option value="default">ডিফল্ট</option>
                      <option value="short">সংক্ষিপ্ত</option>
                      <option value="bn_full">বিস্তারিত (বাংলা)</option>
                    </select>
                    <small class="text-muted">কাস্টম বার্তা (ঐচ্ছিক): {student_name}, {bill_id}, {amount}, {due}, {date} ব্যবহার করতে পারবেন</small>
                    <input type="text" name="sms_custom" class="form-control mt-1" placeholder="কাস্টম মেসেজ (ফাঁকা রাখলে টেমপ্লেট ব্যবহার হবে)">
                  </div>
                  <div class="form-group">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="send_sms" name="send_sms" checked>
                      <label class="form-check-label" for="send_sms">এসএমএস পাঠান</label>
                    </div>
                  </div>
                  <button class="btn btn-success submit-once"><i class="fas fa-money-bill-wave"></i> সংরক্ষণ</button>
                  <button type="button" class="btn btn-outline-primary ml-2" onclick="payFullDue()"><i class="fas fa-check-double"></i> পুরো বাকি পরিশোধ</button>
                </form>

              </div>
            </div>
          </div>
        </div>
        <?php elseif(isset($_GET['bill_id']) || isset($_GET['student_id'])): ?>
          <div class="alert alert-warning">বিল পাওয়া যায়নি</div>
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
      list.forEach(function(st){
        var label = (st.roll_number?('Roll '+st.roll_number+' - '):'') + st.name + (st.section_name?(' ('+st.section_name+')'):'');
        opts += '<option value="'+st.id+'"'+(<?php echo (int)$student_id; ?>==st.id?' selected':'')+'>'+label+'</option>';
      });
      $('#student_id').html(opts);
    }).fail(function(){ $('#student_id').html('<option value="">লোড ব্যর্থ</option>'); });
  }
  var selClass = $('#class_id').val(); if(selClass){ loadSections(selClass); }
  $(document).on('change','#class_id', function(){ var v=$(this).val(); if(v){loadSections(v);} loadStudents(); });
  $(document).on('change','#section_id', function(){ loadStudents(); });
});
function payFullDue(){
  try{
    var dueText = '<?php echo number_format($due,2,'.',''); ?>';
    var amt = document.querySelector('input[name="amount"]');
    if(amt){ amt.value = dueText; }
    // submit the enclosing form
    var form = amt ? amt.closest('form') : null;
    if(form){ form.submit(); }
  }catch(e){}
}
// Prevent double submission
document.addEventListener('submit', function(e){
  var btn = e.target.querySelector('button.submit-once');
  if(btn){
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> প্রসেসিং...';
  }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
