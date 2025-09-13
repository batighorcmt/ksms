<?php
require_once '../config.php';

// Authentication
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

// Load classes
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();

// Load existing subjects (to map to classes)
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // accept multiple subject ids via subject_ids[] or a single subject_id for backward compatibility
    $subject_ids = [];
    if (isset($_POST['subject_ids']) && is_array($_POST['subject_ids'])) {
        foreach ($_POST['subject_ids'] as $sid) { $subject_ids[] = intval($sid); }
    } elseif (isset($_POST['subject_id'])) {
        $subject_ids[] = intval($_POST['subject_id']);
    }
    $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;

    $errors = [];
    if (!$class_id) $errors[] = 'শ্রেণি নির্বাচন করুন।';
    if (empty($subject_ids)) $errors[] = 'একটি বা একাধিক বিষয় নির্বাচন করুন।';

    if (empty($errors)) {
        // Use a transaction and compute starting numeric_value once to avoid race conditions
        $check = $pdo->prepare("SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ?");
        $subExists = $pdo->prepare("SELECT id FROM subjects WHERE id = ?");
        $inserted = 0; $skipped = 0;
        try {
            $pdo->beginTransaction();
            $maxRow = $pdo->prepare("SELECT COALESCE(MAX(numeric_value), 0) as m FROM class_subjects WHERE class_id = ? FOR UPDATE");
            $maxRow->execute([$class_id]);
            $m = $maxRow->fetchColumn();
            $next = intval($m) + 1;
            $ins = $pdo->prepare("INSERT INTO class_subjects (class_id, subject_id, teacher_id, numeric_value, created_at) VALUES (?, ?, NULL, ?, NOW())");
            foreach ($subject_ids as $sid) {
                $subExists->execute([$sid]);
                if (!$subExists->fetch()) { $skipped++; continue; }
                $check->execute([$class_id, $sid]);
                if ($check->fetch()) { $skipped++; continue; }
                $ins->execute([$class_id, $sid, $next]);
                $inserted++; $next++;
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
        $_SESSION['success'] = "ম্যাপিং সম্পন্ন: $inserted টি নতুন যুক্ত করা হয়েছে, $skipped টি স্কিপ করা হয়েছে।";
        header('Location: subjects.php?class_id=' . $class_id);
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>নতুন বিষয় যোগ করুন - কিন্ডার গার্ডেন</title>
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
                    <div class="col-sm-6"><h1 class="m-0">নতুন বিষয় যোগ করুন</h1></div>
                    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>dashboard.php">হোম</a></li><li class="breadcrumb-item"><a href="subjects.php">বিষয়সমূহ</a></li><li class="breadcrumb-item active">নতুন যোগ</li></ol></div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if(!empty($errors)): ?>
                    <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="post">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>শ্রেণি</label>
                                    <select name="class_id" class="form-control">
                                        <option value="">-- শ্রেণি নির্বাচন করুন --</option>
                                        <?php foreach($classes as $c): ?>
                                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>বিষয় (একটা বা একাধিক নির্বাচন করতে Ctrl/Shift চাপুন)</label>
                                    <select name="subject_ids[]" class="form-control" id="subject_select" multiple size="10">
                                        <?php foreach($subjects as $sub): ?>
                                            <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- multiple select allows choosing many subjects; no bulk-map checkbox required -->

                            <div class="form-row">
                                <div class="form-group col-md-12 text-right">
                                    <button type="submit" class="btn btn-success">সংরক্ষণ করুন</button>
                                    <a href="subjects.php" class="btn btn-secondary">বাতিল</a>
                                </div>
                            </div>
                        </form>
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
<!-- no extra JS needed for multiple select -->
</body>
</html>
