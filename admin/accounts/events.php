<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/inc/finance_helpers.php';
require_once __DIR__ . '/../inc/enrollment_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/sidebar.php';

// Ensure tables exist (safe no-op if already created)
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
}

$classes = ($pdo instanceof PDO) ? (get_classes($pdo) ?? []) : [];
$sectionsByClass = [];
if ($pdo instanceof PDO) {
  foreach ($classes as $c) {
    $sectionsByClass[$c['id']] = get_sections_by_class($pdo, (int)$c['id']);
  }
}

$ok = '';$err='';
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Helpers
function postv($k,$d=null){return isset($_POST[$k])?$_POST[$k]:$d;}
function to_null($v){ $v=trim((string)$v); return $v!=='' ? $v : null; }

if ($pdo instanceof PDO) {
  if ($action==='create') {
    $name = trim((string)postv('name',''));
    $class_id = to_null(postv('class_id',''));
    $section_id = to_null(postv('section_id',''));
    $amount = (float)postv('amount','0');
    $due_date = to_null(postv('due_date',''));
    $fine_enabled = (int)postv('fine_enabled',0) ? 1 : 0;
    $fine_type = in_array(postv('fine_type','flat'), ['flat','percent','per_day'], true) ? postv('fine_type','flat') : 'flat';
    $fine_value = (float)postv('fine_value','0');
    $year_id = current_academic_year_id($pdo);
    if ($name==='') { $err='নাম প্রয়োজন'; }
    if (!$err) {
      try {
        $st=$pdo->prepare("INSERT INTO fee_events(academic_year_id,name,class_id,section_id,amount,due_date,fine_enabled,fine_type,fine_value,is_active) VALUES(?,?,?,?,?,?,?,?,?,1)");
        $st->execute([$year_id,$name, $class_id? (int)$class_id : null, $section_id? (int)$section_id : null, $amount, $due_date, $fine_enabled, $fine_type, $fine_value]);
        $ok='ইভেন্ট যুক্ত হয়েছে';
      } catch(Throwable $e){ $err='সংরক্ষণ ব্যর্থ: '.$e->getMessage(); }
    }
  } elseif ($action==='toggle' && isset($_POST['id'])) {
    $id=(int)$_POST['id']; $val=(int)postv('val',1)?1:0;
    try{ $pdo->prepare("UPDATE fee_events SET is_active=? WHERE id=?")->execute([$val,$id]); $ok='স্ট্যাটাস আপডেট হয়েছে'; }catch(Throwable $e){ $err='ব্যর্থ: '.$e->getMessage(); }
  } elseif ($action==='delete' && isset($_POST['id'])) {
    $id=(int)$_POST['id'];
    try{ $pdo->prepare("DELETE FROM fee_events WHERE id=?")->execute([$id]); $ok='ইভেন্ট মুছে ফেলা হয়েছে'; }catch(Throwable $e){ $err='ব্যর্থ: '.$e->getMessage(); }
  }
}

