<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../login.php');
}

# =========================
# CRUD for Exam Heads
# =========================
if (isset($_POST['add_head'])) {
    $stmt = $pdo->prepare("INSERT INTO exam_heads (name, code, max_marks, pass_marks, weightage, status) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$_POST['name'], $_POST['code'], $_POST['max_marks'], $_POST['pass_marks'], $_POST['weightage'], $_POST['status']]);
}
if (isset($_POST['edit_head'])) {
    $stmt = $pdo->prepare("UPDATE exam_heads SET name=?, code=?, max_marks=?, pass_marks=?, weightage=?, status=? WHERE id=?");
    $stmt->execute([$_POST['name'], $_POST['code'], $_POST['max_marks'], $_POST['pass_marks'], $_POST['weightage'], $_POST['status'], $_POST['id']]);
}
if (isset($_GET['delete_head'])) {
    $stmt = $pdo->prepare("DELETE FROM exam_heads WHERE id=?");
    $stmt->execute([$_GET['delete_head']]);
    header("Location: exam_settings.php"); exit;
}

# =========================
# CRUD for Exam Types
# =========================
if (isset($_POST['add_type'])) {
    $stmt = $pdo->prepare("INSERT INTO exam_types (name, code, description, weightage, status) VALUES (?,?,?,?,?)");
    $stmt->execute([$_POST['name'], $_POST['code'], $_POST['description'], $_POST['weightage'], $_POST['status']]);
}
if (isset($_POST['edit_type'])) {
    $stmt = $pdo->prepare("UPDATE exam_types SET name=?, code=?, description=?, weightage=?, status=? WHERE id=?");
    $stmt->execute([$_POST['name'], $_POST['code'], $_POST['description'], $_POST['weightage'], $_POST['status'], $_POST['id']]);
}
if (isset($_GET['delete_type'])) {
    $stmt = $pdo->prepare("DELETE FROM exam_types WHERE id=?");
    $stmt->execute([$_GET['delete_type']]);
    header("Location: exam_settings.php"); exit;
}

# =========================
# CRUD for Exam Result Rules
# =========================
if (isset($_POST['add_rule'])) {
    $stmt = $pdo->prepare("INSERT INTO exam_result_rules (rule_name, exam_type_ids, calculation_method, pass_logic, status) VALUES (?,?,?,?,?)");
    $stmt->execute([$_POST['rule_name'], implode(',', $_POST['exam_type_ids']), $_POST['calculation_method'], $_POST['pass_logic'], $_POST['status']]);
}
if (isset($_POST['edit_rule'])) {
    $stmt = $pdo->prepare("UPDATE exam_result_rules SET rule_name=?, exam_type_ids=?, calculation_method=?, pass_logic=?, status=? WHERE id=?");
    $stmt->execute([$_POST['rule_name'], implode(',', $_POST['exam_type_ids']), $_POST['calculation_method'], $_POST['pass_logic'], $_POST['status'], $_POST['id']]);
}
if (isset($_GET['delete_rule'])) {
    $stmt = $pdo->prepare("DELETE FROM exam_result_rules WHERE id=?");
    $stmt->execute([$_GET['delete_rule']]);
    header("Location: exam_settings.php"); exit;
}

# Fetch Data
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
  <style> body { font-family:'SolaimanLipi',sans-serif; } .card { border-radius:10px; } .card-header { background:#4e73df; color:#fff; } </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include 'inc/header.php'; ?>
  <?php include 'inc/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1>পরীক্ষা সেটিংস</h1></div></section>
    <section class="content">
      <div class="container-fluid">
        <div class="row">

          <!-- Exam Heads -->
          <div class="col-md-4">
            <div class="card">
              <div class="card-header"><h3 class="card-title"><i class="fas fa-list"></i> পরীক্ষার হেড</h3></div>
              <div class="card-body">
                <form method="post">
                  <input type="text" name="name" class="form-control mb-2" placeholder="হেড নাম" required>
                  <input type="text" name="code" class="form-control mb-2" placeholder="কোড" required>
                  <input type="number" name="max_marks" class="form-control mb-2" placeholder="পূর্ণমান" required>
                  <input type="number" name="pass_marks" class="form-control mb-2" placeholder="পাশ মার্কস" required>
                  <input type="number" step="0.01" name="weightage" class="form-control mb-2" placeholder="ওজন" value="1">
                  <select name="status" class="form-control mb-2"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                  <button type="submit" name="add_head" class="btn btn-primary btn-block">হেড যোগ করুন</button>
                </form>
                <hr>
                <table class="table table-sm table-bordered">
                  <thead><tr><th>নাম</th><th>পূর্ণমান</th><th>পাশ</th><th>স্ট্যাটাস</th><th>Action</th></tr></thead>
                  <tbody>
                    <?php foreach($heads as $h): ?>
                    <tr>
                      <td><?= $h['name']; ?></td>
                      <td><?= $h['max_marks']; ?></td>
                      <td><?= $h['pass_marks']; ?></td>
                      <td><?= $h['status']; ?></td>
                      <td>
                        <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#editHead<?= $h['id']; ?>"><i class="fa fa-edit"></i></button>
                        <a href="?delete_head=<?= $h['id']; ?>" onclick="return confirm('ডিলিট করবেন?')" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></a>
                      </td>
                    </tr>
                    <!-- Edit Modal -->
                    <div class="modal fade" id="editHead<?= $h['id']; ?>">
                      <div class="modal-dialog"><div class="modal-content">
                        <form method="post">
                          <div class="modal-header bg-primary text-white"><h5>হেড এডিট</h5></div>
                          <div class="modal-body">
                            <input type="hidden" name="id" value="<?= $h['id']; ?>">
                            <input type="text" name="name" class="form-control mb-2" value="<?= $h['name']; ?>">
                            <input type="text" name="code" class="form-control mb-2" value="<?= $h['code']; ?>">
                            <input type="number" name="max_marks" class="form-control mb-2" value="<?= $h['max_marks']; ?>">
                            <input type="number" name="pass_marks" class="form-control mb-2" value="<?= $h['pass_marks']; ?>">
                            <input type="number" step="0.01" name="weightage" class="form-control mb-2" value="<?= $h['weightage']; ?>">
                            <select name="status" class="form-control">
                              <option value="active" <?= $h['status']=='active'?'selected':''; ?>>Active</option>
                              <option value="inactive" <?= $h['status']=='inactive'?'selected':''; ?>>Inactive</option>
                            </select>
                          </div>
                          <div class="modal-footer"><button type="submit" name="edit_head" class="btn btn-success">আপডেট</button></div>
                        </form>
                      </div></div>
                    </div>
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
                  <input type="text" name="name" class="form-control mb-2" placeholder="ধরন নাম" required>
                  <input type="text" name="code" class="form-control mb-2" placeholder="কোড">
                  <textarea name="description" class="form-control mb-2" placeholder="বর্ণনা"></textarea>
                  <input type="number" step="0.01" name="weightage" class="form-control mb-2" placeholder="ওজন" value="1">
                  <select name="status" class="form-control mb-2"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                  <button type="submit" name="add_type" class="btn btn-primary btn-block">ধরন যোগ করুন</button>
                </form>
                <hr>
                <table class="table table-sm table-bordered">
                  <thead><tr><th>নাম</th><th>ওজন</th><th>স্ট্যাটাস</th><th>Action</th></tr></thead>
                  <tbody>
                    <?php foreach($types as $t): ?>
                    <tr>
                      <td><?= $t['name']; ?></td>
                      <td><?= $t['weightage']; ?></td>
                      <td><?= $t['status']; ?></td>
                      <td>
                        <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#editType<?= $t['id']; ?>"><i class="fa fa-edit"></i></button>
                        <a href="?delete_type=<?= $t['id']; ?>" onclick="return confirm('ডিলিট করবেন?')" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></a>
                      </td>
                    </tr>
                    <!-- Edit Modal -->
                    <div class="modal fade" id="editType<?= $t['id']; ?>">
                      <div class="modal-dialog"><div class="modal-content">
                        <form method="post">
                          <div class="modal-header bg-primary text-white"><h5>ধরন এডিট</h5></div>
                          <div class="modal-body">
                            <input type="hidden" name="id" value="<?= $t['id']; ?>">
                            <input type="text" name="name" class="form-control mb-2" value="<?= $t['name']; ?>">
                            <input type="text" name="code" class="form-control mb-2" value="<?= $t['code']; ?>">
                            <textarea name="description" class="form-control mb-2"><?= $t['description']; ?></textarea>
                            <input type="number" step="0.01" name="weightage" class="form-control mb-2" value="<?= $t['weightage']; ?>">
                            <select name="status" class="form-control">
                              <option value="active" <?= $t['status']=='active'?'selected':''; ?>>Active</option>
                              <option value="inactive" <?= $t['status']=='inactive'?'selected':''; ?>>Inactive</option>
                            </select>
                          </div>
                          <div class="modal-footer"><button type="submit" name="edit_type" class="btn btn-success">আপডেট</button></div>
                        </form>
                      </div></div>
                    </div>
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
                  <input type="text" name="rule_name" class="form-control mb-2" placeholder="রুল নাম" required>
                  <select name="exam_type_ids[]" class="form-control mb-2" multiple required>
                    <?php foreach($types as $t): ?><option value="<?= $t['id']; ?>"><?= $t['name']; ?></option><?php endforeach; ?>
                  </select>
                  <select name="calculation_method" class="form-control mb-2">
                    <option value="average">গড়</option>
                    <option value="weighted_average">Weighted Average</option>
                    <option value="best_of">Best Of</option>
                  </select>
                  <select name="pass_logic" class="form-control mb-2">
                    <option value="total_based">মোট ভিত্তিক</option>
                    <option value="head_wise">হেড অনুযায়ী</option>
                  </select>
                  <select name="status" class="form-control mb-2"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                  <button type="submit" name="add_rule" class="btn btn-primary btn-block">রুল যোগ করুন</button>
                </form>
                <hr>
                <table class="table table-sm table-bordered">
                  <thead><tr><th>নাম</th><th>মেথড</th><th>স্ট্যাটাস</th><th>Action</th></tr></thead>
                  <tbody>
                    <?php foreach($rules as $r): ?>
                    <tr>
                      <td><?= $r['rule_name']; ?></td>
                      <td><?= $r['calculation_method']; ?></td>
                      <td><?= $r['status']; ?></td>
                      <td>
                        <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#editRule<?= $r['id']; ?>"><i class="fa fa-edit"></i></button>
                        <a href="?delete_rule=<?= $r['id']; ?>" onclick="return confirm('ডিলিট করবেন?')" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></a>
                      </td>
                    </tr>
                    <!-- Edit Modal -->
                    <div class="modal fade" id="editRule<?= $r['id']; ?>">
                      <div class="modal-dialog"><div class="modal-content">
                        <form method="post">
                          <div class="modal-header bg-primary text-white"><h5>রুল এডিট</h5></div>
                          <div class="modal-body">
                            <input type="hidden" name="id" value="<?= $r['id']; ?>">
                            <input type="text" name="rule_name" class="form-control mb-2" value="<?= $r['rule_name']; ?>">
                            <select name="exam_type_ids[]" class="form-control mb-2" multiple>
                              <?php foreach($types as $t): ?>
                              <option value="<?= $t['id']; ?>" <?= in_array($t['id'], explode(',', $r['exam_type_ids']))?'selected':''; ?>><?= $t['name']; ?></option>
                              <?php endforeach; ?>
                            </select>
                            <select name="calculation_method" class="form-control mb-2">
                              <option value="average" <?= $r['calculation_method']=='average'?'selected':''; ?>>গড়</option>
                              <option value="weighted_average" <?= $r['calculation_method']=='weighted_average'?'selected':''; ?>>Weighted</option>
                              <option value="best_of" <?= $r['calculation_method']=='best_of'?'selected':''; ?>>Best Of</option>
                            </select>
                            <select name="pass_logic" class="form-control mb-2">
                              <option value="total_based" <?= $r['pass_logic']=='total_based'?'selected':''; ?>>মোট ভিত্তিক</option>
                              <option value="head_wise" <?= $r['pass_logic']=='head_wise'?'selected':''; ?>>হেড অনুযায়ী</option>
                            </select>
                            <select name="status" class="form-control mb-2">
                              <option value="active" <?= $r['status']=='active'?'selected':''; ?>>Active</option>
                              <option value="inactive" <?= $r['status']=='inactive'?'selected':''; ?>>Inactive</option>
                            </select>
                          </div>
                          <div class="modal-footer"><button type="submit" name="edit_rule" class="btn btn-success">আপডেট</button></div>
                        </form>
                      </div></div>
                    </div>
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
  <?php include 'inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>