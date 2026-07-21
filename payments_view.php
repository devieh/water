<?php
// ============================================
// VIEW PAYMENT
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$payment_id = (int)($_GET['id'] ?? 0);
if (empty($payment_id)) {
    redirect('payments.php');
}

$payment = getPaymentById($payment_id);
if (!$payment) {
    setFlash('Payment not found', 'danger');
    redirect('payments.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Payment - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tint"></i> <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customers.php"><i class="fas fa-users"></i> Customers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="meters.php"><i class="fas fa-water"></i> Meters</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="billing.php"><i class="fas fa-file-invoice"></i> Billing</a>
                    </li>
                    <li class="nav-item active">
                        <a class="nav-link" href="payments.php"><i class="fas fa-credit-card"></i> Payments</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-credit-card"></i> Payment Details</h4>
            <a href="payments.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Payments
            </a>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Payment Information
                    </div>
                    <div class="card-body">
                        <p><strong>Payment #:</strong> <?php echo htmlspecialchars($payment['payment_number']); ?></p>
                        <p><strong>Bill #:</strong> <?php echo htmlspecialchars($payment['bill_number']); ?></p>
                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($payment['customer_name']); ?></p>
                        <p><strong>Amount:</strong> TSh <?php echo formatCurrency($payment['amount_paid']); ?></p>
                        <p><strong>Date:</strong> <?php echo formatDate($payment['payment_date']); ?></p>
                        <p><strong>Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></p>
                        <p><strong>Status:</strong> <?php echo getStatusBadge($payment['payment_status']); ?></p>
                        <?php if ($payment['transaction_reference']): ?>
                            <p><strong>Reference:</strong> <?php echo htmlspecialchars($payment['transaction_reference']); ?></p>
                        <?php endif; ?>
                        <?php if ($payment['notes']): ?>
                            <p><strong>Notes:</strong> <?php echo htmlspecialchars($payment['notes']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-file-invoice"></i> Bill Details
                    </div>
                    <div class="card-body">
                        <?php 
                            $bill = getBillById($payment['bill_id']);
                            if ($bill): 
                        ?>
                            <p><strong>Bill #:</strong> <?php echo htmlspecialchars($bill['bill_number']); ?></p>
                            <p><strong>Period:</strong> <?php echo formatDate($bill['billing_period_start']) . ' - ' . formatDate($bill['billing_period_end']); ?></p>
                            <p><strong>Total Amount:</strong> TSh <?php echo formatCurrency($bill['total_amount']); ?></p>
                            <p><strong>Due Date:</strong> <?php echo formatDate($bill['due_date']); ?></p>
                            <p><strong>Status:</strong> <?php echo getStatusBadge($bill['bill_status']); ?></p>
                        <?php else: ?>
                            <p class="text-muted">Bill details not available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>