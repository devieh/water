<?php
// ============================================
// ADD BILL FOR CUSTOMER - FIXED
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$error = '';
$success = '';

// Get customers
$customers = getCustomers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $customer_id = (int)$_POST['customer_id'];
        $billing_period_start = sanitize($_POST['billing_period_start']);
        $billing_period_end = sanitize($_POST['billing_period_end']);
        $amount = (float)$_POST['amount'];
        
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
                
                // Generate bill number
                $bill_number = 'BIL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                
                // Insert bill
                $stmt = $pdo->prepare("
                    INSERT INTO bills (
                        bill_number, customer_id, meter_id, billing_period_start, billing_period_end,
                        previous_reading, current_reading, total_consumption, tariff_id,
                        base_amount, consumption_amount, total_before_tax, tax_amount, total_amount,
                        due_date, bill_date, bill_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(?, INTERVAL 15 DAY), CURDATE(), 'issued')
                ");
                $stmt->execute([
                    $bill_number,
                    $customer_id,
                    $meter_id,
                    $billing_period_start,
                    $billing_period_end,
                    0,
                    0,
                    0,
                    1,
                    5000,
                    $amount,
                    $amount,
                    0,
                    $amount,
                    $billing_period_end
                ]);
                
                $pdo->commit();
                $success = 'Bill generated successfully: ' . $bill_number;
                
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
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Bill - <?php echo APP_NAME; ?></title>
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
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.3);
        }
        .form-control {
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            padding: 10px 15px;
        }
        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
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
                    <small style="font-size:0.7rem; opacity:0.7; margin-left:8px;">| Generate Bill</small>
                </h4>
            </div>
            <div class="col-md-6 text-right">
                <a href="dashboard.php">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="billing.php">
                    <i class="fas fa-file-invoice"></i> Billing
                </a>
                <a href="logout.php" style="color: #ff6b6b;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
   MAIN CONTENT
   ========================================================== -->
<div class="container">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-file-invoice" style="color: #4f46e5;"></i> Generate Bill for Customer</h4>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-info-circle"></i> Bill Information
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Customer <span class="text-danger">*</span></label>
                            <select class="form-control" name="customer_id" required>
                                <option value="">-- Select Customer --</option>
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
                            <label class="font-weight-bold">Amount (TSh) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="amount" 
                                   placeholder="Enter bill amount" required>
                            <small class="text-muted">Enter the total amount for this bill</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Period Start <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="billing_period_start" 
                                   value="<?php echo date('Y-m-01', strtotime('-1 month')); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Period End <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="billing_period_end" 
                                   value="<?php echo date('Y-m-t', strtotime('-1 month')); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Note:</strong> This will generate a bill for the selected customer.
                    The bill will be marked as <strong>"Issued"</strong> and the customer can pay it.
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="fas fa-file-invoice"></i> Generate Bill
                    </button>
                    <a href="billing.php" class="btn btn-secondary btn-lg px-4">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center text-muted py-4 mt-3" style="border-top: 1px solid #e9ecef; font-size:0.8rem;">
        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> — All rights reserved
    </div>

</div>

<!-- ==========================================================
   SCRIPTS
   ========================================================== -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>