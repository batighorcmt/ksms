<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/inc/finance_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/sidebar.php';

// Quick stats
$today = date('Y-m-d');
function acc_safe_sum($pdo, $sql) {
  if ($pdo instanceof PDO) {
    try {
      $val = $pdo->query($sql)->fetchColumn();
      return (float)($val ?: 0);
    } catch (Throwable $e) {
      return 0.0;
    }
  }
  return 0.0;
}
$sum_income = acc_safe_sum($pdo, "SELECT COALESCE(SUM(amount),0) FROM income");
$sum_expense = acc_safe_sum($pdo, "SELECT COALESCE(SUM(amount),0) FROM expense");
$sum_fee = acc_safe_sum($pdo, "SELECT COALESCE(SUM(amount),0) FROM fee_payments");
$sum_salary = acc_safe_sum($pdo, "SELECT COALESCE(SUM(net),0) FROM salary_payments");
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>হিসাব ড্যাশবোর্ড</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <style>
    body { font-family:'SolaimanLipi','Source Sans Pro',sans-serif; }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">হিসাব ড্যাশবোর্ড</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="../dashboard.php">হোম</a></li><li class="breadcrumb-item active">হিসাব</li></ol></div>
        </div>
      </div>
    </div>
    <section class="content">
      <div class="container-fluid">
        <?php finance_flash_show(); ?>
        <div class="row">
          <div class="col-md-3">
            <div class="small-box bg-success"><div class="inner"><h3>৳ <?php echo money($sum_fee); ?></h3><p>শিক্ষার্থী ফি সংগ্রহ</p></div><div class="icon"><i class="fas fa-receipt"></i></div><a href="fee_collect.php" class="small-box-footer">ফি সংগ্রহ <i class="fas fa-arrow-circle-right"></i></a></div>
          </div>
          <div class="col-md-3">
            <div class="small-box bg-info"><div class="inner"><h3>৳ <?php echo money($sum_income); ?></h3><p>অন্যান্য আয়</p></div><div class="icon"><i class="fas fa-arrow-down"></i></div><a href="income_entry.php" class="small-box-footer">আয় যুক্ত করুন <i class="fas fa-arrow-circle-right"></i></a></div>
          </div>
          <div class="col-md-3">
            <div class="small-box bg-danger"><div class="inner"><h3>৳ <?php echo money($sum_expense); ?></h3><p>ব্যয়</p></div><div class="icon"><i class="fas fa-arrow-up"></i></div><a href="expense_entry.php" class="small-box-footer">ব্যয় যুক্ত করুন <i class="fas fa-arrow-circle-right"></i></a></div>
          </div>
          <div class="col-md-3">
            <div class="small-box bg-warning"><div class="inner"><h3>৳ <?php echo money($sum_salary); ?></h3><p>বেতন প্রদান</p></div><div class="icon"><i class="fas fa-money-bill"></i></div><a href="salary_pay.php" class="small-box-footer">বেতন প্রদান <i class="fas fa-arrow-circle-right"></i></a></div>
          </div>
        </div>

        <div class="card">
          <div class="card-header bg-primary text-white">দ্রুত লিংক</div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-3"><a class="btn btn-outline-primary btn-block" href="fees_settings.php"><i class="fas fa-cog"></i> ফি সেটিংস</a></div>
              <div class="col-md-3"><a class="btn btn-outline-primary btn-block" href="student_waivers.php"><i class="fas fa-percentage"></i> শিক্ষার্থী ছাড়</a></div>
              <div class="col-md-3"><a class="btn btn-outline-primary btn-block" href="fee_billing.php"><i class="fas fa-file-invoice"></i> মাসিক বিল তৈরি</a></div>
              <div class="col-md-3"><a class="btn btn-outline-primary btn-block" href="reports/income_expense_report.php"><i class="fas fa-chart-line"></i> আয়-ব্যয় রিপোর্ট</a></div>
            </div>
            <div class="row mt-2">
              <div class="col-md-3"><a class="btn btn-outline-secondary btn-block" href="settings/sessions.php"><i class="fas fa-calendar"></i> শিক্ষাবর্ষ</a></div>
              <div class="col-md-3"><a class="btn btn-outline-secondary btn-block" href="settings/categories.php"><i class="fas fa-list"></i> আয়/ব্যয় খাত</a></div>
              <div class="col-md-3"><a class="btn btn-outline-secondary btn-block" href="reports/dues_report.php"><i class="fas fa-exclamation-circle"></i> বকেয়া রিপোর্ট</a></div>
              <div class="col-md-3"><a class="btn btn-outline-secondary btn-block" href="reports/collection_report.php"><i class="fas fa-receipt"></i> কালেকশন রিপোর্ট</a></div>
            </div>
            <div class="row mt-2">
              <div class="col-md-3"><a class="btn btn-outline-secondary btn-block" href="reports/student_ledger.php"><i class="fas fa-user"></i> শিক্ষার্থী লেজার</a></div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
  <?php include __DIR__ . '/../inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
