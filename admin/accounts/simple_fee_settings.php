<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/inc/finance_helpers.php';
require_once __DIR__ . '/../inc/enrollment_helpers.php';
finance_require_admin();

// Ensure core tables/columns exist (non-destructive)
if ($pdo instanceof PDO) {
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(150) NOT NULL,
      item_type ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
      amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      due_day INT NULL,
      fine_enabled TINYINT(1) NOT NULL DEFAULT 0,
      fine_type ENUM('flat','percent','per_day') NOT NULL DEFAULT 'flat',
      fine_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_name_type (name, item_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Throwable $e) { /* ignore */ }
  // Backfill columns if table exists but columns missing
  try {
    $cols = $pdo->query("SHOW COLUMNS FROM fee_items")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $defs = [
      'item_type'    => "ADD COLUMN item_type ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly'",
      'amount'       => "ADD COLUMN amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
      'due_day'      => "ADD COLUMN due_day INT NULL",
      'fine_enabled' => "ADD COLUMN fine_enabled TINYINT(1) NOT NULL DEFAULT 0",
      'fine_type'    => "ADD COLUMN fine_type ENUM('flat','percent','per_day') NOT NULL DEFAULT 'flat'",
      'fine_value'   => "ADD COLUMN fine_value DECIMAL(10,2) NOT NULL DEFAULT 0.00",
      'is_active'    => "ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
      'created_at'   => "ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP"
    ];
    foreach ($defs as $col => $ddl) {
      if (!in_array($col, $cols, true)) {
        try { $pdo->exec('ALTER TABLE fee_items ' . $ddl); } catch (Throwable $e) { /* ignore individual column errors */ }
      }
    }
    // Ensure ENUM ranges include required values even if column exists
    try {
      $cinfo = $pdo->query("SHOW COLUMNS FROM fee_items LIKE 'item_type'")->fetch(PDO::FETCH_ASSOC);
      if ($cinfo && !empty($cinfo['Type']) && stripos($cinfo['Type'], "'yearly'") === false) {
        // Expand enum to include 'yearly'
        $pdo->exec("ALTER TABLE fee_items MODIFY COLUMN item_type ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly'");
      }
    } catch (Throwable $e) { /* ignore */ }
    try {
      $cinfo2 = $pdo->query("SHOW COLUMNS FROM fee_items LIKE 'fine_type'")->fetch(PDO::FETCH_ASSOC);
      if ($cinfo2 && !empty($cinfo2['Type'])) {
        $t = $cinfo2['Type'];
        $needsPerDay = stripos($t, "'per_day'") === false;
        $needsFlat = stripos($t, "'flat'") === false;
        $needsPercent = stripos($t, "'percent'") === false;
        if ($needsPerDay || $needsFlat || $needsPercent) {
          // Normalize to expected set
          $pdo->exec("ALTER TABLE fee_items MODIFY COLUMN fine_type ENUM('flat','percent','per_day') NOT NULL DEFAULT 'flat'");
        }
      }
    } catch (Throwable $e) { /* ignore */ }
  } catch (Throwable $e) { /* ignore */ }

  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_fees (
      id INT AUTO_INCREMENT PRIMARY KEY,
      class_id INT NOT NULL,
      section_id INT NULL,
      fee_item_id INT NOT NULL,
      amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      UNIQUE KEY uniq (class_id, section_id, fee_item_id),
      INDEX idx_item (fee_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Throwable $e) { /* ignore */ }
}

function sf_post($k, $d=null){ return isset($_POST[$k])?$_POST[$k]:$d; }
function sf_num($v){ return (float)($v!==''?$v:0); }
function sf_to_null($v){ $v=trim((string)$v); return $v!==''?$v:null; }

$ok=''; $err='';

// CSRF token setup and flash retrieval (for PRG)
if (empty($_SESSION['csrf_token'])) {
  try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Throwable $e) { $_SESSION['csrf_token'] = sha1(uniqid('', true)); }
}
$FLASH_OK = isset($_SESSION['flash_ok']) ? (string)$_SESSION['flash_ok'] : '';
$FLASH_ERR = isset($_SESSION['flash_err']) ? (string)$_SESSION['flash_err'] : '';
if ($FLASH_OK !== '' || $FLASH_ERR !== '') {
  $ok = $FLASH_OK;
  $err = $FLASH_ERR;
  unset($_SESSION['flash_ok'], $_SESSION['flash_err']);
}

