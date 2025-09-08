<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?php echo BASE_URL; ?>dashboard.php" class="brand-link text-center">
        <span class="brand-text font-weight-light logo-custom">কিন্ডার গার্ডেন</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="https://adminlte.io/themes/v3/dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                <a href="#" class="d-block">সুপার অ্যাডমিন</a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="nav-link">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>ড্যাশবোর্ড</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>admin/students.php" class="nav-link">
                        <i class="nav-icon fas fa-user-graduate"></i>
                        <p>শিক্ষার্থী ব্যবস্থাপনা</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>admin/classes.php" class="nav-link">
                        <i class="nav-icon fas fa-school"></i>
                        <p>শ্রেণি ব্যবস্থাপনা</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>admin/teachers.php" class="nav-link">
                        <i class="nav-icon fas fa-chalkboard-teacher"></i>
                        <p>শিক্ষক ব্যবস্থাপনা</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>admin/attendance.php" class="nav-link">
                        <i class="nav-icon fas fa-clipboard-check"></i>
                        <p>উপস্থিতি ব্যবস্থাপনা</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>admin/exam.php" class="nav-link">
                        <i class="nav-icon fas fa-book"></i>
                        <p>পরীক্ষা ব্যবস্থাপনা</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>admin/fees.php" class="nav-link">
                        <i class="nav-icon fas fa-money-bill-wave"></i>
                        <p>ফি সংগ্রহ</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>admin/reports.php" class="nav-link">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>রিপোর্ট</p>
                    </a>
                </li>
                <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/monthly_attendance.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>মাসিক হাজিরাশীট</p>
                            </a>
                        </li>

                    </ul>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-cog"></i>
                        <p>
                            সেটিংস
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/institute_info.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>প্রতিষ্ঠানের তথ্য</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/settings.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>সাধারণ সেটিংস</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item mt-3">
                    <a href="<?php echo BASE_URL; ?>logout.php" class="nav-link bg-danger">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>লগআউট</p>
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>