// Load list
$events=[];
if ($pdo instanceof PDO) {
  $year_id = current_academic_year_id($pdo);
  $where=[]; $params=[];
  if ($year_id) { $where[] = ' (academic_year_id IS NULL OR academic_year_id = ?) '; $params[]=$year_id; }
  $sql="SELECT fe.*, c.name AS class_name, s.name AS section_name
        FROM fee_events fe
        LEFT JOIN classes c ON c.id = fe.class_id
        LEFT JOIN sections s ON s.id = fe.section_id";
  if ($where) { $sql.=' WHERE '.implode(' AND ',$where); }
  $sql.=' ORDER BY COALESCE(fe.due_date, STR_TO_DATE(CONCAT(YEAR(CURDATE()),"-12-31"),"%Y-%m-%d")) ASC, fe.id DESC';
  $st=$pdo->prepare($sql); $st->execute($params);
  $events=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ইভেন্ট ফিস</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <style>body{font-family:'SolaimanLipi','Source Sans Pro',sans-serif}</style>
  <script>var sectionsByClass = <?php echo json_encode($sectionsByClass); ?>;</script>
  <script>
    function onClassChange(sel){
      var cid = sel.value || '';
      var secSel = document.getElementById('section_id');
      secSel.innerHTML = '<option value="">- সব -</option>';
      if (!cid || !sectionsByClass[cid]) return;
      var list = sectionsByClass[cid] || [];
      list.forEach(function(s){
        var opt = document.createElement('option');
        opt.value = s.id; opt.text = s.name; secSel.appendChild(opt);
      });
    }
  </script>
  <style>.table td,.table th{vertical-align:middle!important}</style>
  </head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">ইভেন্ট ফিস</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="index.php">হিসাব</a></li><li class="breadcrumb-item active">ইভেন্ট ফিস</li></ol></div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <?php echo $ok?'<div class="alert alert-success">'.htmlspecialchars($ok).'</div>':''; ?>
        <?php echo $err?'<div class="alert alert-danger">'.htmlspecialchars($err).'</div>':''; ?>

        <div class="card">
          <div class="card-header bg-primary text-white">নতুন ইভেন্ট</div>
          <div class="card-body">
            <form method="post" class="form-inline">
              <input type="hidden" name="action" value="create">
              <div class="form-group mb-2 mr-2">
                <label class="mr-2">নাম</label>
                <input type="text" name="name" class="form-control" required placeholder="যেমন: সেশন চার্জ">
              </div>
              <div class="form-group mb-2 mr-2">
                <label class="mr-2">শ্রেণি</label>
                <select name="class_id" class="form-control" onchange="onClassChange(this)">
                  <option value="">- সব -</option>
                  <?php foreach($classes as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group mb-2 mr-2">
                <label class="mr-2">শাখা</label>
                <select name="section_id" id="section_id" class="form-control"><option value="">- সব -</option></select>
              </div>
              <div class="form-group mb-2 mr-2">
                <label class="mr-2">পরিমাণ (৳)</label>
                <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
              </div>
              <div class="form-group mb-2 mr-2">
                <label class="mr-2">শেষ তারিখ</label>
                <input type="date" name="due_date" class="form-control">
              </div>
              <div class="form-group mb-2 mr-2">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1" id="fine_enabled" name="fine_enabled">
                  <label class="form-check-label" for="fine_enabled">জরিমানা</label>
                </div>
              </div>
              <div class="form-group mb-2 mr-2">
                <select name="fine_type" class="form-control">
                  <option value="flat">ফ্ল্যাট</option>
                  <option value="percent">শতাংশ</option>
                  <option value="per_day">প্রতি দিন</option>
                </select>
              </div>
              <div class="form-group mb-2 mr-2">
                <input type="number" step="0.01" min="0" name="fine_value" class="form-control" placeholder="জরিমানার মান">
              </div>
              <button class="btn btn-success mb-2"><i class="fas fa-plus"></i> যুক্ত</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header">ইভেন্ট তালিকা</div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped mb-0">
                <thead><tr><th>#</th><th>নাম</th><th>শ্রেণি</th><th>শাখা</th><th class="text-right">পরিমাণ</th><th>শেষ তারিখ</th><th>জরিমানা</th><th>স্ট্যাটাস</th><th class="text-right pr-3">অ্যাকশন</th></tr></thead>
                <tbody>
                  <?php if(!$events): ?><tr><td colspan="9" class="text-center text-muted">ডাটা নেই</td></tr><?php endif; ?>
                  <?php $i=1; foreach($events as $ev): ?>
                    <tr>
                      <td><?php echo $i++; ?></td>
                      <td><?php echo htmlspecialchars($ev['name']); ?></td>
                      <td><?php echo htmlspecialchars($ev['class_name'] ?? 'সব'); ?></td>
                      <td><?php echo htmlspecialchars($ev['section_name'] ?? 'সব'); ?></td>
                      <td class="text-right">৳ <?php echo number_format((float)$ev['amount'],2); ?></td>
                      <td><?php echo htmlspecialchars($ev['due_date'] ?? '-'); ?></td>
                      <td>
                        <?php if((int)$ev['fine_enabled']===1): ?>
                          <?php echo htmlspecialchars($ev['fine_type']); ?>: <?php echo htmlspecialchars($ev['fine_value']); ?>
                        <?php else: ?>-
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="badge badge-<?php echo ((int)$ev['is_active']===1?'success':'secondary'); ?>"><?php echo ((int)$ev['is_active']===1?'সক্রিয়':'নিষ্ক্রিয়'); ?></span>
                      </td>
                      <td class="text-right pr-3">
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="toggle">
                          <input type="hidden" name="id" value="<?php echo (int)$ev['id']; ?>">
                          <input type="hidden" name="val" value="<?php echo (int)$ev['is_active']?0:1; ?>">
                          <button class="btn btn-sm btn-outline-<?php echo ((int)$ev['is_active']? 'warning':'success'); ?>" title="Toggle">
                            <?php echo ((int)$ev['is_active']? 'নিষ্ক্রিয়':'সক্রিয়'); ?>
                          </button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('মুছতে চান?');">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?php echo (int)$ev['id']; ?>">
                          <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
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
