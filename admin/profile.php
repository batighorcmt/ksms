<?php
require_once '../config.php';

if (!isAuthenticated()) {
    redirect('../index.php');
}

$currentUser = null;
// Try currentUser() helper first, then common session keys, then DB lookup by id if possible
if (function_exists('currentUser')) {
    $currentUser = call_user_func('currentUser');
}
if (!$currentUser && !empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $currentUser = $_SESSION['user'];
}
// try a few common session keys for user id
if (!$currentUser) {
    $uid = null;
    if (!empty($_SESSION['user_id'])) $uid = $_SESSION['user_id'];
    if (empty($uid) && !empty($_SESSION['userid'])) $uid = $_SESSION['userid'];
    if (empty($uid) && !empty($_SESSION['id'])) $uid = $_SESSION['id'];
    if (empty($uid) && !empty($_SESSION['uid'])) $uid = $_SESSION['uid'];
    if (empty($uid) && !empty($_SESSION['user']['id'])) $uid = $_SESSION['user']['id'];
    if (!empty($uid)) {
        $stmt = $pdo->prepare('SELECT id,full_name,email,photo FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if ($row) {
            $currentUser = $row;
            $_SESSION['user'] = $row;
        }
    }
}
// ensure it's at least an array to avoid warnings
if (!$currentUser || !is_array($currentUser)) {
    $currentUser = ['id' => null, 'full_name' => '', 'email' => '', 'photo' => null];
}

$errors = [];
$success = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new === '') $errors[] = 'নতুন পাসওয়ার্ড প্রদান করুন।';
    if ($new !== $confirm) $errors[] = 'নতুন পাসওয়ার্ড এবং নিশ্চিত পাসওয়ার্ড মিলছে না।';

    if (empty($errors)) {
        // fetch fresh user from DB
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$currentUser['id']]);
        $user = $stmt->fetch();
        if (!$user) {
            $errors[] = 'ব্যবহারকারী পাওয়া যায়নি।';
        } elseif (!password_verify($current, $user['password'])) {
            $errors[] = 'বর্তমান পাসওয়ার্ড সঠিক নয়।';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $u = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            if ($u->execute([$hash, $currentUser['id']])) {
                $success = 'পাসওয়ার্ড সফলভাবে পরিবর্তিত হয়েছে।';
            } else {
                $errors[] = 'পাসওয়ার্ড আপডেটে সমস্যা হয়েছে।';
            }
        }
    }
}

// Handle profile update (name + photo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (empty($currentUser['id'])) {
        $errors[] = 'ব্যবহারকারী সনাক্ত করা যায়নি।';
    } else {
        $newName = trim($_POST['full_name'] ?? '');
        if ($newName === '') {
            $errors[] = 'নাম প্রদান করুন।';
        }

        // photo upload
        $photoFile = null;
        if (!empty($_FILES['photo']['name'])) {
            $f = $_FILES['photo'];
            if ($f['error'] === 0) {
                $allowed = ['image/jpeg','image/png','image/webp'];
                if (!in_array($f['type'], $allowed)) {
                    $errors[] = 'ছবি অবশ্যই jpg/png/webp ফরম্যাটে হতে হবে।';
                } else {
                    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                    $photoFile = 'user_' . $currentUser['id'] . '_' . time() . '.' . $ext;
                    $dst = __DIR__ . '/../uploads/users/' . $photoFile;
                    if (!is_dir(dirname($dst))) { mkdir(dirname($dst), 0755, true); }
                    if (!move_uploaded_file($f['tmp_name'], $dst)) {
                        $errors[] = 'ছবি আপলোড করতে ব্যর্থ হয়েছে।';
                    }
                }
            } else {
                $errors[] = 'ছবি আপলোডে ত্রুটি।';
            }
        }

        if (empty($errors)) {
            if ($photoFile) {
                $u = $pdo->prepare('UPDATE users SET full_name = ?, photo = ? WHERE id = ?');
                $ok = $u->execute([$newName, $photoFile, $currentUser['id']]);
            } else {
                $u = $pdo->prepare('UPDATE users SET full_name = ? WHERE id = ?');
                $ok = $u->execute([$newName, $currentUser['id']]);
            }
            if (!empty($ok)) {
                // refresh current user info in session and local var
                $stmt = $pdo->prepare('SELECT id,full_name,email,photo FROM users WHERE id = ?');
                $stmt->execute([$currentUser['id']]);
                $fresh = $stmt->fetch();
                if ($fresh) {
                    $_SESSION['user'] = $fresh;
                    $currentUser = $fresh;
                }
                $success = 'প্রোফাইল সফলভাবে আপডেট হয়েছে।';
            } else {
                $errors[] = 'প্রোফাইল আপডেটে সমস্যা হয়েছে।';
            }
        }
    }
}

?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>প্রোফাইল</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>body{font-family:SolaimanLipi,Arial,sans-serif}</style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'inc/header.php'; ?>
    <?php include 'inc/sidebar.php'; ?>
    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid py-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">প্রোফাইল</div>
                    <div class="card-body">
                        <?php if(!empty($errors)): ?>
                            <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
                        <?php endif; ?>
                        <?php if($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-4 text-center">
                                <?php $photo = (!empty($currentUser['photo']) ? BASE_URL.'uploads/users/'.$currentUser['photo'] : 'https://adminlte.io/themes/v3/dist/img/user2-160x160.jpg'); ?>
                                <img id="profilePhotoPreview" src="<?php echo htmlspecialchars($photo); ?>" class="img-fluid rounded mb-3" alt="profile">
                                <h5 id="profileNameHeading"><?php echo htmlspecialchars($currentUser['full_name'] ?? $currentUser['email'] ?? ''); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></p>

                                <hr>
                                <form method="post" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label>নাম</label>
                                        <input name="full_name" id="nameInput" class="form-control" value="<?php echo htmlspecialchars($currentUser['full_name'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>প্রোফাইল ছবি (ঐচ্ছিক)</label>
                                        <input type="file" name="photo" id="photoInput" accept="image/*" class="form-control-file">
                                    </div>
                                    <div class="form-group text-right">
                                        <button class="btn btn-primary" name="update_profile" value="1">প্রোফাইল আপডেট করুন</button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <h5>অ্যাকাউন্ট</h5>
                                <table class="table table-sm">
                                    <tr><th>নাম</th><td><?php echo htmlspecialchars($currentUser['full_name'] ?? ''); ?></td></tr>
                                    <tr><th>ইমেইল</th><td><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></td></tr>
                                </table>

                                <hr>
                                <h5>পাসওয়ার্ড পরিবর্তন</h5>
                                <form method="post">
                                    <div class="form-group">
                                        <label>বর্তমান পাসওয়ার্ড</label>
                                        <input type="password" name="current_password" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>নতুন পাসওয়ার্ড</label>
                                        <input type="password" name="new_password" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>নতুন পাসওয়ার্ড নিশ্চিত করুন</label>
                                        <input type="password" name="confirm_password" class="form-control">
                                    </div>
                                    <div class="form-group text-right">
                                        <button class="btn btn-primary" name="change_password" value="1">পাসওয়ার্ড পরিবর্তন</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php include 'inc/footer.php'; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
$(function(){
    // preview photo
    $('#photoInput').on('change', function(){
        var f = this.files[0];
        if(!f) return;
        var reader = new FileReader();
        reader.onload = function(e){
            $('#profilePhotoPreview').attr('src', e.target.result);
        }
        reader.readAsDataURL(f);
    });
    // live name update
    $('#nameInput').on('input', function(){
        $('#profileNameHeading').text($(this).val());
    });
});
</script>
</body>
</html>
