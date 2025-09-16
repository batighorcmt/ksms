<?php
require_once '../config.php';

// Fetch all students
$students = $pdo->query("SELECT st.id, st.first_name, st.last_name, st.class_id, c.name as class_name FROM students st JOIN classes c ON st.class_id = c.id WHERE st.status = 'active' ORDER BY c.numeric_value, st.first_name, st.last_name")->fetchAll();

// Determine student id from GET (from students.php) or from search
$searched_student_id = '';
if (isset($_GET['student_id']) && trim($_GET['student_id']) !== '') {
    $searched_student_id = trim($_GET['student_id']);
} elseif (isset($_GET['search_student_id']) && trim($_GET['search_student_id']) !== '') {
    $searched_student_id = trim($_GET['search_student_id']);
}
$selected_student = null;
$class_subjects = [];
$assigned_subjects = [];
if ($searched_student_id !== '') {
    $stmt = $pdo->prepare("SELECT st.*, c.name as class_name FROM students st JOIN classes c ON st.class_id = c.id WHERE st.student_id = ?");
    $stmt->execute([$searched_student_id]);
    $selected_student = $stmt->fetch();
    if ($selected_student) {
        $class_subjects = $pdo->prepare("SELECT s.id, s.name FROM subjects s JOIN class_subjects cs ON cs.subject_id = s.id WHERE cs.class_id = ? AND s.status = 'active' ORDER BY cs.numeric_value");
        $class_subjects->execute([$selected_student['class_id']]);
        $class_subjects = $class_subjects->fetchAll();
        // Get already assigned subjects
        $assigned_stmt = $pdo->prepare("SELECT subject_id FROM student_subjects WHERE student_id = ?");
        $assigned_stmt->execute([$selected_student['id']]);
        $assigned_subjects = array_column($assigned_stmt->fetchAll(), 'subject_id');
        // If no assigned subjects, select all by default
        if (empty($assigned_subjects)) {
            $assigned_subjects = array_column($class_subjects, 'id');
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = intval($_POST['student_id']);
    $subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    // Remove old assignments
    $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?")->execute([$student_id]);
    // Insert new assignments
    if (!empty($subjects)) {
        $student = $pdo->prepare("SELECT class_id FROM students WHERE id = ?");
        $student->execute([$student_id]);
        $class_id = $student->fetchColumn();
        $insert = $pdo->prepare("INSERT INTO student_subjects (student_id, class_id, subject_id) VALUES (?, ?, ?)");
        foreach ($subjects as $subject_id) {
            $insert->execute([$student_id, $class_id, $subject_id]);
        }
    }
    // After save, redirect to students.php with success message
    $_SESSION['success'] = 'বিষয় নির্ধারণ সফলভাবে সম্পন্ন হয়েছে!';
    header("Location: students.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject Assignment</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(120deg, #f8fafc 0%, #e0e7ff 100%);
            min-height: 100vh;
        }
        .main-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(80, 112, 255, 0.08);
            padding: 2rem 2.5rem;
            margin-top: 40px;
        }
        .subject-badge {
            font-size: 1rem;
            margin: 0.2rem 0.4rem 0.2rem 0;
            padding: 0.5em 1em;
            border-radius: 20px;
            background: linear-gradient(90deg, #6366f1 0%, #60a5fa 100%);
            color: #fff;
            display: inline-block;
            transition: background 0.2s;
        }
        .subject-badge input[type=checkbox] {
            margin-right: 8px;
            accent-color: #6366f1;
        }
        .subject-badge input[type=checkbox]:checked + label {
            font-weight: bold;
        }
        .student-info {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .student-info h5 {
            color: #4f46e5;
            font-weight: 700;
        }
        .save-btn {
            background: linear-gradient(90deg, #22d3ee 0%, #6366f1 100%);
            border: none;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.6em 2em;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px rgba(99,102,241,0.08);
            transition: background 0.2s;
        }
        .save-btn:hover {
            background: linear-gradient(90deg, #6366f1 0%, #22d3ee 100%);
        }
        .select-student-label {
            font-weight: 600;
            color: #6366f1;
            font-size: 1.1rem;
        }
        .form-inline .form-control {
            min-width: 220px;
        }
        .no-subjects {
            color: #ef4444;
            font-weight: 600;
        }
        @media (max-width: 600px) {
            .main-card { padding: 1rem 0.5rem; }
            .student-info { padding: 0.7rem 0.5rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="main-card mx-auto" style="max-width: 700px;">
        <h2 class="mb-4 text-center" style="color:#4f46e5;font-weight:700;letter-spacing:1px;text-shadow:0 2px 8px #c7d2fe"><i class="fas fa-book-open"></i> বিষয় নির্ধারণ</h2>

        <?php if (!$selected_student): ?>
            <form method="get" class="mb-4 d-flex flex-column flex-md-row align-items-center justify-content-center" style="gap:1rem;">
                <label for="search_student_id" class="select-student-label mb-0"><i class="fas fa-search"></i> শিক্ষার্থী আইডি:</label>
                <input type="text" name="search_student_id" id="search_student_id" class="form-control" placeholder="উদাহরণ: STU20251234" value="<?php echo htmlspecialchars($searched_student_id); ?>" style="max-width:260px;font-size:1.1rem;font-weight:600;letter-spacing:1px;" required>
                <button type="submit" class="btn btn-info" style="font-weight:600;font-size:1.1rem;"><i class="fas fa-search"></i> খুঁজুন</button>
            </form>
        <?php endif; ?>

        <?php if ($searched_student_id && !$selected_student): ?>
            <div class="alert alert-danger text-center">শিক্ষার্থী আইডি <b><?php echo htmlspecialchars($searched_student_id); ?></b> খুঁজে পাওয়া যায়নি।</div>
        <?php endif; ?>

        <?php if ($selected_student): ?>
            <div class="student-info mb-3 d-flex align-items-center" style="gap:1.5rem;">
                <div style="flex:0 0 70px;">
                    <div style="width:70px;height:70px;border-radius:50%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;font-size:2.2rem;color:#6366f1;box-shadow:0 2px 8px #e0e7ff;">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
                <div>
                    <h5 style="margin-bottom:0.2rem;"><i class="fas fa-user"></i> <?php echo htmlspecialchars($selected_student['first_name'] . ' ' . $selected_student['last_name']); ?></h5>
                    <p style="margin-bottom:0.2rem"><span style="color:#6366f1;font-weight:600"><i class="fas fa-chalkboard"></i> ক্লাস:</span> <?php echo htmlspecialchars($selected_student['class_name']); ?></p>
                    <p style="margin-bottom:0.2rem"><span style="color:#6366f1;font-weight:600"><i class="fas fa-id-badge"></i> আইডি:</span> <?php echo htmlspecialchars($selected_student['student_id']); ?></p>
                </div>
            </div>
            <form method="post">
                <input type="hidden" name="student_id" value="<?php echo $selected_student['id']; ?>">
                <div class="form-group mb-4">
                    <label style="font-weight:600;color:#6366f1;font-size:1.1rem"><i class="fas fa-book"></i> বিষয়সমূহ:</label>
                    <?php if ($class_subjects): ?>
                        <div class="row" style="max-width:400px;">
                            <?php foreach ($class_subjects as $subject): ?>
                                <div class="col-12 mb-2">
                                    <div class="custom-control custom-checkbox" style="font-size:1.13rem;font-family:'Segoe UI',Arial,sans-serif;">
                                        <input type="checkbox" class="custom-control-input" name="subjects[]" value="<?php echo $subject['id']; ?>" id="subject_<?php echo $subject['id']; ?>" <?php if (in_array($subject['id'], $assigned_subjects)) echo 'checked'; ?>>
                                        <label class="custom-control-label" for="subject_<?php echo $subject['id']; ?>" style="font-weight:600;letter-spacing:0.5px;cursor:pointer;color:#3730a3;background:rgba(99,102,241,0.07);padding:0.4em 1em;border-radius:8px;box-shadow:0 1px 4px #e0e7ff;">
                                            <i class="fas fa-book"></i> <?php echo htmlspecialchars($subject['name']); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span class="no-subjects"><i class="fas fa-exclamation-circle"></i> এই ক্লাসের জন্য কোনো বিষয় পাওয়া যায়নি।</span>
                    <?php endif; ?>
                </div>
                <div class="text-center">
                    <button type="submit" class="save-btn" style="font-size:1.15rem;padding:0.7em 2.5em;"><i class="fas fa-save"></i> সংরক্ষণ করুন</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
