<?php
require_once '../config.php';

// Auth
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('../index.php');
}

// Load helper data
$classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();
$relations = $pdo->query("SELECT * FROM guardian_relations")->fetchAll();

$errors = [];
$success = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic sanitization
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $mother_name = trim($_POST['mother_name'] ?? '');
    $guardian_relation = trim($_POST['guardian_relation'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $birth_certificate_no = trim($_POST['birth_certificate_no'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $blood_group = trim($_POST['blood_group'] ?? '');
    $religion = trim($_POST['religion'] ?? '');
    $present_address = trim($_POST['present_address'] ?? '');
    $permanent_address = trim($_POST['permanent_address'] ?? '');
    $copy_address = isset($_POST['copy_address']);
    $class_id = intval($_POST['class_id'] ?? 0);
    $section_id = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
    $roll_number = trim($_POST['roll_number'] ?? '');
    $admission_date = $_POST['admission_date'] ?? null;
    $year = !empty($_POST['year']) ? intval($_POST['year']) : null;

    // Guardian logic validation
    if ($guardian_relation === 'পিতা') {
        $guardian_name = $father_name;
    } elseif ($guardian_relation === 'মাতা') {
        $guardian_name = $mother_name;
    }

    if ($guardian_relation !== 'পিতা' && $guardian_relation !== 'মাতা') {
        if ($guardian_name === '') {
            $errors[] = 'অভিভাবকের নাম প্রদান করতে হবে।';
        }
        if ($mobile_number === '') {
            $errors[] = 'অভিভাবকের মোবাইল নম্বর প্রদান করতে হবে।';
        }
    }

    // address validation
    if ($present_address === '') {
        $errors[] = 'বর্তমান ঠিকানা প্রদান করুন।';
    }
    if ($copy_address) {
        $permanent_address = $present_address;
    }

    if ($first_name === '') { $errors[] = 'শিক্ষার্থীর প্রথম নাম প্রয়োজন।'; }
    if ($class_id <= 0) { $errors[] = 'শ্রেণি নির্বাচন করুন।'; }

    // Photo upload handling
    $photo_file = null;
    if (!empty($_FILES['photo']['name'])) {
        $f = $_FILES['photo'];
        if ($f['error'] === 0) {
            $allowed = ['image/jpeg','image/png','image/webp'];
            if (!in_array($f['type'], $allowed)) {
                $errors[] = 'ছবি অবশ্যই jpg/png/webp ফরম্যাটে হতে হবে।';
            } else {
                $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                $photo_file = 'stu_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                $dst = __DIR__ . '/../uploads/students/' . $photo_file;
                if (!is_dir(dirname($dst))) { mkdir(dirname($dst), 0755, true); }
                if (!move_uploaded_file($f['tmp_name'], $dst)) {
                    $errors[] = 'ছবি আপলোড করতে ব্যর্থ হয়েছে।';
                }
            }
        } else {
            $errors[] = 'ছবি আপলোডে ত্রুটি।';
        }
    }

    if (empty($errors)) {
        // Generate student id and default password (hashed)
        $student_id = 'STU' . date('Y') . str_pad(rand(0,9999),4,'0',STR_PAD_LEFT);
        $default_password = '123456';
        $password_hash = password_hash($default_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO students
            (student_id, first_name, last_name, father_name, mother_name, guardian_relation, guardian_name, mobile_number, class_id, section_id, roll_number, birth_certificate_no, date_of_birth, blood_group, religion, present_address, permanent_address, admission_date, year, photo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $ok = $stmt->execute([
            $student_id, $first_name, $last_name, $father_name, $mother_name, $guardian_relation, $guardian_name, $mobile_number, $class_id, $section_id, $roll_number, $birth_certificate_no, $date_of_birth, $blood_group, $religion, $present_address, $permanent_address, $admission_date, $year, $photo_file
        ]);

        if ($ok) {
            // Optionally create a user account for the student in users table if your app uses it.
            $success = "শিক্ষার্থী সফলভাবে যোগ করা হয়েছে।<br>Student ID: <strong>{$student_id}</strong><br>Default password: <strong>{$default_password}</strong>";
            // reset form values
            $_POST = [];
        } else {
            $errors[] = 'ডাটাবেসে সংরক্ষণ করতে সমস্যা হয়েছে।';
        }
    }
}

// Minimal helper to fetch sections for class (for initial page render)
$sectionsAll = $pdo->query("SELECT * FROM sections ORDER BY name ASC")->fetchAll();

?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>নতুন শিক্ষার্থী যোগ করুন</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <!-- AdminLTE style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body{font-family:SolaimanLipi,Arial,sans-serif} .photo-preview{max-width:160px;max-height:160px;display:block;margin-top:8px;border:1px solid #ccc;padding:3px;border-radius:6px}</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Navbar -->
    <?php include 'inc/header.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php include 'inc/sidebar.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid py-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">নতুন শিক্ষার্থী যোগ করুন</div>
                    <div class="card-body">
            <?php if(!empty($errors)): ?>
                <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="addStudentForm">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>প্রথম নাম *</label>
                        <input name="first_name" class="form-control" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>শেষ নাম *</label>
                        <input name="last_name" class="form-control" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>পিতার নাম</label>
                        <input name="father_name" id="father_name" class="form-control" value="<?php echo htmlspecialchars($_POST['father_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>মাতার নাম</label>
                        <input name="mother_name" id="mother_name" class="form-control" value="<?php echo htmlspecialchars($_POST['mother_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>অভিভাবকের সম্পর্ক *</label>
                        <select name="guardian_relation" id="guardian_relation" class="form-control" required>
                            <option value="">নির্বাচন করুন</option>
                            <option value="পিতা" <?php if(!empty($_POST['guardian_relation']) && $_POST['guardian_relation']=='পিতা') echo 'selected'; ?>>পিতা</option>
                            <option value="মাতা" <?php if(!empty($_POST['guardian_relation']) && $_POST['guardian_relation']=='মাতা') echo 'selected'; ?>>মাতা</option>
                            <option value="অন্যান্য" <?php if(!empty($_POST['guardian_relation']) && $_POST['guardian_relation']=='অন্যান্য') echo 'selected'; ?>>অন্যান্য</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>অভিভাবকের নাম *</label>
                        <input name="guardian_name" id="guardian_name" class="form-control" value="<?php echo htmlspecialchars($_POST['guardian_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>অভিভাবকের মোবাইল</label>
                        <input name="mobile_number" id="mobile_number" class="form-control" value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>শ্রেণি *</label>
                        <select name="class_id" id="class_id" class="form-control" required>
                            <option value="">নির্বাচন করুন</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php if(!empty($_POST['class_id']) && $_POST['class_id']==$c['id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>শাখা</label>
                        <select name="section_id" id="section_id" class="form-control">
                            <option value="">নির্বাচন করুন</option>
                            <?php foreach($sectionsAll as $s): ?>
                                <option value="<?php echo $s['id']; ?>" data-class="<?php echo $s['class_id']; ?>" <?php if(!empty($_POST['section_id']) && $_POST['section_id']==$s['id']) echo 'selected'; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>রোল নম্বর</label>
                        <input name="roll_number" class="form-control" value="<?php echo htmlspecialchars($_POST['roll_number'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>ভর্তির তারিখ</label>
                        <input type="date" name="admission_date" class="form-control" value="<?php echo htmlspecialchars($_POST['admission_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>সাল (Year)</label>
                        <input name="year" type="number" min="1900" max="2100" class="form-control" value="<?php echo htmlspecialchars($_POST['year'] ?? date('Y')); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>ছবি আপলোড</label>
                        <div>
                            <input type="file" name="photo" id="photo" accept="image/*" class="form-control-file">
                            <img id="photoPreview" class="photo-preview" style="display:none">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>জন্ম সনদের নং</label>
                        <input name="birth_certificate_no" class="form-control" value="<?php echo htmlspecialchars($_POST['birth_certificate_no'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>জন্ম তারিখ</label>
                        <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>রক্তের গ্রুপ</label>
                        <select name="blood_group" class="form-control">
                            <option value="">নির্বাচন করুন</option>
                            <?php $bgs = ['A+','A-','B+','B-','AB+','AB-','O+','O-']; foreach($bgs as $bg): ?>
                                <option value="<?php echo $bg; ?>" <?php if(!empty($_POST['blood_group']) && $_POST['blood_group']==$bg) echo 'selected'; ?>><?php echo $bg; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>ধর্ম</label>
                        <input name="religion" class="form-control" value="<?php echo htmlspecialchars($_POST['religion'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>বর্তমান ঠিকানা</label>
                        <textarea name="present_address" id="present_address" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['present_address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label>স্থায়ী ঠিকানা</label>
                        <textarea name="permanent_address" id="permanent_address" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['permanent_address'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group col-md-4 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="copy_address" id="copy_address" <?php if(!empty($_POST['copy_address'])) echo 'checked'; ?>>
                            <label class="form-check-label" for="copy_address">বর্তমান ঠিকানা স্থায়ী ঠিকানায় কপি করুন</label>
                        </div>
                    </div>
                </div>

                <div class="form-group text-right">
                    <a href="students.php" class="btn btn-secondary">বাতিল</a>
                    <button class="btn btn-primary" type="submit">সংরক্ষণ করুন</button>
                </div>
            </form>
        </div>
    </div>
    <p class="text-muted small mt-2">নোট: ডিফল্ট পাসওয়ার্ড হবে <strong>123456</strong></p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Main Footer -->
    <?php include 'inc/footer.php'; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap + AdminLTE scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
$(function(){
    // Guardian relation logic
    function updateGuardianFields(){
        var rel = $('#guardian_relation').val();
        if(rel === 'পিতা'){
            $('#guardian_name').val($('#father_name').val()).prop('disabled', true);
            $('#mobile_number').prop('required', false);
        } else if(rel === 'মাতা'){
            $('#guardian_name').val($('#mother_name').val()).prop('disabled', true);
            $('#mobile_number').prop('required', false);
        } else {
            $('#guardian_name').val('').prop('disabled', false);
            $('#mobile_number').prop('required', true);
        }
    }

    $('#guardian_relation').on('change', updateGuardianFields);
    $('#father_name,#mother_name').on('input', updateGuardianFields);
    updateGuardianFields();

    // copy present -> permanent address
    function syncAddresses(copy){
        if(copy){
            $('#permanent_address').val($('#present_address').val()).prop('readonly', true);
        } else {
            $('#permanent_address').prop('readonly', false);
        }
    }

    $('#copy_address').on('change', function(){
        syncAddresses($(this).is(':checked'));
    });
    // when present address changes and copy is checked, update permanent
    $('#present_address').on('input', function(){
        if($('#copy_address').is(':checked')) $('#permanent_address').val($(this).val());
    });
    // initialize copy state
    syncAddresses($('#copy_address').is(':checked'));

    // photo preview
    $('#photo').on('change', function(e){
        var file = this.files[0];
        if(!file) return;
        var reader = new FileReader();
        reader.onload = function(ev){
            $('#photoPreview').attr('src', ev.target.result).show();
        }
        reader.readAsDataURL(file);
    });

    // filter sections by class
    var originalOptions = $('#section_id option');
    $('#class_id').on('change', function(){
        var cid = $(this).val();
        $('#section_id').html('<option value="">নির্বাচন করুন</option>');
        originalOptions.each(function(){
            var cls = $(this).data('class');
            if(!cid || cls == cid) $('#section_id').append($(this).clone());
        });
    });
});
</script>
</body>
</html>
