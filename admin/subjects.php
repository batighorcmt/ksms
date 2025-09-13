<?php
require_once '../config.php';

// Authentication
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

// Load classes
$classes = $pdo->query("SELECT * FROM classes WHERE status='active' ORDER BY numeric_value ASC")->fetchAll();

// Determine selected class
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Load subjects for the class (via class_subjects)
if ($class_id) {
    $stmt = $pdo->prepare("SELECT cs.id as cs_id, cs.numeric_value, s.* FROM class_subjects cs LEFT JOIN subjects s ON cs.subject_id = s.id WHERE cs.class_id = ? ORDER BY cs.numeric_value ASC, s.name ASC");
    $stmt->execute([$class_id]);
    $subjects = $stmt->fetchAll();
} else {
    // load all subjects grouped by class
    $stmt = $pdo->query("SELECT cs.class_id, cs.numeric_value, c.name as class_name, s.* FROM class_subjects cs LEFT JOIN subjects s ON cs.subject_id = s.id LEFT JOIN classes c ON cs.class_id = c.id ORDER BY c.numeric_value ASC, cs.numeric_value ASC, s.name ASC");
    $subjects = $stmt->fetchAll();
}

// Handle edit/delete of class_subjects via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'update') {
        $cs_id = isset($_POST['cs_id']) ? intval($_POST['cs_id']) : 0;
        $new_subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
        if ($cs_id && $new_subject_id) {
            // find class_id for this mapping
            $row = $pdo->prepare("SELECT class_id FROM class_subjects WHERE id = ?"); $row->execute([$cs_id]); $r = $row->fetch();
            if ($r) {
                $classId = $r['class_id'];
                // check duplicate
                $dup = $pdo->prepare("SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ? AND id != ?");
                $dup->execute([$classId, $new_subject_id, $cs_id]);
                if ($dup->fetch()) {
                    $_SESSION['errors'] = ['এই শ্রেণির জন্য বিষয় ইতোমধ্যে আছে।'];
                } else {
                    $upd = $pdo->prepare("UPDATE class_subjects SET subject_id = ? WHERE id = ?");
                    $upd->execute([$new_subject_id, $cs_id]);
                    $_SESSION['success'] = 'ম্যাপিং আপডেট করা হয়েছে।';
                }
            }
        }
    }
    if ($action === 'delete') {
        $cs_id = isset($_POST['cs_id']) ? intval($_POST['cs_id']) : 0;
        if ($cs_id) {
            $del = $pdo->prepare("DELETE FROM class_subjects WHERE id = ?");
            $del->execute([$cs_id]);
            $_SESSION['success'] = 'ম্যাপিং মুছে ফেলা হয়েছে।';
        }
    }
    if ($action === 'move') {
        $cs_id = isset($_POST['cs_id']) ? intval($_POST['cs_id']) : 0;
        $dir = isset($_POST['dir']) ? $_POST['dir'] : 'up';
        if ($cs_id) {
            $pdo->beginTransaction();
            $row = $pdo->prepare("SELECT class_id, numeric_value FROM class_subjects WHERE id = ?"); $row->execute([$cs_id]); $r = $row->fetch();
            if ($r) {
                $classId = $r['class_id']; $nv = (int)$r['numeric_value'];
                if ($dir === 'up') {
                    $neigh = $pdo->prepare("SELECT id, numeric_value FROM class_subjects WHERE class_id = ? AND numeric_value < ? ORDER BY numeric_value DESC LIMIT 1");
                    $neigh->execute([$classId, $nv]);
                } else {
                    $neigh = $pdo->prepare("SELECT id, numeric_value FROM class_subjects WHERE class_id = ? AND numeric_value > ? ORDER BY numeric_value ASC LIMIT 1");
                    $neigh->execute([$classId, $nv]);
                }
                if ($n = $neigh->fetch()) {
                    $pdo->prepare("UPDATE class_subjects SET numeric_value = ? WHERE id = ?")->execute([$n['numeric_value'], $cs_id]);
                    $pdo->prepare("UPDATE class_subjects SET numeric_value = ? WHERE id = ?")->execute([$nv, $n['id']]);
                }
            }
            $pdo->commit();
        }
    }
    if ($action === 'reorder') {
        // expected: $_POST['order'] = array of cs_id in desired order
        $order = isset($_POST['order']) && is_array($_POST['order']) ? $_POST['order'] : [];
        if (!empty($order)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE class_subjects SET numeric_value = ? WHERE id = ?");
                $pos = 1;
                foreach ($order as $csid) {
                    $stmt->execute([intval($pos), intval($csid)]);
                    $pos++;
                }
                $pdo->commit();
                echo json_encode(['status' => 'ok']);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
        }
    }
    header('Location: subjects.php' . ($class_id ? '?class_id=' . $class_id : '')); exit;
}

