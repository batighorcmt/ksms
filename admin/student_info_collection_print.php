<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/print_common.php';

// Auth: allow super_admin and teacher to access/print
if (!isAuthenticated() || !hasRole(['super_admin','teacher'])) {
  redirect('login.php');
}

// Fetch current academic year (if available)
$currentYear = '';
try {
  $stmt = $pdo->query("SELECT year FROM academic_years WHERE is_current = 1 LIMIT 1");
  if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $currentYear = (string)$row['year']; }
} catch (Throwable $e) { /* ignore */ }

$title = 'শিক্ষার্থী তথ্য সংগ্রহ ফরম';
?>
<!doctype html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($title); ?></title>
  <style>
    body { font-family: 'SolaimanLipi', Arial, sans-serif; color:#000; }
    .container { max-width: 900px; margin: 0 auto; }
    .print-actions { display:flex; justify-content:flex-end; gap:8px; margin:10px 0; }
    .btn { padding:8px 14px; border:1px solid #444; background:#f8f8f8; cursor:pointer; border-radius:4px; }
    .btn-primary { background:#2d6cdf; color:#fff; border-color:#2d6cdf; }
    .btn-outline { background:#fff; color:#333; }
    .header-line { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
    .meta { font-size:0.95rem; color:#000; }
    .section-title { font-weight:700; font-size:1.05rem; margin:20px 0 8px; border-bottom:2px solid #222; padding-bottom:4px; }
    table.form { width:100%; border-collapse:collapse; }
    table.form td { padding:6px 8px; vertical-align:top; }
    .label { width:220px; white-space:nowrap; }
    .line { border-bottom:1px dotted #222; min-width:120px; display:inline-block; padding:2px 6px; }
    .line.wide { min-width:260px; }
    .line.narrow { min-width:100px; }
    .row { display:flex; gap:18px; }
    .col { flex:1; }
    .photo-box { width:120px; height:140px; border:1px dashed #333; display:flex; align-items:center; justify-content:center; font-size:0.9rem; color:#444; }
    .boxes { display:flex; gap:14px; flex-wrap:wrap; }
    .box { width:14px; height:14px; border:1px solid #000; display:inline-block; margin-right:6px; vertical-align:middle; }
    .muted { color:#333; font-size:0.95rem; }
    .note { font-size:0.9rem; color:#333; margin-top:4px; }
    .signature-row { display:flex; justify-content:space-between; margin-top:36px; }
    .sig { text-align:center; }
    .sig .line { min-width:220px; }
    .small { font-size:0.9rem; }
    .hr { border-top:1px solid #ddd; margin:12px 0; }
    @media print {
      .print-actions { display:none !important; }
      @page { size: A4; margin: 0.5in; }
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
<div class="container">
  <?php echo print_header($pdo, $title); ?>

  <div class="header-line">
    <div class="meta">সাল (Year): <span class="line narrow"><?php echo htmlspecialchars($currentYear ?: ''); ?></span></div>
    <div class="meta">ফরম নং: <span class="line narrow"></span></div>
  </div>

  <div class="print-actions">
    <button class="btn btn-outline" onclick="window.history.back()">ফিরে যান</button>
    <button class="btn btn-primary" onclick="window.print()">প্রিন্ট করুন</button>
  </div>

  <div class="section-title">১) শিক্ষার্থীর প্রাথমিক তথ্য</div>
  <table class="form">
    <tr>
      <td class="label">শিক্ষার্থীর নাম (বাংলা)</td>
      <td><span class="line wide"></span></td>
      <td rowspan="4" style="width:140px; text-align:right;">
        <div class="photo-box">পাসপোর্ট সাইজ ছবি</div>
      </td>
    </tr>
    <tr>
      <td class="label">শিক্ষার্থীর নাম (ইংরেজি)</td>
      <td><span class="line wide"></span></td>
    </tr>
    <tr>
      <td class="label">জন্ম তারিখ</td>
      <td><span class="line narrow"></span> &nbsp; লিঙ্গ: [ ] পুরুষ [ ] মহিলা [ ] অন্যান্য</td>
    </tr>
    <tr>
      <td class="label">জন্ম সনদের নং</td>
      <td><span class="line wide"></span></td>
    </tr>
    <tr>
      <td class="label">রক্তের গ্রুপ</td>
      <td colspan="2"><span class="line narrow"></span> &nbsp; ধর্ম: <span class="line narrow"></span> &nbsp; জাতীয়তা: <span class="line narrow"></span></td>
    </tr>
  </table>

  <div class="section-title">২) অভিভাবক/অভিভাবিকা তথ্য</div>
  <table class="form">
    <tr>
      <td class="label">পিতার নাম</td>
      <td><span class="line wide"></span></td>
      <td class="label">মোবাইল</td>
      <td><span class="line narrow"></span></td>
    </tr>
    <tr>
      <td class="label">মাতার নাম</td>
      <td><span class="line wide"></span></td>
      <td class="label">মোবাইল</td>
      <td><span class="line narrow"></span></td>
    </tr>
    <tr>
      <td class="label">অভিভাবকের সম্পর্ক</td>
      <td><span class="line narrow"></span></td>
      <td class="label">অভিভাবকের নাম</td>
      <td><span class="line wide"></span></td>
    </tr>
    <tr>
      <td class="label">অভিভাবকের মোবাইল</td>
      <td><span class="line narrow"></span></td>
      <td class="label">ই-মেইল (যদি থাকে)</td>
      <td><span class="line wide"></span></td>
    </tr>
    <tr>
      <td class="label">পিতার পেশা</td>
      <td><span class="line wide"></span></td>
      <td class="label">মাতার পেশা</td>
      <td><span class="line wide"></span></td>
    </tr>
  </table>

  <div class="section-title">৩) ঠিকানা</div>
  <table class="form">
    <tr>
      <td class="label">বর্তমান ঠিকানা</td>
      <td colspan="3"><span class="line" style="min-width:100%;"></span></td>
    </tr>
    <tr>
      <td class="label">স্থায়ী ঠিকানা</td>
      <td colspan="3"><span class="line" style="min-width:100%;"></span></td>
    </tr>
    <tr>
      <td class="label">উপজেলা/থানা</td>
      <td><span class="line wide"></span></td>
      <td class="label">জেলা</td>
      <td><span class="line wide"></span></td>
    </tr>
    <tr>
      <td class="label">পোস্ট কোড</td>
      <td><span class="line narrow"></span></td>
      <td class="label">জরুরী যোগাযোগ (মোবাইল)</td>
      <td><span class="line wide"></span></td>
    </tr>
  </table>

  <div class="section-title">৪) একাডেমিক তথ্য</div>
  <table class="form">
    <tr>
      <td class="label">শ্রেণি</td>
      <td><span class="line wide"></span></td>
      <td class="label">শাখা</td>
      <td><span class="line wide"></span></td>
    </tr>
    <tr>
      <td class="label">রোল নম্বর</td>
      <td><span class="line narrow"></span></td>
      <td class="label">ভর্তির তারিখ</td>
      <td><span class="line narrow"></span></td>
    </tr>
    <tr>
      <td class="label">পূর্ববর্তী প্রতিষ্ঠান (যদি থাকে)</td>
      <td colspan="3"><span class="line" style="min-width:100%;"></span></td>
    </tr>
  </table>

  <div class="section-title">৫) সংযুক্তি</div>
  <div class="boxes muted">
    <div><span class="box"></span> জন্ম নিবন্ধন সনদ কপি</div>
    <div><span class="box"></span> টিসি/ছাড়পত্র (যদি থাকে)</div>
    <div><span class="box"></span> ২ কপি ছবি</div>
  </div>

  <div class="section-title">৬) ঘোষণা</div>
  <div class="muted small">
    আমি নিশ্চিত করছি যে উপরে প্রদত্ত সকল তথ্য সঠিক। ভর্তির শর্তাবলী ও বিদ্যালয়ের সকল নিয়ম-কানুন মেনে চলতে বাধ্য থাকব/থাকবো।
  </div>

  <div class="signature-row">
    <div class="sig">
      <div class="line"></div>
      <div>অভিভাবকের স্বাক্ষর</div>
      <div class="small">তারিখ: <span class="line narrow"></span></div>
    </div>
    <div class="sig">
      <div class="line"></div>
      <div>কর্তৃপক্ষের স্বাক্ষর</div>
      <div class="small">তারিখ: <span class="line narrow"></span></div>
    </div>
  </div>

  <div class="hr"></div>
  <div class="small muted">নোট: ফরমটি স্পষ্টভাবে পূরণ করুন। প্রযোজ্য তথ্যের স্থানে √ চিহ্ন দিন। অসম্পূর্ণ তথ্য প্রদান করলে ভর্তি প্রক্রিয়া বিলম্বিত হতে পারে।</div>

  <?php echo print_footer(); ?>
</div>
</body>
</html>
