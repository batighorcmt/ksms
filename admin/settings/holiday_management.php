<?php
require_once '../../config.php';

// Authentication check - only super_admin can manage holidays
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    header('Location: ../login.php');
}

$page_title = 'ছুটির দিন ব্যবস্থাপনা';
$active_menu = 'settings';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_holiday'])) {
        $title = trim($_POST['title']);
        $date = $_POST['date'];
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        
        // Validate date
        if (empty($title) || empty($date)) {
            $_SESSION['error'] = 'শিরোনাম এবং তারিখ অবশ্যই প্রয়োজন';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO holidays (title, date, description, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $date, $description, $status]);
                $_SESSION['success'] = 'ছুটির দিন সফলভাবে যোগ করা হয়েছে';
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error'] = 'এই তারিখের জন্য ইতিমধ্যেই একটি ছুটি রয়েছে';
                } else {
                    $_SESSION['error'] = 'ছুটির দিন যোগ করতে সমস্যা হয়েছে: ' . $e->getMessage();
                }
            }
        }
    } 
    elseif (isset($_POST['update_holiday'])) {
        $id = intval($_POST['id']);
        $title = trim($_POST['title']);
        $date = $_POST['date'];
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        
        if (empty($title) || empty($date)) {
            $_SESSION['error'] = 'শিরোনাম এবং তারিখ অবশ্যই প্রয়োজন';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE holidays SET title=?, date=?, description=?, status=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$title, $date, $description, $status, $id]);
                $_SESSION['success'] = 'ছুটির দিন সফলভাবে আপডেট করা হয়েছে';
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error'] = 'এই তারিখের জন্য ইতিমধ্যেই একটি ছুটি রয়েছে';
                } else {
                    $_SESSION['error'] = 'ছুটির দিন আপডেট করতে সমস্যা হয়েছে: ' . $e->getMessage();
                }
            }
        }
    }
    elseif (isset($_POST['delete_holiday'])) {
        $id = intval($_POST['id']);
        
        $stmt = $pdo->prepare("DELETE FROM holidays WHERE id=?");
        if ($stmt->execute([$id])) {
            $_SESSION['success'] = 'ছুটির দিন সফলভাবে মুছে ফেলা হয়েছে';
        } else {
            $_SESSION['error'] = 'ছুটির দিন মুছতে সমস্যা হয়েছে';
        }
    }
    
    header('Location: settings/holiday_management.php');
    exit;
}

// Fetch all holidays
$holidays = $pdo->query("SELECT * FROM holidays ORDER BY date DESC")->fetchAll();

// Fetch holiday for editing if ID is provided
$edit_holiday = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM holidays WHERE id = ?");
    $stmt->execute([$id]);
    $edit_holiday = $stmt->fetch();
}

// Include header
include '../inc/header.php';
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <!-- Notification Alerts -->
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-check"></i> সফল!</h5>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h5><i class="icon fas fa-ban"></i> ত্রুটি!</h5>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <?php echo $edit_holiday ? 'ছুটির দিন সম্পাদনা করুন' : 'নতুন ছুটির দিন যোগ করুন'; ?>
                                </h3>
                            </div>
                            <form method="POST" action="">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="title">শিরোনাম *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo $edit_holiday ? $edit_holiday['title'] : ''; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="date">তারিখ *</label>
                                        <input type="date" class="form-control" id="date" name="date" 
                                               value="<?php echo $edit_holiday ? $edit_holiday['date'] : ''; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="description">বিবরণ</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                            echo $edit_holiday ? $edit_holiday['description'] : ''; 
                                        ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="status">স্ট্যাটাস</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="active" <?php echo ($edit_holiday && $edit_holiday['status'] == 'active') ? 'selected' : ''; ?>>সক্রিয়</option>
                                            <option value="inactive" <?php echo ($edit_holiday && $edit_holiday['status'] == 'inactive') ? 'selected' : ''; ?>>নিষ্ক্রিয়</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <?php if($edit_holiday): ?>
                                        <input type="hidden" name="id" value="<?php echo $edit_holiday['id']; ?>">
                                        <button type="submit" name="update_holiday" class="btn btn-primary">আপডেট করুন</button>
                                        <a href="holiday_management.php" class="btn btn-default">বাতিল করুন</a>
                                    <?php else: ?>
                                        <button type="submit" name="add_holiday" class="btn btn-primary">যোগ করুন</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card card-secondary">
                            <div class="card-header">
                                <h3 class="card-title">ছুটির দিনের তালিকা</h3>
                            </div>
                            <div class="card-body p-0">
                                <?php if(count($holidays) > 0): ?>
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>তারিখ</th>
                                                <th>শিরোনাম</th>
                                                <th>স্ট্যাটাস</th>
                                                <th>ক্রিয়া</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($holidays as $holiday): 
                                                $date = new DateTime($holiday['date']);
                                                $formatted_date = $date->format('d/m/Y');
                                            ?>
                                                <tr>
                                                    <td><?php echo $formatted_date; ?></td>
                                                    <td><?php echo $holiday['title']; ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $holiday['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                            <?php echo $holiday['status'] == 'active' ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="holiday_management.php?edit=<?php echo $holiday['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="POST" action="" style="display:inline;">
                                                            <input type="hidden" name="id" value="<?php echo $holiday['id']; ?>">
                                                            <button type="submit" name="delete_holiday" class="btn btn-sm btn-danger" 
                                                                    onclick="return confirm('আপনি কি নিশ্চিত যে আপনি এই ছুটির দিনটি মুছতে চান?')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div class="p-3 text-center">
                                        <p>কোন ছুটির দিন যোগ করা হয়নি</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Include footer
include '../inc/footer.php';
?>