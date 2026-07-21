<?php
// ============================================
// REPORTS & ANALYTICS - WITH BACKGROUND
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

// Get summary data
$total_customers = getTotalCustomers();
$total_meters = getTotalActiveMeters();
$total_revenue = getTotalRevenue();
$overdue_bills = getOverdueBills();

// Get monthly revenue
try {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            COUNT(*) as count,
            SUM(amount_paid) as total
        FROM payments
        WHERE payment_status = 'completed'
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    $monthly_revenue = $stmt->fetchAll();
} catch (PDOException $e) {
    $monthly_revenue = [];
}

// Get bill status distribution
try {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT 
            bill_status,
            COUNT(*) as count,
            SUM(total_amount) as total
        FROM bills
        GROUP BY bill_status
    ");
    $bill_stats = $stmt->fetchAll();
} catch (PDOException $e) {
    $bill_stats = [];
}

// Get meter status distribution
try {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT 
            meter_status,
            COUNT(*) as count
        FROM water_meters
        GROUP BY meter_status
    ");
    $meter_stats = $stmt->fetchAll();
} catch (PDOException $e) {
    $meter_stats = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ==========================================================
           BODY - BACKGROUND IMAGE
           ========================================================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            
            /* ==========================================================
               BACKGROUND IMAGE - BADILISHA HAPA
               ========================================================== */
            background-image: url('images/lake.jpg');
            /* Ikiwa picha haipo, tumia hii kutoka mtandaoni */
            /* background-image: url('https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1920'); */
            
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            position: relative;
        }

        /* Overlay - inafanya maandishi yasomeke vizuri */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.7);
            z-index: -1;
        }

        /* ==========================================================
           HEADER - BILA NAVBAR
           ========================================================== */
        .header-simple {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(10px);
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .header-simple h4 {
            margin: 0;
            font-weight: 600;
        }

        .header-simple a {
            color: rgba(255, 255, 255, 0.8);
            margin-left: 15px;
            text-decoration: none;
        }

        .header-simple a:hover {
            color: #fff;
        }

        .header-simple .logout-link {
            color: #ff6b6b;
        }

        .header-simple .logout-link:hover {
            color: #ff4444;
        }

        /* ==========================================================
           PAGE TITLE
           ========================================================== */
        .page-title-custom {
            color: #fff;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .page-title-custom i {
            color: #818cf8;
        }

        /* ==========================================================
           STATS CARDS - TRANSPARENT
           ========================================================== */
        .stats-card {
            border: none;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            color: #fff;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.18);
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        }

        .stats-card .icon {
            font-size: 2.5rem;
            opacity: 0.5;
        }

        .stats-card .number {
            font-size: 2rem;
            font-weight: 700;
            margin: 5px 0;
        }

        .stats-card .card-title {
            font-size: 0.9rem;
            opacity: 0.8;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stats-card.bg-primary { background: rgba(79, 70, 229, 0.7); backdrop-filter: blur(10px); }
        .stats-card.bg-success { background: rgba(16, 185, 129, 0.7); backdrop-filter: blur(10px); }
        .stats-card.bg-info { background: rgba(6, 182, 212, 0.7); backdrop-filter: blur(10px); }
        .stats-card.bg-danger { background: rgba(239, 68, 68, 0.7); backdrop-filter: blur(10px); }

        /* ==========================================================
           CARDS - TRANSPARENT
           ========================================================== */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
        }

        .card:hover {
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
            padding: 15px 20px;
        }

        .card-body {
            padding: 20px;
        }

        /* ==========================================================
           TABLE - TRANSPARENT
           ========================================================== */
        .table {
            margin: 0;
        }

        .table thead th {
            background: rgba(248, 250, 252, 0.3);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            color: var(--gray-600);
            border-top: none;
            padding: 10px 15px;
        }

        .table tbody td {
            padding: 10px 15px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-size: 0.85rem;
            color: var(--gray-800);
        }

        .table tbody tr:hover {
            background: rgba(0,0,0,0.02);
        }

        /* ==========================================================
           FOOTER
           ========================================================== */
        .footer-custom {
            color: rgba(255, 255, 255, 0.5) !important;
            border-top: 1px solid rgba(255, 255, 255, 0.05) !important;
        }

        .footer-custom strong {
            color: rgba(255, 255, 255, 0.7) !important;
        }

        /* ==========================================================
           RESPONSIVE
           ========================================================== */
        @media (max-width: 768px) {
            .header-simple .row {
                flex-direction: column;
                text-align: center;
            }

            .header-simple .text-right {
                text-align: center !important;
                margin-top: 10px;
            }

            .header-simple a {
                margin: 0 8px;
            }

            .stats-card .number {
                font-size: 1.5rem;
            }

            .card {
                margin: 0 5px;
            }
        }

        @media (max-width: 576px) {
            .header-simple h4 {
                font-size: 1rem;
            }

            .stats-card .number {
                font-size: 1.2rem;
            }

            .stats-card .icon {
                font-size: 1.8rem;
            }

            .table thead th {
                font-size: 0.5rem;
                padding: 5px 8px;
            }

            .table tbody td {
                padding: 5px 8px;
                font-size: 0.7rem;
            }
        }

        /* ==========================================================
           ANIMATIONS
           ========================================================== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stats-card, .card {
            animation: fadeInUp 0.4s ease forwards;
        }

        .stats-card:nth-child(2) { animation-delay: 0.05s; }
        .stats-card:nth-child(3) { animation-delay: 0.1s; }
        .stats-card:nth-child(4) { animation-delay: 0.15s; }
    </style>
</head>
<body>

<!-- ==========================================================
   HEADER - BILA NAVBAR
   ========================================================== -->
<div class="header-simple">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4>
                    <i class="fas fa-tint" style="color: #60a5fa;"></i> 
                    <?php echo APP_NAME; ?>
                    <small style="font-size:0.7rem; opacity:0.7; margin-left:8px;">| Reports</small>
                </h4>
            </div>
            <div class="col-md-6 text-right">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="billing.php"><i class="fas fa-file-invoice"></i> Billing</a>
                <a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a>
                <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
   MAIN CONTENT
   ========================================================== -->
<div class="container">

    <!-- Page Title -->
    <h4 class="page-title-custom mb-4">
        <i class="fas fa-chart-bar" style="color: #818cf8;"></i> Reports & Analytics
    </h4>

    <!-- Summary Cards -->
    <div class="row mt-3">
        <div class="col-md-3 mb-3">
            <div class="stats-card bg-primary">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Total Customers</h6>
                        <h2 class="number"><?php echo $total_customers; ?></h2>
                    </div>
                    <div class="icon"><i class="fas fa-users"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card bg-success">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Active Meters</h6>
                        <h2 class="number"><?php echo $total_meters; ?></h2>
                    </div>
                    <div class="icon"><i class="fas fa-water"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card bg-info">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Total Revenue</h6>
                        <h2 class="number">TSh <?php echo formatCurrency($total_revenue); ?></h2>
                    </div>
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card bg-danger">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Overdue Bills</h6>
                        <h2 class="number"><?php echo $overdue_bills; ?></h2>
                    </div>
                    <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mt-4">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i> Monthly Revenue
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie"></i> Bill Status Distribution
                </div>
                <div class="card-body">
                    <canvas id="billChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-2">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie"></i> Meter Status Distribution
                </div>
                <div class="card-body">
                    <canvas id="meterChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-table"></i> Quick Stats
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Total Customers</td>
                                <td><strong><?php echo $total_customers; ?></strong></td>
                            </tr>
                            <tr>
                                <td>Active Meters</td>
                                <td><strong><?php echo $total_meters; ?></strong></td>
                            </tr>
                            <tr>
                                <td>Total Revenue</td>
                                <td><strong>TSh <?php echo formatCurrency($total_revenue); ?></strong></td>
                            </tr>
                            <tr>
                                <td>Overdue Bills</td>
                                <td><strong><?php echo $overdue_bills; ?></strong></td>
                            </tr>
                            <tr>
                                <td>Total Bills</td>
                                <td>
                                    <strong>
                                        <?php 
                                            $total_bills = 0;
                                            foreach ($bill_stats as $stat) {
                                                $total_bills += $stat['count'];
                                            }
                                            echo $total_bills;
                                        ?>
                                    </strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center py-4 mt-3 footer-custom" style="font-size:0.8rem;">
        &copy; <?php echo date('Y'); ?> <strong>Water Management System</strong>  Haki zote zimehifadhiwa
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($monthly_revenue, 'month')); ?>,
            datasets: [{
                label: 'Monthly Revenue (TSh)',
                data: <?php echo json_encode(array_column($monthly_revenue, 'total')); ?>,
                backgroundColor: 'rgba(79, 70, 229, 0.6)',
                borderColor: 'rgba(79, 70, 229, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: {
                        color: '#1e293b'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#475569'
                    }
                },
                x: {
                    ticks: {
                        color: '#475569'
                    }
                }
            }
        }
    });

    // Bill Status Chart
    const billCtx = document.getElementById('billChart').getContext('2d');
    new Chart(billCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($bill_stats, 'bill_status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($bill_stats, 'count')); ?>,
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#94a3b8']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: {
                        color: '#1e293b'
                    }
                }
            }
        }
    });

    // Meter Status Chart
    const meterCtx = document.getElementById('meterChart').getContext('2d');
    new Chart(meterCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($meter_stats, 'meter_status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($meter_stats, 'count')); ?>,
                backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#94a3b8']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: {
                        color: '#1e293b'
                    }
                }
            }
        }
    });
</script>
</body>
</html>