<?php
require_once '../config.php';

// Authentication check
if (!isAuthenticated() || !hasRole(['super_admin', 'teacher'])) {
    redirect('../login.php');
}

// ডিফল্ট মান সেট করুন
$current_month = date('m');
$current_year = date('Y');
$months = [
    '01' => 'জানুয়ারি', '02' => 'ফেব্রুয়ারি', '03' => 'মার্চ',
    '04' => 'এপ্রিল', '05' => 'মে', '06' => 'জুন',
    '07' => 'জুলাই', '08' => 'আগস্ট', '09' => 'সেপ্টেম্বর',
    '10' => 'অক্টোবর', '11' => 'নভেম্বর', '12' => 'ডিসেম্বর'
];

// স্কুল তথ্য লোড করুন
$school_info = $pdo->query("SELECT * FROM school_info WHERE id = 1")->fetch();

// প্রধান শিক্ষকের তথ্য লোড করুন
$head_teacher_query = $pdo->query("SELECT u.first_name, u.last_name, tp.designation FROM users u JOIN teacher_profiles tp ON u.id = tp.teacher_id WHERE u.role = 'head_teacher' LIMIT 1");
$head_teacher_info = $head_teacher_query->fetch();

// ক্লাস এবং সেকশন লোড করুন
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY name ASC")->fetchAll();
$sections = [];

