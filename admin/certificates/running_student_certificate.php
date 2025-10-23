<?php

require_once '../../config.php';
// Encryption secret key (should be in config.php ideally)
// Removed encryption logic for student ID


// Auth
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('../../index.php');
}


// Check if student id is provided
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'শিক্ষার্থী আইডি প্রদান করা হয়নি বা সঠিক নয়।';
    redirect('admin/certificates/print_certificate_options.php');
}

        $student_id = intval($_GET['id']);
        // Removed redundant fetch block

// Resolve academic year: GET > certificate_number lookup > current year > latest enrollment
$year_id = null;
if (!empty($_GET['academic_year_id']) && ctype_digit((string)$_GET['academic_year_id'])) {
    $year_id = (int)$_GET['academic_year_id'];
} elseif (!empty($_GET['certificate_number'])) {
    // Guard: some deployments may not have academic_year_id on certificate_issues yet
    $ciHasYear = false;
    try {
        $ciCols = $pdo->query("SHOW COLUMNS FROM certificate_issues")->fetchAll(PDO::FETCH_COLUMN);
        $ciHasYear = in_array('academic_year_id', $ciCols);
    } catch (Exception $e) {
        $ciHasYear = false;
    }
    if ($ciHasYear) {
        $yr = $pdo->prepare('SELECT academic_year_id FROM certificate_issues WHERE certificate_number = ? LIMIT 1');
        $yr->execute([$_GET['certificate_number']]);
        $yrRow = $yr->fetch(PDO::FETCH_ASSOC);
        if ($yrRow && !empty($yrRow['academic_year_id'])) $year_id = (int)$yrRow['academic_year_id'];
    }
}
if (empty($year_id)) {
    $cy = $pdo->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($cy && !empty($cy['id'])) $year_id = (int)$cy['id'];
}

// Build enrollment-aware student fetch
if (!empty($year_id)) {
    $stmt = $pdo->prepare("SELECT s.*, se.roll_number, se.status AS enrollment_status, c.name AS class_name, sec.name AS section_name\n                           FROM students s\n                           LEFT JOIN students_enrollment se ON se.student_id = s.id AND se.academic_year_id = ?\n                           LEFT JOIN classes c ON c.id = se.class_id\n                           LEFT JOIN sections sec ON sec.id = se.section_id\n                           WHERE s.id = ?\n                           LIMIT 1");
    $stmt->execute([$year_id, $student_id]);
} else {
    // fallback to latest enrollment row if year not resolved
    $stmt = $pdo->prepare("SELECT s.*, se.roll_number, se.status AS enrollment_status, c.name AS class_name, sec.name AS section_name\n                           FROM students s\n                           LEFT JOIN students_enrollment se ON se.student_id = s.id\n                             AND se.academic_year_id = (SELECT MAX(se2.academic_year_id) FROM students_enrollment se2 WHERE se2.student_id = s.id)\n                           LEFT JOIN classes c ON c.id = se.class_id\n                           LEFT JOIN sections sec ON sec.id = se.section_id\n                           WHERE s.id = ?\n                           LIMIT 1");
    $stmt->execute([$student_id]);
}
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = 'শিক্ষার্থী খুঁজে পাওয়া যায়নি।';
    redirect('admin/certificates/print_certificate_options.php');
}

// Prevent certificate for inactive enrollments when available
if (isset($student['enrollment_status']) && $student['enrollment_status'] === 'inactive') {
    $_SESSION['error'] = 'নিষ্ক্রিয় শিক্ষার্থীর প্রত্যয়নপত্র প্রদান করা যাবে না।';
    redirect('admin/certificates/print_certificate_options.php');
}

