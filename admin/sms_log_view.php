<?php
require_once '../config.php';
require_once __DIR__ . '/print_common.php';

if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../login.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$row = null;
if ($id > 0) {
    try {
        $st = $pdo->prepare('SELECT l.*, u.full_name AS sender_name FROM sms_logs l LEFT JOIN users u ON u.id = l.sent_by_user_id WHERE l.id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $row = null; }
}
if (!$row) {
    $_SESSION['error'] = 'লগটি পাওয়া যায়নি।';
    redirect('sms_logs.php');
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Try to load institute name and address from settings table (best-effort)
$inst_name = '';
$inst_address = '';
try {
    $keys = [
        'school_name','institute_name','institute','school_title','site_name',
        'school_address','institute_address','address'
    ];
    $in = implode(',', array_fill(0, count($keys), '?'));
    $st = $pdo->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ($in)");
    $st->execute($keys);
    $kv = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $kv[$r['key']] = $r['value']; }
    foreach (['school_name','institute_name','institute','school_title','site_name'] as $k) {
        if (!empty($kv[$k])) { $inst_name = trim((string)$kv[$k]); break; }
    }
    foreach (['school_address','institute_address','address'] as $k) {
        if (!empty($kv[$k])) { $inst_address = trim((string)$kv[$k]); break; }
    }
} catch (Exception $e) { /* ignore */ }
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>এসএমএস লগ ভিউ #<?= (int)$row['id'] ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
<style>
  body { font-family: 'SolaimanLipi','Source Sans Pro',sans-serif; font-size:16px; line-height:1.5; }
  .container-box { max-width: 980px; margin: 22px auto; }
  /* Header styling now comes from print_common.php */
  .print-actions { text-align:right; margin-bottom: 10px; }
  .detail-grid { display: grid; grid-template-columns: 1fr 2fr; grid-row-gap: 8px; grid-column-gap: 14px; }
  .label { color:#666; }
  .value { font-weight: 600; color:#111; }
  .card { box-shadow: 0 2px 6px rgba(0,0,0,0.05); border-radius:8px; }
  .msg-box { border:1px solid #e0e0e0; border-radius:6px; padding:12px; white-space:pre-wrap; background:#fafafa; }
  hr { border-top: 1px solid #e5e5e5; }
  @media print {
    .print-actions { display:none; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .card { box-shadow:none; border:1px solid #ddd; }
  }
</style>
</head>
<body>
<div class="container-box">
  <?php echo print_header($pdo, 'এসএমএস লগ ভিউ'); ?>
  <div class="print-actions">
    <a class="btn btn-sm btn-secondary" href="sms_logs.php">Back</a>
    <button class="btn btn-sm btn-primary" onclick="window.print()">Print</button>
  </div>
  <div class="card">
    <div class="card-body">
      <div class="detail-grid">
        <div class="label">লগ আইডি</div><div class="value"><?= (int)$row['id'] ?></div>
        <div class="label">সময়</div><div class="value"><?= h($row['created_at'] ?? '') ?></div>
        <div class="label">স্ট্যাটাস</div><div class="value"><?= h($row['status'] ?? '') ?></div>
        <div class="label">ধরন</div><div class="value"><?= h($row['recipient_type'] ?? '') ?></div>
        <div class="label">প্রাপক বিভাগ</div><div class="value"><?= h($row['recipient_category'] ?? '') ?></div>
        <div class="label">প্রাপকের নাম</div><div class="value"><?= h($row['recipient_name'] ?? '') ?></div>
        <div class="label">রোল</div><div class="value"><?= h($row['roll_number'] ?? '') ?></div>
        <div class="label">শ্রেণি</div><div class="value"><?= h($row['class_name'] ?? '') ?></div>
        <div class="label">শাখা</div><div class="value"><?= h($row['section_name'] ?? '') ?></div>
        <div class="label">মোবাইল</div><div class="value"><?= h($row['recipient_number'] ?? '') ?></div>
        <div class="label">প্রেরক</div><div class="value"><?= h($row['sender_name'] ?? ($row['sent_by_user_id'] ? ('User#'.$row['sent_by_user_id']) : '')) ?></div>
      </div>
      <hr>
      <div class="label mb-1">বার্তা</div>
      <div class="msg-box"><?= nl2br(h($row['message'] ?? '')) ?></div>
    </div>
  </div>
</div>
</body>
</html>
