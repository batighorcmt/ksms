<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/inc/enrollment_helpers.php';
/** @var PDO $pdo */

if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../login.php');
}

// Small helpers
function column_exists(PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $st->execute([$table, $column]);
    return (bool)$st->fetchColumn();
}
function fetch_all($pdo, $sql, $params = []) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function fetch_one($pdo, $sql, $params = []) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetch(PDO::FETCH_ASSOC);
}

// Load dropdown data
$yearHasName = column_exists($pdo, 'academic_years', 'name');
$years = $yearHasName
    ? fetch_all($pdo, "SELECT id, name AS label, is_current FROM academic_years ORDER BY id DESC")
    : fetch_all($pdo, "SELECT id, year AS label, is_current FROM academic_years ORDER BY id DESC");
$classes = fetch_all($pdo, "SELECT id, name, numeric_value FROM classes ORDER BY numeric_value, name");

// Resolve defaults
$current_year_id = function_exists('current_academic_year_id') ? current_academic_year_id($pdo) : null;
$default_from_year = intval($_GET['from_year'] ?? ($current_year_id ?: ($years[0]['id'] ?? 0)));
$default_to_year = intval($_GET['to_year'] ?? 0);

$errors = [];
$notices = [];
$preview_rows = [];
$already_enrolled = [];
$will_insert = [];
$summary = ['total' => 0, 'skipped' => 0, 'insert' => 0];

