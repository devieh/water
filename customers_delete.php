<?php
// ============================================
// DELETE CUSTOMER
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$customer_id = (int)($_GET['id'] ?? 0);
if (empty($customer_id)) {
    redirect('customers.php');
}

$customer = getCustomerById($customer_id);
if (!$customer) {
    setFlash('Customer not found', 'danger');
    redirect('customers.php');
}

try {
    $pdo = getDB();
    
    // Check if customer has meters
    $check = $pdo->prepare("SELECT COUNT(*) FROM water_meters WHERE customer_id = ?");
    $check->execute([$customer_id]);
    $meter_count = $check->fetchColumn();
    
    if ($meter_count > 0) {
        setFlash('Cannot delete customer with active meters. Please delete meters first.', 'danger');
        redirect('customers.php');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Log audit
    $audit = $pdo->prepare("
        INSERT INTO audit_trail (user_id, action_type, module_name, record_id, old_data) 
        VALUES (?, 'delete', 'customers', ?, ?)
    ");
    $audit->execute([
        $_SESSION['user_id'],
        $customer_id,
        json_encode(['customer_number' => $customer['customer_number'], 'full_name' => $customer['full_name']])
    ]);
    
    // Delete customer
    $stmt = $pdo->prepare("DELETE FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    
    // Delete user
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$customer['user_id']]);
    
    $pdo->commit();
    setFlash('Customer deleted successfully!', 'success');
    
} catch (PDOException $e) {
    $pdo->rollBack();
    setFlash('Error deleting customer: ' . $e->getMessage(), 'danger');
}

redirect('customers.php');
?>