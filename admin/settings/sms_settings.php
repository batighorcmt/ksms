<?php
require_once '../../config.php';
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    redirect('../../login.php');
}

// API settings
$default_api_url = '';
$default_api_key = '';
$default_sender_id = '';
$default_masking = '';

// Try to load from DB (settings table)
$settings = $pdo->query("SELECT * FROM settings WHERE `key` LIKE 'sms_%'")->fetchAll();
foreach ($settings as $row) {
    if ($row['key'] === 'sms_api_url') $default_api_url = $row['value'];
    if ($row['key'] === 'sms_api_key') $default_api_key = $row['value'];
    if ($row['key'] === 'sms_sender_id') $default_sender_id = $row['value'];
    if ($row['key'] === 'sms_masking') $default_masking = $row['value'];
}

// Handle API settings form
if (isset($_POST['save_api'])) {
    $api_url = $_POST['api_url'] ?? '';
    $api_key = $_POST['api_key'] ?? '';
    $sender_id = $_POST['sender_id'] ?? '';
    $masking = $_POST['masking'] ?? '';
    $save = $pdo->prepare("REPLACE INTO settings (`key`, `value`) VALUES
        ('sms_api_url', ?),
        ('sms_api_key', ?),
        ('sms_sender_id', ?),
        ('sms_masking', ?)");
    $save->execute([$api_url, $api_key, $sender_id, $masking]);
    $_SESSION['success'] = 'API settings updated!';
    header('Location: sms_settings.php');
    exit;
}

// SMS templates CRUD
if (isset($_POST['add_template'])) {
    $title = trim($_POST['template_title'] ?? '');
    $content = trim($_POST['template_body'] ?? '');
    if ($title && $content) {
        $stmt = $pdo->prepare("INSERT INTO sms_templates (title, content) VALUES (?, ?)");
        $stmt->execute([$title, $content]);
        $_SESSION['success'] = 'Template added!';
    }
    header('Location: sms_settings.php#templates');
    exit;
}
if (isset($_POST['edit_template'])) {
    $id = intval($_POST['template_id']);
    $title = trim($_POST['template_title'] ?? '');
    $content = trim($_POST['template_body'] ?? '');
    if ($id && $title && $content) {
        $stmt = $pdo->prepare("UPDATE sms_templates SET title=?, content=? WHERE id=?");
        $stmt->execute([$title, $content, $id]);
        $_SESSION['success'] = 'Template updated!';
    }
    header('Location: sms_settings.php#templates');
    exit;
}
if (isset($_POST['delete_template'])) {
    $id = intval($_POST['template_id']);
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM sms_templates WHERE id=?");
        $stmt->execute([$id]);
        $_SESSION['success'] = 'Template deleted!';
    }
    header('Location: sms_settings.php#templates');
    exit;
}

