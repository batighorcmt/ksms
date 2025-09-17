<?php
require_once '../../config.php';

// Authentication check - only super_admin can manage holidays
if (!isAuthenticated() || !hasRole(['super_admin'])) {
    header('Location: ../login.php');
    exit;
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
    elseif (isset($_POST['update_weekly_holidays'])) {
        // সাপ্তাহিক ছুটি আপডেট করুন
        if (isset($_POST['weekly_holidays']) && is_array($_POST['weekly_holidays'])) {
            // প্রথমে সব সাপ্তাহিক ছুটি নিষ্ক্রিয় করুন
            $pdo->query("UPDATE weekly_holidays SET status = 'inactive'");
            
            // নির্বাচিত দিনগুলো সক্রিয় করুন
            foreach ($_POST['weekly_holidays'] as $day_id) {
                $day_id = intval($day_id);
                $stmt = $pdo->prepare("UPDATE weekly_holidays SET status = 'active' WHERE id = ?");
                $stmt->execute([$day_id]);
            }
            
            $_SESSION['success'] = 'সাপ্তাহিক ছুটির দিনগুলি সফলভাবে আপডেট করা হয়েছে';
        } else {
            // কোনো দিন নির্বাচন না করলে সব নিষ্ক্রিয় করুন
            $pdo->query("UPDATE weekly_holidays SET status = 'inactive'");
            $_SESSION['success'] = 'সাপ্তাহিক ছুটির দিনগুলি সফলভাবে আপডেট করা হয়েছে';
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

// সাপ্তাহিক ছুটির দিনগুলো লোড করুন
$weekly_holidays = $pdo->query("SELECT * FROM weekly_holidays ORDER BY day_number ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ছুটির দিন ব্যবস্থাপনা - কিন্ডার গার্ডেন</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Bengali Font -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    
    <style>
        body, .main-sidebar, .nav-link {
            font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;
        }
        .holiday-card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        .holiday-card .card-header {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
        }
        .holiday-table th {
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 600;
            text-align: center;
            vertical-align: middle;
            padding: 10px 5px;
        }
        .holiday-table td {
            text-align: center;
            vertical-align: middle;
            padding: 8px 5px;
        }
        .btn-sm-compact {
            padding: 0.2rem 0.4rem;
            font-size: 0.8rem;
            line-height: 1.2;
            border-radius: 0.2rem;
        }
        .weekly-holiday-card {
            background: linear-gradient(45deg, #f8f9fc, #e3e6f0);
            border-left: 4px solid #4e73df;
        }
        .active-weekly-holidays {
            background: linear-gradient(45deg, #e8f5e9, #c8e6c9);
            border-left: 4px solid #2e7d32;
        }
        .form-check-input:checked {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .holiday-badge {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Navbar -->
    <?php include '../inc/header.php'; ?>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <?php include '../inc/sidebar.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">ছুটির দিন ব্যবস্থাপনা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard.php">হোম</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/settings.php">সেটিংস</a></li>
                            <li class="breadcrumb-item active">ছুটির দিন ব্যবস্থাপনা</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
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
                    <div class="col-md-12">
                        <div class="card holiday-card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-calendar-day mr-2"></i>
                                    ছুটির দিন ব্যবস্থাপনা
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card card-primary">
                                            <div class="card-header">
                                                <h3 class="card-title">
                                                    <i class="fas fa-plus-circle mr-2"></i>
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
                                                        <button type="submit" name="update_holiday" class="btn btn-primary">
                                                            <i class="fas fa-save mr-1"></i> আপডেট করুন
                                                        </button>
                                                        <a href="holiday_management.php" class="btn btn-default">
                                                            <i class="fas fa-times mr-1"></i> বাতিল করুন
                                                        </a>
                                                    <?php else: ?>
                                                        <button type="submit" name="add_holiday" class="btn btn-primary">
                                                            <i class="fas fa-plus-circle mr-1"></i> যোগ করুন
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </div>

                                        <div class="card weekly-holiday-card mt-4">
                                            <div class="card-header">
                                                <h3 class="card-title">
                                                    <i class="fas fa-calendar-week mr-2"></i>
                                                    সাপ্তাহিক ছুটির দিন নির্ধারণ
                                                </h3>
                                            </div>
                                            <form method="POST" action="">
                                                <div class="card-body">
                                                    <div class="form-group">
                                                        <label>সাপ্তাহিক ছুটির দিন নির্বাচন করুন:</label>
                                                        <div class="row">
                                                            <?php foreach($weekly_holidays as $day): ?>
                                                                <div class="col-md-6 mb-2">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="weekly_holidays[]" 
                                                                               value="<?php echo $day['id']; ?>" 
                                                                               id="day_<?php echo $day['id']; ?>"
                                                                               <?php echo $day['status'] == 'active' ? 'checked' : ''; ?>>
                                                                        <label class="form-check-label" for="day_<?php echo $day['id']; ?>">
                                                                            <i class="fas fa-calendar-day mr-1"></i> <?php echo $day['day_name']; ?>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <button type="submit" name="update_weekly_holidays" class="btn btn-primary">
                                                        <i class="fas fa-save mr-1"></i> সংরক্ষণ করুন
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card card-secondary">
                                            <div class="card-header">
                                                <h3 class="card-title">
                                                    <i class="fas fa-list-alt mr-2"></i>
                                                    ছুটির দিনের তালিকা
                                                </h3>
                                            </div>
                                            <div class="card-body p-0">
                                                <?php if(count($holidays) > 0): ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-striped holiday-table">
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
                                                                            <span class="badge holiday-badge badge-<?php echo $holiday['status'] == 'active' ? 'success' : 'danger'; ?>">
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
                                                    </div>
                                                <?php else: ?>
                                                    <div class="p-3 text-center">
                                                        <p class="text-muted">
                                                            <i class="fas fa-calendar-times fa-2x mb-2"></i><br>
                                                            কোন ছুটির দিন যোগ করা হয়নি
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="card active-weekly-holidays mt-4">
                                            <div class="card-header">
                                                <h3 class="card-title">
                                                    <i class="fas fa-check-circle mr-2"></i>
                                                    সক্রিয় সাপ্তাহিক ছুটি
                                                </h3>
                                            </div>
                                            <div class="card-body">
                                                <?php
                                                $active_weekly_holidays = $pdo->query("SELECT * FROM weekly_holidays WHERE status = 'active' ORDER BY day_number ASC")->fetchAll();
                                                if(count($active_weekly_holidays) > 0): ?>
                                                    <div class="row">
                                                        <?php foreach($active_weekly_holidays as $day): ?>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="alert alert-success py-2">
                                                                    <i class="fas fa-calendar-check mr-2"></i> <?php echo $day['day_name']; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted text-center">
                                                        <i class="fas fa-calendar-minus fa-2x mb-2"></i><br>
                                                        কোন সাপ্তাহিক ছুটি সক্রিয় নেই
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Main Footer -->
    <?php include '../inc/footer.php'; ?>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

</body>
</html>