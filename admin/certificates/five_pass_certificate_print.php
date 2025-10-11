<?php
// Individual certificate print page
require_once '../../config.php';
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
    redirect('../../index.php');
}

// Student ID (not primary key)
$student_id = isset($_GET['id']) ? trim($_GET['id']) : '';
if (!$student_id) {
    echo '<div style="padding:40px;text-align:center;font-size:20px;">শিক্ষার্থী নির্ধারিত নেই!</div>';
    exit;
}

// Fetch student and certificate info by student_id
$stmt = $pdo->prepare("SELECT s.id, s.student_id, s.first_name, s.last_name, s.father_name, s.mother_name, s.date_of_birth, s.roll_number, s.class_id, s.year_id, s.photo, c.gpa, c.certificate_id, c.issue_date, c.exam_year FROM students s JOIN five_pass_certificate_info c ON s.id = c.student_id WHERE s.student_id = ?");
$stmt->execute([$student_id]);
$stu = $stmt->fetch();
if (!$stu) {
    echo '<div style="padding:40px;text-align:center;font-size:20px;">সার্টিফিকেট তথ্য পাওয়া যায়নি!</div>';
    exit;
}

// Fetch class name
$class_name = '';
$class_stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
$class_stmt->execute([$stu['class_id']]);
$class_row = $class_stmt->fetch();
if ($class_row) $class_name = $class_row['name'];

// Fetch school info
$school = $pdo->query("SELECT * FROM school_info LIMIT 1")->fetch();

$school_name = $school['name'] ?? 'বিদ্যালয়';
$school_address = $school['address'] ?? '';
$school_mobile = $school['phone'] ?? '';
$school_email = $school['email'] ?? '';
$principal_name = $school['principal_name'] ?? 'প্রধান শিক্ষক';
$principal_designation = $school['principal_designation'] ?? 'প্রধান শিক্ষক';
$logo = !empty($school['logo']) ? '../../uploads/logo/' . $school['logo'] : '';


$logo = !empty($school['logo']) ? BASE_URL . 'uploads/logo/' . $school['logo'] : '';

