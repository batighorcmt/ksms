<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>শিক্ষার্থী সম্পাদনা - কিন্ডার গার্ডেন</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Bengali Font -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    
    <style>
        body, .main-sidebar, .nav-link {
            font-family: 'SolaimanLipi', 'Source Sans Pro', sans-serif;
        }
        .student-profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #3c8dbc;
        }
        .profile-image-upload {
            position: relative;
            display: inline-block;
        }
        .profile-image-upload .btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            padding: 0;
        }
        .required-field::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">শিক্ষার্থী সম্পাদনা</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">হোম</a></li>
                            <li class="breadcrumb-item"><a href="#">শিক্ষার্থী ব্যবস্থাপনা</a></li>
                            <li class="breadcrumb-item active">শিক্ষার্থী সম্পাদনা</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Notification Alerts -->
                <div class="alert alert-success alert-dismissible" style="display: none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h5><i class="icon fas fa-check"></i> সফল!</h5>
                    <span id="success-message"></span>
                </div>

                <div class="alert alert-danger alert-dismissible" style="display: none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h5><i class="icon fas fa-ban"></i> ত্রুটি!</h5>
                    <span id="error-message"></span>
                </div>

                <form id="edit-student-form" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-4">
                            <!-- Student Profile Card -->
                            <div class="card card-primary">
                                <div class="card-body box-profile">
                                    <div class="text-center profile-image-upload">
                                        <img class="student-profile-img" id="profile-image" src="https://via.placeholder.com/150" alt="শিক্ষার্থীর ছবি">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('photo-upload').click()">
                                            <i class="fas fa-camera"></i>
                                        </button>
                                        <input type="file" id="photo-upload" name="photo" accept="image/*" style="display: none;" onchange="previewImage(this)">
                                    </div>

                                    <h3 class="profile-username text-center" id="student-name">নতুন শিক্ষার্থী</h3>

                                    <p class="text-muted text-center" id="class-section">ক্লাস: - সেকশন: -</p>

                                    <ul class="list-group list-group-unbordered mb-3">
                                        <li class="list-group-item">
                                            <b>রোল নম্বর</b> <span class="float-right" id="roll-number-display">-</span>
                                        </li>
                                        <li class="list-group-item">
                                            <b>শিক্ষার্থী আইডি</b> <span class="float-right" id="student-id-display">-</span>
                                        </li>
                                        <li class="list-group-item">
                                            <b>ভর্তির তারিখ</b> <span class="float-right" id="admission-date-display">-</span>
                                        </li>
                                        <li class="list-group-item">
                                            <b>স্ট্যাটাস</b> 
                                            <span class="float-right">
                                                <span class="badge badge-success" id="status-display">সক্রিয়</span>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Guardian Information -->
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">অভিভাবকের তথ্য</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="guardian_id" class="required-field">অভিভাবক নির্বাচন করুন</label>
                                        <select class="form-control select2" id="guardian_id" name="guardian_id" style="width: 100%;" required>
                                            <option value="">অভিভাবক নির্বাচন করুন</option>
                                            <option value="1" selected>আব্দুর রহমান (01711223344)</option>
                                            <option value="2">আয়েশা আক্তার (01811223355)</option>
                                            <option value="3">মোঃ সেলিম (01911223366)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="guardian_relation" class="required-field">সম্পর্ক</label>
                                        <input type="text" class="form-control" id="guardian_relation" name="guardian_relation" value="পিতা" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header p-2">
                                    <ul class="nav nav-pills">
                                        <li class="nav-item"><a class="nav-link active" href="#personal" data-toggle="tab">ব্যক্তিগত তথ্য</a></li>
                                        <li class="nav-item"><a class="nav-link" href="#academic" data-toggle="tab">একাডেমিক তথ্য</a></li>
                                        <li class="nav-item"><a class="nav-link" href="#other" data-toggle="tab">অন্যান্য তথ্য</a></li>
                                    </ul>
                                </div>
                                <div class="card-body">
                                    <div class="tab-content">
                                        <!-- Personal Information Tab -->
                                        <div class="tab-pane active" id="personal">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="first_name" class="required-field">প্রথম নাম</label>
                                                        <input type="text" class="form-control" id="first_name" name="first_name" value="রায়ান" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="last_name" class="required-field">শেষ নাম</label>
                                                        <input type="text" class="form-control" id="last_name" name="last_name" value="আহমেদ" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="date_of_birth" class="required-field">জন্ম তারিখ</label>
                                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="2018-05-15" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="gender" class="required-field">লিঙ্গ</label>
                                                        <select class="form-control" id="gender" name="gender" required>
                                                            <option value="male" selected>ছেলে</option>
                                                            <option value="female">মেয়ে</option>
                                                            <option value="other">অন্যান্য</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="mobile_number">মোবাইল নম্বর</label>
                                                        <input type="text" class="form-control" id="mobile_number" name="mobile_number" value="০১৭১২৩৪৫৬৭৮">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="blood_group">রক্তের গ্রুপ</label>
                                                        <select class="form-control" id="blood_group" name="blood_group">
                                                            <option value="">নির্বাচন করুন</option>
                                                            <option value="A+">A+</option>
                                                            <option value="A-">A-</option>
                                                            <option value="B+">B+</option>
                                                            <option value="B-">B-</option>
                                                            <option value="AB+">AB+</option>
                                                            <option value="AB-">AB-</option>
                                                            <option value="O+">O+</option>
                                                            <option value="O-">O-</option>
                                                            <option value="B+" selected>B+</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label for="religion">ধর্ম</label>
                                                <select class="form-control" id="religion" name="religion">
                                                    <option value="">নির্বাচন করুন</option>
                                                    <option value="islam" selected>ইসলাম</option>
                                                    <option value="hinduism">হিন্দু</option>
                                                    <option value="christianity">খ্রিস্টান</option>
                                                    <option value="buddhism">বৌদ্ধ</option>
                                                    <option value="other">অন্যান্য</option>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="birth_certificate_no">জন্ম নিবন্ধন নম্বর</label>
                                                <input type="text" class="form-control" id="birth_certificate_no" name="birth_certificate_no" value="১২৩৪৫৬৭৮৯০">
                                            </div>
                                        </div>

                                        <!-- Academic Information Tab -->
                                        <div class="tab-pane" id="academic">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="class_id" class="required-field">ক্লাস</label>
                                                        <select class="form-control" id="class_id" name="class_id" required>
                                                            <option value="">ক্লাস নির্বাচন করুন</option>
                                                            <option value="1" selected>নার্সারি</option>
                                                            <option value="2">কেজি-১</option>
                                                            <option value="3">কেজি-২</option>
                                                            <option value="4">প্রথম শ্রেণী</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="section_id" class="required-field">সেকশন</label>
                                                        <select class="form-control" id="section_id" name="section_id" required>
                                                            <option value="">সেকশন নির্বাচন করুন</option>
                                                            <option value="1">A</option>
                                                            <option value="2" selected>B</option>
                                                            <option value="3">C</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="roll_number" class="required-field">রোল নম্বর</label>
                                                        <input type="text" class="form-control" id="roll_number" name="roll_number" value="১০" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="student_id" class="required-field">শিক্ষার্থী আইডি</label>
                                                        <input type="text" class="form-control" id="student_id" name="student_id" value="KG2023001" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="admission_date" class="required-field">ভর্তির তারিখ</label>
                                                        <input type="date" class="form-control" id="admission_date" name="admission_date" value="2023-01-01" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="status" class="required-field">স্ট্যাটাস</label>
                                                        <select class="form-control" id="status" name="status" required>
                                                            <option value="active" selected>সক্রিয়</option>
                                                            <option value="inactive">নিষ্ক্রিয়</option>
                                                            <option value="graduated">পাস আউট</option>
                                                            <option value="transferred">স্থানান্তরিত</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Other Information Tab -->
                                        <div class="tab-pane" id="other">
                                            <div class="form-group">
                                                <label for="present_address" class="required-field">বর্তমান ঠিকানা</label>
                                                <textarea class="form-control" id="present_address" name="present_address" rows="3" required>১২/৩, মিরপুর, ঢাকা</textarea>
                                            </div>

                                            <div class="form-group">
                                                <label for="permanent_address">স্থায়ী ঠিকানা</label>
                                                <textarea class="form-control" id="permanent_address" name="permanent_address" rows="3">১২/৩, মিরপুর, ঢাকা</textarea>
                                            </div>

                                            <div class="form-group">
                                                <label for="previous_school">পূর্ববর্তী স্কুল (যদি থাকে)</label>
                                                <input type="text" class="form-control" id="previous_school" name="previous_school" value="কিডজি স্কুল">
                                            </div>

                                            <div class="form-group">
                                                <label for="notes">মন্তব্য</label>
                                                <textarea class="form-control" id="notes" name="notes" rows="3">সবসময় সময়মতো স্কুলে উপস্থিত হয়</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> আপডেট করুন
                                    </button>
                                    <button type="button" class="btn btn-default float-right">
                                        <i class="fas fa-times"></i> বাতিল করুন
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <!-- Main Footer -->
    <footer class="main-footer">
        <div class="float-right d-none d-sm-block">
            <b>Version</b> 1.0.0
        </div>
        <strong>কপিরাইট &copy; 2023 <a href="#">কিন্ডার গার্ডেন</a>.</strong> সকল права সংরক্ষিত।
    </footer>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize Select2
        $('.select2').select2();

        // Update display fields based on form inputs
        function updateDisplayFields() {
            $('#student-name').text($('#first_name').val() + ' ' + $('#last_name').val());
            $('#class-section').text('ক্লাস: ' + $('#class_id option:selected').text() + ' - সেকশন: ' + $('#section_id option:selected').text());
            $('#roll-number-display').text($('#roll_number').val());
            $('#student-id-display').text($('#student_id').val());
            $('#admission-date-display').text(formatDate($('#admission_date').val()));
            
            if ($('#status').val() === 'active') {
                $('#status-display').removeClass('badge-danger badge-warning').addClass('badge-success').text('সক্রিয়');
            } else {
                $('#status-display').removeClass('badge-success').addClass('badge-danger').text('নিষ্ক্রিয়');
            }
        }

        // Format date to dd/mm/yyyy
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            const day = date.getDate().toString().padStart(2, '0');
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        }

        // Initial update of display fields
        updateDisplayFields();

        // Update display fields when form values change
        $('#edit-student-form').on('input change', 'input, select', function() {
            updateDisplayFields();
        });

        // Form submission
        $('#edit-student-form').on('submit', function(e) {
            e.preventDefault();
            
            // Simulate successful submission
            $('.alert-success').fadeIn();
            $('#success-message').text('শিক্ষার্থীর তথ্য সফলভাবে আপডেট করা হয়েছে।');
            
            // Hide success message after 5 seconds
            setTimeout(function() {
                $('.alert-success').fadeOut();
            }, 5000);
        });

        // Image preview function
        window.previewImage = function(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#profile-image').attr('src', e.target.result);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    });
</script>
</body>
</html>