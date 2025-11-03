<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/inc/finance_helpers.php';
finance_require_admin();
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/sidebar.php';

function sw_post($k,$d=null){ return isset($_POST[$k])?$_POST[$k]:$d; }
function sw_num($v){ return (float)($v!==''?$v:0); }

$err=''; $ok='';

// Create/Update waiver (upsert)
if ($_SERVER['REQUEST_METHOD']==='POST' && sw_post('form')==='waiver_save') {
  $student_id = (int)sw_post('student_id',0);
  $fee_item_id = (int)sw_post('fee_item_id',0);
  $discount_type = sw_post('discount_type','percent');
  $discount_value = sw_num(sw_post('discount_value','0'));
  $note = trim(sw_post('note',''));
  if ($student_id<=0 || $fee_item_id<=0) $err = 'শিক্ষার্থী এবং আইটেম নির্বাচন করুন';
  if (!in_array($discount_type,['percent','amount'])) $err = 'ডিসকাউন্ট ধরন সঠিক নয়';
  if ($discount_value < 0) $err = 'ডিসকাউন্ট মান সঠিক নয়';
  if (!$err && ($pdo instanceof PDO)) {
    try {
      $sql = "INSERT INTO student_fee_overrides(student_id, fee_item_id, discount_type, discount_value, note) VALUES (?,?,?,?,?) "
           . "ON DUPLICATE KEY UPDATE discount_type=VALUES(discount_type), discount_value=VALUES(discount_value), note=VALUES(note)";
      $st = $pdo->prepare($sql);
      $st->execute([$student_id, $fee_item_id, $discount_type, $discount_value, $note ?: null]);
      $ok = 'ছাড় সংরক্ষণ হয়েছে';
    } catch (Throwable $e) {
      $err = 'সংরক্ষণ ব্যর্থ: '.$e->getMessage();
    }
  }
}

// Inline update row
if ($_SERVER['REQUEST_METHOD']==='POST' && sw_post('form')==='waiver_update') {
  $id = (int)sw_post('id',0);
  $discount_type = sw_post('discount_type','percent');
  $discount_value = sw_num(sw_post('discount_value','0'));
  $note = trim(sw_post('note',''));
  if ($id<=0) $err = 'অবৈধ আইডি';
  if (!in_array($discount_type,['percent','amount'])) $err = 'ডিসকাউন্ট ধরন সঠিক নয়';
  if ($discount_value < 0) $err = 'ডিসকাউন্ট মান সঠিক নয়';
  if (!$err && ($pdo instanceof PDO)) {
    try {
      $st = $pdo->prepare("UPDATE student_fee_overrides SET discount_type=?, discount_value=?, note=? WHERE id=?");
      $st->execute([$discount_type, $discount_value, $note ?: null, $id]);
      $ok = 'হালনাগাদ হয়েছে';
    } catch (Throwable $e) { $err = 'হালনাগাদ ব্যর্থ: '.$e->getMessage(); }
  }
}

// Delete
if (isset($_GET['delete_override'])) {
  $did = (int)$_GET['delete_override'];
  if ($pdo instanceof PDO) {
    try { $pdo->prepare("DELETE FROM student_fee_overrides WHERE id=?")->execute([$did]); $ok='মুছে ফেলা হয়েছে'; } catch(Throwable $e){ $err='মুছতে সমস্যা: '.$e->getMessage(); }
  }
}