// Load classes
$classes = ($pdo instanceof PDO) ? (get_classes($pdo) ?? []) : [];

// Introspect fee_items columns for dynamic queries (compat with legacy schemas)
$FI_COLS = [];
$FI_HAS = ['due_day'=>false,'fine_enabled'=>false,'fine_type'=>false,'fine_value'=>false,'is_active'=>false];
if ($pdo instanceof PDO) {
  try {
    $FI_COLS = $pdo->query("SHOW COLUMNS FROM fee_items")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($FI_HAS as $k=>$_) { $FI_HAS[$k] = in_array($k, $FI_COLS, true); }
  } catch (Throwable $e) { /* ignore */ }
}

// Helper: find or create the Tuition item (monthly)
function sf_get_or_create_tuition(PDO $pdo){
  // try a few common names, fallback to create বাংলা 'বেতন'
  $names = ['বেতন','টিউশন ফি','Tuition','Monthly Tuition'];
  $in = implode(',', array_fill(0, count($names), '?'));
  $st = $pdo->prepare("SELECT * FROM fee_items WHERE item_type='monthly' AND name IN ($in) ORDER BY id ASC LIMIT 1");
  $st->execute($names);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) return $row;
  $pdo->prepare("INSERT INTO fee_items(name,item_type,amount,is_active) VALUES(?, 'monthly', 0, 1)")->execute(['বেতন']);
  $id = (int)$pdo->lastInsertId();
  $st = $pdo->prepare("SELECT * FROM fee_items WHERE id=?"); $st->execute([$id]);
  return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Actions