// Handle actions: preview or promote
$action = $_POST['action'] ?? '';
if ($action === 'preview' || $action === 'promote') {
    $from_year = intval($_POST['from_year'] ?? 0);
    $to_year = intval($_POST['to_year'] ?? 0);
    $from_class = intval($_POST['from_class'] ?? 0);
    $from_section = intval($_POST['from_section'] ?? 0);
    $to_class = intval($_POST['to_class'] ?? 0);
    $to_section = intval($_POST['to_section'] ?? 0);
    $roll_mode = $_POST['roll_mode'] ?? 'keep'; // 'keep' or 'reassign'

    if (!$from_year || !$to_year) $errors[] = 'শিক্ষাবর্ষ নির্বাচন করুন (From এবং To দুটোই)।';
    if ($from_year && $to_year && $from_year === $to_year) $errors[] = 'From এবং To শিক্ষাবর্ষ এক হতে পারে না।';
    if (!$from_class || !$to_class) $errors[] = 'শ্রেণি নির্বাচন করুন (From এবং To)।';

    if (!$errors) {
        // Load candidate students from source year/class/section via students_enrollment
        $params = [$from_year, $from_class];
        $where = "se.academic_year_id = ? AND se.class_id = ? AND (se.status = 'active' OR se.status IS NULL)";
        if ($from_section) { $where .= " AND se.section_id = ?"; $params[] = $from_section; }
        $candidates = fetch_all($pdo, "
            SELECT s.id AS student_id, s.first_name, s.last_name, s.gender, s.photo,
                   se.roll_number AS from_roll, se.section_id AS from_section_id,
                   c.name AS from_class_name, sec.name AS from_section_name
              FROM students s
              JOIN students_enrollment se ON se.student_id = s.id
              JOIN classes c ON c.id = se.class_id
              LEFT JOIN sections sec ON sec.id = se.section_id
             WHERE $where
             ORDER BY se.roll_number IS NULL, se.roll_number, s.id
        ", $params);

        // For preview, compute destination mapping and detect duplicates
        $summary['total'] = count($candidates);

        if ($action === 'preview' || $action === 'promote') {
            // Get current max roll if keeping same and there are duplicates later; for reassign, we compute fresh
            $max_roll_row = fetch_one($pdo, "SELECT MAX(roll_number) AS max_roll FROM students_enrollment WHERE academic_year_id = ? AND class_id = ? AND " . ($to_section ? "section_id = ?" : "(section_id IS NULL OR section_id IS NOT NULL)"), $to_section ? [$to_year, $to_class, $to_section] : [$to_year, $to_class]);
            $max_existing_roll = intval($max_roll_row['max_roll'] ?? 0);

            // If reassign - prepare fresh counter starting from 1 or after existing max
            $reassign = ($roll_mode === 'reassign');
            $roll_counter = $reassign ? 1 : null;
            if ($reassign && $max_existing_roll > 0) { $roll_counter = $max_existing_roll + 1; }

            foreach ($candidates as $row) {
                $student_id = (int)$row['student_id'];
                // Check already enrolled at destination year
                $exists = fetch_one($pdo, "SELECT id, roll_number FROM students_enrollment WHERE academic_year_id = ? AND student_id = ? LIMIT 1", [$to_year, $student_id]);
                if ($exists) {
                    $already_enrolled[] = [
                        'student_id' => $student_id,
                        'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                        'existing_roll' => $exists['roll_number'] ?? null
                    ];
                    $summary['skipped']++;
                    continue;
                }
                // Determine destination roll
                $to_roll = ($roll_mode === 'keep') ? ($row['from_roll'] ?? null) : null;
                if ($reassign) {
                    $to_roll = $roll_counter;
                    $roll_counter++;
                }
                $preview_rows[] = [
                    'student_id' => $student_id,
                    'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                    'from_class' => $row['from_class_name'],
                    'from_section' => $row['from_section_name'],
                    'from_roll' => $row['from_roll'],
                    'to_class_id' => $to_class,
                    'to_section_id' => $to_section ?: null,
                    'to_roll' => $to_roll,
                ];
                $will_insert[] = [
                    'student_id' => $student_id,
                    'academic_year_id' => $to_year,
                    'class_id' => $to_class,
                    'section_id' => $to_section ?: null,
                    'roll_number' => $to_roll,
                    'status' => 'active',
                ];
            }
            $summary['insert'] = count($will_insert);
        }

        if ($action === 'promote' && !$errors) {
            if (empty($will_insert)) {
                $notices[] = 'প্রমোশন করার মতো কোনো শিক্ষার্থী নেই (সবাই ইতোমধ্যেই গন্তব্য বছরে আছে)।';
            } else {
                // Execute in a transaction
                try {
                    $pdo->beginTransaction();
                    $ins = $pdo->prepare("INSERT INTO students_enrollment (student_id, academic_year_id, class_id, section_id, roll_number, status) VALUES (?,?,?,?,?,?)");
                    foreach ($will_insert as $insRow) {
                        $ins->execute([
                            $insRow['student_id'],
                            $insRow['academic_year_id'],
                            $insRow['class_id'],
                            $insRow['section_id'],
                            $insRow['roll_number'],
                            $insRow['status']
                        ]);
                    }
                    $pdo->commit();
                    $notices[] = 'প্রমোশন সম্পন্ন: ' . $summary['insert'] . ' জন শিক্ষার্থী যোগ হয়েছে। স্কিপড: ' . $summary['skipped'] . ' জন।';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = 'প্রমোশন ব্যর্থ হয়েছে: ' . $e->getMessage();
                }
            }
        }
    }
}

// HTML starts
$pageTitle = 'শিক্ষার্থী প্রমোশন';
include __DIR__ . '/inc/head_assets.php';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>শিক্ষার্থী প্রমোশন</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
            <?php endif; ?>
            <?php if (!empty($notices)): ?>
                <div class="alert alert-success"><?php echo implode('<br>', array_map('htmlspecialchars', $notices)); ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">প্রমোশন সেটআপ</h3>
                </div>
                <div class="card-body">
                    <form method="post" id="promotionForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>From শিক্ষাবর্ষ</label>
                                    <select name="from_year" class="form-control" required>
                                        <option value="">-- নির্বাচন করুন --</option>
                                        <?php foreach ($years as $y): ?>
                                            <option value="<?php echo (int)$y['id']; ?>" <?php echo ($default_from_year == (int)$y['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(($y['label'] ?? ('Year ' . $y['id'])) . (!empty($y['is_current']) ? ' (বর্তমান)' : '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>From শ্রেণি</label>
                                    <select name="from_class" id="from_class" class="form-control" required>
                                        <option value="">-- নির্বাচন করুন --</option>
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>From শাখা (ঐচ্ছিক)</label>
                                    <select name="from_section" id="from_section" class="form-control">
                                        <option value="">-- সব শাখা --</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>To শিক্ষাবর্ষ</label>
                                    <select name="to_year" class="form-control" required>
                                        <option value="">-- নির্বাচন করুন --</option>
                                        <?php foreach ($years as $y): ?>
                                            <option value="<?php echo (int)$y['id']; ?>" <?php echo ($default_to_year == (int)$y['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($y['label'] ?? ('Year ' . $y['id'])); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>To শ্রেণি</label>
                                    <select name="to_class" id="to_class" class="form-control" required>
                                        <option value="">-- নির্বাচন করুন --</option>
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>To শাখা (ঐচ্ছিক)</label>
                                    <select name="to_section" id="to_section" class="form-control">
                                        <option value="">-- শাখা নির্বাচন করুন --</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>রোল নাম্বার</label>
                                    <select name="roll_mode" class="form-control">
                                        <option value="keep">আগের রোল অপরিবর্তিত রাখুন</option>
                                        <option value="reassign">নতুন করে রোল ধারাবাহিকভাবে দিন</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <button type="submit" name="action" value="preview" class="btn btn-primary">
                                প্রিভিউ দেখুন
                            </button>
                            <?php if (!empty($preview_rows)): ?>
                                <button type="submit" name="action" value="promote" class="btn btn-success ml-2">
                                    প্রমোশন সম্পন্ন করুন
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($preview_rows)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">প্রিভিউ</h3>
                </div>
                <div class="card-body table-responsive">
                    <p>মোট প্রার্থী: <?php echo (int)$summary['total']; ?> | ইতোমধ্যে গন্তব্য বছরে আছে (স্কিপ): <?php echo (int)$summary['skipped']; ?> | নতুনভাবে যুক্ত হবে: <?php echo (int)$summary['insert']; ?></p>
                    <?php if (!empty($already_enrolled)): ?>
                        <div class="alert alert-warning">
                            নিম্নের শিক্ষার্থীরা ইতোমধ্যে গন্তব্য বছরে ভর্তি রয়েছে (স্কিপ করা হবে):
                            <ul class="mb-0">
                                <?php foreach ($already_enrolled as $ae): ?>
                                    <li><?php echo htmlspecialchars($ae['name']); ?> (ID: <?php echo (int)$ae['student_id']; ?>, রোল: <?php echo htmlspecialchars((string)($ae['existing_roll'] ?? '')); ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>শিক্ষার্থী</th>
                                <th>From (শ্রেণি-শাখা)</th>
                                <th>From রোল</th>
                                <th>To শ্রেণি</th>
                                <th>To শাখা</th>
                                <th>To রোল</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i=1; foreach ($preview_rows as $r): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo htmlspecialchars($r['name']); ?> (ID: <?php echo (int)$r['student_id']; ?>)</td>
                                    <td><?php echo htmlspecialchars(($r['from_class'] ?? '') . ($r['from_section'] ? (' - ' . $r['from_section']) : '')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($r['from_roll'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($r['to_class_id'])); ?></td>
                                    <td><?php echo htmlspecialchars((string)($r['to_section_id'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($r['to_roll'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
<?php include __DIR__ . '/inc/scripts_assets.php'; ?>

<script>
// Load sections when class changes (both sides)
function loadSections(selectClassId, targetSelectId){
    const classId = document.getElementById(selectClassId).value;
    const target = document.getElementById(targetSelectId);
    if(!classId){ target.innerHTML = '<option value="">-- সব শাখা --</option>'; return; }
    fetch('<?php echo BASE_URL; ?>admin/get_sections.php?class_id='+encodeURIComponent(classId))
      .then(r=>r.text())
      .then(html=>{ target.innerHTML = '<option value="">-- সব শাখা --</option>' + html; })
      .catch(()=>{ /* ignore */ });
}

document.getElementById('from_class') && document.getElementById('from_class').addEventListener('change', ()=>loadSections('from_class','from_section'));
document.getElementById('to_class') && document.getElementById('to_class').addEventListener('change', ()=>loadSections('to_class','to_section'));
</script>

</div><!-- /.wrapper from head_assets -->
</body>
</html>