// Generate verification URL for QR code
$qrCodeData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='; // 1x1 px fallback
if (!empty($student['student_id'])) {
    $verification_url = BASE_URL . "admin/certificates/verify_certificate.php?id=" . $student['student_id'];
    $qrLibPath = __DIR__ . '/../../assets/phpqrcode/qrlib.php';
    if (!empty($verification_url) && file_exists($qrLibPath)) {
        include_once $qrLibPath;
        if (class_exists('QRcode')) {
            ob_start();
            QRcode::png($verification_url, null, QR_ECLEVEL_L, 3, 1);
            $qrImage = ob_get_clean();
            if (!empty($qrImage)) {
                $qrCodeData = 'data:image/png;base64,' . base64_encode($qrImage);
            }
        }
    }
}

// Fetch school information
$school_info = $pdo->query("SELECT * FROM school_info LIMIT 1")->fetch();
if (!$school_info) {
    $school_info = [
        'name' => 'আমাদের স্কুল',
        'address' => 'স্কুলের ঠিকানা',
        'phone' => '০১XXXXXXXXX',
        'email' => 'school@example.com'
    ];
}

// Set current date in Bengali
$months_bn = [
    'জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন',
    'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'
];
$current_date = date('d') . ' ' . $months_bn[date('n')-1] . ' ' . date('Y');

// Helper function to convert English digits to Bengali
function bn_digits(
    $str
) {
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
    return str_replace($en, $bn, $str);
}

