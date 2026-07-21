<?php
// ============================================
// USER PROFILE
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$user = getCurrentUser();
$customer = null;
if ($user['user_type'] == 'customer') {
    $customer = getCustomerByUserId($user['user_id']);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';
    
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        if ($action === 'profile') {
            // Update profile
            $full_name = sanitize($_POST['full_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            
            if (empty($full_name) || empty($email)) {
                $error = 'Please fill in all required fields';
            } elseif (!validateEmail($email)) {
                $error = 'Invalid email address';
            } else {
                try {
                    $pdo = getDB();
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET full_name = ?, email = ?, phone_number = ? 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$full_name, $email, $phone, $_SESSION['user_id']]);
                    
                    $_SESSION['full_name'] = $full_name;
                    setFlash('Profile updated successfully!', 'success');
                    redirect('profile.php');
                    
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'password') {
            // Change password
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'Please fill in all password fields';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match';
            } elseif (strlen($new_password) < 6) {
                $error = 'New password must be at least 6 characters';
            } else {
                try {
                    $pdo = getDB();
                    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user_data = $stmt->fetch();
                    
                    if (!password_verify($current_password, $user_data['password_hash'])) {
                        $error = 'Current password is incorrect';
                    } else {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                        $update->execute([$hashed_password, $_SESSION['user_id']]);
                        
                        setFlash('Password changed successfully!', 'success');
                        redirect('profile.php');
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
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
    <title>Profile - <?php echo APP_NAME; ?></title>
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
                    <?php if (hasPermission('staff')): ?>
                        <li class="nav-item">
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
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown active">
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
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="profile-avatar mx-auto mb-3 bg-primary text-white d-flex align-items-center justify-content-center" 
                             style="width: 100px; height: 100px; border-radius: 50%; font-size: 40px; margin: 0 auto;">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <h5 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                        <p class="profile-role">
                            <span class="badge badge-<?php echo $user['user_type'] == 'admin' ? 'danger' : ($user['user_type'] == 'staff' ? 'info' : 'success'); ?>">
                                <?php echo ucfirst($user['user_type']); ?>
                            </span>
                        </p>
                        <p class="text-muted">
                            <i class="fas fa-calendar"></i> Member since <?php echo formatDate($user['created_at']); ?>
                        </p>
                        <?php if ($user['last_login']): ?>
                            <p class="text-muted">
                                <i class="fas fa-clock"></i> Last login: <?php echo timeAgo($user['last_login']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($customer): ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <i class="fas fa-user-tag"></i> Customer Details
                        </div>
                        <div class="card-body">
                            <p><strong>Customer #:</strong> <?php echo htmlspecialchars($customer['customer_number']); ?></p>
                            <p><strong>Status:</strong> <?php echo getStatusBadge($customer['customer_status']); ?></p>
                            <p><strong>Registered:</strong> <?php echo formatDate($customer['registration_date']); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-edit"></i> Edit Profile
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="profile">
                            
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone_number']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="fas fa-key"></i> Change Password
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="password">
                            
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required minlength="6">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
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