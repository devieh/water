<?php
// ============================================
// BILLING - ADMIN VIEWS ALL BILLS
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$bills = getBills();
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .header-simple {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .header-simple h4 { margin: 0; font-weight: 600; }
        .header-simple a { color: white; margin-left: 15px; text-decoration: none; }
        .header-simple a:hover { color: #ddd; }
        .header-simple .logout-link { color: #ff6b6b; }
        .header-simple .logout-link:hover { color: #ff4444; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .card-header { background: transparent; border-bottom: 2px solid #f0f2f5; font-weight: 600; }
        .table thead th { background: #f8f9fa; border-bottom: 2px solid #e9ecef; font-weight: 600; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.5px; color: #6c757d; border-top: none; padding: 12px 15px; }
        .table tbody td { padding: 10px 15px; vertical-align: middle; border-bottom: 1px solid #f0f2f5; }
        .table tbody tr:hover { background: #f8f9fa; }
        .badge { padding: 5px 12px; font-weight: 500; border-radius: 20px; font-size: 0.7rem; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #cffafe; color: #0e7490; }
        @media (max-width: 768px) {
            .header-simple .row { flex-direction: column; text-align: center; }
            .header-simple .text-right { text-align: center !important; margin-top: 10px; }
            .header-simple a { margin: 0 8px; }
        }
        @media (max-width: 576px) {
            .header-simple h4 { font-size: 1rem; }
            .table thead th { font-size: 0.5rem; padding: 5px 6px; }
            .table tbody td { padding: 5px 6px; font-size: 0.65rem; }
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
                    <small style="font-size:0.7rem; opacity:0.7; margin-left:8px;">| Billing</small>
                </h4>
            </div>
            <div class="col-md-6 text-right">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a>
                <a href="billing_generate.php" class="text-success"><i class="fas fa-plus-circle"></i> Generate Bill</a>
                <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
   MAIN CONTENT
   ========================================================== -->
<div class="container">

    <?php echo displayFlash(); ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-file-invoice" style="color: #4f46e5;"></i> Billing</h4>
        <span class="badge badge-primary" style="font-size:0.9rem; padding:8px 16px;">
            Total: <?php echo count($bills); ?>
        </span>
    </div>

    <div class="card">
        <div class="card-header">
            <span><i class="fas fa-list"></i> All Bills</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bill No.</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bills) > 0): ?>
                            <?php foreach ($bills as $bill): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($bill['bill_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($bill['customer_name']); ?></td>
                                    <td><strong>TSh <?php echo formatCurrency($bill['total_amount']); ?></strong></td>
                                    <td><?php echo formatDate($bill['due_date']); ?></td>
                                    <td>
                                        <?php if ($bill['bill_status'] == 'paid'): ?>
                                            <span class="badge badge-success"><i class="fas fa-check-circle"></i> Paid</span>
                                        <?php elseif ($bill['bill_status'] == 'overdue'): ?>
                                            <span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
                                        <?php elseif ($bill['bill_status'] == 'issued'): ?>
                                            <span class="badge badge-info"><i class="fas fa-clock"></i> Issued</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary"><?php echo ucfirst($bill['bill_status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($bill['bill_status'] != 'paid'): ?>
                                            <a href="payments_add.php?bill=<?php echo $bill['bill_id']; ?>" 
                                               class="btn btn-sm btn-success">Pay</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:8px; color:#dee2e6;"></i>
                                    No bills recorded
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="text-center text-muted py-4 mt-3" style="border-top: 1px solid #e9ecef; font-size:0.8rem;">
        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>  All rights reserved
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="script.js"></script>
</body>
</html>