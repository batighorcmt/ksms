<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../inc/finance_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../../inc/header.php';
require_once __DIR__ . '/../../inc/sidebar.php';

$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d');
$to   = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

// Decide payments table and method/ref columns
$PAY_TABLE = 'fee_payments'; $FC_COL_DATE='payment_date'; $FC_COL_METHOD='method'; $FC_COL_REF='ref_no';
try {
  $cols = $pdo->query("SHOW COLUMNS FROM fee_payments")->fetchAll(PDO::FETCH_COLUMN) ?: [];
  if ($cols) {
    $hasBill = in_array('bill_id',$cols,true);
    $hasLegacy = in_array('student_id',$cols,true) && in_array('fee_structure_id',$cols,true);
    if (!$hasBill && $hasLegacy) { $PAY_TABLE='fee_bill_payments'; }
    if (!in_array('method',$cols,true) && in_array('payment_method',$cols,true)) $FC_COL_METHOD='payment_method';
    if (!in_array('ref_no',$cols,true) && in_array('transaction_id',$cols,true)) $FC_COL_REF='transaction_id';
  }
} catch(Throwable $e) {}
if ($PAY_TABLE==='fee_bill_payments') {
  try { $pdo->exec("CREATE TABLE IF NOT EXISTS fee_bill_payments (id INT AUTO_INCREMENT PRIMARY KEY, bill_id INT NOT NULL, payment_date DATE NOT NULL, amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, method VARCHAR(50) NULL, ref_no VARCHAR(100) NULL, received_by_user_id INT NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_bill (bill_id), INDEX idx_date (payment_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $e) {}
}

// Income/expense date column detection
$INCOME_TBL='income'; $INC_DATE_COL='date'; $EXPENSE_TBL='expense'; $EXP_DATE_COL='date';
try { $icol=$pdo->query("SHOW COLUMNS FROM income")->fetchAll(PDO::FETCH_COLUMN) ?: []; if (in_array('txn_date',$icol,true)) $INC_DATE_COL='txn_date'; } catch(Throwable $e) {}
try { $ecol=$pdo->query("SHOW COLUMNS FROM expense")->fetchAll(PDO::FETCH_COLUMN) ?: []; if (in_array('txn_date',$ecol,true)) $EXP_DATE_COL='txn_date'; } catch(Throwable $e) {}

// Fetch fee collections within date range
$fees = [];$fees_total=0.0;
try {
  $st=$pdo->prepare("SELECT p.id, p.$FC_COL_DATE AS pay_date, p.amount, p.$FC_COL_METHOD AS method, p.$FC_COL_REF AS ref_no, b.student_id
                     FROM {$PAY_TABLE} p JOIN fee_bills b ON b.id=p.bill_id
                     WHERE p.$FC_COL_DATE BETWEEN ? AND ? ORDER BY p.$FC_COL_DATE ASC, p.id ASC");
  $st->execute([$from, $to]); $fees=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach($fees as $f){ $fees_total += (float)$f['amount']; }
} catch(Throwable $e) { $fees=[]; }

// Fetch misc income
$incomes=[]; $income_total=0.0;
try {
  $st=$pdo->prepare("SELECT id, {$INC_DATE_COL} AS d, category, description, amount FROM {$INCOME_TBL} WHERE {$INC_DATE_COL} BETWEEN ? AND ? ORDER BY {$INC_DATE_COL} ASC, id ASC");
  $st->execute([$from,$to]); $incomes=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach($incomes as $r){ $income_total += (float)$r['amount']; }
} catch(Throwable $e) { $incomes=[]; }

// Expenses (optional summary)
$expenses=[]; $expense_total=0.0;
try {
  $st=$pdo->prepare("SELECT id, {$EXP_DATE_COL} AS d, category, description, amount FROM {$EXPENSE_TBL} WHERE {$EXP_DATE_COL} BETWEEN ? AND ? ORDER BY {$EXP_DATE_COL} ASC, id ASC");
  $st->execute([$from,$to]); $expenses=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach($expenses as $r){ $expense_total += (float)$r['amount']; }
} catch(Throwable $e) { $expenses=[]; }

?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>দৈনিক কালেকশন রিপোর্ট</title>
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
          <div class="col-sm-6"><h1 class="m-0">দৈনিক কালেকশন রিপোর্ট</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/accounts/index.php">হিসাব</a></li><li class="breadcrumb-item active">কালেকশন</li></ol></div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header bg-primary text-white">তারিখ নির্বাচন</div>
          <div class="card-body">
            <form method="get" class="form-row">
              <div class="form-group col-md-3"><label>শুরু</label><input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>"></div>
              <div class="form-group col-md-3"><label>শেষ</label><input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>"></div>
              <div class="form-group col-md-2 align-self-end"><button class="btn btn-info btn-block"><i class="fas fa-search"></i> দেখুন</button></div>
              <div class="form-group col-md-2 align-self-end"><button type="button" class="btn btn-outline-secondary btn-block" onclick="window.print()"><i class="fas fa-print"></i> প্রিন্ট</button></div>
            </form>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">ফি কালেকশন (৳ <?php echo number_format($fees_total,2); ?>)</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped mb-0"><thead><tr><th>#</th><th>তারিখ</th><th>পদ্ধতি</th><th>রেফ</th><th class="text-right">পরিমাণ</th></tr></thead><tbody>
                    <?php if(!$fees): ?><tr><td colspan="5" class="text-center text-muted">ডাটা নেই</td></tr><?php endif; ?>
                    <?php $i=1; foreach($fees as $r): ?>
                      <tr><td><?php echo $i++; ?></td><td><?php echo htmlspecialchars($r['pay_date']); ?></td><td><?php echo htmlspecialchars($r['method'] ?? ''); ?></td><td><?php echo htmlspecialchars($r['ref_no'] ?? ''); ?></td><td class="text-right">৳ <?php echo number_format((float)$r['amount'],2); ?></td></tr>
                    <?php endforeach; ?>
                  </tbody></table>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">অন্যান্য আয় (৳ <?php echo number_format($income_total,2); ?>)</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped mb-0"><thead><tr><th>#</th><th>তারিখ</th><th>ক্যাটাগরি</th><th>বিবরণ</th><th class="text-right">পরিমাণ</th></tr></thead><tbody>
                    <?php if(!$incomes): ?><tr><td colspan="5" class="text-center text-muted">ডাটা নেই</td></tr><?php endif; ?>
                    <?php $i=1; foreach($incomes as $r): ?>
                      <tr><td><?php echo $i++; ?></td><td><?php echo htmlspecialchars($r['d']); ?></td><td><?php echo htmlspecialchars($r['category'] ?? ''); ?></td><td><?php echo htmlspecialchars($r['description'] ?? ''); ?></td><td class="text-right">৳ <?php echo number_format((float)$r['amount'],2); ?></td></tr>
                    <?php endforeach; ?>
                  </tbody></table>
                </div>
              </div>
            </div>
            <div class="card">
              <div class="card-header">ব্যয় (৳ <?php echo number_format($expense_total,2); ?>)</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped mb-0"><thead><tr><th>#</th><th>তারিখ</th><th>ক্যাটাগরি</th><th>বিবরণ</th><th class="text-right">পরিমাণ</th></tr></thead><tbody>
                    <?php if(!$expenses): ?><tr><td colspan="5" class="text-center text-muted">ডাটা নেই</td></tr><?php endif; ?>
                    <?php $i=1; foreach($expenses as $r): ?>
                      <tr><td><?php echo $i++; ?></td><td><?php echo htmlspecialchars($r['d']); ?></td><td><?php echo htmlspecialchars($r['category'] ?? ''); ?></td><td><?php echo htmlspecialchars($r['description'] ?? ''); ?></td><td class="text-right">৳ <?php echo number_format((float)$r['amount'],2); ?></td></tr>
                    <?php endforeach; ?>
                  </tbody></table>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>
  </div>
  <?php include __DIR__ . '/../../inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
