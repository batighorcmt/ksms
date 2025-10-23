<?php
require_once '../config.php';
require_once __DIR__ . '/inc/enrollment_helpers.php';

// Helper: check if student_subjects has academic_year_id column
$ss_has_year = false;
try {
    $colChk = $pdo->query("SHOW COLUMNS FROM student_subjects LIKE 'academic_year_id'");
    $ss_has_year = $colChk && $colChk->fetch() ? true : false;
} catch (Exception $e) {
    $ss_has_year = false;
}

// Helper: check if academic_years has a 'name' column (schema varies across envs)
$ay_has_name = false;
try {
    $ayChk = $pdo->query("SHOW COLUMNS FROM academic_years LIKE 'name'");
    $ay_has_name = $ayChk && $ayChk->fetch() ? true : false;
} catch (Exception $e) {
    $ay_has_name = false;
}

// Academic year selection
$selected_year_id = isset($_GET['year_id']) ? (int)$_GET['year_id'] : (current_academic_year_id($pdo) ?: null);
$years = [];
try {
    if ($ay_has_name) {
        $years = $pdo->query("SELECT id, name, year, is_current FROM academic_years ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $years = $pdo->query("SELECT id, year, is_current FROM academic_years ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Exception $e) {
    $years = [];
}

// Ensure we always have a selected year for display and operations
if (empty($selected_year_id)) {
    // Prefer the one marked current in the loaded list
    foreach ($years as $y) {
        if (!empty($y['is_current'])) { $selected_year_id = (int)$y['id']; break; }
    }
    // Fallback to first available (latest by id desc)
    if (empty($selected_year_id) && !empty($years)) {
        $selected_year_id = (int)$years[0]['id'];
    }
}

// Determine student id from GET (from students.php) or from search
$searched_student_id = '';
if (isset($_GET['student_id']) && trim($_GET['student_id']) !== '') {
    $searched_student_id = trim($_GET['student_id']);
} elseif (isset($_GET['search_student_id']) && trim($_GET['search_student_id']) !== '') {
    $searched_student_id = trim($_GET['search_student_id']);
}

$selected_student = null;            // row from students
$enrollment = null;                  // row from students_enrollment for selected year
$class_subjects = [];                // subjects for the (year-aware) class
$assigned_subjects = [];             // already assigned subject ids
$class_id_for_year = null;           // year-aware class id

if ($searched_student_id !== '') {
    // Base student (no legacy class join)
    $stmt = $pdo->prepare("SELECT st.* FROM students st WHERE st.student_id = ?");
    $stmt->execute([$searched_student_id]);
    $selected_student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selected_student) {
        // Try find enrollment for selected year
        if ($selected_year_id && function_exists('enrollment_table_exists') && enrollment_table_exists($pdo)) {
            $enq = $pdo->prepare("SELECT se.class_id, se.section_id, se.roll_number, se.academic_year_id, c.name AS class_name, sc.name AS section_name
                                   FROM students_enrollment se
                                   JOIN classes c ON c.id = se.class_id
                                   LEFT JOIN sections sc ON sc.id = se.section_id
                                   WHERE se.student_id = ? AND se.academic_year_id = ? LIMIT 1");
            $enq->execute([(int)$selected_student['id'], (int)$selected_year_id]);
            $enrollment = $enq->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        // Fallback to latest enrollment if specific year not found
        if (!$enrollment && function_exists('enrollment_table_exists') && enrollment_table_exists($pdo)) {
            $enq = $pdo->prepare("SELECT se.class_id, se.section_id, se.roll_number, se.academic_year_id, c.name AS class_name, sc.name AS section_name
                                   FROM students_enrollment se
                                   JOIN classes c ON c.id = se.class_id
                                   LEFT JOIN sections sc ON sc.id = se.section_id
                                   WHERE se.student_id = ?
                                   ORDER BY se.academic_year_id DESC
                                   LIMIT 1");
            $enq->execute([(int)$selected_student['id']]);
            $enrollment = $enq->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $class_id_for_year = $enrollment && !empty($enrollment['class_id']) ? (int)$enrollment['class_id'] : 0;

        // Resolve display year name: prefer enrollment's academic_year_id, else selected_year_id, else current
        $display_year_id = null;
        if ($enrollment && !empty($enrollment['academic_year_id'])) {
            $display_year_id = (int)$enrollment['academic_year_id'];
        } elseif (!empty($selected_year_id)) {
            $display_year_id = (int)$selected_year_id;
        } else {
            try {
                $cur = $pdo->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if (!empty($cur['id'])) $display_year_id = (int)$cur['id'];
            } catch (Exception $e) { /* ignore */ }
        }

        $display_year_name = '';
        if (!empty($display_year_id)) {
            if ($ay_has_name) {
                $yrs = $pdo->prepare("SELECT name, year FROM academic_years WHERE id = ? LIMIT 1");
            } else {
                $yrs = $pdo->prepare("SELECT year FROM academic_years WHERE id = ? LIMIT 1");
            }
            $yrs->execute([$display_year_id]);
            $yr = $yrs->fetch(PDO::FETCH_ASSOC);
            if ($yr) { $display_year_name = $yr['name'] ?? ($yr['year'] ?? ''); }
        }

        // Load class subjects for resolved class
        if ($class_id_for_year) {
            $cs = $pdo->prepare("SELECT s.id, s.name
                                  FROM subjects s
                                  JOIN class_subjects cs ON cs.subject_id = s.id
                                  WHERE cs.class_id = ? AND s.status = 'active'
                                  ORDER BY cs.numeric_value, s.name");
            $cs->execute([$class_id_for_year]);
            $class_subjects = $cs->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        // Already assigned subjects (year-aware when column exists)
        if (!empty($selected_student['id'])) {
            if ($ss_has_year && $selected_year_id) {
                $assigned_stmt = $pdo->prepare("SELECT subject_id FROM student_subjects WHERE student_id = ? AND (academic_year_id = ? OR academic_year_id IS NULL)");
                $assigned_stmt->execute([(int)$selected_student['id'], (int)$selected_year_id]);
            } else {
                $assigned_stmt = $pdo->prepare("SELECT subject_id FROM student_subjects WHERE student_id = ?");
                $assigned_stmt->execute([(int)$selected_student['id']]);
            }
            $assigned_subjects = array_map('intval', array_column($assigned_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'subject_id'));
            // Restrict to subjects available in this class
            if (!empty($class_subjects) && !empty($assigned_subjects)) {
                $class_sub_ids = array_map('intval', array_column($class_subjects, 'id'));
                $assigned_subjects = array_values(array_intersect($assigned_subjects, $class_sub_ids));
            }
        }

        // If no assigned subjects exist (after restriction), select all by default
        if (empty($assigned_subjects) && !empty($class_subjects)) {
            $assigned_subjects = array_map('intval', array_column($class_subjects, 'id'));
        }
    }
}

// Handle form submission (save assignments)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = (int)($_POST['student_id']);               // students.id
    $subjects   = isset($_POST['subjects']) && is_array($_POST['subjects']) ? array_map('intval', $_POST['subjects']) : [];
    $post_year  = isset($_POST['year_id']) ? (int)$_POST['year_id'] : ($selected_year_id ?: null);

    // Resolve class for the posted year (prefer enrollment)
    $post_class_id = null;
    if ($post_year && function_exists('enrollment_table_exists') && enrollment_table_exists($pdo)) {
        $st = $pdo->prepare("SELECT class_id FROM students_enrollment WHERE student_id = ? AND academic_year_id = ? LIMIT 1");
        $st->execute([$student_id, $post_year]);
        $post_class_id = $st->fetchColumn();
    }
    if (!$post_class_id && function_exists('enrollment_table_exists') && enrollment_table_exists($pdo)) {
        // Fallback to latest enrollment class if specific year not found
        $st = $pdo->prepare("SELECT class_id FROM students_enrollment WHERE student_id = ? ORDER BY academic_year_id DESC LIMIT 1");
        $st->execute([$student_id]);
        $post_class_id = $st->fetchColumn();
    }

    // Delete old assignments for the scope
    if ($ss_has_year && $post_year) {
        // Remove year-specific rows for this year, and also remove any NULL-year (global) rows for this class to avoid overlap
        $del = $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ? AND academic_year_id = ?");
        $del->execute([$student_id, $post_year]);
        if ($post_class_id) {
            $delNull = $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ? AND class_id = ? AND academic_year_id IS NULL");
            $delNull->execute([$student_id, (int)$post_class_id]);
        }
    } else {
        $del = $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?");
        $del->execute([$student_id]);
    }

    // Insert new assignments
    if (!empty($subjects) && $post_class_id) {
        if ($ss_has_year && $post_year) {
            $ins = $pdo->prepare("INSERT INTO student_subjects (student_id, class_id, subject_id, academic_year_id) VALUES (?,?,?,?)");
            foreach ($subjects as $subject_id) {
                $ins->execute([$student_id, (int)$post_class_id, (int)$subject_id, (int)$post_year]);
            }
        } else {
            $ins = $pdo->prepare("INSERT INTO student_subjects (student_id, class_id, subject_id) VALUES (?,?,?)");
            foreach ($subjects as $subject_id) {
                $ins->execute([$student_id, (int)$post_class_id, (int)$subject_id]);
            }
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
                <select name="year_id" class="form-control" style="min-width:200px;">
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo (int)$y['id']; ?>" <?php echo (!empty($selected_year_id) && (int)$selected_year_id === (int)$y['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($y['name'] ?? ($y['year'] ?? ('Year '.$y['id']))); ?><?php echo !empty($y['is_current']) ? ' (বর্তমান)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                    <p style="margin-bottom:0.2rem"><span style="color:#6366f1;font-weight:600"><i class="fas fa-chalkboard"></i> ক্লাস:</span>
                        <?php
                        $display_class = $enrollment && !empty($enrollment['class_name']) ? $enrollment['class_name'] : '';
                        $display_section = $enrollment && !empty($enrollment['section_name']) ? (' - ' . $enrollment['section_name']) : '';
                        echo htmlspecialchars($display_class . $display_section);
                        ?>
                    </p>
                    <p style="margin-bottom:0.2rem"><span style="color:#6366f1;font-weight:600"><i class="fas fa-calendar"></i> শিক্ষাবর্ষ:</span>
                        <?php echo htmlspecialchars($display_year_name ?: '-'); ?>
                    </p>
                    <p style="margin-bottom:0.2rem"><span style="color:#6366f1;font-weight:600"><i class="fas fa-id-badge"></i> আইডি:</span> <?php echo htmlspecialchars($selected_student['student_id']); ?></p>
                </div>
            </div>
            <form method="post">
                <input type="hidden" name="student_id" value="<?php echo $selected_student['id']; ?>">
                <?php if ($selected_year_id): ?>
                    <input type="hidden" name="year_id" value="<?php echo (int)$selected_year_id; ?>">
                <?php endif; ?>
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