// format datetime to Bangla date string
function format_bangla_datetime($dt) {
    global $months_bn;
    if (empty($dt)) return '';
    $ts = strtotime($dt);
    if ($ts === false) return '';
    return bn_digits(date('d', $ts)) . ' ' . $months_bn[date('n', $ts)-1] . ' ' . bn_digits(date('Y', $ts));
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>শিক্ষার্থী প্রত্যয়নপত্র - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'SolaimanLipi', 'Siyam Rupali', Arial, sans-serif;
            background: #f5f5f5;
            color: #000;
            line-height: 1.6;
        }
        .certificate-container {
            display: flex;
            flex-direction: column;
            max-width: 210mm;
            min-height: 297mm;
            margin: 10px auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: relative;
            padding: 12mm 10mm 30mm 10mm; /* Increased bottom padding for footer */
            page-break-after: avoid;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px double #000;
            padding-bottom: 3px;
            margin-bottom: 12px;
            position: relative;
            z-index: 2;
        }
        .school-logo {
            margin-left: 0;
            margin-right: 0;
            padding-left: 0;
            display: flex;
            align-items: center;
        }
        .school-logo img {
            max-height: 60px;
            width: auto;
            margin-left: 0;
            margin-right: 0;
            vertical-align: middle;
        }
        .school-info {
            flex: 1;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .school-name {
            display: inline-block;
            font-size: 28px;
            font-weight: bold;
            color: #006400;
        }
        .school-address {
            font-size: 16px;
            color: #333;
        }
        .school-contact {
            font-size: 14px;
            color: #666;
        }
        .certificate-title {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            margin: 18px 0 6px; /* increased top margin and reduced bottom to give underline room */
            color: #000;
            position: relative;
            z-index: 2;
        }
        /* underline only the inner word */
        .certificate-title .title-text {
            display: inline-block;
            padding-bottom: 6px;
            border-bottom: 2px solid #000;
        }
        .content {
            position: relative;
            z-index: 2;
            font-size: 15px;
            text-align: justify;
        }
        .student-info {
            margin: 12px 0;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .info-row {
            display: flex;
            margin-bottom: 5px;
            padding: 2px 0;
        }
        .info-label {
            width: 200px;
            font-weight: bold;
            color: #333;
        }
        .info-value {
            flex: 1;
            color: #000;
        }
        .declaration {
            margin: 10px 0;
            font-size: 14px;
            line-height: 1.5;
        }
        .signature-area {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .signature-box {
            text-align: center;
            flex: 1;
        }
        .signature-line {
            width: 120px;
            height: 1px;
            background: #000;
            margin: 20px auto 8px;
        }
        .signature-name {
            font-weight: bold;
            margin-bottom: 3px;
        }
        .signature-title {
            font-size: 13px;
            color: #666;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 6px;
            margin-top: auto;
            position: sticky;
            bottom: 0;
            background: #fff;
        }
        @media print {
            .certificate-container {
                display: flex;
                flex-direction: column;
                min-height: 297mm;
                max-width: 210mm;
                box-shadow: none;
                margin: 0;
                padding: 10mm 8mm 30mm 8mm; /* Increased bottom padding for footer */
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
            }
            .footer {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                background: #fff;
                page-break-before: avoid !important;
                z-index: 999;
            }
            .print-button, .no-print {
                display: none !important;
            }
        }
        .print-button {
            text-align: center;
            margin: 20px auto;
            max-width: 210mm;
        }
        .btn-print {
            background: #006400;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'SolaimanLipi', sans-serif;
        }
        .btn-print:hover {
            background: #004d00;
        }
    </style>
</head>
<body>
    <div class="print-button no-print">
        <button class="btn-print" onclick="window.print()">
            🖨️ প্রত্যয়নপত্র প্রিন্ট করুন
        </button>
        <a href="<?php echo ADMIN_URL; ?>certificates/issued_certificates.php" style="margin-left: 15px; color: #006400;">← প্রত্যয়নপত্র তালিকায় ফিরে যান</a>
    </div>

    <?php // Manual Record & Print removed: auto-save remains via AJAX ?>

    <!-- removed manual create button and auto-create JS to avoid duplicate records on reload -->

    <div class="certificate-container">
        <?php if (!empty($school_info['logo'])): ?>
        <div style="position:absolute;left:0;top:0;width:100%;height:100%;z-index:0;display:flex;justify-content:center;align-items:center;pointer-events:none;">
            <img src='<?php echo BASE_URL; ?>uploads/logo/<?php echo htmlspecialchars($school_info['logo']); ?>' alt="Watermark Logo" style="opacity:0.13;max-width:70%;max-height:80%;margin:auto;">
        </div>
        <?php endif; ?>
        <div class="header">
            <div class="school-logo">
                <?php if (!empty($school_info['logo'])): ?>
                    <img src="<?php echo BASE_URL; ?>uploads/logo/<?php echo htmlspecialchars($school_info['logo']); ?>" alt="School Logo" style="vertical-align:middle; margin-left:0; margin-right:0; max-height:100px; width:auto;">
                <?php endif; ?>
            </div>
            <div class="school-info">
                <div class="school-name"><?php echo htmlspecialchars($school_info['name']); ?></div>
                <div class="school-address"><?php echo htmlspecialchars($school_info['address']); ?></div>
                <div class="school-contact">
                    মোবাইল: <?php echo bn_digits(htmlspecialchars($school_info['phone'] ?? '০১XXXXXXXXX')); ?> 
                    | ইমেইল: <?php echo htmlspecialchars($school_info['email'] ?? 'school@example.com'); ?>
                </div>
            </div>
            <div style="display: flex; align-items: center; margin-right:0;">
                <a href="<?php echo htmlspecialchars($verification_url); ?>" target="_blank" title="ভেরিফাই করুন">
                    <img src="<?php echo $qrCodeData; ?>" alt="QR Code" style="height:90px;width:90px;vertical-align:middle; margin-right:0;">
                </a>
            </div>
        </div>

        <?php
        // Try to display certificate number and issued date if available via GET or DB
        $display_cert_number = '';
        $display_issued_at = '';
        if (!empty($_GET['certificate_number'])) {
            $display_cert_number = $_GET['certificate_number'];
            // try to fetch issued_at from DB
            $ci = $pdo->prepare('SELECT issued_at FROM certificate_issues WHERE certificate_number = ? LIMIT 1');
            $ci->execute([$display_cert_number]);
            $ciRow = $ci->fetch(PDO::FETCH_ASSOC);
            if ($ciRow && !empty($ciRow['issued_at'])) $display_issued_at = $ciRow['issued_at'];
        }
        ?>
        <div class="certificate-details" style="display:flex;justify-content:space-between;align-items:center;margin-top:2px;margin-bottom:6px;">
            <div class="certificate-id" style="font-weight:700;">স্মারক নং: <span id="certNumberPrint"><?php echo $display_cert_number ? htmlspecialchars(bn_digits($display_cert_number)) : '' ?></span></div>
            <div class="issue-date" style="font-weight:700;">তারিখ: <span id="certDatePrint"><?php echo $display_issued_at ? htmlspecialchars(format_bangla_datetime($display_issued_at)) : '' ?></span></div>
        </div>
        <div class="certificate-title">
            <span class="title-text">প্রত্যয়নপত্র</span>
        </div>
        <div class="content">
            <div class="student-info" style="background:none !important;border:1px solid #ddd;border-radius:5px;">
                <div class="info-row">
                    <div class="info-label">শিক্ষার্থীর নাম:</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">পিতার নাম:</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['father_name'] ?? 'প্রদান করা হয়নি'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">মাতার নাম:</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['mother_name'] ?? 'প্রদান করা হয়নি'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">ঠিকানা:</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['present_address'] ?? 'প্রদান করা হয়নি'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">শ্রেণি ও শাখা:</div>
                    <div class="info-value">
                        <?php 
                        echo bn_digits(htmlspecialchars($student['class_name'] ?? 'প্রদান করা হয়নি'));
                        if (!empty($student['section_name'])) {
                            echo ' (' . bn_digits(htmlspecialchars($student['section_name'])) . ')';
                        }
                        ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">রোল নম্বর:</div>
                    <div class="info-value"><?php echo bn_digits(htmlspecialchars($student['roll_number'] ?? 'প্রদান করা হয়নি')); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">স্টুডেন্ট আইডি:</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['student_id'] ?? ''); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">জন্ম তারিখ:</div>
                    <div class="info-value"><?php echo !empty($student['date_of_birth']) ? bn_digits(date('d/m/Y', strtotime($student['date_of_birth']))) : 'প্রদান করা হয়নি'; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">লিঙ্গ:</div>
                    <div class="info-value">
                        <?php 
                        if ($student['gender'] == 'male') echo 'পুরুষ';
                        elseif ($student['gender'] == 'female') echo 'মহিলা';
                        else echo 'প্রদান করা হয়নি';
                        ?>
                    </div>
                </div>
            </div>
            <div class="declaration">
                <p>এই মর্মে প্রত্যয়ন করা যাচ্ছে যে, <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong> বর্তমানে <?php echo htmlspecialchars($school_info['name']); ?> এর <?php echo htmlspecialchars($student['class_name'] ?? ''); ?> শ্রেণির একজন নিয়মিত শিক্ষার্থী হিসেবে অধ্যয়নরত আছে।</p>
                <p style="margin-top: 8px;">সে একজন মেধাবী ও শৃংখলাবদ্ধ শিক্ষার্থী হিসেবে বিদ্যালয়ের সকলের নিকট পরিচিত। তার বিদ্যালয়ে উপস্থিতি ও আচরণ সন্তোষজনক। কোনো প্রকার শাস্তিমূলক ব্যবস্থার আওতাভুক্ত নয়।</p>
                <p style="margin-top: 8px;">সে বিদ্যালয়ের সকল নিয়ম-কানুন মেনে চলে এবং নিয়মিতভাবে ক্লাসে উপস্থিত থাকে। প্রয়োজনে যেকোনো সময় এই প্রত্যয়নপত্র যাচাই করা যাবে।</p>
            </div>
            <div style="height: 40px;"></div>
            <div class="signature-area">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-name">শ্রেণি শিক্ষক</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-name">প্রধান শিক্ষক/অধ্যক্ষ</div>
                </div>
            </div>
            
        </div>
        <div class="footer" style="margin-top:8px;padding:8px;background:#e9f2ff;color:#000;font-size:0.95rem;text-align:center;">
           কারিগরি সহযোগীতায়ঃ <strong>বাতিঘর কম্পিউটার'স</strong>, মোবাইলঃ <span style="font-weight:700">01762-396713</span>
        </div>
    </div>
</body>
</html>