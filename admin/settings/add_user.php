<?php
require_once '../../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) { redirect('../login.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $status = isset($_POST['status']) ? 1 : 0;

    if ($full_name === '' || $username === '' || $password === '' || $role === '') {
        $errors[] = 'সমস্ত চিহ্নিত ঘর পূরণ করুন।';
    }

    // Check unique username
    if (empty($errors)) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $chk->execute([$username]);
        if ($chk->fetchColumn() > 0) {
            $errors[] = 'এই ইউজারনেমটি ইতিমধ্যে ব্যবহার হচ্ছে।';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (full_name, username, password, role, status) VALUES (?,?,?,?,?)');
        try {
            $stmt->execute([$full_name, $username, $hash, $role, $status]);
            session_start(); $_SESSION['flash'] = 'নতুন ব্যবহারকারী তৈরি হয়েছে।';
            redirect('users.php');
        } catch (Exception $e) {
            $errors[] = 'ডাটাবেস ত্রুটি: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>নতুন ব্যবহারকারী</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style> body,.main-sidebar,.nav-link{font-family:'SolaimanLipi','Source Sans Pro',sans-serif;} </style>
    </head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../inc/header.php'; ?>
    <?php include '../inc/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1>নতুন ব্যবহারকারী</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="users.php">ব্যবহারকারী তালিকা</a></li>
                            <li class="breadcrumb-item active">নতুন</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?php if(!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
                <?php endif; ?>

                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">ব্যবহারকারী তথ্য</h3></div>
                    <form method="post" class="p-3">
                        <div class="form-group">
                            <label>পূর্ণ নাম *</label>
                            <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>ইউজারনেম *</label>
                            <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>পাসওয়ার্ড *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>রোল *</label>
                            <select name="role" class="form-control" required>
                                <option value="">--রোল নির্বাচন--</option>
                                <?php
                                $roles = ['super_admin'=>'সুপার অ্যাডমিন','admin'=>'অ্যাডমিন','teacher'=>'শিক্ষক','guardian'=>'অভিভাবক'];
                                $sel = $_POST['role'] ?? '';
                                foreach($roles as $k=>$v){
                                    echo '<option value="'.htmlspecialchars($k).'" '.($sel===$k?'selected':'').'>'.htmlspecialchars($v).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" name="status" id="status" class="form-check-input" <?php echo !isset($_POST['status']) || $_POST['status'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">সক্রিয়</label>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-primary"><i class="fa fa-save"></i> সংরক্ষণ</button>
                            <a href="users.php" class="btn btn-default">বাতিল</a>
                        </div>
                    </form>
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