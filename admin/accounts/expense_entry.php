<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/inc/finance_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/sidebar.php';

$err = '';$ok='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $date = $_POST['date'] ?? date('Y-m-d');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    if ($amount <= 0) {
        $err = 'পরিমাণ সঠিক নয়';
    } else {
        try {
            if ($pdo instanceof PDO) {
                // Ensure table exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS expense (id INT AUTO_INCREMENT PRIMARY KEY, date DATE NOT NULL, category VARCHAR(100) NULL, description TEXT NULL, amount DECIMAL(12,2) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                $st = $pdo->prepare("INSERT INTO expense(date, category, description, amount) VALUES(?,?,?,?)");
                $st->execute([$date, $category ?: null, $description ?: null, $amount]);
                $ok = 'ব্যয় যুক্ত হয়েছে';
            } else {
                $err = 'ডাটাবেজ সংযোগ পাওয়া যায়নি';
            }
        } catch (Throwable $e) {
            $err = 'সংরক্ষণ করা যায়নি: '.$e->getMessage();
        }
    }
}

$rows = [];
try {
    if ($pdo instanceof PDO) {
        $rows = $pdo->query("SELECT id, date, category, description, amount FROM expense ORDER BY date DESC, id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ব্যয় এন্ট্রি</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">ব্যয় যুক্ত করুন</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="index.php">হিসাব</a></li><li class="breadcrumb-item active">ব্যয়</li></ol></div>
        </div>
      </div>
    </div>
    <section class="content">
      <div class="container-fluid">
        <?php if($ok) echo '<div class="alert alert-success">'.htmlspecialchars($ok).'</div>'; ?>
        <?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>
        <div class="card">
          <div class="card-header bg-danger text-white">নতুন ব্যয়</div>
          <div class="card-body">
            <form method="post">
              <div class="form-row">
                <div class="form-group col-md-3">
                  <label>তারিখ</label>
                  <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                </div>
                <div class="form-group col-md-3">
                  <label>ক্যাটাগরি</label>
                  <input type="text" name="category" class="form-control" placeholder="যেমন: বিদ্যুৎ, ভাড়া">
                </div>
                <div class="form-group col-md-4">
                  <label>বিবরণ</label>
                  <input type="text" name="description" class="form-control" placeholder="ঐচ্ছিক">
                </div>
                <div class="form-group col-md-2">
                  <label>পরিমাণ (৳)</label>
                  <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
                </div>
              </div>
              <button class="btn btn-primary"><i class="fas fa-save"></i> সংরক্ষণ</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header">সাম্প্রতিক ব্যয়</div>
          <div class="card-body p-0">
            <?php if(!$rows): ?>
              <div class="p-3 text-muted">কোন ডাটা নেই। সম্ভবত finance_schema.sql ইম্পোর্ট করতে হবে।</div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="table table-striped mb-0">
                <thead><tr><th>#</th><th>তারিখ</th><th>ক্যাটাগরি</th><th>বিবরণ</th><th class="text-right">পরিমাণ</th></tr></thead>
                <tbody>
                  <?php $i=1; foreach($rows as $r): ?>
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
            <?php endif; ?>
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
