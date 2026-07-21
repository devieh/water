<?php
// ============================================
// DASHBOARD - COMPLETE (CUSTOMER + ADMIN)
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$user = getCurrentUser();
$customer = null;
$customer_bills = [];
$balance = 0;
$meters = [];

// If customer, get only their data
if ($user['user_type'] == 'customer') {
    $customer = getCustomerByUserId($user['user_id']);
    if ($customer) {
        $customer_bills = getCustomerBills($customer['customer_id']);
        $balance = getCustomerBalance($customer['customer_id']);
        $meters = getCustomerMeters($customer['customer_id']);
    }
}

// Staff/Admin data
$total_customers = 0;
$total_meters = 0;
$total_revenue = 0;
$overdue_bills = 0;
$recent_bills = [];
$recent_payments = [];

if ($user['user_type'] != 'customer') {
    $total_customers = getTotalCustomers();
    $total_meters = getTotalActiveMeters();
    $total_revenue = getTotalRevenue();
    $overdue_bills = getOverdueBills();
    $recent_bills = getBills();
    $recent_payments = getPayments();
}

// Handle Feedback Submission
$feedback_error = '';
$feedback_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $feedback_error = 'Invalid security token.';
    } else {
        if ($user['user_type'] !== 'customer') {
            $feedback_error = 'Only customers can submit feedback.';
        } elseif (!$customer) {
            $feedback_error = 'Customer information not found. Please contact support.';
        } else {
            $feedback_type = sanitize($_POST['feedback_type']);
            $subject = sanitize($_POST['subject']);
            $message = sanitize($_POST['message']);
            $rating = (int)$_POST['rating'];
            $location = sanitize($_POST['location']);
            $phone = sanitize($_POST['phone']);
            
            if (empty($subject) || empty($message)) {
                $feedback_error = 'Tafadhali jaza mada na ujumbe.';
            } else {
                try {
                    $pdo = getDB();
                    $stmt = $pdo->prepare("
                        INSERT INTO feedback (
                            customer_id, feedback_type, location, phone, 
                            subject, message, rating, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([
                        $customer['customer_id'],
                        $feedback_type,
                        $location,
                        $phone,
                        $subject,
                        $message,
                        $rating
                    ]);
                    $feedback_success = 'Maoni yako yametumwa kwa Meneja! Tutawasiliana nawe hivi karibuni.';
                } catch (PDOException $e) {
                    $feedback_error = 'Error: ' . $e->getMessage();
                }
            }
        }
    }
}

$csrf_token = generateCSRFToken();

// Get customer feedback
$customer_feedback = [];
if ($customer) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM feedback WHERE customer_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$customer['customer_id']]);
        $customer_feedback = $stmt->fetchAll();
    } catch (PDOException $e) {
        $customer_feedback = [];
    }
}
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        /* ==========================================================
           ROOT VARIABLES
           ========================================================== */
        :root {
            --primary: #46e583;
            --primary-dark: #38ca7a;
            --primary-light: #eef2ff;
            --primary-gradient: linear-gradient(135deg, #80e546 0%, #6ded3a 100%);
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

        /* ==========================================================
           BACKGROUND IMAGE - DASHBOARD PAGE
           ========================================================== */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--gray-900);
            line-height: 1.6;
            min-height: 100vh;
            
            /* ==========================================================
               BACKGROUND IMAGE - BADILISHA HAPA
               ========================================================== */
            background-image: url('images/water.webp');
            /* Ikiwa picha haipo, tumia hii kutoka mtandaoni */
            /* background-image: url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1920'); */
            /* Au tumia rangi ikiwa hutaki picha */
            /* background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); */
            
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            position: relative;
        }

        /* Overlay - inafanya maandishi yasomeke vizuri */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.65);
            z-index: -1;
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
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            color: #fff;
            padding: 0;
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
            border-right: 1px solid rgba(255,255,255,0.05);
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

        .payment-link {
            background: rgba(16, 185, 129, 0.12) !important;
            color: #10b981 !important;
            border-left: 3px solid #10b981 !important;
            border-radius: 8px !important;
            margin: 4px 0 !important;
        }

        .payment-link:hover {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #059669 !important;
        }

        .payment-link .badge-pay {
            margin-left: auto;
            background: #10b981;
            color: #fff;
            font-size: 0.6rem;
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 600;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 14px 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
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
            color: #fff;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .topbar .page-title p {
            color: rgba(255,255,255,0.7);
            margin: 0;
            font-size: 0.85rem;
        }

        .topbar .page-title p i {
            color: #818cf8;
            margin-right: 6px;
        }

        .topbar .date-display {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 7px 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            font-size: 0.8rem;
            font-weight: 500;
            color: rgba(255,255,255,0.8);
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* ==========================================================
           WELCOME BANNER
           ========================================================== */
        .welcome-banner {
            background: rgba(102, 229, 70, 0.85);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            padding: 24px 32px;
            color: #fff;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -5%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .welcome-banner .content {
            position: relative;
            z-index: 1;
        }

        .welcome-banner .greeting {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.7;
            font-weight: 600;
        }

        .welcome-banner h2 {
            font-weight: 700;
            font-size: 1.3rem;
            margin: 2px 0 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .welcome-banner p {
            opacity: 0.8;
            margin: 2px 0 0;
            font-size: 0.9rem;
        }

        .welcome-banner .badge-role {
            display: inline-block;
            padding: 3px 16px;
            border-radius: 20px;
            background: rgba(255,255,255,0.15);
            font-size: 0.65rem;
            font-weight: 600;
            margin-top: 6px;
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
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            border-radius: var(--radius-lg);
            padding: 18px 22px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            background: rgba(255, 255, 255, 0.95);
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
        .stat-card .stat-icon.green { background: var(--success-light); color: var(--success); }
        .stat-card .stat-icon.orange { background: var(--warning-light); color: #d39e00; }
        .stat-card .stat-icon.red { background: var(--danger-light); color: var(--danger); }
        .stat-card .stat-icon.purple { background: #ede9fe; color: #7c3aed; }

        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--gray-900);
            line-height: 1.2;
        }

        .stat-card .stat-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        /* ==========================================================
           SECTION TITLE
           ========================================================== */
        .section-title {
            font-weight: 700;
            font-size: 1rem;
            color: #fff;
            margin-bottom: 14px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .section-title i {
            color: #818cf8;
            margin-right: 8px;
        }

        /* ==========================================================
           TABLE CARD
           ========================================================== */
        .table-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(15px);
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            overflow: hidden;
        }

        .table-card:hover {
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .table-card .table-header {
            padding: 14px 22px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .table-card .table-header .title {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--gray-900);
        }

        .table-card .table-header .title i {
            color: var(--primary);
            margin-right: 8px;
        }

        .table-card .table-header .btn-sm-custom {
            font-size: 0.75rem;
            padding: 4px 14px;
            border-radius: var(--radius);
            background: var(--primary-light);
            color: var(--primary);
            border: none;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
        }

        .table-card .table-header .btn-sm-custom:hover {
            background: var(--primary);
            color: #fff;
            text-decoration: none;
        }

        .table-custom {
            margin: 0;
        }

        .table-custom thead th {
            background: rgba(248, 250, 252, 0.5);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 8px 16px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-600);
            border-top: none;
        }

        .table-custom tbody td {
            padding: 8px 16px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-size: 0.82rem;
        }

        .table-custom tbody tr:hover {
            background: rgba(0,0,0,0.02);
        }

        .badge-status {
            padding: 3px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.65rem;
            text-transform: capitalize;
        }

        .badge-status.paid { background: var(--success-light); color: #0a5e36; }
        .badge-status.issued { background: var(--warning-light); color: #856404; }
        .badge-status.overdue { background: var(--danger-light); color: #842029; }
        .badge-status.pending { background: var(--warning-light); color: #856404; }
        .badge-status.reviewed { background: var(--info-light); color: #055160; }
        .badge-status.responded { background: var(--primary-light); color: var(--primary); }
        .badge-status.closed { background: var(--success-light); color: #0a5e36; }

        /* ==========================================================
           SERVICE CARDS
           ========================================================== */
        .service-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 20px 22px;
            transition: var(--transition);
            height: 100%;
            text-align: center;
        }

        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            background: rgba(255, 255, 255, 0.95);
        }

        .service-card .service-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin: 0 auto 12px;
        }

        .service-card .service-icon.blue { background: var(--primary-light); color: var(--primary); }
        .service-card .service-icon.green { background: var(--success-light); color: var(--success); }
        .service-card .service-icon.orange { background: var(--warning-light); color: #d39e00; }
        .service-card .service-icon.purple { background: #ede9fe; color: #7c3aed; }
        .service-card .service-icon.teal { background: #ccfbf1; color: #0d9488; }
        .service-card .service-icon.red { background: var(--danger-light); color: var(--danger); }

        .service-card h5 {
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .service-card p {
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-bottom: 0;
        }

        /* ==========================================================
           CHALLENGE CARDS
           ========================================================== */
        .challenge-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 18px 22px;
            transition: var(--transition);
            height: 100%;
            border-left: 4px solid var(--warning);
        }

        .challenge-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            background: rgba(255, 255, 255, 0.95);
        }

        .challenge-card.danger { border-left-color: var(--danger); }
        .challenge-card.primary { border-left-color: var(--primary); }
        .challenge-card.success { border-left-color: var(--success); }

        .challenge-card .challenge-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .challenge-card .challenge-icon.orange { background: var(--warning-light); color: #d39e00; }
        .challenge-card .challenge-icon.red { background: var(--danger-light); color: var(--danger); }
        .challenge-card .challenge-icon.blue { background: var(--primary-light); color: var(--primary); }
        .challenge-card .challenge-icon.green { background: var(--success-light); color: var(--success); }

        .challenge-card h5 {
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }

        .challenge-card p {
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-bottom: 0;
        }

        /* ==========================================================
           FEEDBACK FORM
           ========================================================== */
        .feedback-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(15px);
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 24px 28px;
        }

        .feedback-card .form-control {
            border-radius: var(--radius);
            padding: 9px 14px;
            border: 2px solid rgba(255,255,255,0.2);
            font-size: 0.8rem;
            transition: var(--transition);
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(5px);
        }

        .feedback-card .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
            background: rgba(255,255,255,0.9);
        }

        .feedback-card .form-label {
            font-weight: 500;
            font-size: 0.75rem;
            color: var(--gray-700);
            margin-bottom: 3px;
        }

        .feedback-card .form-label i {
            color: var(--primary);
            width: 16px;
        }

        .btn-submit {
            background: var(--primary-gradient);
            border: none;
            padding: 10px 28px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            color: #fff;
            transition: var(--transition);
            cursor: pointer;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.3);
            color: #fff;
        }

        .star-rating {
            display: flex;
            gap: 6px;
            font-size: 1.6rem;
            cursor: pointer;
        }

        .star-rating .star {
            color: var(--gray-300);
            transition: var(--transition);
        }

        .star-rating .star.active {
            color: #f59e0b;
        }

        .star-rating .star:hover {
            transform: scale(1.15);
        }

        .feedback-history .list-group-item {
            border-left: 4px solid var(--gray-300);
            border-radius: var(--radius);
            margin-bottom: 6px;
        }

        .feedback-history .list-group-item.pending { border-left-color: var(--warning); }
        .feedback-history .list-group-item.reviewed { border-left-color: var(--info); }
        .feedback-history .list-group-item.responded { border-left-color: var(--primary); }
        .feedback-history .list-group-item.closed { border-left-color: var(--success); }

        /* ==========================================================
           CONTACT CARD
           ========================================================== */
        .contact-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 20px 24px;
            text-align: center;
            transition: var(--transition);
        }

        .contact-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            background: rgba(255, 255, 255, 0.95);
        }

        .contact-card .contact-number {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
            display: block;
        }

        .contact-card .contact-number i {
            color: var(--success);
        }

        .contact-card .contact-label {
            font-size: 0.8rem;
            color: var(--gray-500);
        }

        /* ==========================================================
           PAYMENT SECTION
           ========================================================== */
        .payment-info-box {
            background: rgba(238, 242, 255, 0.7);
            backdrop-filter: blur(5px);
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            border-left: 4px solid #4f46e5;
        }

        .payment-info-box p {
            margin-bottom: 0;
            font-size: 0.85rem;
            color: #1e293b;
        }

        .payment-form-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(15px);
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 24px 28px;
        }

        .payment-form-card .form-control {
            border-radius: var(--radius);
            padding: 9px 14px;
            border: 2px solid rgba(255,255,255,0.2);
            font-size: 0.8rem;
            transition: var(--transition);
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(5px);
        }

        .payment-form-card .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
            background: rgba(255,255,255,0.9);
        }

        .payment-form-card .form-label {
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--gray-700);
            margin-bottom: 4px;
        }

        .payment-form-card .form-label i {
            color: var(--primary);
            width: 20px;
        }

        .payment-methods {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .payment-methods .method-btn {
            padding: 8px 14px;
            border-radius: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(5px);
            font-size: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
            flex: 1;
            text-align: center;
            min-width: 60px;
        }

        .payment-methods .method-btn:hover,
        .payment-methods .method-btn.active {
            border-color: #10b981;
            background: #d1fae5;
            color: #065f46;
        }

        .payment-methods .method-btn i {
            margin-right: 4px;
        }

        .text-danger {
            color: #dc3545 !important;
        }

        /* ==========================================================
           FOOTER
           ========================================================== */
        .footer-custom {
            color: rgba(255,255,255,0.5) !important;
            border-top: 1px solid rgba(255,255,255,0.05) !important;
        }

        .footer-custom strong {
            color: rgba(194, 69, 69, 0.7) !important;
        }

        /* ==========================================================
           RESPONSIVE
           ========================================================== */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

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
            .payment-form-card {
                padding: 16px;
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
            .welcome-banner {
                padding: 18px 22px;
            }
            .welcome-banner h2 {
                font-size: 1.1rem;
            }
            .feedback-card {
                padding: 18px 16px;
            }
            .star-rating {
                font-size: 1.3rem;
            }
            .payment-methods .method-btn {
                font-size: 0.7rem;
                padding: 6px 10px;
                min-width: 50px;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .main-content {
                padding: 12px 14px 24px;
            }
            .service-card, .challenge-card, .contact-card {
                padding: 16px 18px;
            }
            .payment-form-card {
                padding: 12px;
            }
            .payment-methods .method-btn {
                font-size: 0.6rem;
                padding: 4px 6px;
                min-width: 40px;
            }
            .topbar .page-title h1 {
                font-size: 1.1rem;
            }
            .welcome-banner h2 {
                font-size: 1rem;
            }
        }

        /* ==========================================================
           ANIMATIONS
           ========================================================== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card, .service-card, .challenge-card, .feedback-card, .contact-card, .table-card, .welcome-banner, .payment-form-card {
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
            <div class="logo-icon">
                <i class="fas fa-tint"></i>
            </div>
            <div class="brand-text">
                <span class="main">Water<span>MS</span></span>
                <span class="sub">Management System</span>
            </div>
        </a>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-label">Navigation</div>
        <a href="dashboard.php" class="active">
            <i class="fas fa-th-large"></i> Dashboard
        </a>

        <?php if (hasPermission('staff')): ?>
            <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
            <a href="meters.php"><i class="fas fa-water"></i> Water Meters</a>
            <a href="readings.php"><i class="fas fa-chart-line"></i> Readings</a>

            <div class="menu-label" style="margin-top:16px;">Finance</div>
            <a href="billing.php"><i class="fas fa-file-invoice"></i> Billing</a>
            <a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a>

            <div class="menu-label" style="margin-top:16px;">Support</div>
            <a href="feedback_manage.php"><i class="fas fa-comment-dots"></i> Feedback</a>

            <div class="menu-label" style="margin-top:16px;">Reports</div>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
        <?php else: ?>
            <a href="#services"><i class="fas fa-concierge-bell"></i> Huduma Zetu</a>
            <a href="#challenges"><i class="fas fa-exclamation-triangle"></i> Changamoto</a>
            <a href="#paymentSection" class="payment-link">
                <i class="fas fa-credit-card"></i> Lipa Bili
                <span class="badge-pay">Sasa</span>
            </a>
            <a href="#feedback"><i class="fas fa-comment-dots"></i> Maoni</a>
            <a href="#contact"><i class="fas fa-phone-alt"></i> Wasiliana</a>
        <?php endif; ?>

        <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                <div class="user-role"><?php echo ucfirst($_SESSION['user_type'] ?? 'user'); ?></div>
            </div>
            <a href="logout.php" class="logout-btn" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
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
            <button class="mobile-toggle" id="mobileToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <h1>Dashboard</h1>
                <p><i class="fas fa-compass"></i> <?php echo $user['user_type'] == 'customer' ? 'Water usage overview' : 'Water management overview'; ?></p>
            </div>
        </div>
        <div class="date-display">
            <i class="fas fa-calendar-alt"></i> <?php echo date('l, d F Y'); ?>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php echo displayFlash(); ?>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="content">
            <div class="greeting"><?php echo $user['user_type'] == 'customer' ? 'Karibu tena' : 'Welcome back'; ?></div>
            <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
            <p><?php echo $user['user_type'] == 'customer' ? 'Muhtasari wa matumizi yako ya maji na akaunti yako' : 'Complete water management system overview'; ?></p>
            <span class="badge-role">
                <i class="fas fa-circle" style="font-size:5px; vertical-align:middle; margin-right:6px;"></i>
                <?php echo ucfirst($user['user_type']); ?>
            </span>
        </div>
    </div>

    <?php if ($user['user_type'] == 'customer'): ?>
        <!-- ==========================================================
        CUSTOMER DASHBOARD
        ========================================================== -->

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-water"></i></div>
                <div class="stat-number"><?php echo count($meters); ?></div>
                <div class="stat-label">Mita Zangu</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon <?php echo $balance > 0 ? 'red' : 'green'; ?>">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-number">TSh <?php echo formatCurrency($balance); ?></div>
                <div class="stat-label"><?php echo $balance > 0 ? 'Deni Lililobaki' : 'Hakuna Deni'; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-file-invoice"></i></div>
                <div class="stat-number"><?php echo count($customer_bills); ?></div>
                <div class="stat-label">Bili Zangu</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-user-check"></i></div>
                <div class="stat-number" style="font-size:1.1rem; margin-top:4px;">
                    <?php echo ucfirst($customer['customer_status'] ?? 'Active'); ?>
                </div>
                <div class="stat-label">Hali ya Akaunti</div>
            </div>
        </div>

        <!-- ==========================================================
        SECTION: BILI ZANGU NA MALIPO
        ========================================================== -->
        <div class="mt-4" id="paymentSection">
            <h5 class="section-title"><i class="fas fa-credit-card"></i> Bili Zangu</h5>
            
            <div class="payment-info-box">
                <p>
                    <i class="fas fa-info-circle" style="color: #4f46e5;"></i>
                    <strong>Jinsi ya Kulipa:</strong> Jaza taarifa zako zote hapo chini.
                    Utahitaji kujaza: <strong>Jina Kamili, Namba ya Bili, Njia ya Malipo, Namba ya Marejeleo, Eneo</strong> na <strong>Namba ya Simu</strong>.
                </p>
            </div>
            
            <div class="table-card">
                <div class="table-header">
                    <span class="title"><i class="fas fa-list"></i> Orodha ya Bili Zangu</span>
                    <span class="text-muted" style="font-size:0.8rem;">
                        <i class="fas fa-credit-card"></i> Angalia namba ya bili yako halafu jaza hapo chini
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Bill No.</th>
                                <th>Period</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($customer_bills) > 0): ?>
                                <?php foreach ($customer_bills as $bill): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($bill['bill_number']); ?></strong></td>
                                        <td><?php echo formatDate($bill['billing_period_start']) . ' — ' . formatDate($bill['billing_period_end']); ?></td>
                                        <td><strong>TSh <?php echo formatCurrency($bill['total_amount']); ?></strong></td>
                                        <td><?php echo formatDate($bill['due_date']); ?></td>
                                        <td>
                                            <?php if ($bill['bill_status'] == 'paid'): ?>
                                                <span class="badge-status paid"><i class="fas fa-check-circle"></i> Imelipwa</span>
                                            <?php elseif ($bill['bill_status'] == 'overdue'): ?>
                                                <span class="badge-status overdue"><i class="fas fa-exclamation-triangle"></i> Imechelewa</span>
                                            <?php elseif ($bill['bill_status'] == 'issued'): ?>
                                                <span class="badge-status issued"><i class="fas fa-clock"></i> Imetolewa</span>
                                            <?php else: ?>
                                                <span class="badge-status draft"><?php echo ucfirst($bill['bill_status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox" style="font-size:1.5rem; display:block; margin-bottom:6px; color:#dee2e6;"></i>
                                        Huna bili yoyote
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==========================================================
        SECTION: JAZA TAARIFA ZA MALIPO
        ========================================================== -->
        <div class="mt-4" id="paymentForm">
            <h5 class="section-title"><i class="fas fa-edit"></i> Jaza Taarifa za Malipo</h5>
            
            <div class="payment-form-card">
                <div class="alert alert-info" style="border-radius:12px; border-left: 4px solid #4f46e5; margin-bottom:16px;">
                    <h6 class="mb-1"><i class="fas fa-info-circle"></i> Maelekezo</h6>
                    <p class="mb-0" style="font-size:0.85rem;">
                        Jaza taarifa zako zote hapo chini kwa usahihi.
                        Baada ya kujaza, bonyeza <strong>"Lipa Sasa"</strong> ili kuthibitisha malipo.
                    </p>
                </div>
                
                <form method="POST" action="customer_pay.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <!-- Jina Kamili -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Jina Lako Kamili <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" 
                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                               placeholder="Andika jina lako kamili" required>
                        <small class="text-muted">Andika jina lako kamili kama lilivyo kwenye akaunti yako</small>
                    </div>
                    
                    <!-- Namba ya Bili -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-file-invoice"></i> Namba ya Bili <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="bill_number" 
                               placeholder="e.g. BIL-2026-001" required>
                        <small class="text-muted">Andika namba ya bili unayotaka kulipa</small>
                    </div>
                    
                    <!-- Kiasi cha Kulipa -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-money-bill-wave"></i> Kiasi cha Kulipa <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="amount_paid" 
                               id="amountInput" placeholder="Weka kiasi unachotaka kulipa" required>
                        <small class="text-muted">Weka kiasi unachotaka kulipa (kiasi chote kinapendekezwa)</small>
                    </div>
                    
                    <!-- Njia ya Malipo -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-credit-card"></i> Njia ya Malipo <span class="text-danger">*</span></label>
                        <div class="payment-methods" id="paymentMethods">
                            <button type="button" class="method-btn active" data-value="mobile_money">
                                <i class="fas fa-mobile-alt"></i> M-Pesa
                            </button>
                            <button type="button" class="method-btn" data-value="cash">
                                <i class="fas fa-money-bill-wave"></i> Cash
                            </button>
                            <button type="button" class="method-btn" data-value="bank_transfer">
                                <i class="fas fa-university"></i> Bank
                            </button>
                            <button type="button" class="method-btn" data-value="online">
                                <i class="fas fa-globe"></i> Online
                            </button>
                            <button type="button" class="method-btn" data-value="cheque">
                                <i class="fas fa-pen"></i> Cheque
                            </button>
                        </div>
                        <input type="hidden" name="payment_method" id="selectedMethod" value="mobile_money">
                        <small class="text-muted">Chagua njia unayotaka kulipia</small>
                    </div>
                    
                    <!-- Namba ya Marejeleo -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-hashtag"></i> Namba ya Marejeleo</label>
                        <input type="text" class="form-control" name="transaction_reference" 
                               placeholder="e.g. MPESA-2026-001 au namba ya risiti">
                        <small class="text-muted">Weka namba ya M-Pesa, slip ya benki, au namba ya risiti</small>
                    </div>
                    
                    <!-- Eneo Lako -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-map-marker-alt"></i> Eneo Lako <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="location" 
                               placeholder="Andika eneo lako (Mtaa/Kata)" required>
                        <small class="text-muted">Andika eneo unalopokea huduma ya maji</small>
                    </div>
                    
                    <!-- Namba ya Simu -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-phone"></i> Namba ya Simu <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="phone" 
                               placeholder="Andika namba yako" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" required>
                        <small class="text-muted">Tumia namba ya simu unayotumia</small>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success btn-lg px-5" style="border-radius:10px; font-weight:600;">
                            <i class="fas fa-credit-card"></i> Lipa Sasa
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary btn-lg px-4" style="border-radius:10px;">
                            <i class="fas fa-times"></i> Futa
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- ==========================================================
        SECTION: HUDUMA ZETU
        ========================================================== -->
        <div id="services" class="mt-4">
            <h5 class="section-title"><i class="fas fa-concierge-bell"></i> Huduma Zetu</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="service-card">
                        <div class="service-icon blue"><i class="fas fa-tint"></i></div>
                        <h5>Usambazaji wa Maji</h5>
                        <p>Huduma ya maji safi na salama kwa wateja wetu wote.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="service-card">
                        <div class="service-icon green"><i class="fas fa-file-invoice"></i></div>
                        <h5>Bili za Kielektroniki</h5>
                        <p>Pata bili yako kwa simu au barua pepe. Lipa mtandaoni.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="service-card">
                        <div class="service-icon purple"><i class="fas fa-headset"></i></div>
                        <h5>Msaada kwa Wateja</h5>
                        <p>Timu yetu iko tayari kukusaidia kwa maswali yako.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="service-card">
                        <div class="service-icon orange"><i class="fas fa-credit-card"></i></div>
                        <h5>Malipo Rahisi</h5>
                        <p>Lipa kwa M-Pesa, Benki, au ofisini kwetu.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="service-card">
                        <div class="service-icon teal"><i class="fas fa-chart-line"></i></div>
                        <h5>Ripoti za Matumizi</h5>
                        <p>Angalia ripoti za matumizi yako ya maji kila mwezi.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="service-card">
                        <div class="service-icon red"><i class="fas fa-bell"></i></div>
                        <h5>Arifa za Bili</h5>
                        <p>Pokea arifa kabla ya bili yako kuwa overdue.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================================
        SECTION: CHANGAMOTO ZA MAJI
        ========================================================== -->
        <div id="challenges" class="mt-4">
            <h5 class="section-title"><i class="fas fa-exclamation-triangle"></i> Changamoto za Maji</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="challenge-card danger">
                        <div class="challenge-icon red"><i class="fas fa-times-circle"></i></div>
                        <h5>Ukosefu wa Maji</h5>
                        <p>Katika baadhi ya maeneo, maji yanaweza kukosekana kwa muda.</p>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="challenge-card primary">
                        <div class="challenge-icon blue"><i class="fas fa-water"></i></div>
                        <h5>Matumizi Makubwa ya Maji</h5>
                        <p>Ikiwa bili imeongezeka, huenda kuna uvujaji.</p>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="challenge-card">
                        <div class="challenge-icon orange"><i class="fas fa-clock"></i></div>
                        <h5>Maji Yanaisha Haraka</h5>
                        <p>Wakati wa kiangazi, tumia maji kwa uangalifu.</p>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="challenge-card success">
                        <div class="challenge-icon green"><i class="fas fa-check-circle"></i></div>
                        <h5>Hatua za Kukabiliana</h5>
                        <p>Weka mabaki ya maji na kutumia kwa uangalifu.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================================
        SECTION: MAONI NA CHANGAMOTO
        ========================================================== -->
        <div id="feedback" class="mt-4">
            <h5 class="section-title"><i class="fas fa-comment-dots"></i> Maoni na Changamoto Yako</h5>
            <div class="feedback-card">
                <?php if ($feedback_error): ?>
                    <div class="alert alert-danger"><?php echo $feedback_error; ?></div>
                <?php endif; ?>
                <?php if ($feedback_success): ?>
                    <div class="alert alert-success"><?php echo $feedback_success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-tag"></i> Aina ya Maoni</label>
                                <select class="form-control" name="feedback_type">
                                    <option value="complaint">Malalamiko</option>
                                    <option value="suggestion">Mapendekezo</option>
                                    <option value="compliment">Pongezi</option>
                                    <option value="inquiry">Swali</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-map-marker-alt"></i> Eneo Lako</label>
                                <input type="text" class="form-control" name="location" placeholder="Andika eneo lako (Mtaa/Kata)">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-phone"></i> Namba ya Simu</label>
                                <input type="text" class="form-control" name="phone" placeholder="Andika namba yako" value="0684871169">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-star"></i> Kiwango cha Kuridhika</label>
                                <div class="star-rating" id="starRating">
                                    <span class="star" data-value="1"><i class="fas fa-star"></i></span>
                                    <span class="star" data-value="2"><i class="fas fa-star"></i></span>
                                    <span class="star" data-value="3"><i class="fas fa-star"></i></span>
                                    <span class="star" data-value="4"><i class="fas fa-star"></i></span>
                                    <span class="star" data-value="5"><i class="fas fa-star"></i></span>
                                </div>
                                <input type="hidden" name="rating" id="ratingValue" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-heading"></i> Mada</label>
                        <input type="text" class="form-control" name="subject" placeholder="Andika mada fupi" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-pen"></i> Maelezo</label>
                        <textarea class="form-control" name="message" rows="4" placeholder="Elezea maoni au changamoto yako kwa kina..." required></textarea>
                    </div>

                    <button type="submit" name="submit_feedback" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Tuma Maoni
                    </button>
                </form>
            </div>
        </div>

        <!-- ==========================================================
        HISTORIA YA MAONI YAKO
        ========================================================== -->
        <?php if (count($customer_feedback) > 0): ?>
        <div class="mt-4">
            <h5 class="section-title"><i class="fas fa-history"></i> Historia ya Maoni Yako</h5>
            <div class="feedback-history">
                <div class="list-group">
                    <?php foreach ($customer_feedback as $fb): ?>
                        <div class="list-group-item <?php echo $fb['status']; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($fb['subject']); ?></strong>
                                    <span class="badge-status <?php echo $fb['status']; ?>"><?php echo ucfirst($fb['status']); ?></span>
                                </div>
                                <small class="text-muted"><?php echo formatDate($fb['created_at']); ?></small>
                            </div>
                            <p class="mb-1 text-muted" style="font-size:0.85rem;"><?php echo htmlspecialchars(substr($fb['message'], 0, 100)); ?>...</p>
                            <?php if ($fb['response']): ?>
                                <div class="mt-2 p-2 bg-light rounded" style="font-size:0.85rem; border-left: 3px solid var(--primary);">
                                    <strong><i class="fas fa-reply"></i> Jibu:</strong> <?php echo htmlspecialchars($fb['response']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ==========================================================
        SECTION: WASILIANA NASI
        ========================================================== -->
        <div id="contact" class="mt-4">
            <h5 class="section-title"><i class="fas fa-phone-alt"></i> Wasiliana Nasi</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="contact-card">
                        <i class="fas fa-phone" style="font-size:2rem; color:var(--primary); margin-bottom:8px;"></i>
                        <span class="contact-number">0684 871 169</span>
                        <span class="contact-label">Namba ya Simu</span>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="contact-card">
                        <i class="fas fa-envelope" style="font-size:2rem; color:var(--primary); margin-bottom:8px;"></i>
                        <span class="contact-number" style="font-size:1rem;">info@waterms.com</span>
                        <span class="contact-label">Barua Pepe</span>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="contact-card">
                        <i class="fas fa-map-marker-alt" style="font-size:2rem; color:var(--primary); margin-bottom:8px;"></i>
                        <span class="contact-number" style="font-size:1rem;">Dar es Salaam, Tanzania</span>
                        <span class="contact-label">Ofisi Kuu</span>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ==========================================================
        ADMIN/STAFF DASHBOARD
        ========================================================== -->

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $total_customers; ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-water"></i></div>
                <div class="stat-number"><?php echo $total_meters; ?></div>
                <div class="stat-label">Active Meters</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-coins"></i></div>
                <div class="stat-number">TSh <?php echo formatCurrency($total_revenue); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo $overdue_bills; ?></div>
                <div class="stat-label">Overdue Bills</div>
            </div>
        </div>

        <!-- Recent Bills & Payments -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="table-card">
                    <div class="table-header">
                        <span class="title"><i class="fas fa-file-invoice"></i> Recent Bills</span>
                        <a href="billing.php" class="btn-sm-custom">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr><th>Bill No.</th><th>Customer</th><th>Amount</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_bills) > 0): ?>
                                    <?php foreach (array_slice($recent_bills, 0, 5) as $bill): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($bill['bill_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($bill['customer_name']); ?></td>
                                            <td>TSh <?php echo formatCurrency($bill['total_amount']); ?></td>
                                            <td><span class="badge-status <?php echo $bill['bill_status']; ?>"><?php echo ucfirst($bill['bill_status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No bills recorded</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="table-card">
                    <div class="table-header">
                        <span class="title"><i class="fas fa-credit-card"></i> Recent Payments</span>
                        <a href="payments.php" class="btn-sm-custom">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr><th>Payment No.</th><th>Customer</th><th>Amount</th><th>Method</th></tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_payments) > 0): ?>
                                    <?php foreach (array_slice($recent_payments, 0, 5) as $payment): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($payment['payment_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                            <td>TSh <?php echo formatCurrency($payment['amount_paid']); ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? 'N/A')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No payments recorded</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feedback Summary for Admin -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="table-card">
                    <div class="table-header">
                        <span class="title"><i class="fas fa-comment-dots"></i> Recent Customer Feedback</span>
                        <a href="feedback_manage.php" class="btn-sm-custom">Manage All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr><th>Customer</th><th>Subject</th><th>Type</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php 
                                try {
                                    $pdo = getDB();
                                    $stmt = $pdo->query("
                                        SELECT f.*, u.full_name as customer_name 
                                        FROM feedback f
                                        JOIN customers c ON f.customer_id = c.customer_id
                                        JOIN users u ON c.user_id = u.user_id
                                        ORDER BY f.created_at DESC LIMIT 5
                                    ");
                                    $recent_feedback = $stmt->fetchAll();
                                } catch (PDOException $e) {
                                    $recent_feedback = [];
                                }
                                ?>
                                <?php if (count($recent_feedback) > 0): ?>
                                    <?php foreach ($recent_feedback as $fb): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($fb['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($fb['subject']); ?></td>
                                            <td><?php echo ucfirst($fb['feedback_type']); ?></td>
                                            <td><span class="badge-status <?php echo $fb['status']; ?>"><?php echo ucfirst($fb['status']); ?></span></td>
                                            <td><?php echo formatDate($fb['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted py-3">No feedback received</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="text-center py-4 mt-2 footer-custom" style="font-size:0.75rem;">
        &copy; <?php echo date('Y'); ?> <strong>Water Management System</strong>  Haki zote zimehifadhiwa
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

    // Star Rating
    document.querySelectorAll('.star-rating .star').forEach(function(star) {
        star.addEventListener('click', function() {
            var value = parseInt(this.dataset.value);
            document.getElementById('ratingValue').value = value;
            
            document.querySelectorAll('.star-rating .star').forEach(function(s) {
                if (parseInt(s.dataset.value) <= value) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
        });
        
        star.addEventListener('mouseenter', function() {
            var value = parseInt(this.dataset.value);
            document.querySelectorAll('.star-rating .star').forEach(function(s) {
                if (parseInt(s.dataset.value) <= value) {
                    s.style.color = '#f59e0b';
                } else {
                    s.style.color = '';
                }
            });
        });
        
        star.addEventListener('mouseleave', function() {
            document.querySelectorAll('.star-rating .star').forEach(function(s) {
                s.style.color = '';
            });
        });
    });

    // Payment Method Selection
    document.querySelectorAll('.method-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.method-btn').forEach(function(b) {
                b.classList.remove('active');
            });
            this.classList.add('active');
            document.getElementById('selectedMethod').value = this.dataset.value;
        });
    });
</script>
</body>
</html>