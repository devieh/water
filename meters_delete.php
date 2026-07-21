<?php
// ============================================
// DELETE WATER METER - FIXED
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$meter_id = (int)($_GET['id'] ?? 0);
if (empty($meter_id)) {
    redirect('meters.php');
}

$meter = getWaterMeterById($meter_id);
if (!$meter) {
    setFlash('Meter not found', 'danger');
    redirect('meters.php');
}

// Initialize PDO variable
$pdo = null;

try {
    $pdo = getDB();
    
    // Check if meter has readings
    $check = $pdo->prepare("SELECT COUNT(*) FROM meter_readings WHERE meter_id = ?");
    $check->execute([$meter_id]);
    $reading_count = $check->fetchColumn();
    
    if ($reading_count > 0) {
        setFlash('Cannot delete meter with existing readings. Please delete readings first.', 'danger');
        redirect('meters.php');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Log audit
    $audit = $pdo->prepare("
        INSERT INTO audit_trail (user_id, action_type, module_name, record_id, old_data) 
        VALUES (?, 'delete', 'meters', ?, ?)
    ");
    $audit->execute([
        $_SESSION['user_id'],
        $meter_id,
        json_encode(['meter_number' => $meter['meter_number'], 'customer_id' => $meter['customer_id']])
    ]);
    
    // Delete meter
    $stmt = $pdo->prepare("DELETE FROM water_meters WHERE meter_id = ?");
    $stmt->execute([$meter_id]);
    
    $pdo->commit();
    setFlash('Meter deleted successfully!', 'success');
    
} catch (PDOException $e) {
    // Check if transaction is active before rollback
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('Error deleting meter: ' . $e->getMessage(), 'danger');
}

redirect('meters.php');
?>