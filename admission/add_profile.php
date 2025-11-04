<?php
require_once '../config.php';

// Auth check
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('login.php');
}

$admId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($admId <= 0) {
    $_SESSION['error'] = 'সঠিক ভর্তি আইডি দিন।';
    header('Location: '.BASE_URL.'admission/students_quick_list.php');
    exit;
}

// Fetch admission record
$a = null;
try {
    $stm = $pdo->prepare('SELECT * FROM admissions WHERE id = ? LIMIT 1');
    $stm->execute([$admId]);
    $a = $stm->fetch(PDO::FETCH_ASSOC);
    if (!$a) {
        $_SESSION['error'] = 'ভর্তি রেকর্ড পাওয়া যায়নি।';
        header('Location: '.BASE_URL.'admission/students_quick_list.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'ত্রুটি: '.$e->getMessage();
    header('Location: '.BASE_URL.'admission/students_quick_list.php');
    exit;
}

// Detect students table columns and existing profile by mobile
$studentMobileCol = null; $existingStudentId = null; $colSet = [];
try {
    $scols = $pdo->query('SHOW COLUMNS FROM students')->fetchAll(PDO::FETCH_COLUMN);
    $colSet = array_flip($scols);
    if (isset($colSet['mobile_number'])) { $studentMobileCol = 'mobile_number'; }
    elseif (isset($colSet['mobile'])) { $studentMobileCol = 'mobile'; }
    if ($studentMobileCol && !empty($a['mobile'])) {
        $chk = $pdo->prepare("SELECT MIN(id) AS id FROM students WHERE $studentMobileCol = ?");
        $chk->execute([$a['mobile']]);
        $existingStudentId = (int)($chk->fetchColumn());
        if ($existingStudentId <= 0) { $existingStudentId = null; }
    }
} catch (Exception $e) {
    // ignore, maybe students table differs
}

// Handle explicit profile creation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        // If profile already exists, just notify and return to this page
        if ($existingStudentId) {
            $_SESSION['success'] = 'প্রোফাইল আগে থেকেই রয়েছে।';
            header('Location: '.BASE_URL.'admission/add_profile.php?id='.$admId);
            exit;
        }

        // Map and insert into students table (only using admissions data)
        $cols=[]; $place=[]; $params=[];
        if (isset($colSet['student_id'])) { $cols[]='student_id'; $place[]='?'; $params[]='STU'.date('Y').rand(1000,9999); }
        $full = trim($a['student_name'] ?? '');
        if (isset($colSet['first_name']) || isset($colSet['last_name'])) {
            $parts = preg_split('/\s+/', $full, 2);
            if (isset($colSet['first_name'])) { $cols[]='first_name'; $place[]='?'; $params[]=$parts[0] ?? $full; }
            if (isset($colSet['last_name'])) { $cols[]='last_name'; $place[]='?'; $params[]=$parts[1] ?? ''; }
        } elseif (isset($colSet['name'])) {
            $cols[]='name'; $place[]='?'; $params[]=$full;
        }
        if (isset($colSet['father_name'])) { $cols[]='father_name'; $place[]='?'; $params[]=$a['father_name'] ?? ''; }
        $addr = implode(', ', array_filter([$a['village'] ?? '', $a['para_moholla'] ?? '', $a['upazila'] ?? '', $a['district'] ?? '']));
        if (isset($colSet['present_address'])) { $cols[]='present_address'; $place[]='?'; $params[]=$addr; }
        if (isset($colSet['permanent_address'])) { $cols[]='permanent_address'; $place[]='?'; $params[]=$addr; }
        if ($studentMobileCol) { $cols[]=$studentMobileCol; $place[]='?'; $params[]=$a['mobile'] ?? ''; }
        if (isset($colSet['admission_date'])) { $cols[]='admission_date'; $place[]='?'; $params[]=date('Y-m-d'); }
        if (isset($colSet['status'])) { $cols[]='status'; $place[]='?'; $params[]='active'; }

        if (empty($cols)) { throw new Exception('students টেবিলের কাঠামো অনুপস্থিত।'); }

        $sqlIns = 'INSERT INTO students ('.implode(',', $cols).') VALUES ('.implode(',', $place).')';
        $ins = $pdo->prepare($sqlIns);
        if ($ins->execute($params)) {
            $_SESSION['success'] = 'শিক্ষার্থী প্রোফাইল তৈরি হয়েছে।';
            header('Location: '.BASE_URL.'admission/add_profile.php?id='.$admId);
            exit;
        }
        $_SESSION['error'] = 'প্রোফাইল তৈরি করা যায়নি।';
    } catch (Exception $e) {
        $_SESSION['error'] = 'ত্রুটি: '.$e->getMessage();
    }
    header('Location: '.BASE_URL.'admission/add_profile.php?id='.$admId);
    exit;
}

