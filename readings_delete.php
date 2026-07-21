<?php
// ============================================
// DELETE METER READING - FIXED
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !hasPermission('staff')) {
    redirect('index.php');
}

$reading_id = (int)($_GET['id'] ?? 0);
if (empty($reading_id)) {
    redirect('readings.php');
}

// Initialize PDO variable
$pdo = null;

try {
    $pdo = getDB();
    
    // Get reading data for audit
    $get = $pdo->prepare("SELECT * FROM meter_readings WHERE reading_id = ?");
    $get->execute([$reading_id]);
    $reading = $get->fetch();
    
    if (!$reading) {
        setFlash('Reading not found', 'danger');
        redirect('readings.php');
    }
    
    $pdo->beginTransaction();
    
    // Log audit
    $audit = $pdo->prepare("
        INSERT INTO audit_trail (user_id, action_type, module_name, record_id, old_data) 
        VALUES (?, 'delete', 'readings', ?, ?)
    ");
    $audit->execute([
        $_SESSION['user_id'],
        $reading_id,
        json_encode(['meter_id' => $reading['meter_id'], 'reading_value' => $reading['reading_value']])
    ]);
    
    // Delete reading
    $stmt = $pdo->prepare("DELETE FROM meter_readings WHERE reading_id = ?");
    $stmt->execute([$reading_id]);
    
    $pdo->commit();
    setFlash('Reading deleted successfully!', 'success');
    
} catch (PDOException $e) {
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('Error deleting reading: ' . $e->getMessage(), 'danger');
}

redirect('readings.php');
?>