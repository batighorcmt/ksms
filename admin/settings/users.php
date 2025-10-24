<?php
require_once '../../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) { redirect('../login.php'); }

// Fetch users
$stmt = $pdo->query("SELECT id, username, full_name, role, status FROM users ORDER BY id DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Flash helper
session_start();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ব্যবহারকারী তালিকা</title>
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
                    <div class="col-sm-6"><h1>ব্যবহারকারী তালিকা</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">ব্যবহারকারী তালিকা</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?php if($flash): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <i class="icon fas fa-check"></i> <?php echo htmlspecialchars($flash); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title">মোট: <?php echo count($users); ?></h3>
                        <a href="add_user.php" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> নতুন ব্যবহারকারী</a>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:70px">আইডি</th>
                                    <th>ইউজারনেম</th>
                                    <th>পূর্ণ নাম</th>
                                    <th>রোল</th>
                                    <th>স্ট্যাটাস</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($users)): ?>
                                <tr><td colspan="5" class="text-center text-muted">কোনো ব্যবহারকারী পাওয়া যায়নি</td></tr>
                                <?php else: foreach($users as $u): ?>
                                <tr>
                                    <td><?php echo (int)$u['id']; ?></td>
                                    <td><?php echo htmlspecialchars($u['username'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($u['full_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($u['role'] ?? ''); ?></td>
                                    <td>
                                        <?php if((int)$u['status']===1): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
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