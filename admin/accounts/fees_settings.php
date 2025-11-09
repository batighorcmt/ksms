<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/inc/finance_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/sidebar.php';

// Helpers
function fs_post($key,$default=null){ return isset($_POST[$key])?$_POST[$key]:$default; }
function fs_num($v){ return (float)($v!==''?$v:0); }

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'items';
$errors = [];$success='';

// Actions: Fee Items
if ($_SERVER['REQUEST_METHOD']==='POST' && fs_post('form')==='item_create') {
  $name = trim(fs_post('name',''));
  $item_type = fs_post('item_type','monthly');
  $amount = fs_num(fs_post('amount','0'));
  if ($name==='') $errors[]='আইটেমের নাম দিন';
  if (!in_array($item_type,['monthly','item','exam'])) $errors[]='ভুল আইটেম ধরন';
  if (!$errors && $pdo instanceof PDO) {
    $st=$pdo->prepare("INSERT INTO fee_items(name,item_type,amount,is_active) VALUES(?,?,?,1)");
    $st->execute([$name,$item_type,$amount]);
    $success='আইটেমটি সংযুক্ত হয়েছে';
    $tab='items';
  }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && fs_post('form')==='item_update') {
  $id = (int)fs_post('id',0);
  $name = trim(fs_post('name',''));
  $amount = fs_num(fs_post('amount','0'));
  $is_active = (int)fs_post('is_active',1);
  if ($id<=0) $errors[]='অবৈধ আইডি';
  if ($name==='') $errors[]='আইটেমের নাম দিন';
  if (!$errors && $pdo instanceof PDO) {
    $st=$pdo->prepare("UPDATE fee_items SET name=?, amount=?, is_active=? WHERE id=?");
    $st->execute([$name,$amount,$is_active,$id]);
    $success='আইটেমটি হালনাগাদ হয়েছে';
    $tab='items';
  }
}
if (isset($_GET['delete_item'])) {
  $id=(int)$_GET['delete_item'];
  if ($pdo instanceof PDO) {
    $pdo->prepare("DELETE FROM fee_items WHERE id=?")->execute([$id]);
    $success='আইটেমটি মুছে ফেলা হয়েছে';
    $tab='items';
  }
}

// Actions: Class Fees (monthly)
if ($_SERVER['REQUEST_METHOD']==='POST' && fs_post('form')==='class_fee_save') {
  $class_id=(int)fs_post('class_id',0);
  $section_id=fs_post('section_id','');
  $section_id = ($section_id==='')?null:(int)$section_id;
  $fee_item_id=(int)fs_post('fee_item_id',0);
  $amount = fs_num(fs_post('amount','0'));
  if ($class_id<=0 || $fee_item_id<=0) $errors[]='শ্রেণি এবং আইটেম নির্বাচন করুন';
  if (!$errors && $pdo instanceof PDO) {
    // Ensure unique row: class_id, section_id, fee_item_id
    $sql = "INSERT INTO class_fees(class_id,section_id,fee_item_id,amount) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount)";
    $st=$pdo->prepare($sql);
    $st->execute([$class_id,$section_id,$fee_item_id,$amount]);
    $success='ক্লাস ফি সংরক্ষণ হয়েছে';
    $tab='class';
  }
}
if (isset($_GET['delete_class_fee'])) {
  $id=(int)$_GET['delete_class_fee'];
  if ($pdo instanceof PDO) {
    $pdo->prepare("DELETE FROM class_fees WHERE id=?")->execute([$id]);
    $success='ক্লাস ফি মুছে ফেলা হয়েছে';
    $tab='class';
  }
}

