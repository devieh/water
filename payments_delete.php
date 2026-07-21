<?php
// ============================================
// DELETE PAYMENT - FIXED
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$payment_id = (int)($_GET['id'] ?? 0);
if (empty($payment_id)) {
    redirect('payments.php');
}

// Initialize PDO variable
$pdo = null;

try {
    $pdo = getDB();
    
    // Get payment data
    $get = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ?");
    $get->execute([$payment_id]);
    $payment = $get->fetch();
    
    if (!$payment) {
        setFlash('Payment not found', 'danger');
        redirect('payments.php');
    }
    
    $pdo->beginTransaction();
    
    // Update bill status back to issued
    $update = $pdo->prepare("
        UPDATE bills 
        SET bill_status = 'issued', payment_date = NULL, payment_reference = NULL, payment_method = NULL 
        WHERE bill_id = ?
    ");
    $update->execute([$payment['bill_id']]);
    
    // Log audit
    $audit = $pdo->prepare("
        INSERT INTO audit_trail (user_id, action_type, module_name, record_id, old_data) 
        VALUES (?, 'delete', 'payments', ?, ?)
    ");
    $audit->execute([
        $_SESSION['user_id'],
        $payment_id,
        json_encode(['payment_number' => $payment['payment_number']])
    ]);
    
    // Delete payment
    $stmt = $pdo->prepare("DELETE FROM payments WHERE payment_id = ?");
    $stmt->execute([$payment_id]);
    
    $pdo->commit();
    setFlash('Payment deleted successfully!', 'success');
    
} catch (PDOException $e) {
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('Error deleting payment: ' . $e->getMessage(), 'danger');
}

redirect('payments.php');
?>