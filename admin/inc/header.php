<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="dashboard.php" class="nav-link">হোম</a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="#" class="nav-link">যোগাযোগ</a>
        </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
        <!-- Notifications Dropdown Menu -->
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="far fa-bell"></i>
                <span class="badge badge-warning navbar-badge">15</span>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-item dropdown-header">15টি নোটিফিকেশন</span>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-envelope mr-2"></i> 4টি নতুন বার্তা
                    <span class="float-right text-muted text-sm">3 mins</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-users mr-2"></i> 8টি বন্ধুত্বের অনুরোধ
                    <span class="float-right text-muted text-sm">12 hours</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-file mr-2"></i> 3টি নতুন রিপোর্ট
                    <span class="float-right text-muted text-sm">2 days</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item dropdown-footer">সমস্ত নোটিফিকেশন দেখুন</a>
            </div>
        </li>
        <!-- Quick Add Dropdown (+) -->
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" title="কুইক অ্যাকশন">
                <i class="fas fa-plus"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-2" style="min-width: 260px;">
                <a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/add_student.php">
                    <i class="fas fa-user-plus mr-2 text-primary"></i> নতুন শিক্ষার্থী
                </a>
                <a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/add_teacher.php">
                    <i class="fas fa-chalkboard-teacher mr-2 text-info"></i> নতুন শিক্ষক
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/attendance.php">
                    <i class="fas fa-clipboard-check mr-2 text-success"></i> উপস্থিতি গ্রহণ
                </a>
                <a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/lesson_evaluation.php">
                    <i class="fas fa-clipboard-list mr-2 text-secondary"></i> লেসন ইভুলেশন
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?php echo BASE_URL; ?>exam/create_exam.php">
                    <i class="fas fa-book mr-2 text-warning"></i> নতুন পরীক্ষা তৈরি
                </a>
                <a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/certificates/print_certificate_options.php">
                    <i class="fas fa-file-alt mr-2 text-danger"></i> নতুন প্রত্যয়নপত্র
                </a>
                <a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/certificates/five_pass_certificate_genarate.php">
                    <i class="fas fa-certificate mr-2 text-success"></i> নতুন সার্টিফিকেট জেনারেটর
                </a>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../logout.php" role="button">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </li>
    </ul>
</nav>
<!-- /.navbar -->
<?php
// Append institute name to the page title at runtime to enforce pattern: "Page/Action - Institute"
// Safe no-op if $pdo not available or school_info missing
$__instName = '';
try {
        if (isset($pdo) && $pdo instanceof PDO) {
                $row = $pdo->query("SELECT name FROM school_info LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $__instName = $row['name'] ?? '';
        }
} catch (Exception $e) { $__instName = $__instName ?: ''; }
?>
<script>
(function(){
    try{
        var site = <?php echo json_encode($__instName, JSON_UNESCAPED_UNICODE); ?> || '';
        if(!site) return;
        var t = document.title || '';
        if(!t) return;
        var suffix = ' - ' + site;
        if(t.slice(-suffix.length) !== suffix){ document.title = t + suffix; }
    }catch(e){}
})();
</script>
<script>
// Fallback dropdown handler: ensures navbar dropdowns work even if Bootstrap 4's dropdown plugin isn't available
(function(){
    function initFallback(){
        var hasBs4Dropdown = !!(window.jQuery && jQuery.fn && jQuery.fn.dropdown);
        if (hasBs4Dropdown) return; // Bootstrap 4 present; use built-in behavior
        var $ = window.jQuery; if (!$) return;

        // Click to toggle for any navbar dropdown link
        $(document).on('click', '.nav-item.dropdown > .nav-link', function(e){
            e.preventDefault(); e.stopPropagation();
            var $menu = $(this).siblings('.dropdown-menu');
            // close other open menus
            $('.nav-item.dropdown .dropdown-menu.show').not($menu).removeClass('show').hide();
            // toggle current
            if ($menu.hasClass('show')) { $menu.removeClass('show').hide(); }
            else { $menu.addClass('show').css('display','block'); }
        });

        // Click outside closes any open dropdown
        $(document).on('click', function(){
            $('.nav-item.dropdown .dropdown-menu.show').removeClass('show').hide();
        });

        // Prevent menu click from bubbling to document and closing itself immediately
        $(document).on('click', '.nav-item.dropdown .dropdown-menu', function(e){ e.stopPropagation(); });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFallback);
    } else {
        initFallback();
    }
})();
</script>