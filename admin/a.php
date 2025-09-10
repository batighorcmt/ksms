<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// Get today's date for default selection
$current_date = date('Y-m-d');

// Get classes and sections
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();

// Initialize variables
$selected_class = $_GET['class_id'] ?? '';
$selected_section = $_GET['section_id'] ?? '';
$selected_date = $_GET['date'] ?? $current_date;

$sections = [];
if ($selected_class) {
    $sections = $pdo->prepare("SELECT * FROM sections WHERE class_id=? AND status='active'");
    $sections->execute([$selected_class]);
    $sections = $sections->fetchAll();
}

// Get students
$students = [];
if ($selected_class) {
    if ($selected_section) {
        // যদি section select করা হয়
        $stmt = $pdo->prepare("SELECT * FROM students WHERE class=? AND section=? AND status='active' ORDER BY roll_no ASC");
        $stmt->execute([$selected_class, $selected_section]);
    } else {
        // যদি section select না করা হয় → ওই class-এর সব section
        $stmt = $pdo->prepare("SELECT * FROM students WHERE class=? AND status='active' ORDER BY roll_no ASC");
        $stmt->execute([$selected_class]);
    }
    $students = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>উপস্থিতি সিস্টেম</title>
    <link href="../assets/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
    <h4>উপস্থিতি ব্যবস্থাপনা</h4>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-3">
            <label>শ্রেণী</label>
            <select name="class_id" class="form-control" required onchange="this.form.submit()">
                <option value="">শ্রেণী নির্বাচন করুন</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?= $class['id'] ?>" <?= $selected_class == $class['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($class['class_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label>শাখা (অইচ্ছিক)</label>
            <select name="section_id" class="form-control" onchange="this.form.submit()">
                <option value="">সব শাখা</option>
                <?php foreach ($sections as $section): ?>
                    <option value="<?= $section['id'] ?>" <?= $selected_section == $section['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($section['section_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label>তারিখ</label>
            <input type="date" name="date" value="<?= $selected_date ?>" class="form-control" onchange="this.form.submit()">
        </div>
    </form>

    <?php if ($students): ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>রোল</th>
                    <th>নাম</th>
                    <th>অবস্থা</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $st): ?>
                    <tr>
                        <td><?= htmlspecialchars($st['roll_no']) ?></td>
                        <td><?= htmlspecialchars($st['student_name']) ?></td>
                        <td>
                            <input type="radio" name="status_<?= $st['id'] ?>" value="present" checked> উপস্থিত
                            <input type="radio" name="status_<?= $st['id'] ?>" value="absent"> অনুপস্থিত
                            <input type="radio" name="status_<?= $st['id'] ?>" value="late"> দেরি
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($selected_class): ?>
        <div class="alert alert-warning">কোনো শিক্ষার্থী পাওয়া যায়নি।</div>
    <?php endif; ?>
</body>
</html>