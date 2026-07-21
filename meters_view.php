<?php
// ============================================
// VIEW METER
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$meter_id = (int)($_GET['id'] ?? 0);
if (empty($meter_id)) {
    redirect('meters.php');
}

$meter = getWaterMeterById($meter_id);
if (!$meter) {
    setFlash('Meter not found', 'danger');
    redirect('meters.php');
}

$readings = getMeterReadings($meter_id);
$latest = getLatestMeterReading($meter_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Meter - <?php echo APP_NAME; ?></title>
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
                    <li class="nav-item active">
                        <a class="nav-link" href="meters.php"><i class="fas fa-water"></i> Meters</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="readings.php"><i class="fas fa-chart-line"></i> Readings</a>
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
            <h4><i class="fas fa-water"></i> Meter Details</h4>
            <div>
                <a href="meters_edit.php?id=<?php echo $meter_id; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="meters.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Meter Information
                    </div>
                    <div class="card-body">
                        <p><strong>Meter #:</strong> <?php echo htmlspecialchars($meter['meter_number']); ?></p>
                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($meter['customer_name']); ?></p>
                        <p><strong>Type:</strong> <?php echo ucfirst($meter['meter_type']); ?></p>
                        <p><strong>Status:</strong> <?php echo getStatusBadge($meter['meter_status']); ?></p>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($meter['meter_location']); ?></p>
                        <p><strong>Installation:</strong> <?php echo formatDate($meter['installation_date']); ?></p>
                        <p><strong>Initial Reading:</strong> <?php echo number_format($meter['initial_reading'], 2); ?> m³</p>
                        <?php if ($meter['last_reading_date']): ?>
                            <p><strong>Last Reading:</strong> <?php echo formatDate($meter['last_reading_date']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($latest): ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <i class="fas fa-chart-line"></i> Latest Reading
                        </div>
                        <div class="card-body">
                            <p><strong>Date:</strong> <?php echo formatDate($latest['reading_date']); ?></p>
                            <p><strong>Reading:</strong> <?php echo number_format($latest['reading_value'], 2); ?> m³</p>
                            <p><strong>Consumption:</strong> <?php echo number_format($latest['consumption'], 2); ?> m³</p>
                            <p><strong>Status:</strong> <?php echo getStatusBadge($latest['reading_status']); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i> Reading History
                        <a href="readings_add.php?meter=<?php echo $meter_id; ?>" class="btn btn-sm btn-primary float-right">
                            <i class="fas fa-plus"></i> Add Reading
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Reading (m³)</th>
                                        <th>Consumption</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($readings) > 0): ?>
                                        <?php foreach ($readings as $reading): ?>
                                            <tr>
                                                <td><?php echo formatDate($reading['reading_date']); ?></td>
                                                <td><?php echo number_format($reading['reading_value'], 2); ?></td>
                                                <td><?php echo number_format($reading['consumption'], 2); ?></td>
                                                <td><?php echo getStatusBadge($reading['reading_status']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No readings found</td>
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