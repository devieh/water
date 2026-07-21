<?php
// ============================================
// ADD WATER METER
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$customer_id = (int)($_GET['customer'] ?? 0);
$error = '';
$success = '';

// Get customers for dropdown
$customers = getCustomers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $customer_id = (int)$_POST['customer_id'];
        $meter_number = sanitize($_POST['meter_number']);
        $meter_type = sanitize($_POST['meter_type']);
        $installation_date = sanitize($_POST['installation_date']);
        $meter_location = sanitize($_POST['meter_location']);
        $initial_reading = (float)$_POST['initial_reading'];
        
        if (empty($customer_id) || empty($meter_number) || empty($installation_date)) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                $pdo = getDB();
                
                // Check if meter number exists
                $check = $pdo->prepare("SELECT * FROM water_meters WHERE meter_number = ?");
                $check->execute([$meter_number]);
                
                if ($check->rowCount() > 0) {
                    $error = 'Meter number already exists';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO water_meters (
                            meter_number, customer_id, meter_type, installation_date, 
                            meter_location, initial_reading, meter_status
                        ) VALUES (?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([
                        $meter_number,
                        $customer_id,
                        $meter_type,
                        $installation_date,
                        $meter_location,
                        $initial_reading
                    ]);
                    
                    // Log audit
                    $audit = $pdo->prepare("
                        INSERT INTO audit_trail (user_id, action_type, module_name, record_id, new_data) 
                        VALUES (?, 'create', 'meters', ?, ?)
                    ");
                    $audit->execute([
                        $_SESSION['user_id'],
                        $pdo->lastInsertId(),
                        json_encode(['meter_number' => $meter_number, 'customer_id' => $customer_id])
                    ]);
                    
                    setFlash('Water meter added successfully!', 'success');
                    redirect('meters.php');
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Meter - <?php echo APP_NAME; ?></title>
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
            <h4><i class="fas fa-plus-circle"></i> Add Water Meter</h4>
            <a href="meters.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Meters
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Meter Information
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Customer *</label>
                                <select class="form-control" name="customer_id" required>
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $cust): ?>
                                        <option value="<?php echo $cust['customer_id']; ?>" 
                                            <?php echo $cust['customer_id'] == $customer_id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cust['customer_number'] . ' - ' . $cust['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Meter Number *</label>
                                <input type="text" class="form-control" name="meter_number" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Meter Type</label>
                                <select class="form-control" name="meter_type">
                                    <option value="analog">Analog</option>
                                    <option value="digital">Digital</option>
                                    <option value="smart">Smart</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Installation Date *</label>
                                <input type="date" class="form-control" name="installation_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Meter Location</label>
                                <input type="text" class="form-control" name="meter_location">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Initial Reading</label>
                                <input type="number" step="0.01" class="form-control" name="initial_reading" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Meter
                        </button>
                        <a href="meters.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>