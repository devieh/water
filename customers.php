<?php
// ============================================
// CUSTOMERS - ADMIN VIEWS ALL CUSTOMERS
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$customers = getCustomers();
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        /* ==========================================================
           ROOT VARIABLES
           ========================================================== */
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #eef2ff;
            --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
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
            --radius-xl: 20px;
            --transition: all 0.3s ease;
        }

        /* ==========================================================
           BODY - BACKGROUND IMAGE
           ========================================================== */
        body {
            background: var(--gray-50);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            
            /* ==========================================================
               BACKGROUND IMAGE - BADILISHA HAPA
               ========================================================== */
            background-image: url('images/drop.jpeg');
           
            background-size: 95%;
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
           HEADER - INATRANSPARENT
           ========================================================== */
        .header-simple {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(10px);
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .header-simple h4 {
            margin: 0;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .header-simple a {
            color: rgba(255, 255, 255, 0.8);
            margin-left: 15px;
            text-decoration: none;
            transition: var(--transition);
        }

        .header-simple a:hover {
            color: #fff;
        }

        .header-simple .logout-link {
            color: #ff6b6b;
        }

        .header-simple .logout-link:hover {
            color: #ff4444;
        }

        /* ==========================================================
           CARDS - ZINATRANSPARENT
           ========================================================== */
        .card {
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: var(--transition);
        }

        .card:hover {
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
            padding: 16px 22px;
        }

        /* ==========================================================
           TABLE
           ========================================================== */
        .table {
            margin: 0;
        }

        .table thead th {
            background: rgba(248, 250, 252, 0.5);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            color: var(--gray-600);
            border-top: none;
            padding: 12px 15px;
        }

        .table tbody td {
            padding: 10px 15px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-size: 0.85rem;
            color: var(--gray-800);
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: rgba(0,0,0,0.02);
        }

        /* ==========================================================
           BADGES
           ========================================================== */
        .badge {
            padding: 5px 12px;
            font-weight: 500;
            border-radius: 20px;
            font-size: 0.7rem;
        }

        .badge-success {
            background: var(--success-light);
            color: #065f46;
        }

        .badge-danger {
            background: var(--danger-light);
            color: #991b1b;
        }

        .badge-warning {
            background: var(--warning-light);
            color: #92400e;
        }

        .badge-primary-custom {
            background: rgba(79, 70, 229, 0.15);
            color: var(--primary);
            font-size: 0.9rem;
            padding: 8px 16px;
        }

        /* ==========================================================
           BUTTONS
           ========================================================== */
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.75rem;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .btn-info {
            background: rgba(13, 202, 240, 0.15);
            color: #0dcaf0;
            border: none;
        }

        .btn-info:hover {
            background: #0dcaf0;
            color: #fff;
        }

        .btn-warning {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border: none;
        }

        .btn-warning:hover {
            background: #ffc107;
            color: #6798c9;
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #35ff22;
            border: none;
        }

        .btn-danger:hover {
            background: #ef4444;
            color: #fff;
        }

        /* ==========================================================
           FOOTER
           ========================================================== */
        .footer-custom {
            color: rgba(255, 255, 255, 0.5) !important;
            border-top: 1px solid rgba(255, 255, 255, 0.05) !important;
        }

        .footer-custom strong {
            color: rgba(255, 255, 255, 0.7) !important;
        }

        /* ==========================================================
           PAGE TITLE
           ========================================================== */
        .page-title-custom {
            color: #fff;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .page-title-custom i {
            color: #41aa17;
        }

        /* ==========================================================
           RESPONSIVE
           ========================================================== */
        @media (max-width: 768px) {
            .header-simple .row {
                flex-direction: column;
                text-align: center;
            }

            .header-simple .text-right {
                text-align: center !important;
                margin-top: 10px;
            }

            .header-simple a {
                margin: 0 8px;
            }

            .card {
                margin: 0 5px;
            }
        }

        @media (max-width: 576px) {
            .header-simple h4 {
                font-size: 1rem;
            }

            .table thead th {
                font-size: 0.5rem;
                padding: 5px 6px;
            }

            .table tbody td {
                padding: 5px 6px;
                font-size: 0.65rem;
            }

            .btn-sm {
                font-size: 0.6rem;
                padding: 3px 6px;
            }

            .badge {
                font-size: 0.55rem;
                padding: 3px 8px;
            }
        }

        /* ==========================================================
           ANIMATIONS
           ========================================================== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            animation: fadeInUp 0.4s ease forwards;
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
                    <small style="font-size:0.7rem; opacity:0.7; margin-left:8px;">| Customers</small>
                </h4>
            </div>
            <div class="col-md-6 text-right">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="customers_add.php" class="text-success"><i class="fas fa-plus-circle"></i> Add Customer</a>
                <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
   MAIN CONTENT
   ========================================================== -->
<div class="container">

    <?php echo displayFlash(); ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="page-title-custom">
            <i class="fas fa-users" style="color: #818cf8;"></i> Customers
        </h4>
        <span class="badge badge-primary-custom">
            <i class="fas fa-users"></i> Total: <?php echo count($customers); ?>
        </span>
    </div>

    <!-- Table Card -->
    <div class="card">
        <div class="card-header">
            <span><i class="fas fa-list"></i> All Customers</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer No.</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($customers) > 0): ?>
                            <?php foreach ($customers as $index => $customer): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($customer['customer_number']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['phone_number']); ?></td>
                                    <td>
                                        <?php if ($customer['customer_status'] == 'active'): ?>
                                            <span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span>
                                        <?php elseif ($customer['customer_status'] == 'inactive'): ?>
                                            <span class="badge badge-warning"><i class="fas fa-clock"></i> Inactive</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger"><?php echo ucfirst($customer['customer_status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="customers_view.php?id=<?php echo $customer['customer_id']; ?>" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="customers_edit.php?id=<?php echo $customer['customer_id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="customers_delete.php?id=<?php echo $customer['customer_id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:8px; color:rgba(255,255,255,0.3);"></i>
                                    No customers recorded
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center py-4 mt-3 footer-custom" style="font-size:0.8rem;">
        &copy; <?php echo date('Y'); ?> <strong>Water Management System</strong>  Haki zote zimehifadhiwa
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="script.js"></script>
</body>
</html>