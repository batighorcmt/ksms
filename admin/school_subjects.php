<?php
require_once '../config.php';

// Authentication
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

// detect additional columns on subjects table
$hasCode = (bool)$pdo->query("SHOW COLUMNS FROM subjects LIKE 'code'")->fetch();
$hasDescription = (bool)$pdo->query("SHOW COLUMNS FROM subjects LIKE 'description'")->fetch();
$hasStatus = (bool)$pdo->query("SHOW COLUMNS FROM subjects LIKE 'status'")->fetch();

// Handle actions: add, update, delete via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $code = isset($_POST['code']) ? trim($_POST['code']) : null;
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        $status = isset($_POST['status']) ? trim($_POST['status']) : null;
        $errors = [];
        if (!$name) $errors[] = 'বিষয় নাম লিখুন।';
        if (empty($errors)) {
            // prevent duplicate (case-insensitive) - prefer code if provided
            if ($hasCode && $code) {
                $dupStmt = $pdo->prepare("SELECT id FROM subjects WHERE code = ?");
                $dupStmt->execute([$code]);
            } else {
                $dupStmt = $pdo->prepare("SELECT id FROM subjects WHERE LOWER(name) = LOWER(?)");
                $dupStmt->execute([$name]);
            }
            if ($dupStmt->fetch()) {
                $errors[] = 'এই নাম/কোডের বিষয় ইতোমধ্যে বিদ্যমান।';
            } else {
                // build insert dynamically
                $cols = ['name'];
                $placeholders = ['?'];
                $vals = [$name];
                if ($hasCode) { $cols[] = 'code'; $placeholders[] = '?'; $vals[] = $code; }
                if ($hasDescription) { $cols[] = 'description'; $placeholders[] = '?'; $vals[] = $description; }
                if ($hasStatus) { $cols[] = 'status'; $placeholders[] = '?'; $vals[] = $status; }
                $cols[] = 'created_at'; $cols[] = 'updated_at';
                $placeholders[] = 'NOW()'; $placeholders[] = 'NOW()';
                $sql = "INSERT INTO subjects (".implode(',', $cols).") VALUES (".implode(',', $placeholders).")";
                $ins = $pdo->prepare($sql);
                $ins->execute($vals);
                $_SESSION['success'] = 'বিষয় যোগ করা হয়েছে।';
                header('Location: school_subjects.php'); exit;
            }
        }
        $_SESSION['errors'] = $errors;
        header('Location: school_subjects.php'); exit;
    }

    if ($action === 'update') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $code = isset($_POST['code']) ? trim($_POST['code']) : null;
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        $status = isset($_POST['status']) ? trim($_POST['status']) : null;
        $errors = [];
        if (!$id) $errors[] = 'অবৈধ আইডি।';
        if (!$name) $errors[] = 'বিষয় নাম লিখুন।';
        if (empty($errors)) {
            // check duplicate excluding self (prefer code if available)
            if ($hasCode && $code) {
                $dup = $pdo->prepare("SELECT id FROM subjects WHERE code = ? AND id != ?");
                $dup->execute([$code, $id]);
            } else {
                $dup = $pdo->prepare("SELECT id FROM subjects WHERE LOWER(name) = LOWER(?) AND id != ?");
                $dup->execute([$name, $id]);
            }
            if ($dup->fetch()) {
                $errors[] = 'এই নাম/কোড অন্য একটি বিষয় দ্বারা ব্যবহার হচ্ছে।';
            } else {
                $sets = ['name = ?'];
                $vals = [$name];
                if ($hasCode) { $sets[] = 'code = ?'; $vals[] = $code; }
                if ($hasDescription) { $sets[] = 'description = ?'; $vals[] = $description; }
                if ($hasStatus) { $sets[] = 'status = ?'; $vals[] = $status; }
                $vals[] = $id;
                $sql = "UPDATE subjects SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
                $upd = $pdo->prepare($sql);
                $upd->execute($vals);
                $_SESSION['success'] = 'বিষয় আপডেট করা হয়েছে।';
                header('Location: school_subjects.php'); exit;
            }
        }
        $_SESSION['errors'] = $errors;
        header('Location: school_subjects.php'); exit;
    }

    if ($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id) {
            // optionally check foreign keys (class_subjects)
            $del = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
            $del->execute([$id]);
            // cleanup mappings
            $pdo->prepare("DELETE FROM class_subjects WHERE subject_id = ?")->execute([$id]);
            $_SESSION['success'] = 'বিষয় মুছে ফেলা হয়েছে।';
        }
        header('Location: school_subjects.php'); exit;
    }
}

// Load all subjects
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();

