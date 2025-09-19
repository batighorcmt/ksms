<?php
require_once '../config.php';

// শুধুমাত্র super_admin প্রবেশ করতে পারবে
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../login.php');
}

// Add Exam Head
if (isset($_POST['add_head'])) {
    $stmt = $pdo->prepare("INSERT INTO exam_heads (name, code, max_marks, pass_marks, weightage, status) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$_POST['name'], $_POST['code'], $_POST['max_marks'], $_POST['pass_marks'], $_POST['weightage'], $_POST['status']]);
}

// Add Exam Type
if (isset($_POST['add_type'])) {
    $stmt = $pdo->prepare("INSERT INTO exam_types (name, code, description, weightage, status) VALUES (?,?,?,?,?)");
    $stmt->execute([$_POST['name'], $_POST['code'], $_POST['description'], $_POST['weightage'], $_POST['status']]);
}

// Add Result Rule
if (isset($_POST['add_rule'])) {
    $stmt = $pdo->prepare("INSERT INTO exam_result_rules (rule_name, exam_type_ids, calculation_method, pass_logic, status) VALUES (?,?,?,?,?)");
    $stmt->execute([$_POST['rule_name'], implode(',', $_POST['exam_type_ids']), $_POST['calculation_method'], $_POST['pass_logic'], $_POST['status']]);
}

// Fetch all data
$heads = $pdo->query("SELECT * FROM exam_heads ORDER BY id DESC")->fetchAll();
$types = $pdo->query("SELECT * FROM exam_types ORDER BY id DESC")->fetchAll();
$rules = $pdo->query("SELECT * FROM exam_result_rules ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <title>পরীক্ষা সেটিংস</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
  <style>
    body { font-family: 'SolaimanLipi', sans-serif; }
    .card { border-radius: 12px; }
    .card-header { background: linear-gradient(135deg,#4e73df,#224abe); color:white; }
    .btn-primary { background: #4e73df; border:none; }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include '../inc/header.php'; ?>
  <?php include '../inc/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid"><h1>পরীক্ষা সেটিংস</h1></div>
    </section>

    <section class="content">
      <div class="container-fluid">

        <div class="row">
          <!-- Exam Heads -->
          <div class="col-md-4">
            <div class="card">
              <div class="card-header"><h3 class="card-title"><i class="fas fa-list"></i> পরীক্ষার হেড</h3></div>
              <div class="card-body">
                <form method="post">
                  <div class="form-group"><label>হেড নাম</label><input type="text" name="name" class="form-control" required></div>
                  <div class="form-group"><label>কোড</label><input type="text" name="code" class="form-control" required></div>
                  <div class="form-group"><label>পূর্ণমান</label><input type="number" name="max_marks" class="form-control" required></div>
                  <div class="form-group"><label>পাশ মার্কস</label><input type="number" name="pass_marks" class="form-control" required></div>
                  <div class="form-group"><label>ওজন (%)</label><input type="number" step="0.01" name="weightage" class="form-control" value="1"></div>
                  <div class="form-group"><label>স্ট্যাটাস</label>
                    <select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                  </div>
                  <button type="submit" name="add_head" class="btn btn-primary btn-block">হেড যোগ করুন</button>
                </form>
                <hr>
                <table class="table table-sm">
                  <thead><tr><th>নাম</th><th>পূর্ণমান</th><th>পাশ</th><th>স্ট্যাটাস</th></tr></thead>
                  <tbody>
                    <?php foreach($heads as $h): ?>
                    <tr><td><?= $h['name']; ?></td><td><?= $h['max_marks']; ?></td><td><?= $h['pass_marks']; ?></td><td><?= $h['status']; ?></td></tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Exam Types -->
          <div class="col-md-4">
            <div class="card">
              <div class="card-header"><h3 class="card-title"><i class="fas fa-book"></i> পরীক্ষার ধরন</h3></div>
              <div class="card-body">
                <form method="post">
                  <div class="form-group"><label>ধরন নাম</label><input type="text" name="name" class="form-control" required></div>
                  <div class="form-group"><label>কোড</label><input type="text" name="code" class="form-control"></div>
                  <div class="form-group"><label>বর্ণনা</label><textarea name="description" class="form-control"></textarea></div>
                  <div class="form-group"><label>ওজন (%)</label><input type="number" step="0.01" name="weightage" class="form-control" value="1"></div>
                  <div class="form-group"><label>স্ট্যাটাস</label>
                    <select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                  </div>
                  <button type="submit" name="add_type" class="btn btn-primary btn-block">ধরন যোগ করুন</button>
                </form>
                <hr>
                <table class="table table-sm">
                  <thead><tr><th>নাম</th><th>ওজন</th><th>স্ট্যাটাস</th></tr></thead>
                  <tbody>
                    <?php foreach($types as $t): ?>
                    <tr><td><?= $t['name']; ?></td><td><?= $t['weightage']; ?></td><td><?= $t['status']; ?></td></tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Result Rules -->
          <div class="col-md-4">
            <div class="card">
              <div class="card-header"><h3 class="card-title"><i class="fas fa-cogs"></i> ফলাফল নিয়ম</h3></div>
              <div class="card-body">
                <form method="post">
                  <div class="form-group"><label>রুল নাম</label><input type="text" name="rule_name" class="form-control" required></div>
                  <div class="form-group"><label>Exam Types</label>
                    <select name="exam_type_ids[]" class="form-control" multiple required>
                      <?php foreach($types as $t): ?>
                      <option value="<?= $t['id']; ?>"><?= $t['name']; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group"><label>ক্যালকুলেশন মেথড</label>
                    <select name="calculation_method" class="form-control">
                      <option value="average">গড়</option>
                      <option value="weighted_average">Weighted Average</option>
                      <option value="best_of">Best Of</option>
                    </select>
                  </div>
                  <div class="form-group"><label>পাশ লজিক</label>
                    <select name="pass_logic" class="form-control">
                      <option value="total_based">মোট ভিত্তিক</option>
                      <option value="head_wise">হেড অনুযায়ী</option>
                    </select>
                  </div>
                  <div class="form-group"><label>স্ট্যাটাস</label>
                    <select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                  </div>
                  <button type="submit" name="add_rule" class="btn btn-primary btn-block">রুল যোগ করুন</button>
                </form>
                <hr>
                <table class="table table-sm">
                  <thead><tr><th>নাম</th><th>মেথড</th><th>স্ট্যাটাস</th></tr></thead>
                  <tbody>
                    <?php foreach($rules as $r): ?>
                    <tr><td><?= $r['rule_name']; ?></td><td><?= $r['calculation_method']; ?></td><td><?= $r['status']; ?></td></tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>
  </div>

  <?php include '../inc/footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>