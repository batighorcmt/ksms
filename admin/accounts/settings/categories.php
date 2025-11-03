<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../inc/finance_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../../inc/header.php';
require_once __DIR__ . '/../../inc/sidebar.php';

$err=''; $ok='';

// Ensure categories table exists
try { $pdo->exec("CREATE TABLE IF NOT EXISTS accounts_categories (id INT AUTO_INCREMENT PRIMARY KEY, type ENUM('income','expense') NOT NULL, name VARCHAR(100) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1)"); } catch(Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['action'] ?? '';
  if ($act==='add') {
    $type = $_POST['type'] === 'expense' ? 'expense' : 'income';
    $name = trim($_POST['name'] ?? '');
    if ($name==='') { $err='নাম লিখুন'; }
    else {
      try { $st=$pdo->prepare("INSERT INTO accounts_categories(type,name,is_active) VALUES(?, ?, 1)"); $st->execute([$type,$name]); $ok='ক্যাটাগরি যুক্ত হয়েছে'; }
      catch(Throwable $e){ $err='ব্যর্থ: '.$e->getMessage(); }
    }
  }
  if ($act==='toggle') {
    $id=(int)($_POST['id'] ?? 0); $val=(int)($_POST['val'] ?? 1);
    try { $st=$pdo->prepare("UPDATE accounts_categories SET is_active=? WHERE id=?"); $st->execute([$val,$id]); $ok='হালনাগাদ হয়েছে'; }
    catch(Throwable $e){ $err='ব্যর্থ: '.$e->getMessage(); }
  }
  if ($act==='delete') {
    $id=(int)($_POST['id'] ?? 0);
    try { $pdo->prepare("DELETE FROM accounts_categories WHERE id=?")->execute([$id]); $ok='মুছে ফেলা হয়েছে'; }
    catch(Throwable $e){ $err='ব্যর্থ: '.$e->getMessage(); }
  }
}

$incomeCats = $pdo->query("SELECT * FROM accounts_categories WHERE type='income' ORDER BY is_active DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$expenseCats = $pdo->query("SELECT * FROM accounts_categories WHERE type='expense' ORDER BY is_active DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>আয়/ব্যয় খাত</title>
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
          <div class="col-sm-6"><h1 class="m-0">আয়/ব্যয় খাত</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/accounts/index.php">হিসাব</a></li><li class="breadcrumb-item active">খাত</li></ol></div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <?php if($ok) echo '<div class="alert alert-success">'.htmlspecialchars($ok).'</div>'; ?>
        <?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>

        <div class="card">
          <div class="card-header bg-primary text-white">নতুন খাত</div>
          <div class="card-body">
            <form method="post" class="form-row">
              <input type="hidden" name="action" value="add">
              <div class="form-group col-md-3">
                <label>ধরন</label>
                <select name="type" class="form-control"><option value="income">আয়</option><option value="expense">ব্যয়</option></select>
              </div>
              <div class="form-group col-md-7">
                <label>নাম</label>
                <input type="text" name="name" class="form-control" placeholder="যেমন: ভর্তি ফরম, বিদ্যুৎ বিল">
              </div>
              <div class="form-group col-md-2 align-self-end"><button class="btn btn-success btn-block"><i class="fas fa-plus"></i> যুক্ত</button></div>
            </form>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">আয়ের খাত</div>
              <div class="card-body p-0">
                <table class="table table-striped mb-0"><thead><tr><th>#</th><th>নাম</th><th>স্ট্যাটাস</th><th class="text-right">অ্যাকশন</th></tr></thead><tbody>
                  <?php if(!$incomeCats): ?><tr><td colspan="4" class="text-center text-muted">কিছু নেই</td></tr><?php endif; ?>
                  <?php $i=1; foreach($incomeCats as $c): ?>
                    <tr>
                      <td><?php echo $i++; ?></td>
                      <td><?php echo htmlspecialchars($c['name']); ?></td>
                      <td><?php echo !empty($c['is_active'])?'<span class="badge badge-success">সক্রিয়</span>':'<span class="badge badge-secondary">নিষ্ক্রিয়</span>'; ?></td>
                      <td class="text-right">
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>"><input type="hidden" name="val" value="<?php echo empty($c['is_active'])?1:0; ?>">
                          <button class="btn btn-sm btn-outline-primary"><?php echo empty($c['is_active'])?'সক্রিয়':'নিষ্ক্রিয়'; ?></button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('মুছে ফেলবেন?');">
                          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                          <button class="btn btn-sm btn-outline-danger">ডিলিট</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody></table>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">ব্যয়ের খাত</div>
              <div class="card-body p-0">
                <table class="table table-striped mb-0"><thead><tr><th>#</th><th>নাম</th><th>স্ট্যাটাস</th><th class="text-right">অ্যাকশন</th></tr></thead><tbody>
                  <?php if(!$expenseCats): ?><tr><td colspan="4" class="text-center text-muted">কিছু নেই</td></tr><?php endif; ?>
                  <?php $i=1; foreach($expenseCats as $c): ?>
                    <tr>
                      <td><?php echo $i++; ?></td>
                      <td><?php echo htmlspecialchars($c['name']); ?></td>
                      <td><?php echo !empty($c['is_active'])?'<span class="badge badge-success">সক্রিয়</span>':'<span class="badge badge-secondary">নিষ্ক্রিয়</span>'; ?></td>
                      <td class="text-right">
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>"><input type="hidden" name="val" value="<?php echo empty($c['is_active'])?1:0; ?>">
                          <button class="btn btn-sm btn-outline-primary"><?php echo empty($c['is_active'])?'সক্রিয়':'নিষ্ক্রিয়'; ?></button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('মুছে ফেলবেন?');">
                          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                          <button class="btn btn-sm btn-outline-danger">ডিলিট</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody></table>
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
