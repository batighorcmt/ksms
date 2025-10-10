<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="brand-link text-center">
        <span class="brand-text font-weight-light logo-custom">কিন্ডার গার্ডেন</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <?php
        // try to get current user info from session or helper
        $currentUser = null;
        if (function_exists('currentUser')) {
            $currentUser = call_user_func('currentUser');
        } elseif (!empty($_SESSION['user'])) {
            $currentUser = $_SESSION['user'];
        } elseif (!empty($_SESSION['user_id'])) {
            // fallback: fetch user from DB
            if (!isset($pdo)) {
                // If $pdo is not set, cannot fetch user from DB, so skip
                $pdo = null;
            }
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT full_name, photo FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        $userName = 'অ্যাডমিন';
        $userPhoto = 'https://adminlte.io/themes/v3/dist/img/user2-160x160.jpg';
        if ($currentUser) {
            if (!empty($currentUser['full_name'])) $userName = $currentUser['full_name'];
            if (!empty($currentUser['photo'])) $userPhoto = BASE_URL . 'uploads/users/' . $currentUser['photo'];
        }
        ?>
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="<?php echo htmlspecialchars($userPhoto); ?>" class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                    <a href="<?php echo BASE_URL; ?>admin/profile.php" class="d-block"><?php echo htmlspecialchars($userName); ?></a>
                    <?php
                    // determine role name (try currentUser role, session, or DB lookup)
                    $roleLabel = '';
                    if (!empty($currentUser['role'])) $roleKey = $currentUser['role'];
                    elseif (!empty($_SESSION['role'])) $roleKey = $_SESSION['role'];
                    else $roleKey = '';

                    // common mapping
                    $roleMap = [
                        'super_admin' => 'সুপার অ্যাডমিন',
                        'admin' => 'অ্যাডমিন',
                        'teacher' => 'শিক্ষক',
                        'student' => 'শিক্ষার্থী',
                        'parent' => 'অভিভাবক'
                    ];
                    if (!empty($roleKey) && isset($roleMap[$roleKey])) {
                        $roleLabel = $roleMap[$roleKey];
                    } elseif (!empty($roleKey)) {
                        $roleLabel = ucwords(str_replace(['_','-'], ' ', $roleKey));
                    }
                    if ($roleLabel) echo '<small class="text-muted d-block">'.htmlspecialchars($roleLabel).'</small>';
                    ?>
                </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <li class="nav-item has-treeview">
                    <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="nav-link">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>ড্যাশবোর্ড</p>
                    </a>
                </li>
                <li class="nav-item has-treeview">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p> শিক্ষার্থী ব্যবস্থাপনা <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/students.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>শিক্ষার্থী তালিকা</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/student_list_print.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>শিক্ষার্থী রিপোর্ট</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item has-treeview">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-school"></i>
                        <p>শ্রেণি ব্যবস্থাপনা <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/classes.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>শ্রেণি তালিকা</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/routine_list.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>ক্লাস রুটিন</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item has-treeview">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-school"></i>
                        <p>বিষয় ব্যবস্থাপনা <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/school_subjects.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>অনুমোদিত বিষয়</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/subjects.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>বিষয় তালিকা</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item has-treeview">
                    <a href="<?php echo BASE_URL; ?>admin/teachers.php" class="nav-link">
                        <i class="nav-icon fas fa-chalkboard-teacher"></i>
                        <p>শিক্ষক ব্যবস্থাপনা</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-clipboard-check"></i>
                        <p>উপস্থিতি ব্যবস্থাপনা <i class="right fas fa-angle-left"></i> </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/attendance_overview.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>উপস্থিতি ড্যাশবোর্ড</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/attendance.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>উপস্থিতি গ্রহণ</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/attendance_report.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>প্রতিদিনের রিপোর্ট প্রিন্ট</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/monthly_attendance.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>মাসিক উপস্থিতি</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/teacher_attendance_report.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>শিক্ষক উপস্থিতি রিপোর্ট (দৈনিক)</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/teacher_attendance_monthly.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>শিক্ষক উপস্থিতি রিপোর্ট (মাসিক)</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-book"></i>
                        <p>পরীক্ষা ব্যবস্থাপনা <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>exam/exam_list.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>পরীক্ষার তালিকা</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>exam/create_exam.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>নতুন পরীক্ষা তৈরি</p>
                            </a>
                        </li>
                    </ul>
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
                        <p>রিপোর্ট <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/monthly_attendance.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>মাসিক হাজিরাশীট</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>সার্টিফিকেট <i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="<?php echo BASE_URL; ?>admin/certificates/five_pass_certificate_genarate.php" class="nav-link">
                                        <i class="far fa-dot-circle nav-icon"></i>
                                        <p>সার্টিফিকেট জেনারেট</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?php echo BASE_URL; ?>admin/certificates/five_pass_certificate_list.php" class="nav-link">
                                        <i class="far fa-list-alt nav-icon"></i>
                                        <p>সার্টিফিকেট তালিকা</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?php echo BASE_URL; ?>admin/certificates/print_certificate_options.php" class="nav-link">
                                        <i class="far fa-file-pdf nav-icon"></i>
                                        <p>প্রত্যয়পত্র প্রিন্ট</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>admin/homework.php" class="nav-link">
                        <i class="nav-icon fas fa-book-open"></i>
                        <p>হোমওয়ার্ক</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>admin/lesson_evaluation.php" class="nav-link">
                        <i class="nav-icon fas fa-clipboard-list"></i>
                        <p>লেসন ইভুলেশন</p>
                    </a>
                </li>
                
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
                            <a href="<?php echo BASE_URL; ?>admin/settings/institute_info.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>প্রতিষ্ঠানের তথ্য</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/settings/holiday_management.php" class="nav-link">
                                <i class="nav-icon fas fa-calendar-day"></i>
                                <p>ছুটির দিন ব্যবস্থাপনা</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/settings.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>সাধারণ সেটিংস</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/settings/attendance_settings.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>হাজিরা সেটিংস</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/settings/sms_settings.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>এসএমএস সেটিংস</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>admin/settings/academic_year_management.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>শিক্ষাবর্ষ ব্যবস্থাপনা</p>
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