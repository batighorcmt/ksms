<?php
// Ensure application uses Dhaka timezone (+06:00) for printed timestamps
date_default_timezone_set('Asia/Dhaka');
// Simple include to render institute header and footer for print pages.
// Usage:
// include 'print_common.php';
// echo print_header($pdo, $title_extra);
// ... page body ...
// echo print_footer();

if (!function_exists('get_school_info')) {
    function get_school_info($pdo) {
        static $info = null;
        if ($info !== null) return $info;
        $info = $pdo->query("SELECT * FROM school_info LIMIT 1")->fetch();
        return $info ?: [];
    }
}

if (!function_exists('print_header')) {
    function print_header($pdo, $title_extra = '') {
    $si = get_school_info($pdo);
    $name = htmlspecialchars($si['name'] ?? 'আপনার প্রতিষ্ঠান');
    $address = htmlspecialchars($si['address'] ?? '');
    $contact = '';
    if (!empty($si['phone'])) $contact .= 'মোবাইল: ' . htmlspecialchars($si['phone']);
    if (!empty($si['email'])) $contact .= ($contact ? ' | ' : '') . 'ই-মেইল: ' . htmlspecialchars($si['email']);
    $logo = !empty($si['logo']) ? (BASE_URL . 'uploads/logo/' . $si['logo']) : '';

    ob_start();
    ?>
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:8px;">
        <?php if($logo): ?>
            <div style="width:90px;flex:0 0 90px; margin-left: 100px;"><img src="<?php echo $logo; ?>" style="max-width:100%;max-height:90px;object-fit:contain"/></div>
        <?php else: ?>
            <div style="width:90px;flex:0 0 90px;background:#f3f4f6;border-radius:6px;display:flex;align-items:center;justify-content:center">লোগো</div>
        <?php endif; ?>
        <div style="flex:1;text-align:center; margin-left: -100px;">
            <div style="font-weight:700;font-size:1.25rem"><?php echo $name; ?></div>
            <div style="color:#444;font-size:0.95rem"><?php echo $address; ?></div>
            <div style="color:#444;font-size:0.95rem"><?php echo $contact; ?></div>
            <?php if($title_extra): ?><div style="margin-top:6px;color:black;font-size:1.5rem"><?php echo $title_extra; ?></div><?php endif; ?>
        </div>
    </div>
    <hr style="border:none;border-top:2px solid #e6e6e6;margin:8px 0 14px 0;" />
    <?php
        return ob_get_clean();
    }
}

if (!function_exists('print_footer')) {
    function print_footer() {
        // Inline CSS: hide footer on screen, show only when printing
        // Ensure enough bottom padding so content never overlaps the fixed footer
            $styleBlock = '<style>
                /* Standardize print margins globally: 0.5 inch on all sides; no fixed size to allow A4/Legal/etc. */
                @page { margin: 0.5in; }
                .print-footer{ display:none; }
                @media print{
                    /* No body padding; space reserved by @page bottom margin */
                    .print-footer{ display:block; position:fixed; left:0; right:0; bottom:0; font-size:0.95rem; color:#000; background:#fff; height:0.4in; z-index:9999; }
                    .print-footer .pf-inner{ display:flex; justify-content:space-between; align-items:center; border-top:1px solid #d1d5db; padding:6px 0.15in 2px 0.15in; box-sizing:border-box; height:100%; }
                }
            </style>';
        $left = '<div class="pf-left">কারিগরি সহযোগীতায়ঃ <strong>বাতিঘর কম্পিউটার’স</strong>, মোবাইলঃ <span style="font-weight:700">01762-396713</span></div>';
        $right = '<div class="pf-right">মুদ্রিত: ' . date('d M Y, h:i A') . '</div>';
        $html = '<div class="print-footer"><div class="pf-inner">' . $left . $right . '</div></div>';
        return $styleBlock . $html;
    }
}
