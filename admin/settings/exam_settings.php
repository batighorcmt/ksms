<?php
require_once '../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    header('Location: ../login.php');
    exit();
}

// Exam types (could be loaded from DB in future)
$default_types = ['সাময়িক', 'টিউটোরিয়াল', 'ক্লাস টেস্ট'];

// Load current settings (assume settings are stored in a table 'exam_settings')
$settings = $pdo->query("SELECT * FROM exam_settings LIMIT 1")->fetch();
$exam_types = isset($settings['exam_types']) ? json_decode($settings['exam_types'], true) : $default_types;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_types = array_filter(array_map('trim', $_POST['exam_types'] ?? []));
    $exam_types_json = json_encode($exam_types, JSON_UNESCAPED_UNICODE);
    if ($settings) {
        $stmt = $pdo->prepare("UPDATE exam_settings SET exam_types=? WHERE id=?");
        $stmt->execute([$exam_types_json, $settings['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO exam_settings (exam_types) VALUES (?)");
        $stmt->execute([$exam_types_json]);
    }
    $_SESSION['success'] = 'Exam settings updated!';
    header('Location: exam_settings.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>পরীক্ষা সেটিংস</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../inc/header.php'; ?>
    <?php include '../inc/sidebar.php'; ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">পরীক্ষা সেটিংস</h1></div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-header"><b>পরীক্ষার ধরন সেট করুন</b></div>
                    <div class="card-body">
                        <form method="post">
                            <div id="examTypesList">
                                <?php foreach($exam_types as $i => $type): ?>
                                    <div class="input-group mb-2">
                                        <input type="text" name="exam_types[]" class="form-control" value="<?php echo htmlspecialchars($type); ?>" required>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-danger remove-type"><i class="fa fa-trash"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-info mb-2" id="addTypeBtn"><i class="fa fa-plus"></i> নতুন ধরন যোগ করুন</button>
                            <br>
                            <button type="submit" class="btn btn-primary">সংরক্ষণ করুন</button>
                        </form>
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
<script>
$(function(){
    $('#addTypeBtn').click(function(){
        $('#examTypesList').append('<div class="input-group mb-2"><input type="text" name="exam_types[]" class="form-control" required><div class="input-group-append"><button type="button" class="btn btn-danger remove-type"><i class="fa fa-trash"></i></button></div></div>');
    });
    $(document).on('click', '.remove-type', function(){
        $(this).closest('.input-group').remove();
    });
});
</script>
</body>
</html>