// Data for UI
$classes = [];
$items = [];
$overrides = [];
if ($pdo instanceof PDO) {
  try { $classes = get_classes($pdo); } catch (Throwable $e) { $classes=[]; }
  try { $items = $pdo->query("SELECT id,name,item_type,amount FROM fee_items WHERE is_active=1 ORDER BY item_type,name")->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch(Throwable $e){ $items=[]; }
  try {
    // fetch with student name, class/section via current enrollment if available
    $overrides = $pdo->query(
      "SELECT o.id,o.student_id,o.fee_item_id,o.discount_type,o.discount_value,o.note, 
              s.first_name, s.last_name, fi.name AS item_name
         FROM student_fee_overrides o
         JOIN students s ON s.id = o.student_id
         LEFT JOIN fee_items fi ON fi.id = o.fee_item_id
         ORDER BY s.id ASC, fi.name ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { $overrides=[]; }
}

?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>শিক্ষার্থী ছাড়</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <style>
    body{font-family:'SolaimanLipi','Source Sans Pro',sans-serif}
    .small-note{font-size:12px;color:#666}
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0">শিক্ষার্থী ছাড়</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="index.php">হিসাব</a></li><li class="breadcrumb-item active">ছাড়</li></ol></div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <?php if($ok) echo '<div class="alert alert-success">'.htmlspecialchars($ok).'</div>'; ?>
        <?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>

        <div class="card">
          <div class="card-header bg-primary text-white">নতুন ছাড় নির্ধারণ</div>
          <div class="card-body">
            <form method="post">
              <input type="hidden" name="form" value="waiver_save">
              <div class="form-row">
                <div class="form-group col-md-3">
                  <label>শ্রেণি</label>
                  <select id="class_id" class="form-control">
                    <option value="">- নির্বাচন -</option>
                    <?php foreach($classes as $c): ?>
                      <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-md-3">
                  <label>শাখা</label>
                  <select id="section_id" class="form-control"><option value="">- সব -</option></select>
                </div>
                <div class="form-group col-md-3">
                  <label>শিক্ষার্থী</label>
                  <select name="student_id" id="student_id" class="form-control" required>
                    <option value="">প্রথমে শ্রেণি/শাখা নির্বাচন করুন</option>
                  </select>
                  <div class="small-note">শিক্ষার্থী দ্রুত খুঁজতে উপরের ফিল্টার ব্যবহার করুন</div>
                </div>
                <div class="form-group col-md-3">
                  <label>ফি আইটেম</label>
                  <select name="fee_item_id" class="form-control" required>
                    <option value="">- নির্বাচন -</option>
                    <?php foreach($items as $it): ?>
                      <option value="<?php echo (int)$it['id']; ?>"><?php echo htmlspecialchars($it['name'].' ('.$it['item_type'].')'); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group col-md-3">
                  <label>ছাড়ের ধরন</label>
                  <select name="discount_type" class="form-control">
                    <option value="percent">শতকরা (%)</option>
                    <option value="amount">পরিমাণ (৳)</option>
                  </select>
                </div>
                <div class="form-group col-md-3">
                  <label>ছাড়ের মান</label>
                  <input type="number" step="0.01" min="0" name="discount_value" class="form-control" required>
                </div>
                <div class="form-group col-md-6">
                  <label>নোট (ঐচ্ছিক)</label>
                  <input type="text" name="note" class="form-control" placeholder="উদাহরণ: দরিদ্রতাজনিত ছাড়">
                </div>
              </div>
              <button class="btn btn-success"><i class="fas fa-save"></i> সংরক্ষণ</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header">ছাড় তালিকা</div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped mb-0">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>শিক্ষার্থী</th>
                    <th>আইটেম</th>
                    <th>ছাড়</th>
                    <th>নোট</th>
                    <th width="160">অ্যাকশন</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if(!$overrides): ?><tr><td colspan="6" class="text-center text-muted">ডাটা নেই</td></tr><?php endif; ?>
                  <?php $i=1; foreach($overrides as $o): $stName=trim(($o['first_name']??'').' '.($o['last_name']??'')); ?>
                  <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo htmlspecialchars($stName ?: ('ID#'.$o['student_id'])); ?></td>
                    <td><?php echo htmlspecialchars($o['item_name'] ?: '-'); ?></td>
                    <td>
                      <form method="post" class="form-inline">
                        <input type="hidden" name="form" value="waiver_update">
                        <input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>">
                        <select name="discount_type" class="form-control form-control-sm mr-2">
                          <option value="percent" <?php echo $o['discount_type']==='percent'?'selected':''; ?>>%</option>
                          <option value="amount" <?php echo $o['discount_type']==='amount'?'selected':''; ?>>৳</option>
                        </select>
                        <input type="number" step="0.01" min="0" name="discount_value" value="<?php echo htmlspecialchars((string)($o['discount_value'] ?? '')); ?>" class="form-control form-control-sm mr-2" style="width:120px">
                    </td>
                    <td>
                        <input type="text" name="note" value="<?php echo htmlspecialchars($o['note'] ?? ''); ?>" class="form-control form-control-sm" style="min-width:200px">
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary"><i class="fas fa-save"></i></button>
                        <a href="?delete_override=<?php echo (int)$o['id']; ?>" onclick="return confirm('মুছে ফেলতে চান?')" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></a>
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
        opts += '<option value="'+st.id+'">'+label+'</option>';
      });
      $('#student_id').html(opts);
    }).fail(function(){ $('#student_id').html('<option value="">লোড ব্যর্থ</option>'); });
  }
  $(document).on('change','#class_id', function(){ var v=$(this).val(); if(v){loadSections(v);} loadStudents(); });
  $(document).on('change','#section_id', function(){ loadStudents(); });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
