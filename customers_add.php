<?php
// ============================================
// ADD CUSTOMER - FIXED VERSION
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$error = '';
$success = '';

// Get encryption instance
global $encryption;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Get form data
        $full_name = sanitize($_POST['full_name']);
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $national_id = sanitize($_POST['national_id']);
        $date_of_birth = sanitize($_POST['date_of_birth']);
        $gender = sanitize($_POST['gender']);
        $physical_address = sanitize($_POST['physical_address']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate
        if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif (!validateEmail($email)) {
            $error = 'Invalid email address';
        } else {
            // Initialize PDO variable outside try block
            $pdo = null;
            
            try {
                $pdo = getDB();
                
                // Check if username or email exists
                $check = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                $check->execute([$username, $email]);
                
                if ($check->rowCount() > 0) {
                    $error = 'Username or email already exists';
                } else {
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password_hash, full_name, phone_number, user_type) 
                        VALUES (?, ?, ?, ?, ?, 'customer')
                    ");
                    $stmt->execute([$username, $email, $hashed_password, $full_name, $phone]);
                    $user_id = $pdo->lastInsertId();
                    
                    // Generate customer number
                    $customer_number = 'CUS-' . date('Y') . '-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                    
                    // Encrypt sensitive data
                    $encrypted_national_id = $encryption->encrypt($national_id);
                    $encrypted_address = $encryption->encrypt($physical_address);
                    
                    // Insert customer
                    $cust = $pdo->prepare("
                        INSERT INTO customers (
                            user_id, customer_number, national_id, date_of_birth, gender, 
                            physical_address, registration_date
                        ) VALUES (?, ?, ?, ?, ?, ?, CURDATE())
                    ");
                    $cust->execute([
                        $user_id, 
                        $customer_number, 
                        $encrypted_national_id, 
                        $date_of_birth, 
                        $gender,
                        $encrypted_address
                    ]);
                    
                    $customer_id = $pdo->lastInsertId();
                    
                    // Log audit
                    $audit = $pdo->prepare("
                        INSERT INTO audit_trail (user_id, action_type, module_name, record_id, new_data) 
                        VALUES (?, 'create', 'customers', ?, ?)
                    ");
                    $audit->execute([
                        $_SESSION['user_id'],
                        $customer_id,
                        json_encode(['customer_number' => $customer_number, 'full_name' => $full_name])
                    ]);
                    
                    $pdo->commit();
                    setFlash('Customer added successfully!', 'success');
                    redirect('customers.php');
                }
            } catch (PDOException $e) {
                // Check if $pdo exists and transaction is active before rollback
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
    <title>Add Customer - <?php echo APP_NAME; ?></title>
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
            <h4><i class="fas fa-user-plus"></i> Add New Customer</h4>
            <a href="customers.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Customers
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
                <i class="fas fa-info-circle"></i> Customer Information
            </div>
            <div class="card-body">
                <form method="POST" action="" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" required>
                                <div class="invalid-feedback">Please enter full name</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" required>
                                <div class="invalid-feedback">Please enter username</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                                <div class="invalid-feedback">Please enter valid email</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone" placeholder="+255700000000">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">National ID *</label>
                                <input type="text" class="form-control" name="national_id" required>
                                <div class="invalid-feedback">Please enter national ID</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <select class="form-control" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Physical Address</label>
                                <textarea class="form-control" name="physical_address" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" name="password" required minlength="6">
                                <div class="invalid-feedback">Password must be at least 6 characters</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                                <div class="invalid-feedback">Please confirm password</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Customer
                        </button>
                        <a href="customers.php" class="btn btn-secondary">
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