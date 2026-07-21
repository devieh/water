<?php
// ============================================
// VIEW CUSTOMER
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$customer_id = (int)($_GET['id'] ?? 0);
if (empty($customer_id)) {
    redirect('customers.php');
}

$customer = getCustomerById($customer_id);
if (!$customer) {
    setFlash('Customer not found', 'danger');
    redirect('customers.php');
}

global $encryption;

// Decrypt sensitive data
$decrypted_national_id = $encryption->decrypt($customer['national_id']);
$decrypted_address = $encryption->decrypt($customer['physical_address']);

// Get customer's meters
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM water_meters WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $meters = $stmt->fetchAll();
} catch (PDOException $e) {
    $meters = [];
}

// Get customer's bills
$bills = getCustomerBills($customer_id);
$balance = getCustomerBalance($customer_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Customer - <?php echo APP_NAME; ?></title>
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
                    <li class="nav-item active">
                        <a class="nav-link" href="customers.php"><i class="fas fa-users"></i> Customers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="meters.php"><i class="fas fa-water"></i> Meters</a>
                    </li>
                    <li class="nav-item">
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
            <h4><i class="fas fa-user"></i> Customer Details</h4>
            <div>
                <a href="customers_edit.php?id=<?php echo $customer_id; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="customers.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
        
        <div class="row">
            <!-- Customer Info -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Personal Information
                    </div>
                    <div class="card-body">
                        <p><strong>Customer #:</strong> <?php echo htmlspecialchars($customer['customer_number']); ?></p>
                        <p><strong>Full Name:</strong> <?php echo htmlspecialchars($customer['full_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone_number']); ?></p>
                        <p><strong>National ID:</strong> <?php echo htmlspecialchars($decrypted_national_id); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo formatDate($customer['date_of_birth']); ?></p>
                        <p><strong>Gender:</strong> <?php echo ucfirst($customer['gender']); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($decrypted_address); ?></p>
                        <p><strong>Status:</strong> <?php echo getStatusBadge($customer['customer_status']); ?></p>
                        <p><strong>Registered:</strong> <?php echo formatDate($customer['registration_date']); ?></p>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="fas fa-money-bill"></i> Account Balance
                    </div>
                    <div class="card-body text-center">
                        <h2 class="text-<?php echo $balance > 0 ? 'danger' : 'success'; ?>">
                            TSh <?php echo formatCurrency($balance); ?>
                        </h2>
                        <p class="text-muted">
                            <?php echo $balance > 0 ? 'Outstanding balance' : 'No outstanding balance'; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Meters -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-water"></i> Water Meters
                        <a href="meters_add.php?customer=<?php echo $customer_id; ?>" class="btn btn-sm btn-primary float-right">
                            <i class="fas fa-plus"></i> Add Meter
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Meter No.</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Installation</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($meters) > 0): ?>
                                        <?php foreach ($meters as $meter): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($meter['meter_number']); ?></td>
                                                <td><?php echo ucfirst($meter['meter_type']); ?></td>
                                                <td><?php echo getStatusBadge($meter['meter_status']); ?></td>
                                                <td><?php echo formatDate($meter['installation_date']); ?></td>
                                                <td>
                                                    <a href="meters_view.php?id=<?php echo $meter['meter_id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No meters found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Bills -->
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="fas fa-file-invoice"></i> Recent Bills
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Bill No.</th>
                                        <th>Period</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($bills) > 0): ?>
                                        <?php foreach ($bills as $bill): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($bill['bill_number']); ?></td>
                                                <td><?php echo formatDate($bill['billing_period_start']) . ' - ' . formatDate($bill['billing_period_end']); ?></td>
                                                <td>TSh <?php echo formatCurrency($bill['total_amount']); ?></td>
                                                <td><?php echo formatDate($bill['due_date']); ?></td>
                                                <td><?php echo getStatusBadge($bill['bill_status']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No bills found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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