if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD']==='POST') {
  // CSRF validation
  $token = sf_post('csrf_token','');
  $csrf_ok = (!empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token));

  $act = sf_post('action','');
  if ($csrf_ok && $act==='save_tuition') {
    try {
      $tu = sf_get_or_create_tuition($pdo);
      if (!$tu) throw new Exception('বেতন আইটেম পাওয়া যায়নি');
      $id = (int)$tu['id'];
      $due_day = (int)(sf_post('due_day','')!==''?sf_post('due_day',''):0) ?: null;
      $fine_enabled = (int)sf_post('fine_enabled',0) ? 1 : 0;
      $fine_type = in_array(sf_post('fine_type','flat'), ['flat','percent','per_day'], true) ? sf_post('fine_type','flat') : 'flat';
      $fine_value = sf_num(sf_post('fine_value','0'));
      $default_amount = sf_num(sf_post('default_amount','0'));
      // Dynamic UPDATE based on available columns
      $sets = ['amount=?']; $params = [$default_amount];
      if ($FI_HAS['due_day']) { $sets[] = 'due_day=?'; $params[] = $due_day; }
      if ($FI_HAS['fine_enabled']) { $sets[] = 'fine_enabled=?'; $params[] = $fine_enabled; }
      if ($FI_HAS['fine_type']) { $sets[] = 'fine_type=?'; $params[] = $fine_type; }
      if ($FI_HAS['fine_value']) { $sets[] = 'fine_value=?'; $params[] = $fine_value; }
      $params[] = $id;
      $sql = 'UPDATE fee_items SET ' . implode(', ', $sets) . ' WHERE id=?';
      $pdo->prepare($sql)->execute($params);
      // class overrides
      $class_amounts = isset($_POST['class_amount']) && is_array($_POST['class_amount']) ? $_POST['class_amount'] : [];
      foreach ($class_amounts as $cid => $val) {
        $cid = (int)$cid; $val = trim((string)$val);
        if ($val==='') continue; // skip untouched
        $amt = sf_num($val);
        // upsert (class_id, NULL, fee_item_id)
        $st = $pdo->prepare("SELECT id FROM class_fees WHERE class_id=? AND section_id IS NULL AND fee_item_id=? LIMIT 1");
        $st->execute([$cid, $id]); $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
          $pdo->prepare("UPDATE class_fees SET amount=? WHERE id=?")->execute([$amt, (int)$row['id']]);
        } else {
          $pdo->prepare("INSERT INTO class_fees(class_id,section_id,fee_item_id,amount) VALUES(?, NULL, ?, ?)")->execute([$cid, $id, $amt]);
        }
      }
      $ok='বেতনের সেটিংস সংরক্ষণ হয়েছে';
    } catch (Throwable $e) { $err='ব্যর্থ: '.$e->getMessage(); }
  }
  if ($csrf_ok && ($act==='add_monthly_item' || $act==='add_yearly_item')) {
    try {
      $name = trim((string)sf_post('name',''));
      $item_type = $act==='add_monthly_item' ? 'monthly' : 'yearly';
      if ($name==='') throw new Exception('নাম প্রয়োজন');
      $amount = sf_num(sf_post('amount','0'));
      $due_day = sf_to_null(sf_post('due_day',''));
      $fine_enabled = (int)sf_post('fine_enabled',0) ? 1 : 0;
      $fine_type = in_array(sf_post('fine_type','flat'), ['flat','percent','per_day'], true) ? sf_post('fine_type','flat') : 'flat';
      $fine_value = sf_num(sf_post('fine_value','0'));
      // Dynamic INSERT based on available columns
      $cols = ['name','item_type','amount'];
      $vals = [$name,$item_type,$amount];
      if ($FI_HAS['due_day']) { $cols[]='due_day'; $vals[]=$due_day; }
      if ($FI_HAS['fine_enabled']) { $cols[]='fine_enabled'; $vals[]=$fine_enabled; }
      if ($FI_HAS['fine_type']) { $cols[]='fine_type'; $vals[]=$fine_type; }
      if ($FI_HAS['fine_value']) { $cols[]='fine_value'; $vals[]=$fine_value; }
      if ($FI_HAS['is_active']) { $cols[]='is_active'; $vals[]=1; }
      $place = implode(',', array_fill(0, count($cols), '?'));
      $sql = 'INSERT INTO fee_items(' . implode(',', $cols) . ') VALUES(' . $place . ')';
      $st=$pdo->prepare($sql); $st->execute($vals);
      $ok='আইটেম যুক্ত হয়েছে';
    } catch (Throwable $e) { $err='ব্যর্থ: '.$e->getMessage(); }
  }
  if ($csrf_ok && $act==='update_item') {
    try {
      $item_id = (int)sf_post('item_id',0);
      if ($item_id<=0) throw new Exception('আইটেম আইডি নেই');
      $amount = sf_num(sf_post('amount','0'));
      $due_day = sf_to_null(sf_post('due_day',''));
      $fine_enabled = (int)sf_post('fine_enabled',0) ? 1 : 0;
      $fine_type = in_array(sf_post('fine_type','flat'), ['flat','percent','per_day'], true) ? sf_post('fine_type','flat') : 'flat';
      $fine_value = sf_num(sf_post('fine_value','0'));
      // Dynamic UPDATE based on available columns
      $sets = ['amount=?']; $params = [$amount];
      if ($FI_HAS['due_day']) { $sets[]='due_day=?'; $params[]=$due_day; }
      if ($FI_HAS['fine_enabled']) { $sets[]='fine_enabled=?'; $params[]=$fine_enabled; }
      if ($FI_HAS['fine_type']) { $sets[]='fine_type=?'; $params[]=$fine_type; }
      if ($FI_HAS['fine_value']) { $sets[]='fine_value=?'; $params[]=$fine_value; }
      $params[] = $item_id;
      $sql = 'UPDATE fee_items SET ' . implode(', ',$sets) . ' WHERE id=?';
      $pdo->prepare($sql)->execute($params);
      // class overrides
      $class_amounts = isset($_POST['class_amount']) && is_array($_POST['class_amount']) ? $_POST['class_amount'] : [];
      foreach ($class_amounts as $cid => $val) {
        $cid = (int)$cid; $val = trim((string)$val);
        if ($val==='') continue;
        $amt = sf_num($val);
        $st = $pdo->prepare("SELECT id FROM class_fees WHERE class_id=? AND section_id IS NULL AND fee_item_id=? LIMIT 1");
        $st->execute([$cid, $item_id]); $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
          $pdo->prepare("UPDATE class_fees SET amount=? WHERE id=?")->execute([$amt, (int)$row['id']]);
        } else {
          $pdo->prepare("INSERT INTO class_fees(class_id,section_id,fee_item_id,amount) VALUES(?, NULL, ?, ?)")->execute([$cid, $item_id, $amt]);
        }
      }
      $ok='হালনাগাদ হয়েছে';
    } catch (Throwable $e) { $err='ব্যর্থ: '.$e->getMessage(); }
  }
  if ($csrf_ok && $act==='toggle_item') {
    try {
      $item_id = (int)sf_post('item_id',0);
      $val = (int)sf_post('val',1)?1:0;
      $pdo->prepare("UPDATE fee_items SET is_active=? WHERE id=?")->execute([$val,$item_id]);
      $ok='স্ট্যাটাস আপডেট হয়েছে';
    } catch (Throwable $e) { $err='ব্যর্থ: '.$e->getMessage(); }
  }
  if ($csrf_ok && $act==='delete_item') {
    try {
      $item_id = (int)sf_post('item_id',0);
      $pdo->prepare("DELETE FROM fee_items WHERE id=?")->execute([$item_id]);
      // Optionally cleanup class_fees
      $pdo->prepare("DELETE FROM class_fees WHERE fee_item_id=?")->execute([$item_id]);
      $ok='আইটেম মুছে ফেলা হয়েছে';
    } catch (Throwable $e) { $err='ব্যর্থ: '.$e->getMessage(); }
  }
  if (!$csrf_ok && $act) {
    $err = 'নিরাপত্তা টোকেন সঠিক নয়। আবার চেষ্টা করুন।';
  }

  // PRG: store flash and redirect back to this page to prevent resubmission
  $_SESSION['flash_ok'] = $ok;
  $_SESSION['flash_err'] = $err;
  header('Location: ' . ADMIN_URL . 'accounts/simple_fee_settings.php');
  exit;
}

