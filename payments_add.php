<?php
// ============================================
// ADD PAYMENT - NO NAVBAR
// ============================================

require_once 'config.php';
require_once 'functions.php';

// Check if user is staff or admin
if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$error = '';
$selected_bill_id = isset($_GET['bill']) ? (int)$_GET['bill'] : 0;

// Get unpaid bills
try {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT b.*, c.customer_number, u.full_name as customer_name 
        FROM bills b 
        JOIN customers c ON b.customer_id = c.customer_id 
        JOIN users u ON c.user_id = u.user_id 
        WHERE b.bill_status IN ('issued', 'overdue')
        ORDER BY b.due_date ASC
    ");
    $bills = $stmt->fetchAll();
} catch (PDOException $e) {
    $bills = [];
    $error = 'Error loading bills: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $bill_id = (int)$_POST['bill_id'];
        $amount_paid = (float)$_POST['amount_paid'];
        $payment_date = sanitize($_POST['payment_date']);
        $payment_method = sanitize($_POST['payment_method']);
        $transaction_reference = sanitize($_POST['transaction_reference']);
        $notes = sanitize($_POST['notes']);
        
        if (empty($bill_id) || empty($amount_paid) || empty($payment_date)) {
            $error = 'Please fill in all required fields';
        } elseif ($amount_paid <= 0) {
            $error = 'Amount must be greater than zero';
        } else {
            $pdo = null;
            
            try {
                $pdo = getDB();
                $pdo->beginTransaction();
                
                $bill = $pdo->prepare("SELECT * FROM bills WHERE bill_id = ?");
                $bill->execute([$bill_id]);
                $bill_data = $bill->fetch();
                
                if (!$bill_data) {
                    throw new Exception('Bill not found');
                }
                
                if ($amount_paid > $bill_data['total_amount']) {
                    throw new Exception('Amount exceeds bill total. Max: TSh ' . number_format($bill_data['total_amount'], 2));
                }
                
                $payment_number = generateReference('PAY');
                
                $stmt = $pdo->prepare("
                    INSERT INTO payments (
                        payment_number, bill_id, customer_id, amount_paid, payment_date,
                        payment_method, transaction_reference, payment_status, processed_by, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?)
                ");
                $stmt->execute([
                    $payment_number,
                    $bill_id,
                    $bill_data['customer_id'],
                    $amount_paid,
                    $payment_date,
                    $payment_method,
                    $transaction_reference,
                    $_SESSION['user_id'],
                    $notes
                ]);
                
                $payment_id = $pdo->lastInsertId();
                
                if ($amount_paid >= $bill_data['total_amount']) {
                    $status = 'paid';
                } else {
                    $status = 'issued';
                }
                
                $update = $pdo->prepare("
                    UPDATE bills 
                    SET bill_status = ?, payment_date = ?, payment_reference = ?, payment_method = ? 
                    WHERE bill_id = ?
                ");
                $update->execute([
                    $status,
                    $payment_date,
                    $transaction_reference,
                    $payment_method,
                    $bill_id
                ]);
                
                $trans = $pdo->prepare("
                    INSERT INTO payment_transactions (
                        payment_id, transaction_type, amount, transaction_date, 
                        previous_balance, new_balance, transaction_reference, processed_by
                    ) VALUES (?, 'payment', ?, NOW(), ?, ?, ?, ?)
                ");
                $trans->execute([
                    $payment_id,
                    $amount_paid,
                    $bill_data['total_amount'] - $amount_paid,
                    $bill_data['total_amount'] - $amount_paid,
                    $transaction_reference,
                    $_SESSION['user_id']
                ]);
                
                $audit = $pdo->prepare("
                    INSERT INTO audit_trail (user_id, action_type, module_name, record_id, new_data) 
                    VALUES (?, 'create', 'payments', ?, ?)
                ");
                $audit->execute([
                    $_SESSION['user_id'],
                    $payment_id,
                    json_encode(['payment_number' => $payment_number, 'amount' => $amount_paid])
                ]);
                
                $pdo->commit();
                setFlash('Payment recorded successfully!', 'success');
                redirect('payments.php');
                
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
    <title>Add Payment - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6f9;
        }
        .header-simple {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        .header-simple h4 {
            margin: 0;
            font-weight: 600;
        }
        .header-simple a {
            color: white;
            margin-left: 15px;
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
        .btn-success {
            background: #28a745;
            border: none;
        }
        .btn-success:hover {
            background: #218838;
        }
    </style>
</head>
<body>

<!-- SIMPLE HEADER - BILA NAVBAR -->
<div class="header-simple">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <h4>
                <i class="fas fa-tint"></i> <?php echo APP_NAME; ?>
                <small style="font-size:0.7rem; opacity:0.7;">| Record Payment</small>
            </h4>
            <div>
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="payments.php"><i class="fas fa-arrow-left"></i> Back</a>
                <a href="logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-plus-circle"></i> Record Payment</h4>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (count($bills) == 0 && empty($error)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-info-circle"></i> No unpaid bills found. All bills have been paid.
            <a href="billing_generate.php" class="btn btn-sm btn-primary ml-2">Generate Bill</a>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <i class="fas fa-info-circle"></i> Payment Information
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Select Bill <span class="text-danger">*</span></label>
                            <select class="form-control" name="bill_id" id="bill_id" required>
                                <option value="">-- Select Bill --</option>
                                <?php foreach ($bills as $bill): ?>
                                    <option value="<?php echo $bill['bill_id']; ?>" 
                                        <?php echo $bill['bill_id'] == $selected_bill_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($bill['bill_number'] . ' - ' . $bill['customer_name'] . ' (TSh ' . number_format($bill['total_amount'], 2) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Amount Paid <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="amount_paid" 
                                   id="amount_paid" placeholder="Enter amount" required>
                            <small class="text-muted">Enter the amount paid by customer</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="payment_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-control" name="payment_method" required>
                                <option value="">-- Select Method --</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="mobile_money">Mobile Money (M-Pesa, Tigo Pesa)</option>
                                <option value="online">Online Payment</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Transaction Reference</label>
                            <input type="text" class="form-control" name="transaction_reference" 
                                   placeholder="e.g. M-PESA-2026-001 or Receipt No">
                            <small class="text-muted">Enter M-Pesa reference, bank slip no, or receipt no</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Notes</label>
                            <textarea class="form-control" name="notes" rows="2" 
                                      placeholder="Additional notes about this payment"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Important:</strong> Make sure the amount does not exceed the bill total.
                    The system will automatically mark the bill as <strong>"Paid"</strong> if full amount is paid.
                </div>
                
                <div class="mt-3">
                    <button type="submit" class="btn btn-success btn-lg px-4">
                        <i class="fas fa-credit-card"></i> Record Payment
                    </button>
                    <a href="payments.php" class="btn btn-secondary btn-lg px-4">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="text-center text-muted py-4 mt-3" style="border-top: 1px solid #e9ecef;">
        <small>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - All rights reserved</small>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        // Auto-fill amount when bill is selected
        $('#bill_id').on('change', function() {
            var selected = $(this).find('option:selected');
            var text = selected.text();
            var match = text.match(/\(TSh\s+([\d,]+\.\d{2})\)/);
            if (match) {
                var amount = match[1].replace(/,/g, '');
                $('#amount_paid').val(amount);
            }
        });
    });
</script>
</body>
</html>