// Actions: Exam Fees
if ($_SERVER['REQUEST_METHOD']==='POST' && fs_post('form')==='exam_fee_save') {
  $class_id=(int)fs_post('class_id',0);
  $exam_name=trim(fs_post('exam_name',''));
  $amount=fs_num(fs_post('amount','0'));
  if ($class_id<=0 || $exam_name==='') $errors[]='শ্রেণি এবং পরীক্ষার নাম দিন';
  if (!$errors && $pdo instanceof PDO) {
    $sql="INSERT INTO exam_fees(class_id,exam_name,amount) VALUES(?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount)";
    $st=$pdo->prepare($sql);
    $st->execute([$class_id,$exam_name,$amount]);
    $success='পরীক্ষা ফি সংরক্ষণ হয়েছে';
    $tab='exam';
  }
}
if (isset($_GET['delete_exam_fee'])) {
  $id=(int)$_GET['delete_exam_fee'];
  if ($pdo instanceof PDO) {
    $pdo->prepare("DELETE FROM exam_fees WHERE id=?")->execute([$id]);
    $success='পরীক্ষা ফি মুছে ফেলা হয়েছে';
    $tab='exam';
  }
}

// Fetch data for UI
$classes = [];
$monthlyItems = [];
$allItems = [];
$classFees = [];
$examFees = [];
if ($pdo instanceof PDO) {
  try { $classes = get_classes($pdo); } catch (Throwable $e) { $classes=[]; }
  try { $allItems = $pdo->query("SELECT id,name,item_type,amount,is_active FROM fee_items ORDER BY item_type,name")->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch(Throwable $e){ $allItems=[]; }
  $monthlyItems = array_values(array_filter($allItems,function($r){ return $r['item_type']==='monthly' && (int)$r['is_active']===1; }));
  try {
    $classFees = $pdo->query("SELECT cf.id, cf.class_id, cf.section_id, cf.amount, fi.name AS item_name FROM class_fees cf JOIN fee_items fi ON fi.id=cf.fee_item_id ORDER BY cf.class_id, cf.section_id, fi.name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { $classFees=[]; }
  try {
    $examFees = $pdo->query("SELECT id, class_id, exam_name, amount FROM exam_fees ORDER BY class_id, exam_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { $examFees=[]; }
}

?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ফি সেটিংস</title>
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
          <div class="col-sm-6"><h1 class="m-0">ফি সেটিংস</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="index.php">হিসাব</a></li><li class="breadcrumb-item active">ফি সেটিংস</li></ol></div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <?php if($success) echo '<div class="alert alert-success">'.htmlspecialchars($success).'</div>'; ?>
        <?php foreach($errors as $e) echo '<div class="alert alert-danger">'.htmlspecialchars($e).'</div>'; ?>

        <ul class="nav nav-tabs" id="fsTabs" role="tablist">
          <li class="nav-item"><a class="nav-link <?php echo $tab==='items'?'active':''; ?>" href="?tab=items">ফি আইটেম</a></li>
          <li class="nav-item"><a class="nav-link <?php echo $tab==='class'?'active':''; ?>" href="?tab=class">ক্লাসভিত্তিক ফি</a></li>
          <li class="nav-item"><a class="nav-link <?php echo $tab==='exam'?'active':''; ?>" href="?tab=exam">পরীক্ষা ফি</a></li>
        </ul>

        <div class="tab-content pt-3">
          <!-- Fee Items Tab -->
          <?php if($tab==='items'): ?>
          <div>
            <div class="card">
              <div class="card-header bg-primary text-white">নতুন আইটেম</div>
              <div class="card-body">
                <form method="post" class="form-inline">
                  <input type="hidden" name="form" value="item_create">
                  <div class="form-group mr-2">
                    <label class="mr-2">ধরণ</label>
                    <select name="item_type" class="form-control">
                      <option value="monthly">মাসিক</option>
                      <option value="item">আইটেম</option>
                      <option value="exam">পরীক্ষা</option>
                    </select>
                  </div>
                  <div class="form-group mr-2">
                    <label class="mr-2">নাম</label>
                    <input type="text" name="name" class="form-control" required>
                  </div>
                  <div class="form-group mr-2">
                    <label class="mr-2">পরিমাণ</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control" value="0">
                  </div>
                  <button class="btn btn-success"><i class="fas fa-plus"></i> যোগ করুন</button>
                </form>
              </div>
            </div>

            <div class="card">
              <div class="card-header">আইটেম তালিকা</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped mb-0">
                    <thead><tr><th>#</th><th>ধরণ</th><th>নাম</th><th>পরিমাণ</th><th>অবস্থা</th><th width="160">অ্যাকশন</th></tr></thead>
                    <tbody>
                      <?php if(!$allItems): ?><tr><td colspan="6" class="text-center text-muted">কোন আইটেম নেই</td></tr><?php endif; ?>
                      <?php $i=1; foreach($allItems as $it): ?>
                      <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($it['item_type']); ?></td>
                        <td>
                          <form method="post" class="form-inline">
                            <input type="hidden" name="form" value="item_update">
                            <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                            <input type="text" name="name" value="<?php echo htmlspecialchars($it['name']); ?>" class="form-control form-control-sm mr-2" style="min-width:200px">
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0" name="amount" value="<?php echo htmlspecialchars($it['amount']); ?>" class="form-control form-control-sm" style="width:110px">
                        </td>
                        <td>
                            <select name="is_active" class="form-control form-control-sm">
                              <option value="1" <?php echo $it['is_active']? 'selected':''; ?>>সক্রিয়</option>
                              <option value="0" <?php echo !$it['is_active']? 'selected':''; ?>>নিষ্ক্রিয়</option>
                            </select>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary"><i class="fas fa-save"></i></button>
                            <a class="btn btn-sm btn-danger" href="?tab=items&delete_item=<?php echo (int)$it['id']; ?>" onclick="return confirm('মুছে ফেলতে চান?')"><i class="fas fa-trash"></i></a>
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
          <?php endif; ?>

          <!-- Class Fees Tab -->
          <?php if($tab==='class'): ?>
          <div>
            <div class="card">
              <div class="card-header bg-info text-white">ক্লাসভিত্তিক মাসিক ফি</div>
              <div class="card-body">
                <form method="post">
                  <input type="hidden" name="form" value="class_fee_save">
                  <div class="form-row">
                    <div class="form-group col-md-3">
                      <label>শ্রেণি</label>
                      <select name="class_id" id="class_id" class="form-control" required>
                        <option value="">- নির্বাচন -</option>
                        <?php foreach($classes as $c): ?>
                          <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group col-md-3">
                      <label>শাখা (ঐচ্ছিক)</label>
                      <select name="section_id" id="section_id" class="form-control"><option value="">- নেই -</option></select>
                    </div>
                    <div class="form-group col-md-3">
                      <label>আইটেম</label>
                      <select name="fee_item_id" class="form-control" required>
                        <option value="">- নির্বাচন -</option>
                        <?php foreach($monthlyItems as $mi): ?>
                          <option value="<?php echo (int)$mi['id']; ?>"><?php echo htmlspecialchars($mi['name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group col-md-2">
                      <label>পরিমাণ</label>
                      <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group col-md-1 d-flex align-items-end">
                      <button class="btn btn-primary btn-block">সেভ</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>

            <div class="card">
              <div class="card-header">ক্লাস ফি তালিকা</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped mb-0">
                    <thead><tr><th>#</th><th>শ্রেণি</th><th>শাখা</th><th>আইটেম</th><th class="text-right">পরিমাণ</th><th>অ্যাকশন</th></tr></thead>
                    <tbody>
                      <?php if(!$classFees): ?><tr><td colspan="6" class="text-center text-muted">ডাটা নেই</td></tr><?php endif; ?>
                      <?php $i=1; foreach($classFees as $cf): ?>
                        <?php
                          $cName = ''; $sName='';
                          foreach($classes as $c){ if($c['id']==$cf['class_id']){$cName=$c['name'];break;} }
                          if ($cf['section_id'] && ($pdo instanceof PDO)) {
                            try { $secs = get_sections_by_class($pdo, (int)$cf['class_id']); foreach($secs as $s){ if($s['id']==$cf['section_id']){$sName=$s['name'];break;} } } catch(Throwable $e){}
                          }
                        ?>
                        <tr>
                          <td><?php echo $i++; ?></td>
                          <td><?php echo htmlspecialchars($cName); ?></td>
                          <td><?php echo htmlspecialchars($sName ?: '-'); ?></td>
                          <td><?php echo htmlspecialchars($cf['item_name']); ?></td>
                          <td class="text-right">৳ <?php echo money($cf['amount']); ?></td>
                          <td>
                            <a class="btn btn-sm btn-danger" href="?tab=class&delete_class_fee=<?php echo (int)$cf['id']; ?>" onclick="return confirm('মুছে ফেলতে চান?')"><i class="fas fa-trash"></i></a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Exam Fees Tab -->
          <?php if($tab==='exam'): ?>
          <div>
            <div class="card">
              <div class="card-header bg-warning">পরীক্ষা ফি সেটিংস</div>
              <div class="card-body">
                <form method="post" class="form-inline">
                  <input type="hidden" name="form" value="exam_fee_save">
                  <div class="form-group mr-2">
                    <label class="mr-2">শ্রেণি</label>
                    <select name="class_id" class="form-control" required>
                      <option value="">- নির্বাচন -</option>
                      <?php foreach($classes as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group mr-2">
                    <label class="mr-2">পরীক্ষা</label>
                    <input type="text" name="exam_name" class="form-control" placeholder="যেমন: বার্ষিক" required>
                  </div>
                  <div class="form-group mr-2">
                    <label class="mr-2">পরিমাণ</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
                  </div>
                  <button class="btn btn-primary">সেভ</button>
                </form>
              </div>
            </div>

            <div class="card">
              <div class="card-header">পরীক্ষা ফি তালিকা</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped mb-0">
                    <thead><tr><th>#</th><th>শ্রেণি</th><th>পরীক্ষা</th><th class="text-right">পরিমাণ</th><th>অ্যাকশন</th></tr></thead>
                    <tbody>
                      <?php if(!$examFees): ?><tr><td colspan="5" class="text-center text-muted">ডাটা নেই</td></tr><?php endif; ?>
                      <?php $i=1; foreach($examFees as $ef): ?>
                        <?php $cName=''; foreach($classes as $c){ if($c['id']==$ef['class_id']){$cName=$c['name'];break;} } ?>
                        <tr>
                          <td><?php echo $i++; ?></td>
                          <td><?php echo htmlspecialchars($cName); ?></td>
                          <td><?php echo htmlspecialchars($ef['exam_name']); ?></td>
                          <td class="text-right">৳ <?php echo money($ef['amount']); ?></td>
                          <td>
                            <a class="btn btn-sm btn-danger" href="?tab=exam&delete_exam_fee=<?php echo (int)$ef['id']; ?>" onclick="return confirm('মুছে ফেলতে চান?')"><i class="fas fa-trash"></i></a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </section>
  </div>
  <?php include __DIR__ . '/../inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Load sections by class
$(function(){
  function loadSections(cid){
    $('#section_id').html('<option value="">লোড হচ্ছে...</option>');
    $.get('<?php echo BASE_URL; ?>admin/get_sections.php',{class_id: cid}, function(html){
      if(!html){ $('#section_id').html('<option value="">- নেই -</option>'); return; }
      // Expecting HTML options from existing endpoint
      $('#section_id').html('<option value="">- নেই -</option>'+html);
    }).fail(function(){ $('#section_id').html('<option value="">- নেই -</option>'); });
  }
  $(document).on('change','#class_id',function(){ var cid=$(this).val(); if(cid){ loadSections(cid);} else { $('#section_id').html('<option value="">- নেই -</option>'); }});
});
</script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
