<?php
// ============================================
// VIEW BILL
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$bill_id = (int)($_GET['id'] ?? 0);
if (empty($bill_id)) {
    redirect('billing.php');
}

$bill = getBillById($bill_id);
if (!$bill) {
    setFlash('Bill not found', 'danger');
    redirect('billing.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bill - <?php echo APP_NAME; ?></title>
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
                    <li class="nav-item active">
                        <a class="nav-link" href="billing.php"><i class="fas fa-file-invoice"></i> Billing</a>
                    </li>
                    <li class="nav-item">
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
            <h4><i class="fas fa-file-invoice"></i> Bill Details</h4>
            <div>
                <a href="payments_add.php?bill=<?php echo $bill_id; ?>" class="btn btn-success">
                    <i class="fas fa-credit-card"></i> Record Payment
                </a>
                <a href="billing.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Bill Information
                    </div>
                    <div class="card-body">
                        <p><strong>Bill #:</strong> <?php echo htmlspecialchars($bill['bill_number']); ?></p>
                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($bill['customer_name']); ?></p>
                        <p><strong>Period:</strong> <?php echo formatDate($bill['billing_period_start']) . ' - ' . formatDate($bill['billing_period_end']); ?></p>
                        <p><strong>Due Date:</strong> <?php echo formatDate($bill['due_date']); ?></p>
                        <p><strong>Status:</strong> <?php echo getStatusBadge($bill['bill_status']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-calculator"></i> Bill Breakdown
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tr>
                                <td>Previous Reading</td>
                                <td class="text-right"><?php echo number_format($bill['previous_reading'], 2); ?> m³</td>
                            </tr>
                            <tr>
                                <td>Current Reading</td>
                                <td class="text-right"><?php echo number_format($bill['current_reading'], 2); ?> m³</td>
                            </tr>
                            <tr>
                                <td>Consumption</td>
                                <td class="text-right"><?php echo number_format($bill['total_consumption'], 2); ?> m³</td>
                            </tr>
                            <tr>
                                <td>Base Charge</td>
                                <td class="text-right">TSh <?php echo formatCurrency($bill['base_amount']); ?></td>
                            </tr>
                            <tr>
                                <td>Consumption Charge</td>
                                <td class="text-right">TSh <?php echo formatCurrency($bill['consumption_amount']); ?></td>
                            </tr>
                            <tr>
                                <td>Subtotal</td>
                                <td class="text-right">TSh <?php echo formatCurrency($bill['total_before_tax']); ?></td>
                            </tr>
                            <tr>
                                <td>Tax (<?php echo $bill['tax_amount'] > 0 ? '18%' : '0%'; ?>)</td>
                                <td class="text-right">TSh <?php echo formatCurrency($bill['tax_amount']); ?></td>
                            </tr>
                            <tr class="table-active">
                                <td><strong>Total Amount</strong></td>
                                <td class="text-right"><strong>TSh <?php echo formatCurrency($bill['total_amount']); ?></strong></td>
                            </tr>
                        </table>
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