<?php
// ============================================
// EDIT METER READING - FIXED (NO WARNINGS)
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

$pdo = null;
$reading = null;

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM meter_readings WHERE reading_id = ?");
    $stmt->execute([$reading_id]);
    $reading = $stmt->fetch();
    
    if (!$reading) {
        setFlash('Reading not found', 'danger');
        redirect('readings.php');
    }
} catch (PDOException $e) {
    setFlash('Error loading reading: ' . $e->getMessage(), 'danger');
    redirect('readings.php');
}

$error = '';
$meters = getWaterMeters();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $meter_id = (int)$_POST['meter_id'];
        $reading_date = sanitize($_POST['reading_date']);
        $reading_value = (float)$_POST['reading_value'];
        $reading_type = sanitize($_POST['reading_type']);
        $reading_status = sanitize($_POST['reading_status']);
        $notes = sanitize($_POST['notes']);
        
        if (empty($meter_id) || empty($reading_date) || empty($reading_value)) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                $pdo = getDB();
                
                $prev = $pdo->prepare("
                    SELECT reading_value FROM meter_readings 
                    WHERE meter_id = ? AND reading_date < ? AND reading_id != ?
                    ORDER BY reading_date DESC LIMIT 1
                ");
                $prev->execute([$meter_id, $reading_date, $reading_id]);
                $prev_reading = $prev->fetch();
                $previous_reading = $prev_reading['reading_value'] ?? 0;
                $consumption = $reading_value - $previous_reading;
                
                $stmt = $pdo->prepare("
                    UPDATE meter_readings 
                    SET meter_id = ?, reading_date = ?, reading_value = ?, 
                        previous_reading = ?, consumption = ?, 
                        reading_type = ?, reading_status = ?, notes = ?
                    WHERE reading_id = ?
                ");
                $stmt->execute([
                    $meter_id,
                    $reading_date,
                    $reading_value,
                    $previous_reading,
                    $consumption,
                    $reading_type,
                    $reading_status,
                    $notes,
                    $reading_id
                ]);
                
                $audit = $pdo->prepare("
                    INSERT INTO audit_trail (user_id, action_type, module_name, record_id, new_data) 
                    VALUES (?, 'update', 'readings', ?, ?)
                ");
                $audit->execute([
                    $_SESSION['user_id'],
                    $reading_id,
                    json_encode(['reading_id' => $reading_id, 'reading_value' => $reading_value])
                ]);
                
                setFlash('Reading updated successfully!', 'success');
                redirect('readings.php');
                
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$csrf_token = generateCSRFToken();

/**
 * Safely escape HTML special characters
 * 
 * @param string|null $value The value to escape
 * @return string The escaped value
 */
function safeEscape(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Reading - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <!-- Simple Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tint"></i> <?php echo APP_NAME; ?>
            </a>
            <div class="ml-auto">
                <a href="readings.php" class="btn btn-sm btn-light">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <a href="logout.php" class="btn btn-sm btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-edit"></i> Edit Meter Reading</h4>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo safeEscape($error); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Edit Reading Information
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Meter *</label>
                                <select class="form-control" name="meter_id" required>
                                    <option value="">Select Meter</option>
                                    <?php foreach ($meters as $meter): ?>
                                        <option value="<?php echo $meter['meter_id']; ?>"
                                            <?php echo $meter['meter_id'] == $reading['meter_id'] ? 'selected' : ''; ?>>
                                            <?php echo safeEscape($meter['meter_number'] . ' - ' . $meter['customer_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Reading Date *</label>
                                <input type="date" class="form-control" name="reading_date" 
                                       value="<?php echo safeEscape($reading['reading_date']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Reading Value (m³) *</label>
                                <input type="number" step="0.01" class="form-control" name="reading_value" 
                                       value="<?php echo safeEscape($reading['reading_value']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Reading Type</label>
                                <select class="form-control" name="reading_type">
                                    <option value="normal" <?php echo $reading['reading_type'] == 'normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="estimated" <?php echo $reading['reading_type'] == 'estimated' ? 'selected' : ''; ?>>Estimated</option>
                                    <option value="final" <?php echo $reading['reading_type'] == 'final' ? 'selected' : ''; ?>>Final</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="reading_status">
                                    <option value="pending" <?php echo $reading['reading_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="verified" <?php echo $reading['reading_status'] == 'verified' ? 'selected' : ''; ?>>Verified</option>
                                    <option value="rejected" <?php echo $reading['reading_status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="2"><?php echo safeEscape($reading['notes']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Reading
                        </button>
                        <a href="readings.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>