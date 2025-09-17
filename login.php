<?php
require_once 'config.php';

if (isAuthenticated()) {
    // Redirect based on role
    switch($_SESSION['role']) {
        case 'super_admin':
            redirect('admin/dashboard.php');
            break;
        case 'teacher':
            redirect('teacher/dashboard.php');
            break;
        case 'guardian':
            redirect('guardian/dashboard.php');
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        // Redirect based on role
        switch($user['role']) {
            case 'super_admin':
                redirect('admin/dashboard.php');
                break;
            case 'teacher':
                redirect('teacher/dashboard.php');
                break;
            case 'guardian':
                redirect('guardian/dashboard.php');
                break;
        }
    } else {
        $error = "ব্যবহারকারীর নাম বা পাসওয়ার্ড ভুল";
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>লগইন - কিন্ডার গার্ডেন স্কুল</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --gradient-start: #4e73df;
            --gradient-end: #224abe;
            --text-dark: #5a5c69;
            --text-light: #858796;
            --light-bg: #f8f9fc;
        }
        
        body {
            font-family: 'SolaimanLipi', 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        body::before {
            content: "";
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1440 320'%3E%3Cpath fill='%23ffffff' fill-opacity='0.1' d='M0,128L48,117.3C96,107,192,85,288,112C384,139,480,213,576,224C672,235,768,181,864,181.3C960,181,1056,235,1152,229.3C1248,224,1344,160,1392,128L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z'%3E%3C/path%3E%3C/svg%3E");
            background-size: cover;
            background-position: bottom;
            opacity: 0.3;
        }
        
        .login-container {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            z-index: 1;
            transition: all 0.3s ease;
        }
        
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .login-header h2 {
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .login-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        .input-group-text {
            background-color: white;
            border-radius: 8px 0 0 8px;
            border-right: none;
        }
        
        .form-control:focus + .input-group-text {
            border-color: var(--primary-color);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.4);
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }
        
        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #ddd;
        }
        
        .divider span {
            padding: 0 10px;
            color: var(--text-light);
            font-size: 14px;
        }
        
        .login-footer {
            text-align: center;
            padding: 20px;
            background-color: var(--light-bg);
            border-top: 1px solid #e3e6f0;
        }
        
        .login-footer p {
            margin-bottom: 0;
            color: var(--text-light);
            font-size: 14px;
        }
        
        .floating-animation {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 0;
            overflow: hidden;
        }
        
        .floating-item {
            position: absolute;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
        }
        
        .floating-item:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation: float 15s infinite ease-in-out;
        }
        
        .floating-item:nth-child(2) {
            width: 50px;
            height: 50px;
            top: 20%;
            right: 15%;
            animation: float 12s infinite ease-in-out;
            animation-delay: 1s;
        }
        
        .floating-item:nth-child(3) {
            width: 70px;
            height: 70px;
            bottom: 15%;
            left: 15%;
            animation: float 18s infinite ease-in-out;
            animation-delay: 2s;
        }
        
        .floating-item:nth-child(4) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            right: 10%;
            animation: float 14s infinite ease-in-out;
            animation-delay: 3s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            25% {
                transform: translateY(-20px) rotate(5deg);
            }
            50% {
                transform: translateY(0) rotate(0deg);
            }
            75% {
                transform: translateY(20px) rotate(-5deg);
            }
        }
        
        .error-alert {
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .login-container {
                margin: 0 15px;
            }
            
            .login-header {
                padding: 20px;
            }
            
            .login-body {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <!-- Floating animation elements -->
    <div class="floating-animation">
        <div class="floating-item"></div>
        <div class="floating-item"></div>
        <div class="floating-item"></div>
        <div class="floating-item"></div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <h2>কিন্ডার গার্ডেন স্কুল</h2>
            <p>স্কুল ম্যানেজমেন্ট সিস্টেম</p>
        </div>
        
        <div class="login-body">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger error-alert" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">ব্যবহারকারীর নাম</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="আপনার ব্যবহারকারীর নাম লিখুন" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">পাসওয়ার্ড</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="আপনার পাসওয়ার্ড লিখুন" required>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="rememberMe">
                    <label class="form-check-label" for="rememberMe">আমাকে মনে রাখুন</label>
                </div>
                
                <button type="submit" class="btn btn-login mb-3">
                    <i class="fas fa-sign-in-alt me-2"></i>লগইন
                </button>
                
                <div class="divider">
                    <span>অথবা</span>
                </div>
                
                <div class="text-center">
                    <a href="#" class="text-decoration-none">পাসওয়ার্ড ভুলে গেছেন?</a>
                </div>
            </form>
        </div>
        
        <div class="login-footer">
            <p>&copy; ২০২৩ কিন্ডার গার্ডেন স্কুল. সকল права সংরক্ষিত.</p>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation and enhancements
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.querySelector('form');
            
            loginForm.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>লগইন করা হচ্ছে...';
                submitBtn.disabled = true;
            });
            
            // Remove error message when user starts typing
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    const errorAlert = document.querySelector('.error-alert');
                    if (errorAlert) {
                        errorAlert.remove();
                    }
                });
            });
        });
    </script>
</body>
</html>