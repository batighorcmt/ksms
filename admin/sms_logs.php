<?php
require_once '../config.php';

if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../login.php');
}

// Pagination & filters
$perPage = isset($_GET['per_page']) ? max(10, min(200, (int)$_GET['per_page'])) : 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$filters = [
    'number' => trim($_GET['number'] ?? ''),
    'status' => trim($_GET['status'] ?? ''),
    'type'   => trim($_GET['type'] ?? ''),
    'date_from' => trim($_GET['date_from'] ?? ''),
    'date_to'   => trim($_GET['date_to'] ?? ''),
];

$where = [];
$params = [];
if ($filters['number'] !== '') { $where[] = 'recipient_number LIKE ?'; $params[] = '%' . $filters['number'] . '%'; }
if ($filters['status'] !== '' && in_array($filters['status'], ['success','failed'], true)) { $where[] = 'status = ?'; $params[] = $filters['status']; }
if ($filters['type'] !== '') { $where[] = 'recipient_type = ?'; $params[] = $filters['type']; }
if ($filters['date_from'] !== '') { $where[] = 'created_at >= ?'; $params[] = $filters['date_from'] . ' 00:00:00'; }
if ($filters['date_to'] !== '') { $where[] = 'created_at <= ?'; $params[] = $filters['date_to'] . ' 23:59:59'; }

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

// Count total
$total = 0;
try {
    $stc = $pdo->prepare('SELECT COUNT(*) FROM sms_logs' . $whereSql);
    $stc->execute($params);
    $total = (int)$stc->fetchColumn();
} catch (Exception $e) {
    $total = 0;
}

// Fetch rows
$rows = [];
try {
    $sql = 'SELECT l.*, u.full_name AS sender_name FROM sms_logs l LEFT JOIN users u ON u.id = l.sent_by_user_id' . $whereSql . ' ORDER BY l.created_at DESC, l.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $rows = [];
}

$totalPages = max(1, (int)ceil($total / $perPage));

// Helper to build query string preserving filters
function build_query($overrides = []) {
    $q = array_merge($_GET, $overrides);
    return http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>এসএমএস লগ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style> body, .main-sidebar, .nav-link, .card, .form-control, .btn { font-family: 'SolaimanLipi','Source Sans Pro',sans-serif; } </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">এসএমএস লগ</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="sms_panel.php">এসএমএস প্যানেল</a></li>
                            <li class="breadcrumb-item active">এসএমএস লগ</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header"><strong>ফিল্টার</strong></div>
                    <div class="card-body">
                        <form method="get" class="form-inline">
                            <div class="form-group mr-2 mb-2">
                                <label class="mr-2">নম্বর</label>
                                <input type="text" name="number" value="<?= htmlspecialchars($filters['number']) ?>" class="form-control" placeholder="017...">
                            </div>
                            <div class="form-group mr-2 mb-2">
                                <label class="mr-2">স্ট্যাটাস</label>
                                <select name="status" class="form-control">
                                    <option value="">সব</option>
                                    <option value="success" <?= $filters['status']==='success'?'selected':'' ?>>সফল</option>
                                    <option value="failed" <?= $filters['status']==='failed'?'selected':'' ?>>বিফল</option>
                                </select>
                            </div>
                            <div class="form-group mr-2 mb-2">
                                <label class="mr-2">ধরন</label>
                                <input type="text" name="type" value="<?= htmlspecialchars($filters['type']) ?>" class="form-control" placeholder="teacher_all / students_selected">
                            </div>
                            <div class="form-group mr-2 mb-2">
                                <label class="mr-2">তারিখ (হতে)</label>
                                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" class="form-control">
                            </div>
                            <div class="form-group mr-2 mb-2">
                                <label class="mr-2">তারিখ (পর্যন্ত)</label>
                                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" class="form-control">
                            </div>
                            <div class="form-group mr-2 mb-2">
                                <label class="mr-2">প্রতি পৃষ্ঠা</label>
                                <select name="per_page" class="form-control">
                                    <?php foreach ([25,50,100,150,200] as $pp): ?>
                                        <option value="<?= $pp ?>" <?= $perPage===$pp?'selected':'' ?>><?= $pp ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button class="btn btn-primary mb-2" type="submit"><i class="fas fa-search"></i> খুঁজুন</button>
                            <a class="btn btn-secondary mb-2 ml-2" href="sms_logs.php"><i class="fas fa-undo"></i> রিসেট</a>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>লগসমূহ (মোট: <?= (int)$total ?>)</strong>
                        <a class="btn btn-sm btn-outline-primary" href="sms_panel.php"><i class="fas fa-paper-plane"></i> এসএমএস পাঠান</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>সময়</th>
                                        <th>স্ট্যাটাস</th>
                                        <th>ধরন</th>
                                        <th>প্রাপক বিভাগ</th>
                                        <th>প্রাপকের নাম</th>
                                        <th>রোল</th>
                                        <th>শ্রেণি</th>
                                        <th>শাখা</th>
                                        <th>নম্বর</th>
                                        <th>প্রেরক</th>
                                        <th>বার্তা</th>
                                        <th>একশন</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rows): foreach ($rows as $r): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($r['created_at']) ?></td>
                                            <td>
                                                <?php if (($r['status'] ?? '') === 'success'): ?>
                                                    <span class="badge badge-success">সফল</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">বিফল</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($r['recipient_type'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($r['recipient_category'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($r['recipient_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($r['roll_number'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($r['class_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($r['section_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($r['recipient_number'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($r['sender_name'] ?? ($r['sent_by_user_id'] ? ('User#'.$r['sent_by_user_id']) : '')) ?></td>
                                            <td style="max-width:520px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($r['message'] ?? '') ?>"><?= htmlspecialchars($r['message'] ?? '') ?></td>
                                            <td>
                                                <a class="btn btn-xs btn-outline-primary" href="sms_log_view.php?id=<?= (int)($r['id'] ?? 0) ?>" target="_blank"><i class="fas fa-eye"></i> View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="11" class="text-center text-muted">কোনো তথ্য পাওয়া যায়নি</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <nav>
                            <ul class="pagination mb-0">
                                <?php
                                $prev = max(1, $page-1); $next = min($totalPages, $page+1);
                                ?>
                                <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?<?= build_query(['page'=>1]) ?>">প্রথম</a></li>
                                <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?<?= build_query(['page'=>$prev]) ?>">পূর্ববর্তী</a></li>
                                <li class="page-item disabled"><span class="page-link">পৃষ্ঠা <?= $page ?> / <?= $totalPages ?></span></li>
                                <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link" href="?<?= build_query(['page'=>$next]) ?>">পরবর্তী</a></li>
                                <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link" href="?<?= build_query(['page'=>$totalPages]) ?>">শেষ</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include 'inc/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
