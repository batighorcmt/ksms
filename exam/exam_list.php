<?php
require_once '../config.php';
// simple list of exams with class name and academic year
$stmt = $pdo->query("SELECT e.*, et.name as exam_type_name, c.name as class_name, ay.year as academic_year FROM exams e LEFT JOIN exam_types et ON et.id = e.exam_type_id LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN academic_years ay ON ay.id = e.academic_year_id ORDER BY e.created_at DESC");
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build unique lists for filters
$classesMap = [];
$yearsMap   = [];
$typesMap   = [];
foreach ($exams as $e) {
    if (!empty($e['class_id'])) {
        $classesMap[(int)$e['class_id']] = $e['class_name'] ?? (string)$e['class_id'];
    }
    if (!empty($e['academic_year_id'])) {
        $yearsMap[(int)$e['academic_year_id']] = $e['academic_year'] ?? '';
    }
    if (!empty($e['exam_type_id'])) {
        $typesMap[(int)$e['exam_type_id']] = $e['exam_type_name'] ?? (string)$e['exam_type_id'];
    }
}
asort($classesMap, SORT_NATURAL | SORT_FLAG_CASE);
asort($yearsMap, SORT_NATURAL | SORT_FLAG_CASE);
asort($typesMap, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>পরীক্ষার তালিকা</title>
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Bengali Font -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">

    <style>
        body, .main-sidebar, .nav-link {
            font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;
        }
        .logo-custom {
            font-weight: bold;
            font-size: 22px;
        }
        .info-box {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .info-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .progress-sm {
            height: 10px;
        }
        .small-chart-container {
            position: relative;
            height: 100px;
        }
        .bg-gradient-primary {
            background: linear-gradient(87deg, #5e72e4 0, #825ee4 100%) !important;
        }
        .bg-gradient-success {
            background: linear-gradient(87deg, #2dce89 0, #2dcecc 100%) !important;
        }
        .bg-gradient-info {
            background: linear-gradient(87deg, #11cdef 0, #1171ef 100%) !important;
        }
        .bg-gradient-warning {
            background: linear-gradient(87deg, #fb6340 0, #fbb140 100%) !important;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Navbar -->
    <?php include '../admin/inc/header.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php include '../admin/inc/sidebar.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">পরীক্ষার তালিকা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">পরীক্ষা</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-body">
                        <div class="mb-3 clearfix">
                            <a href="create_exam.php" class="btn btn-sm btn-primary float-end">নতুন পরীক্ষা</a>
                        </div>

                        <!-- Filters -->
                        <div class="mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label for="filterSearch" class="form-label mb-1">সার্চ</label>
                                    <input type="text" id="filterSearch" class="form-control form-control-sm" placeholder="নাম, ক্লাস, বছর, ধরন, আইডি, তারিখ...">
                                </div>
                                <div class="col-md-2">
                                    <label for="filterClass" class="form-label mb-1">শ্রেণী</label>
                                    <select id="filterClass" class="form-select form-select-sm">
                                        <option value="">সব শ্রেণী</option>
                                        <?php foreach ($classesMap as $cid => $cname): ?>
                                            <option value="<?= (int)$cid ?>"><?= htmlspecialchars($cname) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="filterYear" class="form-label mb-1">বছর</label>
                                    <select id="filterYear" class="form-select form-select-sm">
                                        <option value="">সব বছর</option>
                                        <?php foreach ($yearsMap as $yid => $yname): ?>
                                            <option value="<?= (int)$yid ?>"><?= htmlspecialchars($yname) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="filterType" class="form-label mb-1">ধরন</label>
                                    <select id="filterType" class="form-select form-select-sm">
                                        <option value="">সব ধরন</option>
                                        <?php foreach ($typesMap as $tid => $tname): ?>
                                            <option value="<?= (int)$tid ?>"><?= htmlspecialchars($tname) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <label for="dateFrom" class="form-label mb-1">তারিখ (থেকে)</label>
                                    <input type="date" id="dateFrom" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-1">
                                    <label for="dateTo" class="form-label mb-1">তারিখ (পর্যন্ত)</label>
                                    <input type="date" id="dateTo" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-1 d-grid">
                                    <button type="button" id="resetFilters" class="btn btn-sm btn-secondary">রিসেট</button>
                                </div>
                            </div>
                            <div class="mt-1 text-muted small">মোট: <span id="totalCount">0</span></div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr><th>ID</th><th>নাম</th><th>শ্রেণী</th><th>বছর</th><th>ধরন</th><th>প্রকাশ তারিখ</th><th>অ্যাকশন</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach($exams as $e): ?>
                                        <?php
                                            $examId = (int)$e['id'];
                                            $classId = (int)($e['class_id'] ?? 0);
                                            $className = $e['class_name'] ?? ($e['class_id'] ?? '');
                                            $yearId = (int)($e['academic_year_id'] ?? 0);
                                            $yearName = $e['academic_year'] ?? '';
                                            $typeId = (int)($e['exam_type_id'] ?? 0);
                                            $typeName = $e['exam_type_name'] ?? ($e['exam_type_id'] ?? '');
                                            $pubRaw = $e['result_publish_date'] ?? ($e['result_release_date'] ?? '');
                                            $pubIso = '';
                                            if (!empty($pubRaw)) {
                                                $ts = strtotime($pubRaw);
                                                if ($ts) { $pubIso = date('Y-m-d', $ts); }
                                            }
                                        ?>
                                    <tr data-id="<?= $examId ?>"
                                        data-class-id="<?= $classId ?>"
                                        data-class-name="<?= htmlspecialchars($className) ?>"
                                        data-year-id="<?= $yearId ?>"
                                        data-year-name="<?= htmlspecialchars($yearName) ?>"
                                        data-type-id="<?= $typeId ?>"
                                        data-type-name="<?= htmlspecialchars($typeName) ?>"
                                        data-publish-date="<?= htmlspecialchars($pubIso) ?>">
                                        <td><?= $examId ?></td>
                                        <td><?= htmlspecialchars($e['name']) ?></td>
                                        <td><?= htmlspecialchars($className) ?></td>
                                        <td><?= htmlspecialchars($yearName) ?></td>
                                        <td><?= htmlspecialchars($typeName) ?></td>
                                        <td><?= htmlspecialchars($pubRaw) ?></td>
                                                                                <td>
                                                                                        <?php
                                                                                            $yearId = (int)($e['academic_year_id'] ?? 0);
                                                                                            $classId = (int)($e['class_id'] ?? 0);
                                                                                            $examId  = (int)$e['id'];
                                                                                            $tabBase = 'tabulation.php?year='.$yearId.'&class_id='.$classId.'&exam_id='.$examId;
                                                                                        ?>
                                                                                        <div class="btn-group">
                                                                                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                                                                অ্যাকশন
                                                                                            </button>
                                                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                                                <li><a class="dropdown-item" href="create_exam.php?id=<?= $examId ?>"><i class="fa-solid fa-pen-to-square me-1"></i> Edit</a></li>
                                                                                                <li><a class="dropdown-item" href="<?= $tabBase ?>"><i class="fa-solid fa-table me-1"></i> টেবুলেশন দেখুন</a></li>
                                                                                                <li><hr class="dropdown-divider"></li>
                                                                                                <li><a class="dropdown-item" href="admit.php?exam_id=<?= $examId ?>" target="_blank"><i class="fa-solid fa-id-card me-1"></i> Admit Card</a></li>
                                                                                                <li><hr class="dropdown-divider"></li>
                                                                                                <li><a class="dropdown-item" href="<?= $tabBase ?>&print=single" target="_blank"><i class="fa-solid fa-print me-1"></i> Print (Single)</a></li>
                                                                                                <li><a class="dropdown-item" href="<?= $tabBase ?>&print=combined" target="_blank"><i class="fa-solid fa-print me-1"></i> Print (Combined)</a></li>
                                                                                                <li><a class="dropdown-item" href="<?= $tabBase ?>&print=stats" target="_blank"><i class="fa-solid fa-chart-column me-1"></i> Print (Stats)</a></li>
                                                                                                <li><hr class="dropdown-divider"></li>
                                                                                                <li><a class="dropdown-item text-danger" href="delete_exam.php?id=<?= $examId ?>" onclick="return confirm('Are you sure?')"><i class="fa-solid fa-trash me-1"></i> Delete</a></li>
                                                                                            </ul>
                                                                                        </div>
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

    <!-- Main Footer -->
    <?php include '../admin/inc/footer.php'; ?>

</div>

<!-- REQUIRED SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
// Normalize Bangla digits to English for consistent matching
function bnToEnDigits(str){
    if(!str) return '';
    const map = {'০':'0','১':'1','২':'2','৩':'3','৪':'4','৫':'5','৬':'6','৭':'7','৮':'8','৯':'9'};
    return String(str).replace(/[০-৯]/g, d => map[d] || d);
}
function normalizeText(str){
    return bnToEnDigits(str).toLowerCase().trim();
}
document.addEventListener('DOMContentLoaded', function(){
    const searchInput = document.getElementById('filterSearch');
    const classSel = document.getElementById('filterClass');
    const yearSel = document.getElementById('filterYear');
    const typeSel = document.getElementById('filterType');
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const resetBtn = document.getElementById('resetFilters');
    const table = document.querySelector('table.table');
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const totalEl = document.getElementById('totalCount');

    let debounce;
    function applyFilters(){
        const q = normalizeText(searchInput.value);
        const cVal = classSel.value;
        const yVal = yearSel.value;
        const tVal = typeSel.value;
        const dFrom = dateFrom.value ? new Date(dateFrom.value) : null;
        const dTo   = dateTo.value ? new Date(dateTo.value) : null;
        let shown = 0;

        rows.forEach(tr => {
            // skip header safety
            if(!tr || !tr.dataset) return;
            const rowText = normalizeText(tr.textContent || '');
            const matchText = !q || rowText.includes(q);

            const matchClass = !cVal || (tr.dataset.classId === cVal);
            const matchYear  = !yVal || (tr.dataset.yearId === yVal);
            const matchType  = !tVal || (tr.dataset.typeId === tVal);

            let matchDate = true;
            const p = tr.dataset.publishDate || '';
            if ((dFrom || dTo) && p){
                const d = new Date(p);
                if (dFrom && d < dFrom) matchDate = false;
                if (dTo && d > dTo) matchDate = false;
            } else if ((dFrom || dTo) && !p){
                matchDate = false;
            }

            const show = matchText && matchClass && matchYear && matchType && matchDate;
            tr.style.display = show ? '' : 'none';
            if (show) shown++;
        });
        totalEl.textContent = shown;
    }

    function debouncedFilter(){
        clearTimeout(debounce);
        debounce = setTimeout(applyFilters, 120);
    }

    searchInput.addEventListener('input', debouncedFilter);
    [classSel, yearSel, typeSel, dateFrom, dateTo].forEach(el => el.addEventListener('change', applyFilters));
    resetBtn.addEventListener('click', function(){
        searchInput.value = '';
        classSel.value = '';
        yearSel.value = '';
        typeSel.value = '';
        dateFrom.value = '';
        dateTo.value = '';
        applyFilters();
    });

    // Prevent enter key from submitting any enclosing form (if any)
    searchInput.addEventListener('keydown', function(e){
        if (e.key === 'Enter') e.preventDefault();
    });

    // Initialize count
    applyFilters();
});
</script>

</body>
</html>
