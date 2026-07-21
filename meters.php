<?php
// ============================================
// WATER METERS MANAGEMENT
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$meters = getWaterMeters();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Meters - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar (same as above) -->
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
        <?php echo displayFlash(); ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-water"></i> Water Meters</h4>
            <a href="meters_add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Meter
            </a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> All Meters
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Meter No.</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Installation</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($meters) > 0): ?>
                                <?php foreach ($meters as $index => $meter): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($meter['meter_number']); ?></td>
                                        <td><?php echo htmlspecialchars($meter['customer_name']); ?></td>
                                        <td><?php echo ucfirst($meter['meter_type']); ?></td>
                                        <td><?php echo getStatusBadge($meter['meter_status']); ?></td>
                                        <td><?php echo formatDate($meter['installation_date']); ?></td>
                                        <td>
                                            <a href="meters_view.php?id=<?php echo $meter['meter_id']; ?>" 
                                               class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                            <a href="meters_edit.php?id=<?php echo $meter['meter_id']; ?>" 
                                               class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                            <a href="meters_delete.php?id=<?php echo $meter['meter_id']; ?>" 
                                               class="btn btn-sm btn-danger delete-confirm"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No meters found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>