// Load templates
if ($pdo->query("SHOW TABLES LIKE 'sms_templates'")->rowCount() == 0) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        body TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$templates = $pdo->query("SELECT * FROM sms_templates ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <title>SMS Settings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>body, .main-sidebar, .nav-link { font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif; }</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../inc/header.php'; ?>
    <?php include '../inc/sidebar.php'; ?>
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                    <div class="content-wrapper">
                            <section class="content-header">
                                    <div class="container-fluid">
                                            <div class="row mb-2">
                                                    <div class="col-sm-6">
                                                            <h1 class="m-0"><i class="fas fa-sms"></i> এসএমএস সেটিংস</h1>
                                                    </div>
                                            </div>
                                    </div>
                            </section>
                            <section class="content">
                                    <div class="container-fluid" style="max-width:800px;">
                                            <?php if(isset($_SESSION['success'])): ?>
                                                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                                            <?php endif; ?>
                                            <ul class="nav nav-tabs mb-3" id="smsTab" role="tablist">
                                                <li class="nav-item">
                                                    <a class="nav-link active" id="api-tab" data-toggle="tab" href="#api" role="tab">API Settings</a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link" id="templates-tab" data-toggle="tab" href="#templates" role="tab">SMS Templates</a>
                                                </li>
                                            </ul>
                                            <div class="tab-content" id="smsTabContent">
                                                <div class="tab-pane fade show active" id="api" role="tabpanel">
                                                    <form method="post" class="card p-4">
                                                            <div class="form-group">
                                                                    <label>SMS API URL</label>
                                                                    <input type="text" name="api_url" class="form-control" value="<?php echo htmlspecialchars($default_api_url); ?>" required>
                                                                    <small class="form-text text-muted">Example: https://sms.example.com/api/send</small>
                                                            </div>
                                                            <div class="form-group">
                                                                    <label>API Key</label>
                                                                    <input type="text" name="api_key" class="form-control" value="<?php echo htmlspecialchars($default_api_key); ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                    <label>Sender ID</label>
                                                                    <input type="text" name="sender_id" class="form-control" value="<?php echo htmlspecialchars($default_sender_id); ?>">
                                                            </div>
                                                            <div class="form-group">
                                                                    <label>Masking</label>
                                                                    <input type="text" name="masking" class="form-control" value="<?php echo htmlspecialchars($default_masking); ?>">
                                                                    <small class="form-text text-muted">If your provider supports masking, enter here.</small>
                                                            </div>
                                                            <button type="submit" name="save_api" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Save API Settings</button>
                                                    </form>
                                                </div>
                                                <div class="tab-pane fade" id="templates" role="tabpanel">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <h5 class="mb-0">SMS Templates</h5>
                                                        <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#addTemplateModal"><i class="fa fa-plus"></i> Add Template</button>
                                                    </div>
                                                    <table class="table table-bordered table-striped">
                                                        <thead><tr><th>Title</th><th>Body</th><th>Actions</th></tr></thead>
                                                        <tbody>
                                                            <?php foreach($templates as $t): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($t['title'] ?? '') ?></td>
                                                                <td><pre style="white-space:pre-wrap;word-break:break-all;"><?= htmlspecialchars((string)($t['content'] ?? '')) ?></pre></td>
                                                                <td>
                                                                    <button class="btn btn-warning btn-sm edit-btn" 
                                                                        data-id="<?= $t['id'] ?>" 
                                                                        data-title="<?= htmlspecialchars($t['title'] ?? '',ENT_QUOTES) ?>" 
                                                                        data-body="<?= htmlspecialchars((string)($t['content'] ?? ''),ENT_QUOTES) ?>"
                                                                        data-toggle="modal" data-target="#editTemplateModal">
                                                                        <i class="fa fa-edit"></i>
                                                                    </button>
                                                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this template?');">
                                                                        <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
                                                                        <button type="submit" name="delete_template" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></button>
                                                                    </form>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                            <?php if(empty($templates)): ?>
                                                            <tr><td colspan="3" class="text-center text-muted">No templates found.</td></tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <!-- Add Template Modal -->
                                            <div class="modal fade" id="addTemplateModal" tabindex="-1" role="dialog" aria-labelledby="addTemplateModalLabel" aria-hidden="true">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <form method="post">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="addTemplateModalLabel">Add SMS Template</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="form-group">
                                                                    <label>Title</label>
                                                                    <input type="text" name="template_title" class="form-control" required>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label>Body</label>
                                                                    <textarea name="template_body" class="form-control" rows="4" required></textarea>
                                                                    <small class="form-text text-muted">
                                                                        You can use variables like:<br>
                                                                        <code>{student_name}</code>, <code>{date}</code>, <code>{status}</code>, <code>{school_name}</code>, <code>{amount}</code>, <code>{month}</code>, <code>{exam_name}</code>, <code>{exam_date}</code>, <code>{code}</code>, etc.<br>
                                                                        These will be replaced dynamically when sending SMS.
                                                                    </small>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="add_template" class="btn btn-success">Add Template</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Edit Template Modal -->
                                            <div class="modal fade" id="editTemplateModal" tabindex="-1" role="dialog" aria-labelledby="editTemplateModalLabel" aria-hidden="true">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <form method="post">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="editTemplateModalLabel">Edit SMS Template</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="template_id" id="editTemplateId">
                                                                <div class="form-group">
                                                                    <label>Title</label>
                                                                    <input type="text" name="template_title" id="editTemplateTitle" class="form-control" required>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label>Body</label>
                                                                    <textarea name="template_body" id="editTemplateBody" class="form-control" rows="4" required></textarea>
                                                                    <small class="form-text text-muted">
                                                                        You can use variables like:<br>
                                                                        <code>{student_name}</code>, <code>{date}</code>, <code>{status}</code>, <code>{school_name}</code>, <code>{amount}</code>, <code>{month}</code>, <code>{exam_name}</code>, <code>{exam_date}</code>, <code>{code}</code>, etc.<br>
                                                                        These will be replaced dynamically when sending SMS.
                                                                    </small>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="edit_template" class="btn btn-warning">Update Template</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                    </div>
                            </section>
                    </div>
        </section>
    </div>
    <?php include '../inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
// Tab hash navigation
$(function(){
    if(window.location.hash) {
        $(window.location.hash+'-tab').tab('show');
    }
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        window.location.hash = e.target.hash;
    });
    // Edit modal fill
    $('.edit-btn').on('click', function(){
        $('#editTemplateId').val($(this).data('id'));
        $('#editTemplateTitle').val($(this).data('title'));
        $('#editTemplateBody').val($(this).data('body'));
    });
});
</script>
</body>
</html>
