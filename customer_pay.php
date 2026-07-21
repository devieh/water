<?php
// ============================================
// CUSTOMER PAY - PROCESS PAYMENT
// ============================================

require_once 'config.php';
require_once 'functions.php';

// Only customers can access
if (!isLoggedIn() || !hasPermission('customer')) {
    redirect('index.php');
}

$user = getCurrentUser();
$customer = getCustomerByUserId($user['user_id']);

if (!$customer) {
    setFlash('Customer not found', 'danger');
    redirect('dashboard.php');
}

$error = '';
$success = '';
$bill = null;
$amount_paid = 0;

// ==========================================================
// CHECK IF DATA COMES FROM DASHBOARD FORM
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        // Get data from form
        $bill_number = sanitize($_POST['bill_number'] ?? '');
        $full_name = sanitize($_POST['full_name'] ?? '');
        $amount_paid = (float)($_POST['amount_paid'] ?? 0);
        $payment_method = sanitize($_POST['payment_method'] ?? '');
        $transaction_reference = sanitize($_POST['transaction_reference'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $payment_date = date('Y-m-d');
        
        // Validate
        if (empty($bill_number)) {
            $error = 'Tafadhali andika namba ya bili.';
        } elseif (empty($full_name)) {
            $error = 'Tafadhali andika jina lako kamili.';
        } elseif ($amount_paid <= 0) {
            $error = 'Kiasi lazima kiwe kikubwa kuliko sifuri.';
        } elseif (empty($payment_method)) {
            $error = 'Tafadhali chagua njia ya malipo.';
        } elseif (empty($location)) {
            $error = 'Tafadhali andika eneo lako.';
        } elseif (empty($phone)) {
            $error = 'Tafadhali andika namba yako ya simu.';
        } else {
            try {
                $pdo = getDB();
                $pdo->beginTransaction();
                
                // ==========================================================
                // FIND BILL BY BILL NUMBER
                // ==========================================================
                $bill_query = $pdo->prepare("
                    SELECT b.*, c.customer_number 
                    FROM bills b 
                    JOIN customers c ON b.customer_id = c.customer_id 
                    WHERE b.bill_number = ? AND b.customer_id = ?
                ");
                $bill_query->execute([$bill_number, $customer['customer_id']]);
                $bill = $bill_query->fetch();
                
                if (!$bill) {
                    throw new Exception('Bill not found or does not belong to you. Please check the bill number.');
                }
                
                // Check if bill is already paid
                if ($bill['bill_status'] == 'paid') {
                    throw new Exception('This bill is already paid.');
                }
                
                // Check amount
                if ($amount_paid < $bill['total_amount']) {
                    throw new Exception('Amount is less than bill total. Please pay full amount: TSh ' . number_format($bill['total_amount'], 2));
                } elseif ($amount_paid > $bill['total_amount']) {
                    throw new Exception('Amount exceeds bill total. Maximum: TSh ' . number_format($bill['total_amount'], 2));
                }
                
                // ==========================================================
                // GENERATE PAYMENT NUMBER
                // ==========================================================
                $payment_number = 'PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
                
                // If no transaction reference, auto-generate
                if (empty($transaction_reference)) {
                    $transaction_reference = 'AUTO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                }
                
                // ==========================================================
                // INSERT PAYMENT
                // ==========================================================
                $stmt = $pdo->prepare("
                    INSERT INTO payments (
                        payment_number, bill_id, customer_id, amount_paid, payment_date,
                        payment_method, transaction_reference, payment_status, processed_by, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?)
                ");
                $stmt->execute([
                    $payment_number,
                    $bill['bill_id'],
                    $customer['customer_id'],
                    $amount_paid,
                    $payment_date,
                    $payment_method,
                    $transaction_reference,
                    $_SESSION['user_id'],
                    'Payment made by customer from ' . ($location ?? 'N/A') . ' - ' . $full_name
                ]);
                
                $payment_id = $pdo->lastInsertId();
                
                // ==========================================================
                // UPDATE BILL STATUS TO PAID
                // ==========================================================
                $update = $pdo->prepare("
                    UPDATE bills 
                    SET bill_status = 'paid', 
                        payment_date = ?, 
                        payment_reference = ?, 
                        payment_method = ? 
                    WHERE bill_id = ?
                ");
                $update->execute([
                    $payment_date,
                    $transaction_reference,
                    $payment_method,
                    $bill['bill_id']
                ]);
                
                // ==========================================================
                // LOG PAYMENT TRANSACTION
                // ==========================================================
                $trans = $pdo->prepare("
                    INSERT INTO payment_transactions (
                        payment_id, transaction_type, amount, transaction_date, 
                        previous_balance, new_balance, transaction_reference, processed_by
                    ) VALUES (?, 'payment', ?, NOW(), ?, ?, ?, ?)
                ");
                $trans->execute([
                    $payment_id,
                    $amount_paid,
                    $bill['total_amount'] - $amount_paid,
                    $bill['total_amount'] - $amount_paid,
                    $transaction_reference,
                    $_SESSION['user_id']
                ]);
                
                // ==========================================================
                // LOG AUDIT
                // ==========================================================
                $audit = $pdo->prepare("
                    INSERT INTO audit_trail (user_id, action_type, module_name, record_id, new_data) 
                    VALUES (?, 'create', 'payments', ?, ?)
                ");
                $audit->execute([
                    $_SESSION['user_id'],
                    $payment_id,
                    json_encode([
                        'payment_number' => $payment_number, 
                        'amount' => $amount_paid,
                        'method' => $payment_method
                    ])
                ]);
                
                $pdo->commit();
                
                setFlash('✅ Malipo yamefanikiwa! Bili yako imelipwa.', 'success');
                redirect('dashboard.php');
                
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

// ==========================================================
// IF BILL ID IS PASSED VIA GET (from dashboard link)
// ==========================================================
if (isset($_GET['bill']) && empty($_POST)) {
    $bill_id = (int)$_GET['bill'];
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT b.*, c.customer_number 
            FROM bills b 
            JOIN customers c ON b.customer_id = c.customer_id 
            WHERE b.bill_id = ? AND b.customer_id = ?
        ");
        $stmt->execute([$bill_id, $customer['customer_id']]);
        $bill = $stmt->fetch();
        
        if ($bill && $bill['bill_status'] != 'paid') {
            // Pre-fill amount
            $amount_paid = $bill['total_amount'];
            $bill_number = $bill['bill_number'];
        }
    } catch (PDOException $e) {
        // Ignore
    }
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lipa Bili - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Inter', -apple-system, sans-serif;
        }
        
        .payment-card {
            max-width: 600px;
            margin: 30px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .payment-card .bill-summary {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #4f46e5;
        }
        
        .payment-card .bill-summary .amount {
            font-size: 2rem;
            font-weight: 700;
            color: #dc3545;
        }
        
        .payment-card .form-control {
            border-radius: 10px;
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .payment-card .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
        
        .payment-card .btn-pay {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            color: #fff;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .payment-card .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        
        .payment-methods {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .payment-methods .method-btn {
            padding: 8px 14px;
            border-radius: 20px;
            border: 2px solid #e2e8f0;
            background: #fff;
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
        
        .header-simple {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 15px 0;
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
            color: #94a3b8;
        }
        
        .required {
            color: #dc3545;
        }
        
        @media (max-width: 576px) {
            .payment-card {
                padding: 20px;
                margin: 15px;
            }
            .payment-card .bill-summary .amount {
                font-size: 1.4rem;
            }
            .payment-methods .method-btn {
                font-size: 0.65rem;
                padding: 6px 8px;
                min-width: 45px;
            }
        }
    </style>
</head>
<body>

<!-- ==========================================================
   HEADER
   ========================================================== -->
<div class="header-simple">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4>
                    <i class="fas fa-tint" style="color: #60a5fa;"></i> 
                    <?php echo APP_NAME; ?>
                    <small style="font-size:0.7rem; opacity:0.7; margin-left:8px;">| Lipa Bili</small>
                </h4>
            </div>
            <div class="col-md-6 text-right">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
   MAIN CONTENT
   ========================================================== -->
<div class="container">
    <div class="payment-card">
        
        <!-- Header -->
        <div class="text-center mb-4">
            <i class="fas fa-credit-card" style="font-size: 2.5rem; color: #10b981;"></i>
            <h3 class="mt-2">Lipa Bili Yako</h3>
            <p class="text-muted">Jaza taarifa zako na ulipe bili yako</p>
        </div>

        <!-- Errors -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Bill Summary (if bill found) -->
        <?php if (isset($bill) && $bill && $bill !== false): ?>
        <div class="bill-summary">
            <div class="row">
                <div class="col-6">
                    <p class="mb-1 text-muted" style="font-size:0.8rem;">Bill Number</p>
                    <strong><?php echo htmlspecialchars($bill['bill_number']); ?></strong>
                </div>
                <div class="col-6 text-right">
                    <p class="mb-1 text-muted" style="font-size:0.8rem;">Due Date</p>
                    <strong><?php echo formatDate($bill['due_date']); ?></strong>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12 text-center">
                    <p class="mb-0 text-muted" style="font-size:0.8rem;">Total Amount</p>
                    <div class="amount">TSh <?php echo number_format($bill['total_amount'], 2); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payment Form -->
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <!-- Hidden bill_id if from GET -->
            <?php if (isset($bill_id) && $bill_id > 0): ?>
                <input type="hidden" name="bill_id" value="<?php echo $bill_id; ?>">
            <?php endif; ?>

            <!-- Jina Kamili -->
            <div class="form-group">
                <label class="font-weight-bold">Jina Lako Kamili <span class="required">*</span></label>
                <input type="text" class="form-control" name="full_name" 
                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                       placeholder="Andika jina lako kamili" required>
                <small class="text-muted">Andika jina lako kamili kama lilivyo kwenye akaunti yako</small>
            </div>

            <!-- Namba ya Bili -->
            <div class="form-group">
                <label class="font-weight-bold">Namba ya Bili <span class="required">*</span></label>
                <input type="text" class="form-control" name="bill_number" 
                       value="<?php echo isset($bill_number) && $bill_number ? htmlspecialchars($bill_number) : ''; ?>" 
                       placeholder="e.g. BIL-2026-001" required>
                <small class="text-muted">Andika namba ya bili unayotaka kulipa</small>
            </div>

            <!-- Amount -->
            <div class="form-group">
                <label class="font-weight-bold">Kiasi cha Kulipa <span class="required">*</span></label>
                <input type="number" step="0.01" class="form-control" name="amount_paid" 
                       value="<?php echo isset($amount_paid) && $amount_paid > 0 ? $amount_paid : ''; ?>" 
                       placeholder="Weka kiasi unachotaka kulipa" required>
                <small class="text-muted">Weka kiasi unachotaka kulipa (kiasi chote kinapendekezwa)</small>
            </div>

            <!-- Payment Method -->
            <div class="form-group">
                <label class="font-weight-bold">Njia ya Malipo <span class="required">*</span></label>
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

            <!-- Transaction Reference -->
            <div class="form-group">
                <label class="font-weight-bold">Namba ya Marejeleo</label>
                <input type="text" class="form-control" name="transaction_reference" 
                       placeholder="e.g. MPESA-2026-001 au namba ya risiti">
                <small class="text-muted">Weka namba ya M-Pesa, slip ya benki, au namba ya risiti</small>
            </div>

            <!-- Location -->
            <div class="form-group">
                <label class="font-weight-bold">Eneo Lako <span class="required">*</span></label>
                <input type="text" class="form-control" name="location" 
                       placeholder="Andika eneo lako (Mtaa/Kata)" required>
                <small class="text-muted">Andika eneo unalopokea huduma ya maji</small>
            </div>

            <!-- Phone Number -->
            <div class="form-group">
                <label class="font-weight-bold">Namba ya Simu <span class="required">*</span></label>
                <input type="text" class="form-control" name="phone" 
                       placeholder="Andika namba yako" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" required>
                <small class="text-muted">Tumia namba ya simu unayotumia</small>
            </div>

            <!-- Info Alert -->
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle"></i> 
                <strong>Taarifa:</strong> Malipo yako yatachakatwa mara moja.
                Utapata uthibitisho baada ya kukamilisha.
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-pay">
                <i class="fas fa-check-circle"></i> Lipa Sasa
            </button>
        </form>

        <!-- Back Link -->
        <div class="text-center mt-3">
            <a href="dashboard.php" class="text-muted" style="font-size:0.85rem;">
                <i class="fas fa-arrow-left"></i> Rudi kwenye Dashboard
            </a>
        </div>
    </div>
</div>

<!-- ==========================================================
   SCRIPTS
   ========================================================== -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Payment method selection
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