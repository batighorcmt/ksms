<?php
require_once '../config.php';

if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// fetch helper data
$classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();
$sections = $pdo->query("SELECT * FROM sections ORDER BY name ASC")->fetchAll();
$guardians = $pdo->query("SELECT * FROM users WHERE role='guardian'")->fetchAll();
$relations = $pdo->query("SELECT * FROM guardian_relations")->fetchAll();

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $mother_name = trim($_POST['mother_name'] ?? '');
    $guardian_relation = $_POST['guardian_relation'] ?? '';
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $guardian_mobile = trim($_POST['guardian_mobile'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $birth_certificate_no = trim($_POST['birth_certificate_no'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $gender = $_POST['gender'] ?? null;
    $blood_group = $_POST['blood_group'] ?? null;
    $religion = $_POST['religion'] ?? null;
    $present_address = $_POST['present_address'] ?? null;
    $permanent_address = $_POST['permanent_address'] ?? null;
    $mobile_number = $_POST['mobile_number'] ?? null;
    $class_id = $_POST['class_id'] ?? null;
    $section_id = $_POST['section_id'] ?? null;
    $roll_number = $_POST['roll_number'] ?? null;
    $guardian_id = !empty($_POST['guardian_id']) ? $_POST['guardian_id'] : NULL;
    $admission_date = $_POST['admission_date'] ?? null;

    // validation
    $errors = [];
    if ($guardian_relation === 'father') {
        $guardian_name = $father_name;
    } elseif ($guardian_relation === 'mother') {
        $guardian_name = $mother_name;
    } else {
        if (empty($guardian_name)) $errors[] = 'অভিভাবকের নাম আবশ্যক।';
        if (empty($guardian_mobile)) $errors[] = 'অভিভাবকের মোবাইল নম্বর আবশ্যক।';
    }

    if (empty($first_name) || empty($last_name)) $errors[] = 'শিক্ষার্থীর পূর্ণ নাম আবশ্যক।';

    if (empty($errors)) {
        // generate id
        $student_id = 'STU' . date('Y') . rand(1000, 9999);

        $stmt = $pdo->prepare(
            "INSERT INTO students (student_id, first_name, last_name, father_name, mother_name, guardian_relation, guardian_name, guardian_mobile, year, birth_certificate_no, date_of_birth, gender, blood_group, religion, present_address, permanent_address, mobile_number, class_id, section_id, roll_number, guardian_id, admission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $ok = $stmt->execute([
            $student_id, $first_name, $last_name, $father_name, $mother_name, $guardian_relation, $guardian_name, $guardian_mobile, $year, $birth_certificate_no, $date_of_birth, $gender, $blood_group, $religion, $present_address, $permanent_address, $mobile_number, $class_id, $section_id, $roll_number, $guardian_id, $admission_date
        ]);

        if ($ok) {
            $_SESSION['success'] = 'শিক্ষার্থী যোগ করা হয়েছে।';
            redirect('admin/students.php');
        } else {
            $errors[] = 'ডাটাবেজে সেভ করতে সমস্যা হয়েছে।';
        }
    }
}

?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>নতুন শিক্ষার্থী যোগ করুন</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <h3>নতুন শিক্ষার্থী যোগ করুন</h3>
    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
    <?php endif; ?>
    <form method="POST" class="mt-3">
        <div class="form-row">
            <div class="form-group col-md-6">
                <label>প্রথম নাম</label>
                <input name="first_name" class="form-control" required value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
            </div>
            <div class="form-group col-md-6">
                <label>শেষ নাম</label>
                <input name="last_name" class="form-control" required value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>পিতার নাম</label>
                <input name="father_name" id="father_name" class="form-control" value="<?php echo htmlspecialchars($father_name ?? ''); ?>">
            </div>
            <div class="form-group col-md-6">
                <label>মাতার নাম</label>
                <input name="mother_name" id="mother_name" class="form-control" value="<?php echo htmlspecialchars($mother_name ?? ''); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>অভিভাবকের সম্পর্ক</label>
                <select name="guardian_relation" id="guardian_relation" class="form-control">
                    <option value="">নির্বাচন করুন</option>
                    <option value="father">পিতা</option>
                    <option value="mother">মাতা</option>
                    <?php foreach($relations as $r): ?>
                        <option value="<?php echo htmlspecialchars($r['name']); ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                    <?php endforeach; ?>
                    <option value="other">অন্যান্য</option>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label>অভিভাবকের নাম</label>
                <input name="guardian_name" id="guardian_name" class="form-control" value="<?php echo htmlspecialchars($guardian_name ?? ''); ?>">
            </div>
            <div class="form-group col-md-4">
                <label>অভিভাবকের মোবাইল</label>
                <input name="guardian_mobile" id="guardian_mobile" class="form-control" value="<?php echo htmlspecialchars($guardian_mobile ?? ''); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-3">
                <label>বছর</label>
                <input name="year" class="form-control" placeholder="উদাহরণ: ২০২৫" value="<?php echo htmlspecialchars($year ?? ''); ?>">
            </div>
            <div class="form-group col-md-3">
                <label>জেন্ডার</label>
                <select name="gender" class="form-control">
                    <option value="">নির্বাচন করুন</option>
                    <option value="male">ছেলে</option>
                    <option value="female">মেয়ে</option>
                    <option value="other">অন্যান্য</option>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label>ধর্ম</label>
                <select name="religion" class="form-control">
                    <option value="">নির্বাচন করুন</option>
                    <option value="Islam">ইসলাম</option>
                    <option value="Hinduism">হিন্দু</option>
                    <option value="Christianity">খ্রিস্টান</option>
                    <option value="Buddhism">বৌদ্ধ</option>
                    <option value="Other">অন্যান্য</option>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label>ভর্তির তারিখ</label>
                <input type="date" name="admission_date" class="form-control" value="<?php echo htmlspecialchars($admission_date ?? ''); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>বর্তমান ঠিকানা</label>
                <textarea name="present_address" class="form-control"><?php echo htmlspecialchars($present_address ?? ''); ?></textarea>
            </div>
            <div class="form-group col-md-6">
                <label>স্থায়ী ঠিকানা</label>
                <textarea name="permanent_address" class="form-control"><?php echo htmlspecialchars($permanent_address ?? ''); ?></textarea>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>ক্লাস</label>
                <select name="class_id" class="form-control">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label>শাখা</label>
                <select name="section_id" class="form-control">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach($sections as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label>রোল নম্বর</label>
                <input name="roll_number" class="form-control" value="<?php echo htmlspecialchars($roll_number ?? ''); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>মোবাইল নম্বর</label>
                <input name="mobile_number" class="form-control" value="<?php echo htmlspecialchars($mobile_number ?? ''); ?>">
            </div>
            <div class="form-group col-md-6 text-right align-self-end">
                <a href="students.php" class="btn btn-secondary">বাতিল</a>
                <button type="submit" class="btn btn-primary">সংরক্ষণ করুন</button>
            </div>
        </div>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(function(){
        function refreshGuardianFields(){
            var rel = $('#guardian_relation').val();
            if (rel === 'father'){
                $('#guardian_name').val($('#father_name').val()).prop('disabled', true);
                $('#guardian_mobile').prop('disabled', false);
            } else if (rel === 'mother'){
                $('#guardian_name').val($('#mother_name').val()).prop('disabled', true);
                $('#guardian_mobile').prop('disabled', false);
            } else {
                $('#guardian_name').prop('disabled', false);
                $('#guardian_mobile').prop('disabled', false);
            }
        }
        $('#guardian_relation, #father_name, #mother_name').on('change keyup', refreshGuardianFields);
        refreshGuardianFields();
    });
</script>
</body>
</html>
