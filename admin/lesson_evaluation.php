<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শ্রেণি মূল্যায়ন</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body, .main-sidebar, .nav-link { 
            font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; 
        }
        
        /* Modern Color Scheme */
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --info: #4895ef;
            --warning: #f72585;
            --danger: #e63946;
            --light: #f8f9fa;
            --dark: #212529;
            --gradient-primary: linear-gradient(120deg, #4361ee, #3a0ca3);
            --gradient-success: linear-gradient(120deg, #4cc9f0, #4895ef);
            --gradient-warning: linear-gradient(120deg, #f72585, #b5179e);
        }
        
        .card-header {
            background: var(--gradient-primary);
            color: white;
        }
        
        .card-outline {
            border-top: 3px solid;
            border-top-color: var(--primary);
        }
        
        .card-outline-success {
            border-top-color: var(--success);
        }
        
        .card-outline-info {
            border-top-color: var(--info);
        }
        
        .btn-modern {
            background: var(--gradient-primary);
            border: none;
            border-radius: 25px;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            background: var(--gradient-success);
        }
        
        .badge-modern {
            border-radius: 15px;
            padding: 5px 10px;
            font-weight: normal;
            margin: 2px;
        }
        
        .table thead {
            background: var(--gradient-primary);
            color: white;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.1);
        }
        
        .evaluation-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }
        
        .stat-box {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            color: white;
            width: 23%;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .stat-box i {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .stat-box .count {
            font-size: 24px;
            font-weight: bold;
        }
        
        .stat-box-primary {
            background: var(--gradient-primary);
        }
        
        .stat-box-success {
            background: var(--gradient-success);
        }
        
        .stat-box-warning {
            background: var(--gradient-warning);
        }
        
        .stat-box-info {
            background: linear-gradient(120deg, #7209b7, #560bad);
        }
        
        .select2-container--default .select2-selection--multiple {
            border-radius: 10px;
            padding: 5px;
            min-height: 42px;
        }
        
        .form-control {
            border-radius: 10px;
        }
        
        .custom-control-input:checked~.custom-control-label::before {
            background: var(--gradient-primary);
            border-color: var(--primary);
        }
        
        .print-btn {
            background: var(--gradient-warning);
            border: none;
            border-radius: 25px;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">শ্রেণি মূল্যায়ন</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">মূল্যায়ন</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="evaluation-stats">
                    <div class="stat-box stat-box-primary">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <div class="count"><?php echo count($routines); ?></div>
                        <div>মোট ক্লাস</div>
                    </div>
                    <div class="stat-box stat-box-success">
                        <i class="fas fa-tasks"></i>
                        <div class="count"><?php echo count($evaluations); ?></div>
                        <div>মূল্যায়ন</div>
                    </div>
                    <div class="stat-box stat-box-warning">
                        <i class="fas fa-users"></i>
                        <div class="count">
                            <?php 
                            $total_students = 0;
                            foreach($evaluations as $ev) {
                                $st_ids = json_decode($ev['evaluated_students'], true) ?? [];
                                $total_students += count($st_ids);
                            }
                            echo $total_students;
                            ?>
                        </div>
                        <div>মূল্যায়নকৃত শিক্ষার্থী</div>
                    </div>
                    <div class="stat-box stat-box-info">
                        <i class="fas fa-calendar-day"></i>
                        <div class="count"><?php echo $today; ?></div>
                        <div>আজকের তারিখ</div>
                    </div>
                </div>
                
                <div class="card card-outline mb-4">
                    <div class="card-header"><b>রুটিন অনুযায়ী ক্লাস তালিকা</b></div>
                    <div class="card-body">
                        <table class="table table-bordered table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>শ্রেণি</th>
                                    <th>শাখা</th>
                                    <th>বিষয়</th>
                                    <th>মূল্যায়ন</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($routines as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['section_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['subject_name'] ?? ''); ?></td>
                                    <td>
                                        <a href="?class_id=<?php echo $r['class_id']; ?>&section_id=<?php echo $r['section_id']; ?>&subject=<?php echo isset($r['subject_name']) ? urlencode($r['subject_name']) : ''; ?>" class="btn btn-modern btn-sm">দেখুন/মূল্যায়ন</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if(isset($_GET['class_id'], $_GET['section_id'], $_GET['subject'])): ?>
                <div class="card card-outline card-outline-success mb-4">
                    <div class="card-header"><b>মূল্যায়ন ফরম</b></div>
                    <div class="card-body">
                        <?php
                        // Check if evaluation exists for this class/section/subject/date
                        $eval_stmt = $pdo->prepare("SELECT * FROM lesson_evaluation WHERE teacher_id=? AND class_id=? AND section_id=? AND subject=? AND date=?");
                        $eval_stmt->execute([$user_id, $_GET['class_id'], $_GET['section_id'], $_GET['subject'], $today]);
                        $eval = $eval_stmt->fetch();
                        $selected_students = $eval ? json_decode($eval['evaluated_students'], true) : [];
                        // Find the routine row matching the selected class/section/subject
                        $selected_class_name = '';
                        $selected_section_name = '';
                        $selected_subject_name = '';
                        foreach ($routines as $r) {
                            if ($r['class_id'] == $_GET['class_id'] && $r['section_id'] == $_GET['section_id'] && $r['subject_name'] == $_GET['subject']) {
                                $selected_class_name = $r['class_name'];
                                $selected_section_name = $r['section_name'];
                                $selected_subject_name = $r['subject_name'];
                                break;
                            }
                        }
                        // fallback if not found
                        if ($selected_class_name === '') $selected_class_name = htmlspecialchars($_GET['class_id']);
                        if ($selected_section_name === '') $selected_section_name = htmlspecialchars($_GET['section_id']);
                        if ($selected_subject_name === '') $selected_subject_name = htmlspecialchars($_GET['subject']);
                        ?>
                        <form method="POST">
                            <input type="hidden" name="class_id" value="<?php echo (int)$_GET['class_id']; ?>">
                            <input type="hidden" name="section_id" value="<?php echo (int)$_GET['section_id']; ?>">
                            <input type="hidden" name="subject" value="<?php echo htmlspecialchars($_GET['subject']); ?>">
                            <input type="hidden" name="date" value="<?php echo $today; ?>">
                            <?php if($eval): ?><input type="hidden" name="eval_id" value="<?php echo $eval['id']; ?>"><?php endif; ?>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>শ্রেণি</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($selected_class_name); ?>" readonly>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>শাখা</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($selected_section_name); ?>" readonly>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>বিষয়</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($selected_subject_name); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>ছাত্র/ছাত্রী (মাল্টি-সিলেক্ট)</label>
                                <select name="students[]" class="form-control select2" multiple required style="width:100%">
                                    <?php foreach($students as $st): ?>
                                        <option value="<?php echo $st['id']; ?>" <?php echo in_array($st['id'], $selected_students ?? []) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>তারিখ</label>
                                    <input type="text" class="form-control" value="<?php echo $today; ?>" readonly>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>শিক্ষক</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($teacher_name); ?>" readonly>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>পড়া হয়েছে কি?</label><br>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="is_completed" name="is_completed" value="1" <?php echo ($eval && $eval['is_completed']) ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="is_completed">হ্যাঁ</label>
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>মন্তব্য</label>
                                    <input type="text" name="remarks" class="form-control" value="<?php echo $eval['remarks'] ?? ''; ?>" placeholder="মন্তব্য লিখুন...">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-modern">সংরক্ষণ করুন</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                <div class="card card-outline card-outline-info">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <b>মূল্যায়ন রিপোর্ট</b>
                        <a href="?print=1" target="_blank" class="btn btn-sm print-btn"><i class="fa fa-print"></i> প্রিন্ট</a>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>তারিখ</th>
                                    <th>শ্রেণি</th>
                                    <th>শাখা</th>
                                    <th>বিষয়</th>
                                    <th>শিক্ষক</th>
                                    <th>ছাত্র/ছাত্রী</th>
                                    <th>পড়া হয়েছে?</th>
                                    <th>মন্তব্য</th>
                                    <th>সময়</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($evaluations as $ev): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ev['date']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['section_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['teacher_name']); ?></td>
                                    <td>
                                        <?php 
                                        $st_ids = json_decode($ev['evaluated_students'], true) ?? []; 
                                        if ($st_ids) {
                                            $in = str_repeat('?,', count($st_ids)-1) . '?';
                                            $st_stmt = $pdo->prepare("SELECT id, roll_number, first_name, last_name FROM students WHERE id IN ($in)");
                                            $st_stmt->execute($st_ids);
                                            $st_map = [];
                                            foreach($st_stmt->fetchAll() as $st) {
                                                $st_map[$st['id']] = $st;
                                            }
                                            foreach($st_ids as $sid) {
                                                if(isset($st_map[$sid])) {
                                                    $st = $st_map[$sid];
                                                    echo '<span class="badge badge-modern badge-info">'.htmlspecialchars($st['roll_number']).' - '.htmlspecialchars($st['first_name'].' '.$st['last_name']).'</span> ';
                                                } else {
                                                    echo '<span class="badge badge-modern badge-secondary">'.$sid.'</span> ';
                                                }
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if($ev['is_completed']): ?>
                                            <span class="badge badge-modern badge-success">হ্যাঁ</span>
                                        <?php else: ?>
                                            <span class="badge badge-modern badge-danger">না</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($ev['remarks']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($ev['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(function() { 
        $('.select2').select2({ 
            width: 'resolve',
            placeholder: "ছাত্র/ছাত্রী নির্বাচন করুন",
            allowClear: true
        }); 
    });
</script>
</body>
</html>