<?php
// ============================================
// GENERATE BILL - FIXED
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$error = '';
$success = '';

// Get customers with active meters
try {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT DISTINCT c.customer_id, c.customer_number, u.full_name 
        FROM customers c 
        JOIN users u ON c.user_id = u.user_id 
        JOIN water_meters m ON c.customer_id = m.customer_id 
        WHERE c.customer_status = 'active' AND m.meter_status = 'active'
    ");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    $customers = [];
    $error = 'Error loading customers: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $customer_id = (int)$_POST['customer_id'];
        $billing_period_start = sanitize($_POST['billing_period_start']);
        $billing_period_end = sanitize($_POST['billing_period_end']);
        
        if (empty($customer_id) || empty($billing_period_start) || empty($billing_period_end)) {
            $error = 'Please fill in all required fields';
        } else {
            // Initialize PDO variable
            $pdo = null;
            
            try {
                $pdo = getDB();
                $pdo->beginTransaction();
                
                // Get meter ID
                $meter = $pdo->prepare("SELECT meter_id FROM water_meters WHERE customer_id = ? AND meter_status = 'active' LIMIT 1");
                $meter->execute([$customer_id]);
                $meter_data = $meter->fetch();
                
                if (!$meter_data) {
                    throw new Exception('No active meter found for this customer');
                }
                $meter_id = $meter_data['meter_id'];
                
                // Get current reading
                $current = $pdo->prepare("
                    SELECT reading_value FROM meter_readings 
                    WHERE meter_id = ? AND reading_date <= ? 
                    ORDER BY reading_date DESC LIMIT 1
                ");
                $current->execute([$meter_id, $billing_period_end]);
                $current_data = $current->fetch();
                
                if (!$current_data) {
                    throw new Exception('No reading found for the billing period');
                }
                $current_reading = $current_data['reading_value'];
                
                // Get previous reading
                $previous = $pdo->prepare("
                    SELECT reading_value FROM meter_readings 
                    WHERE meter_id = ? AND reading_date < ? 
                    ORDER BY reading_date DESC LIMIT 1
                ");
                $previous->execute([$meter_id, $billing_period_start]);
                $previous_data = $previous->fetch();
                $previous_reading = $previous_data['reading_value'] ?? 0;
                
                $consumption = $current_reading - $previous_reading;
                
                // Get tariff
                $tariff = $pdo->prepare("
                    SELECT tariff_id, base_charge, consumption_charge, tax_rate 
                    FROM water_tariffs 
                    WHERE customer_type = 'residential' AND is_active = 1 
                    ORDER BY effective_from DESC LIMIT 1
                ");
                $tariff->execute();
                $tariff_data = $tariff->fetch();
                
                if (!$tariff_data) {
                    throw new Exception('No active tariff found');
                }
                
                $tariff_id = $tariff_data['tariff_id'];
                $base_charge = $tariff_data['base_charge'];
                $consumption_charge = $tariff_data['consumption_charge'];
                $tax_rate = $tariff_data['tax_rate'];
                
                // Calculate bill
                $consumption_amount = $consumption * $consumption_charge;
                $total_before_tax = $base_charge + $consumption_amount;
                $tax_amount = $total_before_tax * ($tax_rate / 100);
                $total_amount = $total_before_tax + $tax_amount;
                
                // Generate bill number
                $bill_number = generateReference('BIL');
                
                // Insert bill
                $bill = $pdo->prepare("
                    INSERT INTO bills (
                        bill_number, customer_id, meter_id, billing_period_start, billing_period_end,
                        previous_reading, current_reading, total_consumption, tariff_id,
                        base_amount, consumption_amount, total_before_tax, tax_amount, total_amount,
                        due_date, bill_date, bill_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(?, INTERVAL 15 DAY), CURDATE(), 'issued')
                ");
                $bill->execute([
                    $bill_number, $customer_id, $meter_id, $billing_period_start, $billing_period_end,
                    $previous_reading, $current_reading, $consumption, $tariff_id,
                    $base_charge, $consumption_amount, $total_before_tax, $tax_amount, $total_amount,
                    $billing_period_end
                ]);
                
                $bill_id = $pdo->lastInsertId();
                
                // Log audit
                $audit = $pdo->prepare("
                    INSERT INTO audit_trail (user_id, action_type, module_name, record_id, new_data) 
                    VALUES (?, 'create', 'bills', ?, ?)
                ");
                $audit->execute([
                    $_SESSION['user_id'],
                    $bill_id,
                    json_encode(['bill_number' => $bill_number, 'total_amount' => $total_amount])
                ]);
                
                $pdo->commit();
                setFlash('Bill generated successfully: ' . $bill_number, 'success');
                redirect('billing.php');
                
            } catch (Exception $e) {
                if ($pdo !== null && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            } catch (PDOException $e) {
                if ($pdo !== null && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
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
    <title>Generate Bill - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
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
            <h4><i class="fas fa-file-invoice"></i> Generate Bill</h4>
            <a href="billing.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Bills
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Bill Generation
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
                                        <option value="<?php echo $cust['customer_id']; ?>">
                                            <?php echo htmlspecialchars($cust['customer_number'] . ' - ' . $cust['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Billing Period</label>
                                <div class="row">
                                    <div class="col-6">
                                        <input type="date" class="form-control" name="billing_period_start" 
                                               value="<?php echo date('Y-m-01', strtotime('-1 month')); ?>" required>
                                    </div>
                                    <div class="col-6">
                                        <input type="date" class="form-control" name="billing_period_end" 
                                               value="<?php echo date('Y-m-t', strtotime('-1 month')); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i> 
                        Bill will be generated based on the latest meter reading for the selected period.
                        Make sure meter readings are entered before generating the bill.
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-file-invoice"></i> Generate Bill
                        </button>
                        <a href="billing.php" class="btn btn-secondary">
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