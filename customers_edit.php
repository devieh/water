<?php
// ============================================
// EDIT CUSTOMER - FIXED (NO NAVBAR)
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

$error = '';
$success = '';
global $encryption;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $national_id = sanitize($_POST['national_id']);
        $date_of_birth = sanitize($_POST['date_of_birth']);
        $gender = sanitize($_POST['gender']);
        $physical_address = sanitize($_POST['physical_address']);
        
        if (empty($full_name) || empty($email)) {
            $error = 'Please fill in all required fields';
        } elseif (!validateEmail($email)) {
            $error = 'Invalid email address';
        } else {
            try {
                $pdo = getDB();
                $pdo->beginTransaction();
                
                // Update user
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone_number = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$full_name, $email, $phone, $customer['user_id']]);
                
                // Encrypt sensitive data - only if not empty
                $encrypted_national_id = !empty($national_id) ? $encryption->encrypt($national_id) : null;
                $encrypted_address = !empty($physical_address) ? $encryption->encrypt($physical_address) : null;
                
                // Update customer
                $cust = $pdo->prepare("
                    UPDATE customers 
                    SET national_id = ?, date_of_birth = ?, gender = ?, physical_address = ? 
                    WHERE customer_id = ?
                ");
                $cust->execute([
                    $encrypted_national_id,
                    $date_of_birth,
                    $gender,
                    $encrypted_address,
                    $customer_id
                ]);
                
                // Log audit
                $audit = $pdo->prepare("
                    INSERT INTO audit_trail (user_id, action_type, module_name, record_id, new_data) 
                    VALUES (?, 'update', 'customers', ?, ?)
                ");
                $audit->execute([
                    $_SESSION['user_id'],
                    $customer_id,
                    json_encode(['customer_id' => $customer_id, 'full_name' => $full_name])
                ]);
                
                $pdo->commit();
                setFlash('Customer updated successfully!', 'success');
                redirect('customers.php');
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Decrypt sensitive data - with NULL check
$decrypted_national_id = '';
if (!empty($customer['national_id'])) {
    $decrypted_national_id = $encryption->decrypt($customer['national_id']);
}

$decrypted_address = '';
if (!empty($customer['physical_address'])) {
    $decrypted_address = $encryption->decrypt($customer['physical_address']);
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer - <?php echo APP_NAME; ?></title>
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
        .form-control {
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79,70,229,0.3);
        }
        .btn-secondary {
            border-radius: 8px;
            padding: 10px 24px;
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
            font-size: 0.85rem;
            color: #475569;
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
                    <small style="font-size:0.7rem; opacity:0.7; margin-left:8px;">| Edit Customer</small>
                </h4>
            </div>
            <div class="col-md-6 text-right">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
   MAIN CONTENT
   ========================================================== -->
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-user-edit" style="color: #4f46e5;"></i> Edit Customer</h4>
        <a href="customers.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Customers
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <i class="fas fa-info-circle"></i> Edit Customer Information
            <span class="float-right text-muted">Customer #: <?php echo htmlspecialchars($customer['customer_number']); ?></span>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?php echo htmlspecialchars($customer['full_name']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($customer['phone_number']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">National ID *</label>
                            <input type="text" class="form-control" name="national_id" 
                                   value="<?php echo htmlspecialchars($decrypted_national_id); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="date_of_birth" 
                                   value="<?php echo htmlspecialchars($customer['date_of_birth'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select class="form-control" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $customer['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $customer['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $customer['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="form-label">Physical Address</label>
                            <textarea class="form-control" name="physical_address" rows="2"><?php echo htmlspecialchars($decrypted_address); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Customer
                    </button>
                    <a href="customers.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================================
   SCRIPTS
   ========================================================== -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="script.js"></script>
</body>
</html>