// সাপ্তাহিক ছুটির দিনগুলো লোড করুন
$weekly_holidays = $pdo->query("SELECT day_number FROM weekly_holidays WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN);

// সাধারণ ছুটির দিনগুলো লোড করুন
$holidays = $pdo->query("SELECT * FROM holidays WHERE status='active'")->fetchAll();
$holiday_dates = array_column($holidays, 'date');

// ভেরিয়েবল ডিক্লেয়ারেশন
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$section_id = isset($_GET['section_id']) ? $_GET['section_id'] : '';
$selected_month = isset($_GET['month']) ? $_GET['month'] : $current_month;
$selected_year = isset($_GET['year']) ? $_GET['year'] : $current_year;
$is_print_view = isset($_GET['print_view']);

$students = [];
$attendance_records = [];
$monthly_stats = [];
$total_working_days = 0;

if ($class_id && $section_id && $selected_month && $selected_year) {
    // নির্বাচিত ক্লাসের সেকশন লোড করুন
    $sections_query = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? AND status='active'");
    $sections_query->execute([$class_id]);
    $sections = $sections_query->fetchAll();

    // শিক্ষার্থীর তালিকা লোড করুন
    $students_query = $pdo->prepare("SELECT id, first_name, last_name, roll_no FROM students WHERE class_id = ? AND section_id = ? AND status='active' ORDER BY roll_no ASC");
    $students_query->execute([$class_id, $section_id]);
    $students = $students_query->fetchAll();

    // মাসিক উপস্থিতির রেকর্ড লোড করুন
    $attendance_query = $pdo->prepare("SELECT student_id, date, status FROM attendance WHERE class_id = ? AND section_id = ? AND MONTH(date) = ? AND YEAR(date) = ?");
    $attendance_query->execute([$class_id, $section_id, $selected_month, $selected_year]);
    $attendance_records = $attendance_query->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // মাসিক পরিসংখ্যান গণনা করুন
    $num_days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
    for ($i = 1; $i <= $num_days_in_month; $i++) {
        $date = $selected_year . '-' . $selected_month . '-' . sprintf('%02d', $i);
        $day_of_week = date('w', strtotime($date));
        $is_working_day = !in_array($day_of_week, $weekly_holidays) && !in_array($date, $holiday_dates);
        if ($is_working_day) {
            $total_working_days++;
        }
    }

    foreach ($students as $student) {
        $present_days = 0;
        $absent_days = 0;
        $late_days = 0;

        for ($i = 1; $i <= $num_days_in_month; $i++) {
            $date = $selected_year . '-' . $selected_month . '-' . sprintf('%02d', $i);
            $record = isset($attendance_records[$student['id']]) ? array_filter($attendance_records[$student['id']], function($item) use ($date) {
                return $item['date'] == $date;
            }) : [];

            if (!empty($record)) {
                $status = reset($record)['status'];
                if ($status == 'present') {
                    $present_days++;
                } elseif ($status == 'absent') {
                    $absent_days++;
                } elseif ($status == 'late') {
                    $late_days++;
                }
            } else {
                // যদি এই দিনে কোনো রেকর্ড না থাকে এবং এটি কর্মদিবস হয়, তাহলে অনুপস্থিত হিসেবে গণ্য করা হবে
                $day_of_week = date('w', strtotime($date));
                if (!in_array($day_of_week, $weekly_holidays) && !in_array($date, $holiday_dates)) {
                     $absent_days++;
                }
            }
        }
        $monthly_stats[$student['id']] = [
            'present' => $present_days,
            'absent' => $absent_days,
            'late' => $late_days,
            'working_days' => $total_working_days,
            'percentage' => $total_working_days > 0 ? round(($present_days / $total_working_days) * 100, 2) : 0,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>মাসিক উপস্থিতি</title>
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tiro+Bangla&display=swap">
    <style>
        body {
            font-family: 'Tiro Bangla', sans-serif;
        }
        .school-header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }
        .school-header h1 {
            font-size: 2.5rem;
            margin: 0;
        }
        .school-header p {
            margin: 0;
            font-size: 1.1rem;
        }
        .school-logo {
            position: absolute;
            left: 0;
            top: 0;
            width: 100px; /* লোগোর আকার পরিবর্তন করুন */
        }
        .signature-area {
            text-align: right;
            margin-top: 50px;
            padding-right: 20px;
        }
        .signature-area h4 {
            margin: 0;
        }
        .signature-area p {
            margin: 0;
            font-style: italic;
        }
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php if (!$is_print_view): ?>
    <!-- Navbar -->
    <?php include 'inc/header.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php include 'inc/sidebar.php'; ?>
    <!-- /.Main Sidebar Container -->
    <?php endif; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header no-print">
                        <h3 class="card-title">মাসিক উপস্থিতি প্রতিবেদন</h3>
                    </div>
                    <div class="card-body">
                        <!-- প্রতিষ্ঠানের নাম ও ঠিকানা -->
                        <div class="school-header">
                            <?php if (!empty($school_info['logo'])): ?>
                                <img src="<?php echo htmlspecialchars($school_info['logo']); ?>" alt="School Logo" class="school-logo">
                            <?php endif; ?>
                            <h1><?php echo htmlspecialchars($school_info['name']); ?></h1>
                            <p><?php echo htmlspecialchars($school_info['address']); ?></p>
                            <p>যোগাযোগ: <?php echo htmlspecialchars($school_info['phone']); ?> | ইমেইল: <?php echo htmlspecialchars($school_info['email']); ?></p>
                        </div>
                        <hr class="no-print">
                        <!-- ফর্ম -->
                        <form method="GET" class="no-print">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="class_id">শ্রেণী নির্বাচন করুন:</label>
                                        <select name="class_id" id="class_id" class="form-control" required>
                                            <option value="">নির্বাচন করুন</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>" <?php echo ($class['id'] == $class_id) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($class['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="section_id">শাখা নির্বাচন করুন:</label>
                                        <select name="section_id" id="section_id" class="form-control" required>
                                            <option value="">নির্বাচন করুন</option>
                                            <?php
                                                if ($class_id) {
                                                    $sections_query = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? AND status='active'");
                                                    $sections_query->execute([$class_id]);
                                                    $sections = $sections_query->fetchAll();
                                                    foreach ($sections as $section) {
                                                        echo '<option value="' . $section['id'] . '"' . (($section['id'] == $section_id) ? 'selected' : '') . '>' . htmlspecialchars($section['name']) . '</option>';
                                                    }
                                                }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="month">মাস নির্বাচন করুন:</label>
                                        <select name="month" id="month" class="form-control" required>
                                            <?php foreach ($months as $key => $value): ?>
                                                <option value="<?php echo $key; ?>" <?php echo ($key == $selected_month) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($value); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="year">বছর নির্বাচন করুন:</label>
                                        <input type="number" name="year" id="year" class="form-control" value="<?php echo htmlspecialchars($selected_year); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">রিপোর্ট দেখুন</button>
                            <?php if (!empty($students)): ?>
                            <a href="?<?php echo http_build_query($_GET); ?>&print_view=1" target="_blank" class="btn btn-secondary ml-2">প্রিন্ট করুন</a>
                            <?php endif; ?>
                        </form>
                        <hr>
                        <?php if (!empty($students)): ?>
                        <div class="card-header border-0 mt-4 text-center">
                            <h3 class="card-title">
                                <strong>মাসিক উপস্থিতি প্রতিবেদন - <?php echo htmlspecialchars($months[$selected_month]); ?>, <?php echo htmlspecialchars($selected_year); ?></strong>
                            </h3><br>
                            <h5 class="card-title">
                                <strong>শ্রেণী:</strong> <?php echo htmlspecialchars($classes[array_search($class_id, array_column($classes, 'id'))]['name']); ?>, 
                                <strong>শাখা:</strong> <?php echo htmlspecialchars($sections[array_search($section_id, array_column($sections, 'id'))]['name']); ?>
                            </h5>
                        </div>
                        <div class="table-responsive p-0">
                            <table class="table table-bordered text-center">
                                <thead>
                                    <tr>
                                        <th rowspan="2">ক্রমিক নং</th>
                                        <th rowspan="2">শিক্ষার্থীর নাম</th>
                                        <th rowspan="2">রোল</th>
                                        <th colspan="<?php echo $num_days_in_month; ?>">মাসিক উপস্থিতি</th>
                                        <th rowspan="2">উপস্থিতি (দিন)</th>
                                        <th rowspan="2">অনুপস্থিতি (দিন)</th>
                                        <th rowspan="2">উপস্থিতির হার (%)</th>
                                    </tr>
                                    <tr>
                                        <?php for ($i = 1; $i <= $num_days_in_month; $i++): ?>
                                            <?php
                                                $date = $selected_year . '-' . $selected_month . '-' . sprintf('%02d', $i);
                                                $day_of_week = date('w', strtotime($date));
                                                $is_holiday = in_array($day_of_week, $weekly_holidays) || in_array($date, $holiday_dates);
                                                $day_name = date('D', strtotime($date));
                                            ?>
                                            <th class="<?php echo $is_holiday ? 'bg-secondary' : ''; ?>">
                                                <?php echo $i; ?><br><?php echo $day_name; ?>
                                            </th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td class="text-left"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['roll_no']); ?></td>
                                        <?php for ($d = 1; $d <= $num_days_in_month; $d++): ?>
                                            <?php
                                                $date = $selected_year . '-' . $selected_month . '-' . sprintf('%02d', $d);
                                                $day_of_week = date('w', strtotime($date));
                                                $is_holiday = in_array($day_of_week, $weekly_holidays) || in_array($date, $holiday_dates);
                                                $status = 'n/a';
                                                if (!$is_holiday) {
                                                    $record = isset($attendance_records[$student['id']]) ? array_filter($attendance_records[$student['id']], function($item) use ($date) {
                                                        return $item['date'] == $date;
                                                    }) : [];
                                                    if (!empty($record)) {
                                                        $status = reset($record)['status'];
                                                    } else {
                                                        $status = 'absent';
                                                    }
                                                }
                                                $status_class = '';
                                                switch ($status) {
                                                    case 'present': $status_class = 'bg-success'; break;
                                                    case 'absent': $status_class = 'bg-danger'; break;
                                                    case 'late': $status_class = 'bg-warning'; break;
                                                    default: break;
                                                }
                                            ?>
                                            <td class="<?php echo $status_class; ?>">
                                                <?php echo ($status == 'present') ? 'P' : (($status == 'absent') ? 'A' : (($status == 'late') ? 'L' : 'H')); ?>
                                            </td>
                                        <?php endfor; ?>
                                        <td><?php echo $monthly_stats[$student['id']]['present']; ?></td>
                                        <td><?php echo $monthly_stats[$student['id']]['absent']; ?></td>
                                        <td><?php echo $monthly_stats[$student['id']]['percentage']; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-12 text-center my-3">
                                <small>P = উপস্থিত, A = অনুপস্থিত, L = বিলম্ব, H = ছুটির দিন</small>
                                <p>মোট কর্মদিবস: <?php echo $total_working_days; ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 text-right mt-5">
                                <?php if ($head_teacher_info): ?>
                                <div class="signature-area">
                                    <br>
                                    <hr style="width: 200px; border-top: 1px dashed #000; margin: 0 0 5px auto;">
                                    <strong><?php echo htmlspecialchars($head_teacher_info['first_name'] . ' ' . $head_teacher_info['last_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($head_teacher_info['designation']); ?></small><br>
                                    <small>স্বাক্ষর</small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                অনুগ্রহ করে শ্রেণী, শাখা, মাস এবং বছর নির্বাচন করে রিপোর্ট দেখুন।
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php if (!$is_print_view): ?>
    <!-- Main Footer -->
    <?php include 'inc/footer.php'; ?>
    <?php endif; ?>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
    $(document).ready(function() {
        // Load sections when class is selected
        $('#class_id').change(function() {
            var class_id = $(this).val();
            if (class_id) {
                $.ajax({
                    url: 'get_sections.php',
                    type: 'GET',
                    data: {class_id: class_id},
                    success: function(data) {
                        $('#section_id').html(data);
                    }
                });
            } else {
                $('#section_id').html('<option value="">নির্বাচন করুন</option>');
            }
        });

        // Auto-submit form when in print view
        <?php if ($is_print_view): ?>
            window.print();
        <?php endif; ?>
    });
</script>
</body>
</html>
