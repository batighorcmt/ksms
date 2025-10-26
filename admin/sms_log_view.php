<?php
require_once '../config.php';

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
  body { font-family: 'SolaimanLipi','Source Sans Pro',sans-serif; }
  .container-box { max-width: 900px; margin: 20px auto; }
  .print-header { text-align:center; margin-bottom: 10px; }
  .print-actions { text-align:right; margin-bottom: 10px; }
  .detail-grid { display: grid; grid-template-columns: 1fr 2fr; grid-row-gap: 8px; grid-column-gap: 12px; }
  .label { color:#666; }
  .value { font-weight: 600; }
  .msg-box { border:1px solid #ccc; border-radius:6px; padding:12px; white-space:pre-wrap; }
  @media print {
    .print-actions { display:none; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>
<div class="container-box">
  <div class="print-header">
    <h3>এসএমএস লগ ভিউ</h3>
    <small>ID: <?= (int)$row['id'] ?> • সময়: <?= h($row['created_at'] ?? '') ?></small>
  </div>
  <div class="print-actions">
    <a class="btn btn-sm btn-secondary" href="sms_logs.php">Back</a>
    <button class="btn btn-sm btn-primary" onclick="window.print()">Print</button>
  </div>
  <div class="card">
    <div class="card-body">
      <div class="detail-grid">
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