// Prepare formatted fields from admissions only
$fullAddress = implode(', ', array_filter([
    $a['village'] ?? '',
    $a['para_moholla'] ?? '',
    $a['upazila'] ?? '',
    $a['district'] ?? ''
]));

?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ভর্তি প্রোফাইল</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
      body{font-family:'SolaimanLipi','Source Sans Pro',sans-serif}
    </style>
    </head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include __DIR__ . '/../admin/inc/header.php'; ?>
  <?php include __DIR__ . '/../admin/inc/sidebar.php'; ?>

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">ভর্তি প্রোফাইল</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/dashboard.php">হোম</a></li>
              <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admission/students_quick_list.php">সংক্ষিপ্ত তালিকা</a></li>
              <li class="breadcrumb-item active">প্রোফাইল</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <?php if (!empty($_SESSION['error'])): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['success'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h3 class="card-title">ভর্তি তথ্য </h3>
            </div>
            <div>
              <a href="<?php echo BASE_URL; ?>admission/students_quick_list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> ফিরে যান</a>
            </div>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <table class="table table-bordered">
                  <tbody>
                    <tr>
                      <th style="width: 180px;">ভর্তি আইডি</th>
                      <td><?php echo htmlspecialchars($a['admission_id'] ?? ('ADM-'.$admId)); ?></td>
                    </tr>
                    <tr>
                      <th>শিক্ষার্থীর নাম</th>
                      <td><?php echo htmlspecialchars($a['student_name'] ?? ''); ?></td>
                    </tr>
                    <tr>
                      <th>পিতার নাম</th>
                      <td><?php echo htmlspecialchars($a['father_name'] ?? ''); ?></td>
                    </tr>
                    <tr>
                      <th>মাতার নাম</th>
                      <td><?php echo htmlspecialchars($a['mother_name'] ?? ''); ?></td>
                    </tr>
                    <tr>
                      <th>মোবাইল</th>
                      <td><?php echo htmlspecialchars($a['mobile'] ?? ''); ?></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-bordered">
                  <tbody>
                    <tr>
                      <th style="width: 180px;">বর্তমান বিদ্যালয়</th>
                      <td><?php echo htmlspecialchars($a['current_school_name'] ?? ''); ?></td>
                    </tr>
                    <tr>
                      <th>শ্রেণি</th>
                      <td><?php echo htmlspecialchars($a['current_class'] ?? ''); ?></td>
                    </tr>
                    <tr>
                      <th>রেফারেন্স</th>
                      <td><?php echo htmlspecialchars($a['reference'] ?? ''); ?></td>
                    </tr>
                    <tr>
                      <th>ঠিকানা</th>
                      <td><?php echo htmlspecialchars($fullAddress); ?></td>
                    </tr>
                    <tr>
                      <th>যোগ করার সময়</th>
                      <td><?php echo htmlspecialchars($a['created_at'] ?? ''); ?></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>
  </div>

  <?php include __DIR__ . '/../admin/inc/footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