?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>পাঠ্য বিষয় তালিকা - কিন্ডার গার্ডেন</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body, .main-sidebar, .nav-link {font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;}</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">পাঠ্য বিষয়</h1></div>
                    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>dashboard.php">হোম</a></li><li class="breadcrumb-item active">পাঠ্য বিষয়</li></ol></div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>
                <?php if(isset($_SESSION['errors'])): ?><div class="alert alert-danger"><ul><?php foreach($_SESSION['errors'] as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; unset($_SESSION['errors']); ?></ul></div><?php endif; ?>

                <div class="card">
                        <div class="card-header">
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal"><i class="fas fa-plus"></i> নতুন বিষয়</button>
                    </div>
                    <div class="card-body">
                        <?php if(empty($subjects)): ?>
                            <div class="alert alert-info">কোন বিষয় পাওয়া যায়নি।</div>
                        <?php else: ?>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <?php if ($hasCode): ?><th>কোড</th><?php endif; ?>
                                        <th>নাম</th>
                                        <?php if ($hasDescription): ?><th>বিবরণ</th><?php endif; ?>
                                        <?php if ($hasStatus): ?><th>স্ট্যাটাস</th><?php endif; ?>
                                        <th>অপশন</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i=1; foreach($subjects as $s): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <?php if ($hasCode): ?><td><?php echo htmlspecialchars($s['code'] ?? ''); ?></td><?php endif; ?>
                                            <td><?php echo htmlspecialchars($s['name']); ?></td>
                                            <?php if ($hasDescription): ?><td><?php echo htmlspecialchars($s['description'] ?? ''); ?></td><?php endif; ?>
                                            <?php if ($hasStatus): ?><td><?php echo htmlspecialchars($s['status'] ?? ''); ?></td><?php endif; ?>
                                            <td>
                                                <button class="btn btn-sm btn-info editBtn" data-id="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>" data-code="<?php echo htmlspecialchars($s['code'] ?? '', ENT_QUOTES); ?>" data-description="<?php echo htmlspecialchars($s['description'] ?? '', ENT_QUOTES); ?>" data-status="<?php echo htmlspecialchars($s['status'] ?? '', ENT_QUOTES); ?>">এডিট</button>
                                                <button class="btn btn-sm btn-danger deleteBtn" data-id="<?php echo $s['id']; ?>">মুছুন</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <?php include 'inc/footer.php'; ?>
</div>

<!-- Modals -->
<div class="modal fade" id="addModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">নতুন বিষয় যোগ করুন</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body">
            <div class="form-group"><label>বিষয় নাম</label><input type="text" name="name" class="form-control" required></div>
            <?php if ($hasCode): ?><div class="form-group"><label>কোড</label><input type="text" name="code" class="form-control"></div><?php endif; ?>
            <?php if ($hasDescription): ?><div class="form-group"><label>বিবরণ</label><textarea name="description" class="form-control"></textarea></div><?php endif; ?>
            <?php if ($hasStatus): ?><div class="form-group"><label>স্ট্যাটাস</label><select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select></div><?php endif; ?>
        </div>
        <div class="modal-footer"><button class="btn btn-primary" type="submit">সংরক্ষণ</button><button type="button" class="btn btn-secondary" data-dismiss="modal">বন্ধ</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id" value="">
        <div class="modal-header"><h5 class="modal-title">বিষয় আপডেট</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body">
            <div class="form-group"><label>নাম</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
            <?php if ($hasCode): ?><div class="form-group"><label>কোড</label><input type="text" name="code" id="edit_code" class="form-control"></div><?php endif; ?>
            <?php if ($hasDescription): ?><div class="form-group"><label>বিবরণ</label><textarea name="description" id="edit_description" class="form-control"></textarea></div><?php endif; ?>
            <?php if ($hasStatus): ?><div class="form-group"><label>স্ট্যাটাস</label><select name="status" id="edit_status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select></div><?php endif; ?>
        </div>
        <div class="modal-footer"><button class="btn btn-primary" type="submit">আপডেট</button><button type="button" class="btn btn-secondary" data-dismiss="modal">বন্ধ</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id" value="">
        <div class="modal-header"><h5 class="modal-title">বিষয় মুছুন</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body">আপনি কি নিশ্চিত যে আপনি এই বিষয় মুছে ফেলতে চান?</div>
        <div class="modal-footer"><button class="btn btn-danger" type="submit">মুছুন</button><button type="button" class="btn btn-secondary" data-dismiss="modal">বন্ধ</button></div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
$(function(){
    $('.editBtn').on('click', function(){
        var id = $(this).data('id');
        var name = $(this).data('name');
    $('#edit_id').val(id);
    $('#edit_name').val(name);
    if ($('#edit_code').length) $('#edit_code').val($(this).data('code'));
    if ($('#edit_description').length) $('#edit_description').val($(this).data('description'));
    if ($('#edit_status').length) $('#edit_status').val($(this).data('status'));
    $('#editModal').modal('show');
    });

    $('.deleteBtn').on('click', function(){
        var id = $(this).data('id');
        $('#delete_id').val(id);
        $('#deleteModal').modal('show');
    });
});
</script>
</body>
</html>
