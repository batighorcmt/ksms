<?php
// 403 error page
http_response_code(403);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>অনুমতি নেই - কিন্ডার গার্ডেন স্কুল</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #dc3545;
            --secondary-color: #1cc88a;
            --gradient-start: #dc3545;
            --gradient-end: #a71d2a;
            --text-dark: #5a5c69;
            --text-light: #858796;
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
        
        .error-container {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 600px;
            z-index: 1;
            text-align: center;
            padding: 40px;
        }
        
        .error-icon {
            font-size: 8rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .error-title {
            font-size: 3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        
        .error-message {
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 30px;
        }
        
        .btn-home {
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
            color: white;
        }
        
        .btn-back {
            background: transparent;
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            padding: 10px 28px;
            font-weight: 600;
            color: var(--primary-color);
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        
        .btn-back:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .error-container {
                margin: 0 15px;
                padding: 30px 20px;
            }
            
            .error-icon {
                font-size: 6rem;
            }
            
            .error-title {
                font-size: 2.5rem;
            }
            
            .error-message {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-ban"></i>
        </div>
        
        <h1 class="error-title">৪০৩</h1>
        
        <p class="error-message">দুঃখিত, এই ফোল্ডারে অ্যাক্সেসের অনুমতি আপনার নেই।</p>
        
        <div class="error-actions">
            <a href="javascript:history.back()" class="btn-back">
                <i class="fas fa-arrow-left me-2"></i>পিছনে যান
            </a>
            <a href="<?php echo $base_url; ?>" class="btn-home">
                <i class="fas fa-home me-2"></i>হোম পেজ
            </a>
        </div>
        
        <div class="mt-4">
            <p class="text-muted">আপনি যদি মনে করেন এটি একটি ত্রুটি, তাহলে আমাদের <a href="<?php echo $base_url; ?>contact.php" class="text-decoration-none">সহায়তা কেন্দ্র</a> এ যোগাযোগ করুন</p>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>