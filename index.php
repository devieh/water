<?php
// ============================================
// LOGIN & REGISTER PAGE - WITH SHOW PASSWORD
// ============================================

require_once 'config.php';
require_once 'functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$activeTab = 'login';

// ============================================
// HANDLE LOGIN
// ============================================
if (isset($_POST['login'])) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Tafadhali jaza sehemu zote';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['is_active'] == 0) {
                    $error = 'Akaunti yako imezimwa. Wasiliana na msimamizi.';
                } else {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $update->execute([$user['user_id']]);
                    
                    redirect('dashboard.php');
                }
            } else {
                $error = 'Jina la mtumiaji au nenosiri si sahihi';
            }
        } catch (PDOException $e) {
            $error = 'Kuingia kumeshindwa. Tafadhali jaribu tena.';
        }
    }
}

// ============================================
// HANDLE REGISTRATION
// ============================================
if (isset($_POST['register'])) {
    $full_name = sanitize($_POST['full_name']);
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = 'Tafadhali jaza sehemu zote zinazohitajika';
        $activeTab = 'register';
    } elseif ($password !== $confirm_password) {
        $error = 'Manenosiri hayafanani';
        $activeTab = 'register';
    } elseif (strlen($password) < 6) {
        $error = 'Nenosiri lazima liwe na herufi 6 au zaidi';
        $activeTab = 'register';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Barua pepe si sahihi';
        $activeTab = 'register';
    } else {
        try {
            $pdo = getDB();
            
            $check = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            
            if ($check->rowCount() > 0) {
                $error = 'Jina la mtumiaji au barua pepe tayari zipo';
                $activeTab = 'register';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, full_name, phone_number, user_type) 
                    VALUES (?, ?, ?, ?, ?, 'customer')
                ");
                $stmt->execute([$username, $email, $hashed_password, $full_name, $phone]);
                $user_id = $pdo->lastInsertId();
                
                $customer_number = 'CUS-' . date('Y') . '-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                
                $cust = $pdo->prepare("
                    INSERT INTO customers (user_id, customer_number, registration_date) 
                    VALUES (?, ?, CURDATE())
                ");
                $cust->execute([$user_id, $customer_number]);
                
                $success = 'Usajili umefanikiwa! Sasa unaweza kuingia.';
                $activeTab = 'login';
            }
        } catch (PDOException $e) {
            $error = 'Usajili umeshindwa. Tafadhali jaribu tena.';
            $activeTab = 'register';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo APP_NAME; ?> - Ingia / Jisajili</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ==========================================================
           ROOT VARIABLES
           ========================================================== */
        :root {
            --primary: #125355;
            --primary-dark: #38ca3f;
            --primary-light: #eef2ff;
            --primary-gradient: linear-gradient(135deg, #61e546 0%, #3aed61 100%);
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #d3c7fe;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --info: #06b6d4;
            --info-light: #cffafe;
            --dark: #0f172a;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --radius: 10px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: all 0.3s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--gray-900);
            line-height: 1.6;
        }

        /* ==========================================================
           LOGIN PAGE
           ========================================================== */
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
            background-image: url('images/waterfall.webp');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
        }

        .login-page::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 24, 17, 0.7);
            z-index: 0;
        }

        .login-page .row {
            width: 100%;
            max-width: 1200px;
            position: relative;
            z-index: 1;
        }

        /* ==========================================================
           INFO SIDE
           ========================================================== */
        .info-side {
            color: #fff;
            padding: 30px 30px 20px;
            position: relative;
            z-index: 1;
        }

        .info-side .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
        }

        .info-side .brand .logo-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .info-side .brand h2 {
            font-weight: 800;
            font-size: 1.4rem;
            margin: 0;
        }

        .info-side .brand h2 span {
            color: #fac68b;
        }

        .info-side .brand p {
            opacity: 0.7;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 0;
        }

        .info-side .hero-title {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1.2;
            margin: 15px 0 10px;
        }

        .info-side .hero-title span {
            color: #fad78b;
        }

        .info-side .hero-desc {
            opacity: 0.8;
            font-size: 0.85rem;
            margin-bottom: 18px;
            max-width: 90%;
        }

        .info-side .features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 18px;
        }

        .info-side .features .feature-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.08);
            padding: 8px 12px;
            border-radius: var(--radius);
            border: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.7rem;
        }

        .info-side .features .feature-item i {
            color: #fad78b;
            font-size: 0.8rem;
            width: 18px;
        }

        .info-side .payment-info {
            background: rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-lg);
            padding: 14px 18px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .info-side .payment-info h5 {
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }

        .info-side .payment-info h5 i {
            color: #fbbf24;
            margin-right: 8px;
        }

        .info-side .payment-info p {
            font-size: 0.7rem;
            opacity: 0.8;
            margin: 0;
        }

        .info-side .payment-info .methods {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .info-side .payment-info .methods span {
            background: rgba(255, 255, 255, 0.1);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* ==========================================================
           LOGIN CARD
           ========================================================== */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: fadeInUp 0.5s ease;
            position: relative;
            z-index: 1;
        }

        .login-card .card-header {
            background: var(--primary-gradient);
            padding: 16px 24px 14px;
            text-align: center;
            color: #fff;
            border-bottom: none;
        }

        .login-card .card-header .mini-logo {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 4px;
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .login-card .card-header h4 {
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0;
        }

        .login-card .card-header p {
            opacity: 0.8;
            font-size: 0.65rem;
            margin: 2px 0 0;
        }

        .login-card .card-body {
            padding: 16px 22px 14px;
        }

        .login-card .card-footer {
            background: var(--gray-50);
            padding: 10px 22px;
            text-align: center;
            border-top: 1px solid rgba(0,0,0,0.05);
            font-size: 0.6rem;
            color: var(--gray-500);
        }

        .login-card .nav-tabs {
            border-bottom: 2px solid var(--gray-200);
            margin-bottom: 14px;
        }

        .login-card .nav-tabs .nav-link {
            border: none;
            color: var(--gray-500);
            font-weight: 600;
            font-size: 0.75rem;
            padding: 5px 0;
            background: transparent;
            transition: var(--transition);
            border-radius: 0;
            position: relative;
        }

        .login-card .nav-tabs .nav-link i {
            margin-right: 5px;
        }

        .login-card .nav-tabs .nav-link.active {
            color: var(--primary);
            background: transparent;
        }

        .login-card .nav-tabs .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: 4px 4px 0 0;
        }

        .login-card .nav-tabs .nav-link:hover {
            color: var(--primary);
        }

        /* Password Input with Show/Hide */
        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 40px !important;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0;
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        /* Register Grid */
        .register-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 14px;
        }

        .register-grid .full-width {
            grid-column: 1 / -1;
        }

        .login-card .form-group {
            margin-bottom: 10px;
        }

        .login-card .form-group label {
            font-weight: 500;
            font-size: 0.7rem;
            color: var(--gray-700);
            margin-bottom: 2px;
            display: block;
        }

        .login-card .form-group label i {
            color: var(--primary);
            width: 14px;
            font-size: 0.7rem;
        }

        .login-card .form-control {
            border-radius: var(--radius);
            padding: 7px 12px;
            border: 2px solid var(--gray-200);
            font-size: 0.75rem;
            transition: var(--transition);
            background: var(--gray-50);
            width: 100%;
            height: 36px;
        }

        .login-card .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(187, 229, 70, 0.08);
            background: #fff;
        }

        .login-card .btn-login {
            background: var(--primary-gradient);
            border: none;
            padding: 8px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            color: #fff;
            width: 100%;
            transition: var(--transition);
            cursor: pointer;
            height: 38px;
        }

        .login-card .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(229, 200, 70, 0.4);
        }

        .login-card .btn-register {
            background: linear-gradient(135deg, #94b910 0%, #639605 100%);
            border: none;
            padding: 8px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            color: #fff;
            width: 100%;
            transition: var(--transition);
            cursor: pointer;
            height: 38px;
        }

        .login-card .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(148, 185, 16, 0.4);
        }

        .alert {
            border: none;
            border-radius: var(--radius);
            padding: 8px 12px;
            font-size: 0.75rem;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: var(--success-light);
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: var(--danger-light);
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .alert .close {
            font-size: 0.8rem;
            padding: 0 5px;
        }

        .register-info {
            background: var(--gray-50);
            border-radius: var(--radius);
            padding: 8px 12px;
            margin-bottom: 10px;
            border-left: 3px solid var(--primary);
            grid-column: 1 / -1;
        }

        .register-info p {
            font-size: 0.6rem;
            margin: 0;
            color: var(--gray-600);
        }

        .register-info i {
            color: var(--primary);
            margin-right: 4px;
            font-size: 0.65rem;
        }

        /* ==========================================================
           RESPONSIVE
           ========================================================== */
        @media (max-width: 992px) {
            .info-side { display: none; }
            .login-card { max-width: 460px; margin: 0 auto; }
            .login-page { padding: 15px; }
        }

        @media (max-width: 768px) {
            .login-card .card-header { padding: 14px 18px 12px; }
            .login-card .card-body { padding: 14px 18px 12px; }
            .login-card .card-footer { padding: 8px 18px; }
            .login-card .card-header h4 { font-size: 1rem; }
            .login-card .card-header .mini-logo { width: 36px; height: 36px; font-size: 1rem; }
            .login-card .nav-tabs .nav-link { font-size: 0.7rem; }
            .login-card .form-control { padding: 6px 10px; font-size: 0.7rem; height: 32px; }
            .login-card .btn-login, .login-card .btn-register { padding: 7px; font-size: 0.75rem; height: 34px; }
            .login-card .form-group label { font-size: 0.65rem; }
            .alert { font-size: 0.7rem; padding: 6px 10px; }
            .register-grid { grid-template-columns: 1fr; gap: 8px; }
            .register-info { margin-bottom: 8px; }
            .password-toggle { font-size: 0.8rem; right: 8px; }
        }

        @media (max-width: 576px) {
            .login-card { max-width: 100%; border-radius: var(--radius-lg); }
            .login-card .card-header { padding: 12px 14px 10px; }
            .login-card .card-header h4 { font-size: 0.9rem; }
            .login-card .card-header p { font-size: 0.6rem; }
            .login-card .card-header .mini-logo { width: 30px; height: 30px; font-size: 0.9rem; margin-bottom: 3px; }
            .login-card .card-body { padding: 12px 14px 10px; }
            .login-card .card-footer { padding: 6px 14px; font-size: 0.5rem; }
            .login-card .nav-tabs .nav-link { font-size: 0.6rem; padding: 4px 0; }
            .login-card .nav-tabs .nav-link i { margin-right: 3px; font-size: 0.55rem; }
            .login-card .form-group { margin-bottom: 8px; }
            .login-card .form-group label { font-size: 0.6rem; margin-bottom: 2px; }
            .login-card .form-group label i { width: 12px; font-size: 0.6rem; }
            .login-card .form-control { padding: 5px 8px; font-size: 0.65rem; border-radius: 6px; border-width: 1.5px; height: 28px; }
            .login-card .btn-login, .login-card .btn-register { padding: 6px; font-size: 0.7rem; border-radius: 6px; height: 30px; }
            .alert { font-size: 0.6rem; padding: 5px 8px; border-radius: 6px; }
            .login-page { padding: 5px; }
            .register-grid { grid-template-columns: 1fr; gap: 6px; }
            .register-info { padding: 6px 10px; margin-bottom: 6px; }
            .register-info p { font-size: 0.55rem; }
            .password-toggle { font-size: 0.7rem; right: 6px; }
        }

        @media (max-width: 320px) {
            .login-card .card-header { padding: 8px 10px 6px; }
            .login-card .card-header h4 { font-size: 0.75rem; }
            .login-card .card-header p { font-size: 0.5rem; }
            .login-card .card-header .mini-logo { width: 24px; height: 24px; font-size: 0.7rem; }
            .login-card .card-body { padding: 8px 10px 6px; }
            .login-card .card-footer { padding: 4px 10px; font-size: 0.45rem; }
            .login-card .nav-tabs .nav-link { font-size: 0.5rem; padding: 3px 0; }
            .login-card .form-group { margin-bottom: 5px; }
            .login-card .form-group label { font-size: 0.5rem; }
            .login-card .form-control { padding: 3px 6px; font-size: 0.55rem; height: 24px; }
            .login-card .btn-login, .login-card .btn-register { padding: 4px; font-size: 0.6rem; height: 26px; }
            .alert { font-size: 0.5rem; padding: 4px 6px; }
            .login-page { padding: 3px; }
            .register-grid { gap: 4px; }
        }

        /* ==========================================================
           ANIMATIONS
           ========================================================== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card { animation: fadeInUp 0.6s ease; }
    </style>
</head>
<body class="login-page">

<div class="container">
    <div class="row align-items-center">

        <!-- ==========================================================
        LEFT SIDE - INFO
        ========================================================== -->
        <div class="col-lg-7 info-side">
            <div class="brand">
                <div class="logo-icon">
                    <i class="fas fa-tint"></i>
                </div>
                <div>
                    <h2>Water<span>MS</span></h2>
                    <p>Management System</p>
                </div>
            </div>

            <h1 class="hero-title">
                Simamia <span>Maji Yako</span> Kwa Urahisi
            </h1>
            <p class="hero-desc">
                Jisajili na upate taarifa zako za matumizi ya maji, bili, na malipo kwa njia rahisi.
            </p>

            <div class="features">
                <div class="feature-item"><i class="fas fa-water"></i> Matumizi ya Maji</div>
                <div class="feature-item"><i class="fas fa-file-invoice"></i> Bili Zako</div>
                <div class="feature-item"><i class="fas fa-credit-card"></i> Malipo Rahisi</div>
                <div class="feature-item"><i class="fas fa-chart-line"></i> Ripoti</div>
                <div class="feature-item"><i class="fas fa-bell"></i> Arifa</div>
                <div class="feature-item"><i class="fas fa-shield-alt"></i> Usalama</div>
            </div>

            <div class="payment-info">
                <h5><i class="fas fa-info-circle"></i> Jinsi ya Kulipia</h5>
                <p>Lipa kwa njia zifuatazo:</p>
                <div class="methods">
                    <span><i class="fas fa-building"></i> Ofisini</span>
                    <span><i class="fas fa-mobile-alt"></i> M-Pesa</span>
                    <span><i class="fas fa-university"></i> Benki</span>
                    <span><i class="fas fa-credit-card"></i> Online</span>
                </div>
            </div>
        </div>

        <!-- ==========================================================
        RIGHT SIDE - LOGIN CARD
        ========================================================== -->
        <div class="col-lg-5">
            <div class="login-card">

                <div class="card-header">
                    <div class="mini-logo">
                        <i class="fas fa-tint"></i>
                    </div>
                    <h4><?php echo APP_NAME; ?></h4>
                    <p>Ingia au Jisajili</p>
                </div>

                <div class="card-body">

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <!-- Tabs -->
                    <ul class="nav nav-tabs nav-justified" id="authTabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeTab == 'login' ? 'active' : ''; ?>" data-toggle="tab" href="#loginTab">
                                <i class="fas fa-sign-in-alt"></i> Ingia
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeTab == 'register' ? 'active' : ''; ?>" data-toggle="tab" href="#registerTab">
                                <i class="fas fa-user-plus"></i> Jisajili
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">

                        <!-- ==========================================================
                        LOGIN TAB
                        ========================================================== -->
                        <div class="tab-pane fade <?php echo $activeTab == 'login' ? 'show active' : ''; ?>" id="loginTab">
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Jina la Mtumiaji au Barua Pepe</label>
                                    <input type="text" class="form-control" name="username" placeholder="Andika jina lako" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-lock"></i> Nenosiri</label>
                                    <div class="password-wrapper">
                                        <input type="password" class="form-control" name="password" id="loginPassword" placeholder="Andika nenosiri lako" required>
                                        <button type="button" class="password-toggle" onclick="togglePassword('loginPassword', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" name="login" class="btn-login">
                                    <i class="fas fa-sign-in-alt"></i> Ingia
                                </button>
                            </form>
                        </div>

                        <!-- ==========================================================
                        REGISTER TAB
                        ========================================================== -->
                        <div class="tab-pane fade <?php echo $activeTab == 'register' ? 'show active' : ''; ?>" id="registerTab">
                            <form method="POST" action="">
                                <div class="register-grid">
                                    <div class="form-group">
                                        <label><i class="fas fa-user"></i> Jina Kamili</label>
                                        <input type="text" class="form-control" name="full_name" placeholder="Jina lako" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-user-tag"></i> Jina la Mtumiaji</label>
                                        <input type="text" class="form-control" name="username" placeholder="Jina la mtumiaji" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-envelope"></i> Barua Pepe</label>
                                        <input type="email" class="form-control" name="email" placeholder="Barua pepe yako" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-phone"></i> Namba ya Simu</label>
                                        <input type="text" class="form-control" name="phone" placeholder="Namba yako">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-lock"></i> Nenosiri</label>
                                        <div class="password-wrapper">
                                            <input type="password" class="form-control" name="password" id="regPassword" placeholder="Nenosiri (6+)" required>
                                            <button type="button" class="password-toggle" onclick="togglePassword('regPassword', this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-check-circle"></i> Hakikisha</label>
                                        <div class="password-wrapper">
                                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword" placeholder="Andika tena" required>
                                            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="register-info">
                                        <p>
                                            <i class="fas fa-info-circle"></i>
                                            Kwa kujisajili, unakubali kuwa mteja wetu. Utapata taarifa za matumizi yako ya maji kila mwezi.
                                        </p>
                                    </div>
                                </div>

                                <button type="submit" name="register" class="btn-register">
                                    <i class="fas fa-user-plus"></i> Jisajili Sasa
                                </button>
                            </form>
                        </div>

                    </div>

                </div>

                <div class="card-footer">
                    <i class="fas fa-tint" style="color: var(--primary);"></i>
                    &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> Haki zote zimehifadhiwa
                </div>

            </div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ==========================================================
    // TOGGLE PASSWORD - SHOW/HIDE
    // ==========================================================
    function togglePassword(inputId, button) {
        var input = document.getElementById(inputId);
        var icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }

    // ==========================================================
    // AUTO DISMISS ALERTS
    // ==========================================================
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);

    // ==========================================================
    // TABS - Maintain active tab after form submission
    // ==========================================================
    <?php if ($activeTab == 'register'): ?>
    $('#authTabs a[href="#registerTab"]').tab('show');
    <?php else: ?>
    $('#authTabs a[href="#loginTab"]').tab('show');
    <?php endif; ?>
</script>
</body>
</html>