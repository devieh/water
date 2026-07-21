<?php
// ============================================
// FEEDBACK MANAGEMENT - ADMIN ONLY
// ============================================

require_once 'config.php';
require_once 'functions.php';

// Only admin and staff can access
if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$error = '';
$success = '';

// Get all feedback with customer details
try {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT f.*, 
               u.full_name as customer_name, 
               u.email as customer_email,
               u.phone_number as customer_phone,
               c.customer_number,
               c.physical_address
        FROM feedback f
        JOIN customers c ON f.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        ORDER BY 
            CASE f.status 
                WHEN 'pending' THEN 1 
                WHEN 'reviewed' THEN 2 
                WHEN 'responded' THEN 3 
                WHEN 'closed' THEN 4 
            END,
            f.created_at DESC
    ");
    $feedbacks = $stmt->fetchAll();
} catch (PDOException $e) {
    $feedbacks = [];
    $error = 'Error loading feedback: ' . $e->getMessage();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $feedback_id = (int)$_POST['feedback_id'];
        $status = sanitize($_POST['status']);
        $response = sanitize($_POST['response']);
        
        if (empty($feedback_id)) {
            $error = 'Invalid feedback ID.';
        } else {
            try {
                $pdo = getDB();
                $stmt = $pdo->prepare("
                    UPDATE feedback 
                    SET status = ?, response = ?, responded_by = ?, response_date = NOW()
                    WHERE feedback_id = ?
                ");
                $stmt->execute([$status, $response, $_SESSION['user_id'], $feedback_id]);
                $success = 'Feedback status updated successfully!';
                
                // Refresh data
                $stmt = $pdo->query("
                    SELECT f.*, 
                           u.full_name as customer_name, 
                           u.email as customer_email,
                           u.phone_number as customer_phone,
                           c.customer_number,
                           c.physical_address
                    FROM feedback f
                    JOIN customers c ON f.customer_id = c.customer_id
                    JOIN users u ON c.user_id = u.user_id
                    ORDER BY 
                        CASE f.status 
                            WHEN 'pending' THEN 1 
                            WHEN 'reviewed' THEN 2 
                            WHEN 'responded' THEN 3 
                            WHEN 'closed' THEN 4 
                        END,
                        f.created_at DESC
                ");
                $feedbacks = $stmt->fetchAll();
            } catch (PDOException $e) {
                $error = 'Error updating feedback: ' . $e->getMessage();
            }
        }
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete'])) {
    $feedback_id = (int)$_GET['delete'];
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM feedback WHERE feedback_id = ?");
        $stmt->execute([$feedback_id]);
        $success = 'Feedback deleted successfully!';
        
        // Refresh data
        $stmt = $pdo->query("
            SELECT f.*, 
                   u.full_name as customer_name, 
                   u.email as customer_email,
                   u.phone_number as customer_phone,
                   c.customer_number,
                   c.physical_address
            FROM feedback f
            JOIN customers c ON f.customer_id = c.customer_id
            JOIN users u ON c.user_id = u.user_id
            ORDER BY 
                CASE f.status 
                    WHEN 'pending' THEN 1 
                    WHEN 'reviewed' THEN 2 
                    WHEN 'responded' THEN 3 
                    WHEN 'closed' THEN 4 
                END,
                f.created_at DESC
        ");
        $feedbacks = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Error deleting feedback: ' . $e->getMessage();
    }
}

$csrf_token = generateCSRFToken();

// Get stats
$total_feedback = count($feedbacks);
$pending = 0;
$reviewed = 0;
$responded = 0;
$closed = 0;

foreach ($feedbacks as $fb) {
    switch ($fb['status']) {
        case 'pending': $pending++; break;
        case 'reviewed': $reviewed++; break;
        case 'responded': $responded++; break;
        case 'closed': $closed++; break;
    }
}
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Feedback - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        /* ==========================================================
           ROOT VARIABLES
           ========================================================== */
        :root {
            --primary: #4f46e5;
            --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
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
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --radius: 10px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
        }

        /* ==========================================================
           SIDEBAR
           ========================================================== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 270px;
            background: var(--dark);
            color: #fff;
            padding: 0;
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: var(--gray-600); border-radius: 10px; }

        .sidebar-brand {
            padding: 24px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .sidebar-brand a {
            color: #fff;
            text-decoration: none;
            font-size: 1.3rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-brand a .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-gradient);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: #fff;
        }

        .sidebar-brand a .brand-text .main {
            font-size: 1.2rem;
            font-weight: 800;
        }

        .sidebar-brand a .brand-text .main span {
            color: #818cf8;
        }

        .sidebar-brand a .brand-text .sub {
            font-size: 0.6rem;
            color: var(--gray-500);
            font-weight: 400;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .sidebar-menu {
            padding: 16px 12px;
        }

        .sidebar-menu .menu-label {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gray-500);
            padding: 0 12px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 14px;
            color: var(--gray-400);
            text-decoration: none;
            border-radius: var(--radius);
            transition: var(--transition);
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 2px;
            position: relative;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.06);
            color: #fff;
        }

        .sidebar-menu a.active {
            background: rgba(79, 70, 229, 0.12);
            color: #818cf8;
        }

        .sidebar-menu a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 15%;
            height: 70%;
            width: 3px;
            background: var(--primary-gradient);
            border-radius: 0 4px 4px 0;
        }

        .sidebar-menu a i {
            width: 20px;
            text-align: center;
            font-size: 0.95rem;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 14px 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
            background: var(--dark);
        }

        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-footer .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            color: #fff;
        }

        .sidebar-footer .user-name {
            font-weight: 600;
            font-size: 0.85rem;
        }

        .sidebar-footer .user-role {
            font-size: 0.65rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sidebar-footer .logout-btn {
            margin-left: auto;
            color: var(--gray-500);
            transition: var(--transition);
            font-size: 1rem;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius);
        }

        .sidebar-footer .logout-btn:hover {
            color: var(--danger);
            background: rgba(239, 68, 68, 0.1);
        }

        /* ==========================================================
           MAIN CONTENT
           ========================================================== */
        .main-content {
            margin-left: 270px;
            padding: 24px 36px 36px;
            min-height: 100vh;
        }

        .mobile-toggle {
            display: none;
            background: var(--dark);
            border: none;
            color: #fff;
            font-size: 1.2rem;
            padding: 6px 12px;
            border-radius: var(--radius);
            cursor: pointer;
        }

        .mobile-toggle:hover {
            background: var(--gray-800);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 999;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* ==========================================================
           TOP BAR
           ========================================================== */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .topbar .page-title h1 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--gray-900);
            margin: 0;
        }

        .topbar .page-title p {
            color: var(--gray-600);
            margin: 0;
            font-size: 0.85rem;
        }

        .topbar .page-title p i {
            color: var(--primary);
            margin-right: 6px;
        }

        .topbar .date-display {
            background: #fff;
            padding: 7px 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
        }

        /* ==========================================================
           STATS CARDS
           ========================================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #fff;
            border-radius: var(--radius-lg);
            padding: 18px 22px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .stat-card .stat-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .stat-card .stat-icon.blue { background: var(--primary-light); color: var(--primary); }
        .stat-card .stat-icon.orange { background: var(--warning-light); color: #d39e00; }
        .stat-card .stat-icon.green { background: var(--success-light); color: var(--success); }
        .stat-card .stat-icon.purple { background: #ede9fe; color: #7c3aed; }

        /* ==========================================================
           FEEDBACK CARDS
           ========================================================== */
        .feedback-card {
            background: #fff;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .feedback-card:hover {
            box-shadow: var(--shadow-md);
        }

        .feedback-card .feedback-header {
            padding: 14px 22px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .feedback-card .feedback-header .title {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .feedback-card .feedback-header .title i {
            color: var(--primary);
            margin-right: 8px;
        }

        .feedback-card .feedback-header .feedback-type {
            font-size: 0.7rem;
            padding: 2px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .feedback-card .feedback-header .feedback-type.complaint { background: var(--danger-light); color: #991b1b; }
        .feedback-card .feedback-header .feedback-type.suggestion { background: var(--info-light); color: #0e7490; }
        .feedback-card .feedback-header .feedback-type.compliment { background: var(--success-light); color: #065f46; }
        .feedback-card .feedback-header .feedback-type.inquiry { background: var(--primary-light); color: var(--primary); }

        .feedback-card .feedback-body {
            padding: 18px 22px;
        }

        .feedback-card .feedback-body .customer-info {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-200);
        }

        .feedback-card .feedback-body .customer-info .info-item {
            font-size: 0.8rem;
            background: var(--gray-50);
            padding: 4px 12px;
            border-radius: var(--radius);
        }

        .feedback-card .feedback-body .customer-info .info-item i {
            color: var(--primary);
            width: 16px;
        }

        .feedback-card .feedback-body .message-content {
            background: var(--gray-50);
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 12px;
            border-left: 4px solid var(--primary);
        }

        .feedback-card .feedback-body .message-content p {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.7;
        }

        .feedback-card .feedback-body .rating-display {
            display: flex;
            gap: 4px;
            margin-bottom: 12px;
        }

        .feedback-card .feedback-body .rating-display .star {
            color: #d1d5db;
            font-size: 1.1rem;
        }

        .feedback-card .feedback-body .rating-display .star.active {
            color: #f59e0b;
        }

        .badge-status {
            padding: 3px 14px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.7rem;
            text-transform: capitalize;
        }

        .badge-status.pending { background: var(--warning-light); color: #92400e; }
        .badge-status.reviewed { background: var(--info-light); color: #0e7490; }
        .badge-status.responded { background: var(--primary-light); color: var(--primary); }
        .badge-status.closed { background: var(--success-light); color: #065f46; }

        .response-form .form-control {
            border-radius: var(--radius);
            padding: 8px 14px;
            border: 2px solid var(--gray-200);
            font-size: 0.8rem;
            transition: var(--transition);
            background: var(--gray-50);
        }

        .response-form .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
            background: #fff;
        }

        .btn-update {
            background: var(--primary-gradient);
            border: none;
            padding: 6px 18px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.75rem;
            color: #fff;
            transition: var(--transition);
            cursor: pointer;
        }

        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.3);
            color: #fff;
        }

        .btn-delete-feedback {
            background: var(--danger);
            border: none;
            padding: 6px 14px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.7rem;
            color: #fff;
            transition: var(--transition);
            cursor: pointer;
        }

        .btn-delete-feedback:hover {
            background: #dc2626;
            transform: translateY(-2px);
            color: #fff;
        }

        /* ==========================================================
           FILTERS
           ========================================================== */
        .filter-section {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-section .filter-btn {
            padding: 6px 18px;
            border-radius: 20px;
            border: 2px solid var(--gray-200);
            background: transparent;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--gray-600);
            transition: var(--transition);
            cursor: pointer;
        }

        .filter-section .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-section .filter-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        /* ==========================================================
           RESPONSIVE
           ========================================================== */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 16px 20px 30px;
            }
            .mobile-toggle {
                display: inline-block !important;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            .topbar {
                flex-direction: column;
                align-items: stretch;
            }
            .feedback-card .feedback-body .customer-info {
                flex-direction: column;
                gap: 6px;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .main-content {
                padding: 12px 14px 24px;
            }
            .filter-section {
                flex-direction: column;
            }
            .filter-section .filter-btn {
                width: 100%;
                text-align: center;
            }
        }

        /* ==========================================================
           ANIMATIONS
           ========================================================== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card, .feedback-card {
            animation: fadeInUp 0.4s ease forwards;
        }

        .stat-card:nth-child(2) { animation-delay: 0.06s; }
        .stat-card:nth-child(3) { animation-delay: 0.12s; }
        .stat-card:nth-child(4) { animation-delay: 0.18s; }
    </style>
</head>
<body>

<!-- ==========================================================
   SIDEBAR OVERLAY
   ========================================================== -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ==========================================================
   SIDEBAR
   ========================================================== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="dashboard.php">
            <div class="logo-icon"><i class="fas fa-tint"></i></div>
            <div class="brand-text">
                <span class="main">Water<span>MS</span></span>
                <span class="sub">Management System</span>
            </div>
        </a>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-label">Navigation</div>
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
        <a href="meters.php"><i class="fas fa-water"></i> Meters</a>
        <a href="readings.php"><i class="fas fa-chart-line"></i> Readings</a>

        <div class="menu-label" style="margin-top:16px;">Finance</div>
        <a href="billing.php"><i class="fas fa-file-invoice"></i> Billing</a>
        <a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a>

        <div class="menu-label" style="margin-top:16px;">Support</div>
        <a href="feedback_manage.php" class="active"><i class="fas fa-comment-dots"></i> Feedback</a>

        <div class="menu-label" style="margin-top:16px;">Reports</div>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>

        <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?></div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                <div class="user-role"><?php echo ucfirst($_SESSION['user_type'] ?? 'user'); ?></div>
            </div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</aside>

<!-- ==========================================================
   MAIN CONTENT
   ========================================================== -->
<main class="main-content">

    <!-- Top Bar -->
    <div class="topbar">
        <div>
            <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
            <div class="page-title">
                <h1>Feedback Management</h1>
                <p><i class="fas fa-comment-dots"></i> Manage customer feedback and complaints</p>
            </div>
        </div>
        <div class="date-display">
            <i class="fas fa-calendar-alt"></i> <?php echo date('l, d F Y'); ?>
        </div>
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
            <?php echo $success; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-envelope"></i></div>
            <div class="stat-number"><?php echo $total_feedback; ?></div>
            <div class="stat-label">Total Feedback</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?php echo $pending; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-eye"></i></div>
            <div class="stat-number"><?php echo $reviewed; ?></div>
            <div class="stat-label">Reviewed</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?php echo $closed; ?></div>
            <div class="stat-label">Closed</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="pending">Pending</button>
        <button class="filter-btn" data-filter="reviewed">Reviewed</button>
        <button class="filter-btn" data-filter="responded">Responded</button>
        <button class="filter-btn" data-filter="closed">Closed</button>
        <button class="filter-btn" data-filter="complaint">Complaints</button>
        <button class="filter-btn" data-filter="suggestion">Suggestions</button>
        <button class="filter-btn" data-filter="compliment">Compliments</button>
        <button class="filter-btn" data-filter="inquiry">Inquiries</button>
    </div>

    <!-- Feedback List -->
    <?php if (count($feedbacks) > 0): ?>
        <?php foreach ($feedbacks as $fb): ?>
            <div class="feedback-card" data-status="<?php echo $fb['status']; ?>" data-type="<?php echo $fb['feedback_type']; ?>">
                <div class="feedback-header">
                    <div class="title">
                        <i class="fas fa-comment"></i> 
                        <strong>#<?php echo $fb['feedback_id']; ?></strong> — 
                        <?php echo htmlspecialchars($fb['subject']); ?>
                        <span class="badge-status <?php echo $fb['status']; ?>"><?php echo ucfirst($fb['status']); ?></span>
                        <span class="feedback-type <?php echo $fb['feedback_type']; ?>"><?php echo ucfirst($fb['feedback_type']); ?></span>
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-clock"></i> <?php echo formatDate($fb['created_at']); ?>
                    </small>
                </div>

                <div class="feedback-body">
                    <!-- Customer Info -->
                    <div class="customer-info">
                        <span class="info-item"><i class="fas fa-user"></i> <?php echo htmlspecialchars($fb['customer_name']); ?></span>
                        <span class="info-item"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($fb['customer_email']); ?></span>
                        <span class="info-item"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($fb['customer_number']); ?></span>
                        <?php if (!empty($fb['location'])): ?>
                            <span class="info-item"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($fb['location']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($fb['phone'])): ?>
                            <span class="info-item"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($fb['phone']); ?></span>
                        <?php endif; ?>
                        <?php if ($fb['rating'] > 0): ?>
                            <span class="info-item">
                                <i class="fas fa-star" style="color:#f59e0b;"></i> 
                                <?php echo $fb['rating']; ?>/5
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Rating Stars Display -->
                    <?php if ($fb['rating'] > 0): ?>
                        <div class="rating-display">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?php echo $i <= $fb['rating'] ? 'active' : ''; ?>">
                                    <i class="fas fa-star"></i>
                                </span>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Message -->
                    <div class="message-content">
                        <p><?php echo nl2br(htmlspecialchars($fb['message'])); ?></p>
                    </div>

                    <!-- Existing Response -->
                    <?php if ($fb['response']): ?>
                        <div class="mb-3 p-2 bg-light rounded" style="font-size:0.85rem; border-left: 3px solid var(--primary);">
                            <strong><i class="fas fa-reply"></i> Response:</strong> 
                            <?php echo htmlspecialchars($fb['response']); ?>
                            <br>
                            <small class="text-muted">
                                <i class="fas fa-user"></i> Responded by: <?php 
                                    $responder = getUserById($fb['responded_by']);
                                    echo $responder ? htmlspecialchars($responder['full_name']) : 'N/A';
                                ?>
                                <i class="fas fa-clock ml-2"></i> <?php echo $fb['response_date'] ? formatDate($fb['response_date']) : 'N/A'; ?>
                            </small>
                        </div>
                    <?php endif; ?>

                    <!-- Response Form -->
                    <form method="POST" action="" class="response-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="feedback_id" value="<?php echo $fb['feedback_id']; ?>">
                        <input type="hidden" name="update_status" value="1">

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold" style="font-size:0.75rem;">Status</label>
                                    <select class="form-control" name="status">
                                        <option value="pending" <?php echo $fb['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="reviewed" <?php echo $fb['status'] == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                        <option value="responded" <?php echo $fb['status'] == 'responded' ? 'selected' : ''; ?>>Responded</option>
                                        <option value="closed" <?php echo $fb['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold" style="font-size:0.75rem;">Response / Notes</label>
                                    <input type="text" class="form-control" name="response" 
                                           placeholder="Enter response or notes..." 
                                           value="<?php echo htmlspecialchars($fb['response'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group" style="margin-top: 22px;">
                                    <button type="submit" class="btn-update">
                                        <i class="fas fa-save"></i> Update
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Delete Button -->
                    <div class="mt-2">
                        <a href="?delete=<?php echo $fb['feedback_id']; ?>" 
                           class="btn-delete-feedback delete-confirm"
                           onclick="return confirm('Are you sure you want to delete this feedback?')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info text-center py-5">
            <i class="fas fa-inbox" style="font-size:3rem; display:block; margin-bottom:12px; color:#94a3b8;"></i>
            <h5>No feedback received yet</h5>
            <p class="text-muted">Customer feedback and complaints will appear here.</p>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="text-center py-4 mt-2" style="font-size:0.75rem; color:#94a3b8; border-top:1px solid var(--gray-200);">
        &copy; <?php echo date('Y'); ?> <strong style="color:#475569;">Water Management System</strong>  Haki zote zimehifadhiwa
    </div>

</main>

<!-- ==========================================================
   SCRIPTS
   ========================================================== -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Mobile Sidebar Toggle
    document.getElementById('mobileToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('show');
    });

    document.getElementById('sidebarOverlay').addEventListener('click', function() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('show');
    });

    // Filter Functionality
    document.querySelectorAll('.filter-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(function(b) {
                b.classList.remove('active');
            });
            this.classList.add('active');

            var filter = this.dataset.filter;
            var cards = document.querySelectorAll('.feedback-card');

            cards.forEach(function(card) {
                if (filter === 'all') {
                    card.style.display = 'block';
                } else {
                    var status = card.dataset.status;
                    var type = card.dataset.type;
                    if (status === filter || type === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        });
    });

    // Confirm Delete
    document.querySelectorAll('.delete-confirm').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this feedback? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
</script>
</body>
</html>