// Load Tuition
$tuition = ($pdo instanceof PDO) ? sf_get_or_create_tuition($pdo) : null;
$tuition_overrides = [];
if ($pdo instanceof PDO && $tuition) {
  $st=$pdo->prepare("SELECT class_id, amount FROM class_fees WHERE section_id IS NULL AND fee_item_id=?");
  $st->execute([(int)$tuition['id']]);
  foreach(($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r){ $tuition_overrides[(int)$r['class_id']] = (float)$r['amount']; }
}

// Load other monthly and yearly items
$monthly_items=[]; $yearly_items=[]; $overrides_by_item=[];
if ($pdo instanceof PDO) {
  $st=$pdo->query("SELECT * FROM fee_items WHERE item_type='monthly' ORDER BY is_active DESC, name ASC");
  foreach(($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $it){
    if ($tuition && (int)$it['id']===(int)$tuition['id']) continue;
    $monthly_items[]=$it;
  }
  $st=$pdo->query("SELECT * FROM fee_items WHERE item_type='yearly' ORDER BY is_active DESC, name ASC");
  $yearly_items=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  // Preload overrides for all items displayed
  $itemIds = array_merge(array_map(function($x){return (int)$x['id'];}, $monthly_items), array_map(function($x){return (int)$x['id'];}, $yearly_items));
  if ($tuition) $itemIds[] = (int)$tuition['id'];
  $itemIds = array_values(array_unique($itemIds));
  if ($itemIds) {
    $in = implode(',', array_fill(0, count($itemIds), '?'));
    $st = $pdo->prepare("SELECT fee_item_id,class_id,amount FROM class_fees WHERE section_id IS NULL AND fee_item_id IN ($in)");
    $st->execute($itemIds);
    foreach(($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r){
      $overrides_by_item[(int)$r['fee_item_id']][(int)$r['class_id']] = (float)$r['amount'];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>সহজ ফিস সেটিংস</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <style>
    body{font-family:'SolaimanLipi','Source Sans Pro',sans-serif}
    .table td,.table th{vertical-align:middle!important}
    .small-muted{font-size:12px;color:#666}
    .amount-input{max-width:120px}
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include __DIR__ . '/../inc/header.php'; ?>
  <?php include __DIR__ . '/../inc/sidebar.php'; ?>
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">সহজ ফিস সেটিংস</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="index.php">হিসাব</a></li><li class="breadcrumb-item active">ফিস সেটিংস</li></ol></div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <?php if($ok) echo '<div class="alert alert-success">'.htmlspecialchars($ok).'</div>'; ?>
        <?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>

        <div class="card">
          <div class="card-header bg-primary text-white">১) শ্রেণি ভিত্তিক মাসিক বেতন</div>
          <div class="card-body">
            <form method="post">
              <input type="hidden" name="action" value="save_tuition">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <div class="form-row">
                <div class="form-group col-md-2">
                  <label>শেষ তারিখ (মাসের দিন)</label>
                  <input type="number" name="due_day" min="1" max="31" class="form-control" value="<?php echo htmlspecialchars((string)($tuition['due_day'] ?? '')); ?>" placeholder="যেমন: 10">
                </div>
                <div class="form-group col-md-2">
                  <label>জরিমানা</label>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="fine_enabled" value="1" id="t_fine_enabled" <?php echo !empty($tuition['fine_enabled'])?'checked':''; ?>>
                    <label for="t_fine_enabled" class="form-check-label">সক্রিয়</label>
                  </div>
                </div>
                <div class="form-group col-md-3">
                  <label>জরিমানার ধরন</label>
                  <select name="fine_type" class="form-control">
                    <?php $ft=$tuition['fine_type'] ?? 'flat'; ?>
                    <option value="flat" <?php echo $ft==='flat'?'selected':''; ?>>ফ্ল্যাট</option>
                    <option value="percent" <?php echo $ft==='percent'?'selected':''; ?>>শতাংশ</option>
                    <option value="per_day" <?php echo $ft==='per_day'?'selected':''; ?>>প্রতি দিন</option>
                  </select>
                </div>
                <div class="form-group col-md-3">
                  <label>জরিমানার মান</label>
                  <input type="number" step="0.01" min="0" name="fine_value" class="form-control" value="<?php echo htmlspecialchars(number_format((float)($tuition['fine_value'] ?? 0),2,'.','')); ?>">
                </div>
                <div class="form-group col-md-2">
                  <label>ডিফল্ট পরিমাণ (৳)</label>
                  <input type="number" step="0.01" min="0" name="default_amount" class="form-control" value="<?php echo htmlspecialchars(number_format((float)($tuition['amount'] ?? 0),2,'.','')); ?>">
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-bordered">
                  <thead><tr><th>শ্রেণি</th><th style="width:160px">পরিমাণ (৳)</th></tr></thead>
                  <tbody>
                    <?php foreach($classes as $c): $cid=(int)$c['id']; $val = $tuition_overrides[$cid] ?? ''; ?>
                      <tr>
                        <td><?php echo htmlspecialchars($c['name']); ?></td>
                        <td>
                          <input type="number" step="0.01" min="0" class="form-control amount-input" name="class_amount[<?php echo $cid; ?>]" value="<?php echo $val!==''?htmlspecialchars(number_format((float)$val,2,'.','')):''; ?>" placeholder="ডিফল্ট অনুসরণ করবে">
                          <div class="small-muted">ফাঁকা রাখলে উপরের ডিফল্ট প্রযোজ্য</div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <button class="btn btn-success"><i class="fas fa-save"></i> সংরক্ষণ</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header bg-info text-white">২) অন্যান্য মাসিক ফিস</div>
          <div class="card-body">
            <form method="post" class="form-row mb-3">
              <input type="hidden" name="action" value="add_monthly_item">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <div class="form-group col-md-4"><label>নাম</label><input type="text" name="name" class="form-control" placeholder="যেমন: রুটিন ফি" required></div>
              <div class="form-group col-md-2"><label>ডিফল্ট (৳)</label><input type="number" step="0.01" min="0" name="amount" class="form-control" required></div>
              <div class="form-group col-md-2"><label>শেষ দিন</label><input type="number" min="1" max="31" name="due_day" class="form-control" placeholder="যেমন: 10"></div>
              <div class="form-group col-md-2"><label>জরিমানা</label><div class="form-check"><input class="form-check-input" type="checkbox" name="fine_enabled" value="1" id="mfe"><label for="mfe" class="form-check-label">সক্রিয়</label></div></div>
              <div class="form-group col-md-2"><label>ধরন/মান</label>
                <div class="input-group">
                  <select name="fine_type" class="form-control"><option value="flat">ফ্ল্যাট</option><option value="percent">%</option><option value="per_day">প্রতি দিন</option></select>
                  <input type="number" step="0.01" min="0" name="fine_value" class="form-control" placeholder="মান">
                </div>
              </div>
              <div class="form-group col-md-12"><button class="btn btn-success"><i class="fas fa-plus"></i> যুক্ত</button></div>
            </form>

            <div class="table-responsive">
              <table class="table table-striped table-bordered">
                <thead><tr><th>#</th><th>নাম</th><th>ডিফল্ট (৳)</th><th>শেষ দিন</th><th>জরিমানা</th><th>স্ট্যাটাস</th><th class="text-right">অ্যাকশন</th></tr></thead>
                <tbody>
                  <?php if(!$monthly_items): ?><tr><td colspan="7" class="text-center text-muted">কিছু নেই</td></tr><?php endif; ?>
                  <?php $i=1; foreach($monthly_items as $it): $iid=(int)$it['id']; $ov=$overrides_by_item[$iid] ?? []; ?>
                    <tr>
                      <td><?php echo $i++; ?></td>
                      <td><?php echo htmlspecialchars($it['name']); ?></td>
                      <td>৳ <?php echo number_format((float)$it['amount'],2); ?></td>
                      <td><?php echo htmlspecialchars((string)($it['due_day'] ?? '-')); ?></td>
                      <td>
                        <?php if((int)$it['fine_enabled']===1): ?>
                          <?php echo htmlspecialchars($it['fine_type']); ?>: <?php echo htmlspecialchars((string)$it['fine_value']); ?>
                        <?php else: ?>-
                        <?php endif; ?>
                      </td>
                      <td><?php echo !empty($it['is_active'])?'<span class="badge badge-success">সক্রিয়</span>':'<span class="badge badge-secondary">নিষ্ক্রিয়</span>'; ?></td>
                      <td class="text-right">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse" data-target="#edit_item_<?php echo $iid; ?>">এডিট</button>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="toggle_item"><input type="hidden" name="item_id" value="<?php echo $iid; ?>">
                          <input type="hidden" name="val" value="<?php echo empty($it['is_active'])?1:0; ?>">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <button class="btn btn-sm btn-outline-warning"><?php echo empty($it['is_active'])?'সক্রিয়':'নিষ্ক্রিয়'; ?></button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('মুছে ফেলবেন?');">
                          <input type="hidden" name="action" value="delete_item"><input type="hidden" name="item_id" value="<?php echo $iid; ?>">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <button class="btn btn-sm btn-outline-danger">ডিলিট</button>
                        </form>
                      </td>
                    </tr>
                    <tr class="collapse" id="edit_item_<?php echo $iid; ?>">
                      <td colspan="7">
                        <form method="post">
                          <input type="hidden" name="action" value="update_item">
                          <input type="hidden" name="item_id" value="<?php echo $iid; ?>">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <div class="form-row">
                            <div class="form-group col-md-2"><label>ডিফল্ট (৳)</label><input type="number" step="0.01" min="0" name="amount" class="form-control" value="<?php echo htmlspecialchars(number_format((float)$it['amount'],2,'.','')); ?>"></div>
                            <div class="form-group col-md-2"><label>শেষ দিন</label><input type="number" min="1" max="31" name="due_day" class="form-control" value="<?php echo htmlspecialchars((string)($it['due_day'] ?? '')); ?>"></div>
                            <div class="form-group col-md-2"><label>জরিমানা</label><div class="form-check"><input class="form-check-input" type="checkbox" name="fine_enabled" value="1" id="fe_m_<?php echo $iid; ?>" <?php echo !empty($it['fine_enabled'])?'checked':''; ?>><label class="form-check-label" for="fe_m_<?php echo $iid; ?>">সক্রিয়</label></div></div>
                            <div class="form-group col-md-3"><label>ধরন</label><select name="fine_type" class="form-control"><?php $ft=$it['fine_type'] ?? 'flat'; ?><option value="flat" <?php echo $ft==='flat'?'selected':''; ?>>ফ্ল্যাট</option><option value="percent" <?php echo $ft==='percent'?'selected':''; ?>>শতাংশ</option><option value="per_day" <?php echo $ft==='per_day'?'selected':''; ?>>প্রতি দিন</option></select></div>
                            <div class="form-group col-md-3"><label>মান</label><input type="number" step="0.01" min="0" name="fine_value" class="form-control" value="<?php echo htmlspecialchars(number_format((float)$it['fine_value'],2,'.','')); ?>"></div>
                          </div>
                          <div class="table-responsive">
                            <table class="table table-sm table-bordered"><thead><tr><th>শ্রেণি</th><th style="width:160px">পরিমাণ (৳)</th></tr></thead><tbody>
                              <?php foreach($classes as $c): $cid=(int)$c['id']; $val = $ov[$cid] ?? ''; ?>
                                <tr>
                                  <td><?php echo htmlspecialchars($c['name']); ?></td>
                                  <td><input type="number" step="0.01" min="0" class="form-control amount-input" name="class_amount[<?php echo $cid; ?>]" value="<?php echo $val!==''?htmlspecialchars(number_format((float)$val,2,'.','')):''; ?>" placeholder="ডিফল্ট অনুসরণ করবে"></td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody></table>
                          </div>
                          <button class="btn btn-primary"><i class="fas fa-save"></i> সেভ</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header bg-secondary text-white">৩) শিক্ষাবর্ষে একবারের ফিস</div>
          <div class="card-body">
            <form method="post" class="form-row mb-3">
              <input type="hidden" name="action" value="add_yearly_item">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <div class="form-group col-md-4"><label>নাম</label><input type="text" name="name" class="form-control" placeholder="যেমন: ভর্তি ফি, পরীক্ষার ফি" required></div>
              <div class="form-group col-md-2"><label>ডিফল্ট (৳)</label><input type="number" step="0.01" min="0" name="amount" class="form-control" required></div>
              <div class="form-group col-md-2"><label>শেষ দিন</label><input type="number" min="1" max="31" name="due_day" class="form-control" placeholder="ঐচ্ছিক"></div>
              <div class="form-group col-md-2"><label>জরিমানা</label><div class="form-check"><input class="form-check-input" type="checkbox" name="fine_enabled" value="1" id="yf"><label for="yf" class="form-check-label">সক্রিয়</label></div></div>
              <div class="form-group col-md-2"><label>ধরন/মান</label>
                <div class="input-group">
                  <select name="fine_type" class="form-control"><option value="flat">ফ্ল্যাট</option><option value="percent">%</option><option value="per_day">প্রতি দিন</option></select>
                  <input type="number" step="0.01" min="0" name="fine_value" class="form-control" placeholder="মান">
                </div>
              </div>
              <div class="form-group col-md-12"><span class="small-muted">নির্দিষ্ট শ্রেণির জন্য ভিন্ন পরিমাণ দরকার হলে যুক্ত করার পর নিচে "এডিট" থেকে শ্রেণিভিত্তিক পরিমাণ ঠিক করুন।</span></div>
              <div class="form-group col-md-12"><button class="btn btn-success"><i class="fas fa-plus"></i> যুক্ত</button></div>
            </form>

            <div class="table-responsive">
              <table class="table table-striped table-bordered">
                <thead><tr><th>#</th><th>নাম</th><th>ডিফল্ট (৳)</th><th>শেষ দিন</th><th>জরিমানা</th><th>স্ট্যাটাস</th><th class="text-right">অ্যাকশন</th></tr></thead>
                <tbody>
                  <?php if(!$yearly_items): ?><tr><td colspan="7" class="text-center text-muted">কিছু নেই</td></tr><?php endif; ?>
                  <?php $i=1; foreach($yearly_items as $it): $iid=(int)$it['id']; $ov=$overrides_by_item[$iid] ?? []; ?>
                    <tr>
                      <td><?php echo $i++; ?></td>
                      <td><?php echo htmlspecialchars($it['name']); ?></td>
                      <td>৳ <?php echo number_format((float)$it['amount'],2); ?></td>
                      <td><?php echo htmlspecialchars((string)($it['due_day'] ?? '-')); ?></td>
                      <td>
                        <?php if((int)$it['fine_enabled']===1): ?>
                          <?php echo htmlspecialchars($it['fine_type']); ?>: <?php echo htmlspecialchars((string)$it['fine_value']); ?>
                        <?php else: ?>-
                        <?php endif; ?>
                      </td>
                      <td><?php echo !empty($it['is_active'])?'<span class="badge badge-success">সক্রিয়</span>':'<span class="badge badge-secondary">নিষ্ক্রিয়</span>'; ?></td>
                      <td class="text-right">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse" data-target="#edit_item_<?php echo $iid; ?>_y">এডিট</button>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="toggle_item"><input type="hidden" name="item_id" value="<?php echo $iid; ?>">
                          <input type="hidden" name="val" value="<?php echo empty($it['is_active'])?1:0; ?>">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <button class="btn btn-sm btn-outline-warning"><?php echo empty($it['is_active'])?'সক্রিয়':'নিষ্ক্রিয়'; ?></button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('মুছে ফেলবেন?');">
                          <input type="hidden" name="action" value="delete_item"><input type="hidden" name="item_id" value="<?php echo $iid; ?>">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <button class="btn btn-sm btn-outline-danger">ডিলিট</button>
                        </form>
                      </td>
                    </tr>
                    <tr class="collapse" id="edit_item_<?php echo $iid; ?>_y">
                      <td colspan="7">
                        <form method="post">
                          <input type="hidden" name="action" value="update_item">
                          <input type="hidden" name="item_id" value="<?php echo $iid; ?>">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                          <div class="form-row">
                            <div class="form-group col-md-2"><label>ডিফল্ট (৳)</label><input type="number" step="0.01" min="0" name="amount" class="form-control" value="<?php echo htmlspecialchars(number_format((float)$it['amount'],2,'.','')); ?>"></div>
                            <div class="form-group col-md-2"><label>শেষ দিন</label><input type="number" min="1" max="31" name="due_day" class="form-control" value="<?php echo htmlspecialchars((string)($it['due_day'] ?? '')); ?>"></div>
                            <div class="form-group col-md-2"><label>জরিমানা</label><div class="form-check"><input class="form-check-input" type="checkbox" name="fine_enabled" value="1" id="fe_y_<?php echo $iid; ?>" <?php echo !empty($it['fine_enabled'])?'checked':''; ?>><label class="form-check-label" for="fe_y_<?php echo $iid; ?>">সক্রিয়</label></div></div>
                            <div class="form-group col-md-3"><label>ধরন</label><select name="fine_type" class="form-control"><?php $ft=$it['fine_type'] ?? 'flat'; ?><option value="flat" <?php echo $ft==='flat'?'selected':''; ?>>ফ্ল্যাট</option><option value="percent" <?php echo $ft==='percent'?'selected':''; ?>>শতাংশ</option><option value="per_day" <?php echo $ft==='per_day'?'selected':''; ?>>প্রতি দিন</option></select></div>
                            <div class="form-group col-md-3"><label>মান</label><input type="number" step="0.01" min="0" name="fine_value" class="form-control" value="<?php echo htmlspecialchars(number_format((float)$it['fine_value'],2,'.','')); ?>"></div>
                          </div>
                          <div class="table-responsive">
                            <table class="table table-sm table-bordered"><thead><tr><th>শ্রেণি</th><th style="width:160px">পরিমাণ (৳)</th></tr></thead><tbody>
                              <?php foreach($classes as $c): $cid=(int)$c['id']; $val = $ov[$cid] ?? ''; ?>
                                <tr>
                                  <td><?php echo htmlspecialchars($c['name']); ?></td>
                                  <td><input type="number" step="0.01" min="0" class="form-control amount-input" name="class_amount[<?php echo $cid; ?>]" value="<?php echo $val!==''?htmlspecialchars(number_format((float)$val,2,'.','')):''; ?>" placeholder="ডিফল্ট অনুসরণ করবে"></td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody></table>
                          </div>
                          <button class="btn btn-primary"><i class="fas fa-save"></i> সেভ</button>
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
  <?php include __DIR__ . '/../inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
