<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// Add homework
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_homework'])) {
    $class_id = $_POST['class_id'];
    $section_id = $_POST['section_id'];
    $subject = trim($_POST['subject']);
    $homework_text = trim($_POST['homework_text']);
    $due_date = $_POST['due_date'];
    $created_by = $_SESSION['user_id'];
    $created_at = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO homework (class_id, section_id, subject, homework_text, due_date, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$class_id, $section_id, $subject, $homework_text, $due_date, $created_by, $created_at])) {
        $_SESSION['success'] = 'হোমওয়ার্ক সফলভাবে যোগ করা হয়েছে!';
        redirect('homework.php');
    } else {
        $_SESSION['error'] = 'হোমওয়ার্ক যোগ করতে সমস্যা হয়েছে!';
    }
}

// Fetch homework records
$sql = "SELECT h.*, c.name as class_name, s.name as section_name, u.full_name as teacher_name FROM homework h JOIN classes c ON h.class_id = c.id JOIN sections s ON h.section_id = s.id LEFT JOIN users u ON h.created_by = u.id ORDER BY h.due_date DESC, h.id DESC";
$homeworks = $pdo->query($sql)->fetchAll();

// For dropdowns
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();
$sections = $pdo->query("SELECT * FROM sections ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>হোমওয়ার্ক রিপোর্ট</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        body, .main-sidebar, .nav-link { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }
        .table th, .table td { vertical-align: middle; }
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
                    <div class="col-sm-6"><h1 class="m-0">হোমওয়ার্ক রিপোর্ট</h1></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item active">হোমওয়ার্ক</li>
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
                <div class="card mb-4">
                    <div class="card-header"><b>নতুন হোমওয়ার্ক দিন</b></div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group col-md-2">
                                    <label>ক্লাস</label>
                                    <select name="class_id" class="form-control" required>
                                        <option value="">নির্বাচন করুন</option>
                                        <?php foreach($classes as $c): ?>
                                            <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label>শাখা</label>
                                    <select name="section_id" class="form-control" required>
                                        <option value="">নির্বাচন করুন</option>
                                        <?php foreach($sections as $s): ?>
                                            <option value="<?php echo $s['id']; ?>"><?php echo $s['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>বিষয়</label>
                                    <input type="text" name="subject" class="form-control" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>হোমওয়ার্ক</label>
                                    <input type="text" name="homework_text" class="form-control" required>
                                </div>
                                <div class="form-group col-md-2">
                                    <label>জমা দেয়ার শেষ তারিখ</label>
                                    <input type="date" name="due_date" class="form-control" required>
                                </div>
                            </div>
                            <button type="submit" name="add_homework" class="btn btn-primary">সংরক্ষণ করুন</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><b>হোমওয়ার্ক রিপোর্ট</b></div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>ক্লাস </th>
                                    <th>শাখা</th>
                                    <th>বিষয়</th>
                                    <th>হোমওয়ার্ক</th>
                                    <th>শেষ তারিখ</th>
                                    <th>শিক্ষক</th>
                                    <th>তৈরির সময়</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach($homeworks as $hw): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo htmlspecialchars($hw['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($hw['section_name']); ?></td>
                                    <td><?php echo htmlspecialchars($hw['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($hw['homework_text']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($hw['due_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($hw['teacher_name'] ?? ''); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($hw['created_at'])); ?></td>
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
</body>
</html>