?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ক্লাস অনুযায়ী বিষয় তালিকা - কিন্ডার গার্ডেন</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body, .main-sidebar, .nav-link {font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;}</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">ক্লাস অনুযায়ী বিষয়</h1></div>
                    <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>dashboard.php">হোম</a></li><li class="breadcrumb-item active">বিষয়সমূহ</li></ol></div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <form method="get" class="form-inline">
                            <div class="form-group mr-2">
                                <label class="mr-2">শ্রেণি:</label>
                                <select name="class_id" class="form-control" onchange="this.form.submit()">
                                    <option value="">সব শ্রেণি</option>
                                    <?php foreach($classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo $class_id == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <a href="add_subject.php" class="btn btn-primary btn-sm ml-auto"><i class="fas fa-plus"></i> নতুন বিষয় যোগ করুন</a>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if(empty($subjects)): ?>
                            <div class="alert alert-info">কোন বিষয় পাওয়া যায়নি।</div>
                        <?php else: ?>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>শ্রেণি</th>
                                        <th>বিষয় নাম</th>
                                        <th>অপশন</th>
                                    </tr>
                                </thead>
                                <tbody id="subjects_tbody" data-class-id="<?php echo $class_id; ?>">
                                    <?php $i=1; foreach($subjects as $s): ?>
                                        <tr data-cs-id="<?php echo $s['cs_id'] ?? ''; ?>">
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo isset($s['class_name']) ? htmlspecialchars($s['class_name']) : ( $class_id ? htmlspecialchars(array_values(array_filter($classes, function($c) use ($class_id){ return $c['id']==$class_id; }))[0]['name']) : '—' ); ?></td>
                                            <td><?php echo htmlspecialchars($s['name']); ?></td>
                                            <td>
                                                <form method="post" style="display:inline-block;">
                                                    <input type="hidden" name="action" value="move">
                                                    <input type="hidden" name="cs_id" value="<?php echo $s['cs_id'] ?? ''; ?>">
                                                    <input type="hidden" name="dir" value="up">
                                                    <button class="btn btn-sm btn-secondary moveUpBtn" title="Move up"><i class="fas fa-arrow-up"></i></button>
                                                </form>
                                                <form method="post" style="display:inline-block;">
                                                    <input type="hidden" name="action" value="move">
                                                    <input type="hidden" name="cs_id" value="<?php echo $s['cs_id'] ?? ''; ?>">
                                                    <input type="hidden" name="dir" value="down">
                                                    <button class="btn btn-sm btn-secondary moveDownBtn" title="Move down"><i class="fas fa-arrow-down"></i></button>
                                                </form>
                                                <button class="btn btn-sm btn-info editBtn" data-cs-id="<?php echo $s['cs_id'] ?? ''; ?>" data-subject-id="<?php echo $s['id']; ?>" data-subject-name="<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>">এডিট</button>
                                                <button class="btn btn-sm btn-danger deleteBtn" data-cs-id="<?php echo $s['cs_id'] ?? ''; ?>" data-subject-name="<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>">মুছুন</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include 'inc/footer.php'; ?>
</div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="cs_id" id="edit_cs_id" value="">
                    <div class="modal-header"><h5 class="modal-title">ম্যাপিং এডিট করুন</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                    <div class="modal-body">
                            <div class="form-group"><label>বিষয়</label>
                                    <select name="subject_id" id="edit_subject_id" class="form-control">
                                            <?php foreach($pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll() as $sub): ?>
                                                    <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?></option>
                                            <?php endforeach; ?>
                                    </select>
                            </div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-primary" type="submit">আপডেট</button><button type="button" class="btn btn-secondary" data-dismiss="modal">বন্ধ</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="cs_id" id="delete_cs_id" value="">
                    <div class="modal-header"><h5 class="modal-title">ম্যাপিং মুছুন</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                    <div class="modal-body">আপনি কি নিশ্চিত যে আপনি <strong id="delete_subject_name"></strong> এই ম্যাপিংটি মুছে ফেলতে চান?</div>
                    <div class="modal-footer"><button class="btn btn-danger" type="submit">মুছুন</button><button type="button" class="btn btn-secondary" data-dismiss="modal">বন্ধ</button></div>
                </form>
            </div>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- SortableJS for drag-and-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
$(function(){
    var tbody = document.getElementById('subjects_tbody');
    if (tbody) {
        var sortable = Sortable.create(tbody, {
            handle: '.fa-arrows-alt',
            animation: 150,
            onEnd: function (evt) {
                // collect cs_id order
                var ids = Array.from(tbody.querySelectorAll('tr')).map(function(r){ return r.getAttribute('data-cs-id'); });
                // send via fetch
                fetch('subjects.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: 'action=reorder&' + ids.map(function(id,i){ return 'order[]=' + encodeURIComponent(id); }).join('&')
                }).then(function(r){ return r.json(); }).then(function(j){
                    if (j.status === 'ok') {
                        // success toast
                        $('<div class="toast bg-success text-white" style="position:fixed;top:10px;right:10px;padding:8px;border-radius:4px;">সংরক্ষণ করা হয়েছে</div>').appendTo('body').delay(1500).fadeOut(300, function(){ $(this).remove(); });
                    } else {
                        $('<div class="toast bg-danger text-white" style="position:fixed;top:10px;right:10px;padding:8px;border-radius:4px;">ত্রুটি: '+(j.message||'Unknown')+'</div>').appendTo('body').delay(2500).fadeOut(300, function(){ $(this).remove(); });
                    }
                }).catch(function(err){
                    $('<div class="toast bg-danger text-white" style="position:fixed;top:10px;right:10px;padding:8px;border-radius:4px;">নেটওয়ার্ক ত্রুটি</div>').appendTo('body').delay(2500).fadeOut(300, function(){ $(this).remove(); });
                });
            }
        });
    }
});
</script>
<script>
$(function(){
    $('.editBtn').on('click', function(){
        var csId = $(this).data('cs-id');
        var subjectId = $(this).data('subject-id');
        $('#edit_cs_id').val(csId);
        $('#edit_subject_id').val(subjectId);
        $('#editModal').modal('show');
    });

    $('.deleteBtn').on('click', function(){
        var csId = $(this).data('cs-id');
        var name = $(this).data('subject-name');
        $('#delete_cs_id').val(csId);
        $('#delete_subject_name').text(name);
        $('#deleteModal').modal('show');
    });
    // move up/down buttons: submit their form
    $('.moveUpBtn, .moveDownBtn').on('click', function(e){
        e.preventDefault();
        $(this).closest('form').submit();
    });
});
</script>
</body>
</html>
