<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../inc/finance_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../../inc/header.php';
require_once __DIR__ . '/../../inc/sidebar.php';

$err = '';$ok='';

// Ensure table exists in mixed schemas
try { $pdo->exec("CREATE TABLE IF NOT EXISTS academic_years (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NULL, year VARCHAR(20) NULL, start_date DATE NULL, end_date DATE NULL, is_current TINYINT(1) NOT NULL DEFAULT 0, status ENUM('active','archived') NOT NULL DEFAULT 'active')"); } catch(Throwable $e) {}

// Handle actions: add, set_current, archive
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['action'] ?? '';
  if ($act==='add') {
    $name = trim($_POST['name'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $start = trim($_POST['start_date'] ?? '');
    $end = trim($_POST['end_date'] ?? '');
    try {
      $st = $pdo->prepare("INSERT INTO academic_years(name, year, start_date, end_date, is_current, status) VALUES(?,?,?,?,0,'active')");
      $st->execute([$name ?: null, $year ?: null, $start ?: null, $end ?: null]);
      $ok = 'নতুন শিক্ষাবর্ষ যুক্ত হয়েছে';
    } catch(Throwable $e){ $err = 'ব্যর্থ: '.$e->getMessage(); }
  }
  if ($act==='set_current') {
    $id = (int)($_POST['id'] ?? 0);
    try {
      $pdo->exec("UPDATE academic_years SET is_current=0");
      $st = $pdo->prepare("UPDATE academic_years SET is_current=1, status='active' WHERE id=?");
      $st->execute([$id]);
      $ok = 'বর্তমান শিক্ষাবর্ষ নির্ধারিত';
    } catch(Throwable $e){ $err = 'ব্যর্থ: '.$e->getMessage(); }
  }
  if ($act==='archive') {
    $id = (int)($_POST['id'] ?? 0);
    try { $st=$pdo->prepare("UPDATE academic_years SET status='archived', is_current=0 WHERE id=?"); $st->execute([$id]); $ok='আর্কাইভ করা হয়েছে'; }
    catch(Throwable $e){ $err='ব্যর্থ: '.$e->getMessage(); }
  }
}

// Load list
$years = [];
try { $years = $pdo->query("SELECT * FROM academic_years ORDER BY COALESCE(start_date, '1970-01-01') DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch(Throwable $e) { $years=[]; }

// Determine label builder based on available columns
$hasName=false; $hasYear=false; $cols=[];
try { $cols = $pdo->query("SHOW COLUMNS FROM academic_years")->fetchAll(PDO::FETCH_COLUMN) ?: []; } catch(Throwable $e) {}
$hasName = in_array('name',$cols,true); $hasYear = in_array('year',$cols,true);

function ay_label(array $r, bool $hasName, bool $hasYear): string {
  $parts=[];
  if ($hasName && !empty($r['name'])) $parts[] = $r['name'];
  if ($hasYear && !empty($r['year'])) $parts[] = $r['year'];
  if (!$parts) {
    $range = '';
    if (!empty($r['start_date'])) $range .= $r['start_date'];
    if (!empty($r['end_date'])) $range .= ' - '.$r['end_date'];
    if ($range) $parts[] = $range; else $parts[] = 'Year #'.(int)$r['id'];
  }
  return implode(' | ', $parts);
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>শিক্ষাবর্ষ সেটিংস</title>
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
          <div class="col-sm-6"><h1 class="m-0">শিক্ষাবর্ষ</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/accounts/index.php">হিসাব</a></li><li class="breadcrumb-item active">শিক্ষাবর্ষ</li></ol></div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <?php if($ok) echo '<div class="alert alert-success">'.htmlspecialchars($ok).'</div>'; ?>
        <?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>

        <div class="card">
          <div class="card-header bg-primary text-white">নতুন শিক্ষাবর্ষ</div>
          <div class="card-body">
            <form method="post" class="form-row">
              <input type="hidden" name="action" value="add">
              <div class="form-group col-md-3">
                <label>নাম</label>
                <input type="text" name="name" class="form-control" placeholder="যেমন: ২০২৫-২৬">
              </div>
              <div class="form-group col-md-2">
                <label>বছর</label>
                <input type="text" name="year" class="form-control" placeholder="2025-2026">
              </div>
              <div class="form-group col-md-3">
                <label>শুরু</label>
                <input type="date" name="start_date" class="form-control">
              </div>
              <div class="form-group col-md-3">
                <label>শেষ</label>
                <input type="date" name="end_date" class="form-control">
              </div>
              <div class="form-group col-md-1 align-self-end">
                <button class="btn btn-success btn-block"><i class="fas fa-plus"></i></button>
              </div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header">শিক্ষাবর্ষ তালিকা</div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped mb-0">
                <thead><tr><th>#</th><th>লেবেল</th><th>তারিখ</th><th>স্ট্যাটাস</th><th class="text-right">অ্যাকশন</th></tr></thead>
                <tbody>
                  <?php if(!$years): ?><tr><td colspan="5" class="text-center text-muted">ডাটা নেই</td></tr><?php endif; ?>
                  <?php $i=1; foreach($years as $y): ?>
                    <tr>
                      <td><?php echo $i++; ?></td>
                      <td><?php echo htmlspecialchars(ay_label($y,$hasName,$hasYear)); ?></td>
                      <td><?php echo htmlspecialchars(($y['start_date'] ?? '').($y['end_date']?' - '.$y['end_date']:'')); ?></td>
                      <td>
                        <?php if(!empty($y['is_current'])): ?><span class="badge badge-success">বর্তমান</span><?php endif; ?>
                        <?php if(($y['status'] ?? '')==='archived'): ?><span class="badge badge-secondary">আর্কাইভ</span><?php else: ?><span class="badge badge-info">সক্রিয়</span><?php endif; ?>
                      </td>
                      <td class="text-right">
                        <form method="post" class="d-inline">
                          <input type="hidden" name="id" value="<?php echo (int)$y['id']; ?>">
                          <input type="hidden" name="action" value="set_current">
                          <button class="btn btn-sm btn-primary" <?php echo !empty($y['is_current'])?'disabled':''; ?>>বর্তমান করুন</button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('আর্কাইভ করবেন?');">
                          <input type="hidden" name="id" value="<?php echo (int)$y['id']; ?>">
                          <input type="hidden" name="action" value="archive">
                          <button class="btn btn-sm btn-outline-secondary" <?php echo (($y['status'] ?? '')==='archived')?'disabled':''; ?>>আর্কাইভ</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
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
