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
    if (!empty($si['phone'])) $contact .= 'ফোন: ' . htmlspecialchars($si['phone']);
    if (!empty($si['email'])) $contact .= ($contact ? ' | ' : '') . 'ইমেল: ' . htmlspecialchars($si['email']);
    $logo = !empty($si['logo']) ? (BASE_URL . 'uploads/logo/' . $si['logo']) : '';

    ob_start();
    ?>
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:8px;">
        <?php if($logo): ?>
            <div style="width:90px;flex:0 0 90px;"><img src="<?php echo $logo; ?>" style="max-width:100%;max-height:90px;object-fit:contain"/></div>
        <?php else: ?>
            <div style="width:90px;flex:0 0 90px;background:#f3f4f6;border-radius:6px;display:flex;align-items:center;justify-content:center">লোগো</div>
        <?php endif; ?>
        <div style="flex:1;text-align:center;">
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
        $printed = '<div style="margin-top:12px;text-align:right;color:#444;font-size:0.95rem">মুদ্রিত: ' . date('d M Y, h:i A') . '</div>';
        // Highlight technical support / company line
        $support = '<div style="margin-top:8px;padding:8px;border-left:4px solid #0d6efd;background:#e9f2ff;color:#000;font-size:0.95rem">';
        $support .= 'কারিগরি সহযোগীতায়ঃ <strong>বাতিঘর কম্পিউটার\'স</strong>, মোবাইলঃ <span style="font-weight:700">01762-396713</span>';
        $support .= '</div>';
        return $printed . $support;
    }
}
