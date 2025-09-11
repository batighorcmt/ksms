<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard - Kinder Garden</title>
    <link href="https://fonts.maateen.me/solaiman-lipi/font.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'SolaimanLipi', Arial, sans-serif;
        }
        
        body {
            background-color: #f5f7fb;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo i {
            font-size: 28px;
            margin-right: 10px;
        }
        
        .logo h1 {
            font-size: 24px;
        }
        
        .date-filter {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 30px;
        }
        
        .date-filter input {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 5px;
            padding: 5px 10px;
            color: white;
            margin: 0 10px;
        }
        
        .date-filter button {
            background: white;
            color: #4e73df;
            border: none;
            padding: 5px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
        }
        
        .card-title {
            font-size: 16px;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .card-value {
            font-size: 28px;
            font-weight: bold;
            color: #4e73df;
        }
        
        .card-progress {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin-top: 15px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            border-radius: 4px;
        }
        
        .attendance-summary {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-table {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .summary-table h2 {
            color: #4e73df;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e3e6f0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: center;
        }
        
        table th {
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 600;
        }
        
        table tr {
            border-bottom: 1px solid #e3e6f0;
        }
        
        table tr:last-child {
            border-bottom: none;
        }
        
        .present {
            color: #28a745;
        }
        
        .absent {
            color: #dc3545;
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .chart-container h2 {
            color: #4e73df;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .absent-list {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .absent-list h2 {
            color: #4e73df;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: #4e73df;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        footer {
            text-align: center;
            margin-top: 30px;
            color: #6c757d;
            padding: 20px;
            border-top: 1px solid #e3e6f0;
        }
        
        @media (max-width: 1200px) {
            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .attendance-summary {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-school"></i>
                    <h1>কিন্ডার গার্ডেন হাজিরা ড্যাশবোর্ড</h1>
                </div>
                <div class="date-filter">
                    <span>তারিখ:</span>
                    <input type="date" id="datePicker" value="2023-11-01">
                    <button onclick="loadData()">প্রদর্শন করুন</button>
                </div>
            </div>
        </header>
        
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon" style="background-color: rgba(78, 115, 223, 0.1); color: #4e73df;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="card-title">মোট শিক্ষার্থী</div>
                <div class="card-value">৩২০</div>
                <div class="card-progress">
                    <div class="progress-bar" style="width: 100%; background-color: #4e73df;"></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-icon" style="background-color: rgba(40, 167, 69, 0.1); color: #28a745;">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="card-title">উপস্থিত</div>
                <div class="card-value">২৮৫</div>
                <div class="card-progress">
                    <div class="progress-bar" style="width: 89%; background-color: #28a745;"></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-icon" style="background-color: rgba(220, 53, 69, 0.1); color: #dc3545;">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="card-title">অনুপস্থিত</div>
                <div class="card-value">৩৫</div>
                <div class="card-progress">
                    <div class="progress-bar" style="width: 11%; background-color: #dc3545;"></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-icon" style="background-color: rgba(255, 193, 7, 0.1); color: #ffc107;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="card-title">উপস্থিতির হার</div>
                <div class="card-value">৮৯%</div>
                <div class="card-progress">
                    <div class="progress-bar" style="width: 89%; background-color: #ffc107;"></div>
                </div>
            </div>
        </div>
        
        <div class="attendance-summary">
            <div class="summary-table">
                <h2>শ্রেণিভিত্তিক হাজিরা সারাংশ</h2>
                <table>
                    <thead>
                        <tr>
                            <th>শ্রেণি</th>
                            <th>শাখা</th>
                            <th>মোট ছেলে</th>
                            <th>মোট মেয়ে</th>
                            <th>উপস্থিত ছেলে</th>
                            <th>উপস্থিত মেয়ে</th>
                            <th>অনুপস্থিত ছেলে</th>
                            <th>অনুপস্থিত মেয়ে</th>
                            <th>মোট উপস্থিত</th>
                            <th>মোট অনুপস্থিত</th>
                            <th>উপস্থিতির হার</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>পঞ্চম</td>
                            <td>ক</td>
                            <td>২০</td>
                            <td>১৫</td>
                            <td class="present">১৮</td>
                            <td class="present">১৪</td>
                            <td class="absent">২</td>
                            <td class="absent">১</td>
                            <td class="present">৩২</td>
                            <td class="absent">৩</td>
                            <td>৯১%</td>
                        </tr>
                        <tr>
                            <td>পঞ্চম</td>
                            <td>খ</td>
                            <td>২২</td>
                            <td>১৮</td>
                            <td class="present">২০</td>
                            <td class="present">১৭</td>
                            <td class="absent">২</td>
                            <td class="absent">১</td>
                            <td class="present">৩৭</td>
                            <td class="absent">৩</td>
                            <td>৯৩%</td>
                        </tr>
                        <tr>
                            <td>ষষ্ঠ</td>
                            <td>ক</td>
                            <td>২৫</td>
                            <td>২০</td>
                            <td class="present">২৩</td>
                            <td class="present">১৯</td>
                            <td class="absent">২</td>
                            <td class="absent">১</td>
                            <td class="present">৪২</td>
                            <td class="absent">৩</td>
                            <td>৯৩%</td>
                        </tr>
                        <tr>
                            <td>ষষ্ঠ</td>
                            <td>খ</td>
                            <td>২৪</td>
                            <td>২১</td>
                            <td class="present">২২</td>
                            <td class="present">২০</td>
                            <td class="absent">২</td>
                            <td class="absent">১</td>
                            <td class="present">৪২</td>
                            <td class="absent">৩</td>
                            <td>৯৩%</td>
                        </tr>
                        <tr>
                            <td>সপ্তম</td>
                            <td>ক</td>
                            <td>২০</td>
                            <td>১৫</td>
                            <td class="present">১৮</td>
                            <td class="present">১৩</td>
                            <td class="absent">২</td>
                            <td class="absent">২</td>
                            <td class="present">৩১</td>
                            <td class="absent">৪</td>
                            <td>৮৯%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="chart-container">
                <h2>উপস্থিতির হার (শ্রেণিভিত্তিক)</h2>
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
        
        <div class="absent-list">
            <h2>অনুপস্থিত শিক্ষার্থীদের তালিকা</h2>
            <table>
                <thead>
                    <tr>
                        <th>ক্রমিক নং</th>
                        <th>নাম</th>
                        <th>শ্রেণি</th>
                        <th>শাখা</th>
                        <th>রোল নং</th>
                        <th>মোবাইল নং</th>
                        <th>গ্রাম</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>১</td>
                        <td>আরাফাত রহমান</td>
                        <td>পঞ্চম</td>
                        <td>ক</td>
                        <td>১২</td>
                        <td>০১৭১২৩৪৫৬৭৮</td>
                        <td>নয়াপল্লী</td>
                    </tr>
                    <tr>
                        <td>২</td>
                        <td>সুমাইয়া আক্তার</td>
                        <td>পঞ্চম</td>
                        <td>ক</td>
                        <td>১৮</td>
                        <td>০১৮৯৮৭৬৫৪৩২</td>
                        <td>পুরানবাজার</td>
                    </tr>
                    <tr>
                        <td>৩</td>
                        <td>রিফাত আহমেদ</td>
                        <td>পঞ্চম</td>
                        <td>খ</td>
                        <td>১০</td>
                        <td>০১৯১২৩৪৫৬৭৮</td>
                        <td>নতুনগাঁও</td>
                    </tr>
                    <tr>
                        <td>৪</td>
                        <td>তাসনিমা ইসলাম</td>
                        <td>ষষ্ঠ</td>
                        <td>ক</td>
                        <td>১৫</td>
                        <td>০১৭৯৮৭৬৫৪৩২</td>
                        <td>মধুপুর</td>
                    </tr>
                    <tr>
                        <td>५</td>
                        <td>জুবায়ের হোসেন</td>
                        <td>সপ্তম</td>
                        <td>ক</td>
                        <td>০৮</td>
                        <td>০১৮১২৩৪৫৬৭০</td>
                        <td>শ্যামনগর</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="action-buttons">
                <button class="btn btn-primary">
                    <i class="fas fa-download"></i> রিপোর্ট ডাউনলোড
                </button>
                <button class="btn btn-success">
                    <i class="fas fa-paper-plane"></i> এসএমএস পাঠান
                </button>
            </div>
        </div>
        
        <footer>
            <p>© ২০২৩ কিন্ডার গার্ডেন | সকল অধিকার সংরক্ষিত</p>
        </footer>
    </div>

    <script>
        // Initialize chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            const attendanceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['পঞ্চম - ক', 'পঞ্চম - খ', 'ষষ্ঠ - ক', 'ষষ্ঠ - খ', 'সপ্তম - ক'],
                    datasets: [{
                        label: 'উপস্থিতির হার (%)',
                        data: [91, 93, 93, 93, 89],
                        backgroundColor: [
                            'rgba(78, 115, 223, 0.7)',
                            'rgba(78, 115, 223, 0.7)',
                            'rgba(78, 115, 223, 0.7)',
                            'rgba(78, 115, 223, 0.7)',
                            'rgba(78, 115, 223, 0.7)'
                        ],
                        borderColor: [
                            'rgba(78, 115, 223, 1)',
                            'rgba(78, 115, 223, 1)',
                            'rgba(78, 115, 223, 1)',
                            'rgba(78, 115, 223, 1)',
                            'rgba(78, 115, 223, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        });
        
        // Function to load data based on selected date
        function loadData() {
            const date = document.getElementById('datePicker').value;
            alert('তারিখ: ' + date + ' এর জন্য ডেটা লোড হচ্ছে...');
            // In a real application, you would fetch data from the server here
        }
    </script>
</body>
</html>