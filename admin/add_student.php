<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>শিক্ষার্থী নিবন্ধন ফর্ম</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'SolaimanLipi', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 900px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .header {
            background: #4a6fc0;
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .form-container {
            padding: 20px;
        }
        
        .section-title {
            font-size: 18px;
            color: #4a6fc0;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #4a6fc0;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .col {
            flex: 1;
            padding: 0 10px;
            min-width: 250px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #4a6fc0;
            box-shadow: 0 0 0 2px rgba(74, 111, 192, 0.2);
        }
        
        input:disabled {
            background-color: #f5f5f5;
            color: #777;
        }
        
        .btn {
            background: #4a6fc0;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn:hover {
            background: #3b5aa6;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .photo-upload {
            border: 2px dashed #ddd;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .photo-upload:hover {
            border-color: #4a6fc0;
        }
        
        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 5px;
            object-fit: cover;
            margin-top: 15px;
            display: none;
            border: 1px solid #ddd;
        }
        
        .login-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #4a6fc0;
        }
        
        .login-info p {
            margin-bottom: 8px;
            color: #555;
        }
        
        .info-text {
            font-weight: 600;
            color: #4a6fc0 !important;
        }
        
        @media (max-width: 768px) {
            .col {
                flex: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>শিক্ষার্থী নিবন্ধন ফর্ম</h1>
            <p>নতুন শিক্ষার্থীর তথ্য যোগ করুন</p>
        </div>
        
        <div class="form-container">
            <form id="studentForm">
                <h3 class="section-title">ব্যক্তিগত তথ্য</h3>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label for="firstName">শিক্ষার্থীর নামের প্রথম অংশ</label>
                            <input type="text" id="firstName" name="firstName" required>
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="form-group">
                            <label for="lastName">শিক্ষার্থীর নামের শেষ অংশ</label>
                            <input type="text" id="lastName" name="lastName" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label for="class">শ্রেণী</label>
                            <select id="class" name="class" required>
                                <option value="">নির্বাচন করুন</option>
                                <option value="1">১ম শ্রেণী</option>
                                <option value="2">২য় শ্রেণী</option>
                                <option value="3">৩য় শ্রেণী</option>
                                <option value="4">৪র্থ শ্রেণী</option>
                                <option value="5">৫ম শ্রেণী</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="form-group">
                            <label for="section">শাখা</label>
                            <select id="section" name="section" required>
                                <option value="">নির্বাচন করুন</option>
                                <option value="A">ক</option>
                                <option value="B">খ</option>
                                <option value="C">গ</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label for="roll">রোল নম্বর</label>
                            <input type="number" id="roll" name="roll" required min="1">
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="form-group">
                            <label for="dob">জন্ম তারিখ</label>
                            <input type="date" id="dob" name="dob" required>
                        </div>
                    </div>
                </div>
                
                <h3 class="section-title">অভিভাবকের তথ্য</h3>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label for="fatherName">পিতার নাম</label>
                            <input type="text" id="fatherName" name="fatherName" required>
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="form-group">
                            <label for="motherName">মাতার নাম</label>
                            <input type="text" id="motherName" name="motherName" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label for="guardianRelation">অভিভাবকের সম্পর্ক</label>
                            <select id="guardianRelation" name="guardianRelation" required>
                                <option value="">নির্বাচন করুন</option>
                                <option value="father">পিতা</option>
                                <option value="mother">মাতা</option>
                                <option value="other">অন্যন্য</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="form-group">
                            <label for="guardianName">অভিভাবকের নাম</label>
                            <input type="text" id="guardianName" name="guardianName" required disabled>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label for="contact">যোগাযোগ নম্বর</label>
                            <input type="tel" id="contact" name="contact" required placeholder="01XXXXXXXXX">
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="form-group">
                            <label for="address">ঠিকানা</label>
                            <textarea id="address" name="address" rows="2" required></textarea>
                        </div>
                    </div>
                </div>
                
                <h3 class="section-title">ছবি আপলোড</h3>
                
                <div class="photo-upload">
                    <label for="photo" class="btn">
                        <i class="fas fa-camera"></i> ছবি নির্বাচন করুন
                    </label>
                    <input type="file" id="photo" name="photo" accept="image/*" style="display: none;">
                    <img id="photoPreview" class="photo-preview" alt="ছবি প্রিভিউ">
                </div>
                
                <div class="login-info">
                    <p>লগইন তথ্য:</p>
                    <p>শিক্ষার্থী আইডি: <span class="info-text" id="studentIdDisplay">নিবন্ধন完成时会生成</span></p>
                    <p>ডিফল্ট পাসওয়ার্ড: <span class="info-text">১২৩৪৫৬</span></p>
                </div>
                
                <div style="text-align: center; margin-top: 25px;">
                    <button type="submit" class="btn" style="padding: 12px 30px;">
                        <i class="fas fa-user-plus"></i> নিবন্ধন完成
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Generate student ID
            function generateStudentId() {
                const year = new Date().getFullYear().toString().substr(-2);
                const random = Math.floor(1000 + Math.random() * 9000);
                return `ST${year}${random}`;
            }
            
            // Set student ID
            document.getElementById('studentIdDisplay').textContent = generateStudentId();
            
            // Handle guardian relation change
            const relationSelect = document.getElementById('guardianRelation');
            const guardianNameInput = document.getElementById('guardianName');
            const fatherNameInput = document.getElementById('fatherName');
            const motherNameInput = document.getElementById('motherName');
            
            relationSelect.addEventListener('change', function() {
                if (this.value === 'father') {
                    guardianNameInput.value = fatherNameInput.value;
                    guardianNameInput.disabled = true;
                } else if (this.value === 'mother') {
                    guardianNameInput.value = motherNameInput.value;
                    guardianNameInput.disabled = true;
                } else {
                    guardianNameInput.value = '';
                    guardianNameInput.disabled = false;
                }
            });
            
            // Update guardian name if father/mother name changes
            fatherNameInput.addEventListener('input', function() {
                if (relationSelect.value === 'father') {
                    guardianNameInput.value = this.value;
                }
            });
            
            motherNameInput.addEventListener('input', function() {
                if (relationSelect.value === 'mother') {
                    guardianNameInput.value = this.value;
                }
            });
            
            // Photo preview
            const photoInput = document.getElementById('photo');
            const photoPreview = document.getElementById('photoPreview');
            
            photoInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        photoPreview.src = e.target.result;
                        photoPreview.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                }
            });
            
            // Form submission
            document.getElementById('studentForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate form
                if (this.checkValidity()) {
                    // Show success message (in a real application, you would send data to server)
                    alert('শিক্ষার্থী সফলভাবে নিবন্ধিত হয়েছে!');
                    this.reset();
                    photoPreview.style.display = 'none';
                    document.getElementById('studentIdDisplay').textContent = generateStudentId();
                } else {
                    alert('দয়া করে所有 প্রয়োজনীয় তথ্য পূরণ করুন।');
                }
            });
        });
    </script>
</body>
</html>