// QR code generation (from running_student_certificate.php)
$qrCodeData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='; // fallback
$verification_url = '';
if (!empty($stu['certificate_id'])) {
    $verification_url = BASE_URL . "admin/certificates/verify_certificate.php?id=" . urlencode($stu['student_id']);
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

function format_bangla_date($date) {
    if (!$date) return '';
    $months = ['01'=>'জানুয়ারি','02'=>'ফেব্রুয়ারি','03'=>'মার্চ','04'=>'এপ্রিল','05'=>'মে','06'=>'জুন','07'=>'জুলাই','08'=>'আগস্ট','09'=>'সেপ্টেম্বর','10'=>'অক্টোবর','11'=>'নভেম্বর','12'=>'ডিসেম্বর'];
    $d = date('d', strtotime($date));
    $m = $months[date('m', strtotime($date))];
    $y = date('Y', strtotime($date));
    return convert_to_bangla_number("$d $m, $y");
}

function convert_to_bangla_number($str) {
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
    $bn_str = str_replace($en, $bn, $str);
    return '<span class="bangla-number">' . $bn_str . '</span>';
}

?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>প্রাথমিক বিদ্যালয় সার্টিফিকেট</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap');
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Hind Siliguri', sans-serif; 
        }
        .bangla-number {
            font-family: 'Noto Sans Bengali', sans-serif !important;
        }
        
        body { 
            background-color: #f5f5f5; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px; 
        }
        
        .certificate-container { 
            width: 794px; 
            height: 1123px; 
            background-color: #fff; 
            border: 15px double #1e5799; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
            position: relative; 
            margin-bottom: 20px; 
            overflow: hidden; 
            display: flex;
            flex-direction: column;
        }
        
        .certificate-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 400px;
            height: 400px;
            opacity: 0.08;
            z-index: 0;
            pointer-events: none;
            user-select: none;
        }
        
        .certificate-header { 
            display: flex; 
            align-items: center; 
            padding: 15px 30px; 
            border-bottom: 2px solid #d4af37; 
            margin-bottom: 10px; 
        }
        
        .school-logo {
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
        }
        .school-logo img {
            max-width: 90px;
            max-height: 90px;
        }
        
        .school-info { 
            flex: 1; 
        }
        
        .school-name { 
            font-size: 26px; 
            font-weight: 700; 
            color: #1e5799; 
            margin-bottom: 5px; 
        }
        
        .school-address { 
            font-size: 16px; 
            margin-bottom: 5px; 
        }
        
        .contact-info { 
            font-size: 14px; 
            display: flex; 
            gap: 20px; 
            flex-wrap: wrap; 
        }
        
        .certificate-details { 
            display: flex; 
            justify-content: space-between; 
            padding: 5px 30px; 
            font-size: 14px; 
            border-bottom: 1px solid #ddd; 
            margin-bottom: 10px; 
        }
        
        .certificate-id { 
            font-weight: 600; 
        }
        
        .issue-date { 
            font-weight: 600; 
        }
        
        .certificate-body { 
            padding: 0 40px; 
            text-align: center; 
        }
        
        .certificate-title { 
            font-size: 32px; 
            font-weight: 700; 
            color: #1e5799; 
            margin-bottom: 15px; 
            text-decoration: underline; 
        }
        
        .certificate-text { 
            font-size: 18px; 
            line-height: 1.6; 
            margin-bottom: 15px; 
            text-align: justify; 
        }
        
        .student-details { 
            display: flex; 
            margin: 20px 0; 
            gap: 30px; 
            flex-wrap: wrap; 
        }
        
        .student-info { 
            flex: 1; 
            text-align: left; 
            min-width: 220px; 
        }
        
        .student-photo { 
            width: 150px; 
            height: 180px; 
            background-color: #f0f0f0; 
            border: 1px solid #ddd; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 14px; 
            color: #666; 
            overflow: hidden; 
        }
        
        .student-photo img { 
            max-width: 140px; 
            max-height: 170px; 
        }
        
        .info-row { 
            display: flex; 
            margin-bottom: 8px; 
            border-bottom: 1px dashed #ddd; 
            padding-bottom: 6px; 
        }
        
        .info-label { 
            width: 160px; 
            font-weight: 600; 
        }
        
        .info-value { 
            flex: 1; 
        }
        
        .signature-section { 
            display: flex; 
            justify-content: space-between; 
            margin-top: 30px; 
            padding: 0 20px; 
            flex-wrap: wrap; 
        }
        
        .principal-signature { 
            text-align: center; 
            min-width: 220px; 
        }
        
        .signature-line { 
            width: 200px; 
            height: 1px; 
            background-color: #000; 
            margin: 40px auto 10px; 
        }
        
        .qr-section { 
            text-align: center; 
            min-width: 140px; 
        }
        
        .qr-code { 
            width: 120px; 
            height: 120px; 
            background-color: #f0f0f0; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border: 1px solid #ddd; 
            margin: 0 auto 5px; 
        }
        
        .qr-caption { 
            font-size: 12px; 
            color: #666; 
        }
        
        .certificate-footer { 
            background-color: #f9f9f9; 
            padding: 12px; 
            text-align: center; 
            font-size: 14px; 
            color: #666; 
            border-top: 1px solid #ddd; 
            margin-top: 25px; 
            position: relative;
            bottom: 0;
            left: 0;
            right: 0;
        }
        
        .technical-support { 
            font-weight: bold; 
            color: #1e5799; 
        }
        
        .stamp { 
            position: absolute; 
            bottom: 140px; 
            right: 80px; 
            width: 100px; 
            height: 100px; 
            border: 2px solid #d4af37; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            transform: rotate(-15deg); 
            opacity: 0.9; 
            background-color: rgba(255,255,255,0.8); 
        }
        
        .stamp-text { 
            font-size: 14px; 
            font-weight: bold; 
            color: #d4af37; 
            text-align: center; 
        }
        
        .print-btn { 
            background-color: #1e5799; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            font-size: 16px; 
            border-radius: 4px; 
            cursor: pointer; 
            margin-top: 20px; 
            transition: background-color 0.3s; 
            position: fixed;
            bottom: 20px;
            z-index: 1000;
        }
        
        .print-btn:hover { 
            background-color: #154274; 
        }
        
        /* Print Styles */
        @media print {
            body {
                background-color: white;
                padding: 0;
                margin: 0;
                display: block;
                height: 100%;
            }
            .certificate-container {
                box-shadow: none;
                margin: 0;
                border: 15px double #1e5799;
                width: 100%;
                height: 100%;
                position: relative;
                display: flex;
                flex-direction: column;
            }
            .certificate-footer {
                margin-top: auto !important;
                position: relative !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
            }
            .print-btn {
                display: none;
            }
            .certificate-watermark {
                opacity: 0.12;
            }
            .student-details {
                flex-direction: row !important;
                gap: 30px !important;
                align-items: flex-start !important;
                page-break-inside: avoid !important;
            }
            .student-photo {
                align-self: flex-start !important;
            }
            .signature-section {
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: flex-start !important;
                gap: 0 !important;
                page-break-inside: avoid !important;
            }
            .principal-signature, .qr-section {
                min-width: 220px;
                max-width: 50%;
            }
            @page {
                size: A4;
                margin: 0.5in;
            }
        }
        
        @media (max-width: 820px) {
            .certificate-container {
                width: 100%;
                height: auto;
                min-height: 1123px;
            }
            
            .student-details {
                flex-direction: column;
            }
            
            .student-photo {
                align-self: center;
            }
            
            .signature-section {
                flex-direction: column;
                align-items: center;
                gap: 20px;
            }
            
            .certificate-footer {
                position: relative;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <?php if ($logo): ?>
            <img src="<?php echo $logo; ?>" alt="Watermark Logo" class="certificate-watermark" />
        <?php endif; ?>
        
        <div class="certificate-header">
            <div class="school-logo">
                <?php if ($logo): ?>
                    <img src="<?php echo $logo; ?>" alt="বিদ্যালয় লোগো">
                <?php else: ?>
                    <span style="font-size: 14px; font-weight: bold; color: #1e5799;">বিদ্যালয় লোগো</span>
                <?php endif; ?>
            </div>
            <div class="school-info">
                <h1 class="school-name"><?php echo htmlspecialchars($school_name); ?></h1>
                <p class="school-address"><?php echo htmlspecialchars($school_address); ?></p>
                <div class="contact-info">
                    <span>মোবাইল: <?php echo htmlspecialchars($school_mobile); ?></span>
                    <span>ই-মেইল: <?php echo htmlspecialchars($school_email); ?></span>
                </div>
            </div>
        </div>
        
        <div class="certificate-details">
            <div class="certificate-id">সার্টিফিকেট আইডি: <?php echo htmlspecialchars($stu['certificate_id']); ?></div>
            <div class="issue-date">ইস্যুর তারিখ: <?php echo format_bangla_date($stu['issue_date']); ?></div>
        </div>
        
        <div class="certificate-body">
            <h2 class="certificate-title">প্রশংসা পত্র</h2>
            
            <p class="certificate-text">
                এই মর্মে প্রত্যয়ন করা যাচ্ছে যে, নিম্নলিখিত শিক্ষার্থী আমাদের বিদ্যালয়ের পঞ্চম শ্রেণিতে অধ্যয়ন করেছে এবং <?php echo convert_to_bangla_number($stu['exam_year']); ?> শিক্ষাবর্ষের বার্ষিক পরীক্ষায় সাফল্যের সাথে উত্তীর্ণ হয়েছে। শিক্ষার্থী বিদ্যালয়ের নিয়ম-কানুন মেনে চলেছে এবং তার আচরণ সন্তোষজনক ছিল।
            </p>
            
            <div class="student-details">
                <div class="student-info">
                    <div class="info-row">
                        <div class="info-label">শিক্ষার্থীর নাম:</div>
                        <div class="info-value"><?php echo htmlspecialchars($stu['first_name'].' '.$stu['last_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">পিতার নাম:</div>
                        <div class="info-value"><?php echo htmlspecialchars($stu['father_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">মাতার নাম:</div>
                        <div class="info-value"><?php echo htmlspecialchars($stu['mother_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">জন্ম তারিখ:</div>
                        <div class="info-value"><?php echo format_bangla_date($stu['date_of_birth']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">শ্রেণি:</div>
                        <div class="info-value">পঞ্চম</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">রোল নং:</div>
                        <div class="info-value"><?php echo convert_to_bangla_number((string)($stu['roll_number'] ?? '')); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">শিক্ষার্থী আইডি:</div>
                        <div class="info-value"><?php echo htmlspecialchars((string)($stu['student_id'] ?? '')); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">পরীক্ষার বছর:</div>
                        <div class="info-value"><?php echo convert_to_bangla_number((string)($stu['exam_year'] ?? '')); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">ফলাফল:</div>
                        <div class="info-value"><?php echo convert_to_bangla_number((string)($stu['gpa'] ?? '')); ?></div>
                    </div>
                </div>
                
                <div class="student-photo">
                    <?php if (!empty($stu['photo'])): ?>
                        <img src="../../uploads/students/<?php echo htmlspecialchars($stu['photo']); ?>" alt="ছবি">
                    <?php else: ?>
                        শিক্ষার্থীর ছবি
                    <?php endif; ?>
                </div>
            </div>
            
            <p class="certificate-text">
                শিক্ষার্থীকে ভবিষ্যত জীবনে সাফল্য ও সমৃদ্ধি কামনা করা হল। এই সার্টিফিকেটটি তার শিক্ষাগত যোগ্যতার প্রমাণ হিসেবে গণ্য হবে।
            </p>
            
            <div class="signature-section">
                <div class="principal-signature">
                    <div class="signature-line"></div>
                    <p><?php echo htmlspecialchars($principal_name); ?></p>
                    <p><?php echo htmlspecialchars($principal_designation); ?></p>
                    <p><?php echo htmlspecialchars($school_name); ?></p>
                </div>
                
                <div class="qr-section">
                    <div class="qr-code">
                        <img src="<?php echo $qrCodeData; ?>" alt="QR Code" style="width:100px;height:100px;" />
                    </div>
                    <p class="qr-caption">যাচাই করতে স্ক্যান করুন</p>
                </div>
            </div>
        </div>
        
        <div class="certificate-footer">
            <p>কারিগরি সহযোগীতায়ঃ <span class="technical-support">বাতিঘর কম্পিউটার’স</span>, মোবাইলঃ 01762-396713</p>
        </div>
    </div>
    
    <button class="print-btn" onclick="window.print()">সার্টিফিকেট প্রিন্ট করুন</button>
</body>
</html>