<?php
// ============================================
// DELETE BILL - FIXED
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$bill_id = (int)($_GET['id'] ?? 0);
if (empty($bill_id)) {
    redirect('billing.php');
}

// Initialize PDO variable
$pdo = null;

try {
    $pdo = getDB();
    
    // Check if bill has payments
    $check = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE bill_id = ?");
    $check->execute([$bill_id]);
    $payment_count = $check->fetchColumn();
    
    if ($payment_count > 0) {
        setFlash('Cannot delete bill with existing payments. Delete payments first.', 'danger');
        redirect('billing.php');
    }
    
    $pdo->beginTransaction();
    
    // Log audit
    $audit = $pdo->prepare("
        INSERT INTO audit_trail (user_id, action_type, module_name, record_id, old_data) 
        VALUES (?, 'delete', 'bills', ?, ?)
    ");
    $audit->execute([
        $_SESSION['user_id'],
        $bill_id,
        json_encode(['bill_id' => $bill_id])
    ]);
    
    // Delete bill
    $stmt = $pdo->prepare("DELETE FROM bills WHERE bill_id = ?");
    $stmt->execute([$bill_id]);
    
    $pdo->commit();
    setFlash('Bill deleted successfully!', 'success');
    
} catch (PDOException $e) {
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('Error deleting bill: ' . $e->getMessage(), 'danger');
}

redirect('billing.php');
?>