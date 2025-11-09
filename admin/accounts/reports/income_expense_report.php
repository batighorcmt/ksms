<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../inc/finance_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../../inc/header.php';
require_once __DIR__ . '/../../inc/sidebar.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$income = $expense = 0.0;
$incomeRows = $expenseRows = [];
if ($pdo instanceof PDO) {
  try {
    $st = $pdo->prepare("SELECT date, category, description, amount FROM income WHERE date BETWEEN ? AND ? ORDER BY date ASC");
    $st->execute([$from, $to]);
    $incomeRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach($incomeRows as $r) $income += (float)$r['amount'];
  } catch (Throwable $e) {}
  try {
    $st = $pdo->prepare("SELECT date, category, description, amount FROM expense WHERE date BETWEEN ? AND ? ORDER BY date ASC");
    $st->execute([$from, $to]);
    $expenseRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach($expenseRows as $r) $expense += (float)$r['amount'];
  } catch (Throwable $e) {}
}
$net = $income - $expense;
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>আয়-ব্যয় রিপোর্ট</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    @media print {
      .no-print { display:none; }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">আয়-ব্যয় রিপোর্ট</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="../index.php">হিসাব</a></li><li class="breadcrumb-item active">রিপোর্ট</li></ol></div>
        </div>
      </div>
    </div>
    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">তারিখ বাছাই</div>
          <div class="card-body">
            <form class="form-inline">
              <input type="date" name="from" class="form-control mr-2" value="<?php echo htmlspecialchars($from); ?>">
              <input type="date" name="to" class="form-control mr-2" value="<?php echo htmlspecialchars($to); ?>">
              <button class="btn btn-primary"><i class="fas fa-filter"></i> ফিল্টার</button>
              <button type="button" onclick="window.print()" class="btn btn-secondary ml-2 no-print"><i class="fas fa-print"></i> প্রিন্ট</button>
            </form>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="small-box bg-info"><div class="inner"><h3>৳ <?php echo money($income); ?></h3><p>মোট আয়</p></div><div class="icon"><i class="fas fa-arrow-down"></i></div></div>
          </div>
          <div class="col-md-4">
            <div class="small-box bg-danger"><div class="inner"><h3>৳ <?php echo money($expense); ?></h3><p>মোট ব্যয়</p></div><div class="icon"><i class="fas fa-arrow-up"></i></div></div>
          </div>
          <div class="col-md-4">
            <div class="small-box bg-success"><div class="inner"><h3>৳ <?php echo money($net); ?></h3><p>নেট</p></div><div class="icon"><i class="fas fa-balance-scale"></i></div></div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="card">
              <div class="card-header bg-light">আয়ের তালিকা</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped mb-0">
                    <thead><tr><th>#</th><th>তারিখ</th><th>ক্যাটাগরি</th><th>বিবরণ</th><th class="text-right">পরিমাণ</th></tr></thead>
                    <tbody>
                      <?php if(!$incomeRows): ?><tr><td colspan="5" class="text-center text-muted">ডাটা নেই</td></tr><?php endif; ?>
                      <?php $i=1; foreach($incomeRows as $r): ?>
                        <tr>
                          <td><?php echo $i++; ?></td>
                          <td><?php echo htmlspecialchars($r['date']); ?></td>
                          <td><?php echo htmlspecialchars($r['category']); ?></td>
                          <td><?php echo htmlspecialchars($r['description']); ?></td>
                          <td class="text-right">৳ <?php echo money($r['amount']); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card">
              <div class="card-header bg-light">ব্যয়ের তালিকা</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped mb-0">
                    <thead><tr><th>#</th><th>তারিখ</th><th>ক্যাটাগরি</th><th>বিবরণ</th><th class="text-right">পরিমাণ</th></tr></thead>
                    <tbody>
                      <?php if(!$expenseRows): ?><tr><td colspan="5" class="text-center text-muted">ডাটা নেই</td></tr><?php endif; ?>
                      <?php $j=1; foreach($expenseRows as $r): ?>
                        <tr>
                          <td><?php echo $j++; ?></td>
                          <td><?php echo htmlspecialchars($r['date']); ?></td>
                          <td><?php echo htmlspecialchars($r['category']); ?></td>
                          <td><?php echo htmlspecialchars($r['description']); ?></td>
                          <td class="text-right">৳ <?php echo money($r['amount']); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
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
