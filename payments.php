<?php
// ============================================
// PAYMENTS - ADMIN VIEWS ALL PAYMENTS
// ============================================

require_once 'config.php';
require_once 'functions.php';

// Only staff and admin can access
if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

// Get all payments
try {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT p.*, 
               u.full_name as customer_name, 
               c.customer_number,
               b.bill_number
        FROM payments p
        JOIN customers c ON p.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        JOIN bills b ON p.bill_id = b.bill_id
        ORDER BY p.created_at DESC
    ");
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    $payments = [];
    setFlash('Error loading payments: ' . $e->getMessage(), 'danger');
}
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header-simple {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .header-simple h4 {
            margin: 0;
            font-weight: 600;
        }
        .header-simple a {
            color: white;
            margin-left: 15px;
            text-decoration: none;
        }
        .header-simple a:hover {
            color: #ddd;
        }
        .header-simple .logout-link {
            color: #ff6b6b;
        }
        .header-simple .logout-link:hover {
            color: #ff4444;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .card-header {
            background: transparent;
            border-bottom: 2px solid #f0f2f5;
            font-weight: 600;
        }
        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            color: #6c757d;
            border-top: none;
            padding: 12px 15px;
        }
        .table tbody td {
            padding: 10px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f2f5;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        .badge {
            padding: 5px 12px;
            font-weight: 500;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #cffafe; color: #0e7490; }
        .badge-secondary { background: #f1f5f9; color: #475569; }
        .search-box {
            position: relative;
        }
        .search-box input {
            padding: 6px 12px 6px 35px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            font-size: 0.8rem;
            width: 220px;
            transition: all 0.3s ease;
        }
        .search-box input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
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
            .table thead th {
                font-size: 0.6rem;
                padding: 8px 10px;
            }
            .table tbody td {
                padding: 8px 10px;
                font-size: 0.75rem;
            }
            .search-box input {
                width: 100%;
            }
        }
        @media (max-width: 576px) {
            .header-simple h4 {
                font-size: 1rem;
            }
            .table thead th {
                font-size: 0.5rem;
                padding: 5px 6px;
            }
            .table tbody td {
                padding: 5px 6px;
                font-size: 0.65rem;
            }
        }
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
                    <small style="font-size:0.7rem; opacity:0.7; margin-left:8px;">| Payments</small>
                </h4>
            </div>
            <div class="col-md-6 text-right">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="billing.php"><i class="fas fa-file-invoice"></i> Billing</a>
                <a href="payments_add.php" class="text-success"><i class="fas fa-plus-circle"></i> Add Payment</a>
                <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
   MAIN CONTENT
   ========================================================== -->
<div class="container">

    <!-- Flash Messages -->
    <?php echo displayFlash(); ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-credit-card" style="color: #4f46e5;"></i> Payments</h4>
        <div>
            <span class="badge badge-primary" style="font-size:0.9rem; padding:8px 16px;">
                Total: <?php echo count($payments); ?>
            </span>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <span><i class="fas fa-list"></i> All Payments</span>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search payments...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table" id="paymentsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Payment No.</th>
                            <th>Bill No.</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($payments) > 0): ?>
                            <?php foreach ($payments as $index => $payment): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($payment['payment_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($payment['bill_number']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                    <td><strong>TSh <?php echo formatCurrency($payment['amount_paid']); ?></strong></td>
                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                    <td>
                                        <?php 
                                        $method = str_replace('_', ' ', $payment['payment_method']);
                                        echo ucfirst($method); 
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($payment['payment_status'] == 'completed'): ?>
                                            <span class="badge badge-success"><i class="fas fa-check-circle"></i> Completed</span>
                                        <?php elseif ($payment['payment_status'] == 'pending'): ?>
                                            <span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
                                        <?php elseif ($payment['payment_status'] == 'failed'): ?>
                                            <span class="badge badge-danger"><i class="fas fa-times-circle"></i> Failed</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary"><?php echo ucfirst($payment['payment_status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:8px; color:#dee2e6;"></i>
                                    No payments recorded
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center text-muted py-4 mt-3" style="border-top: 1px solid #e9ecef; font-size:0.8rem;">
        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>  All rights reserved
    </div>

</div>

<!-- ==========================================================
   SCRIPTS
   ========================================================== -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        var value = this.value.toLowerCase();
        var rows = document.querySelectorAll('#paymentsTable tbody tr');
        
        rows.forEach(function(row) {
            var text = row.textContent.toLowerCase();
            row.style.display = text.indexOf(value) > -1 ? '' : 'none';
        });
    });
</script>
</body>
</html>