<?php
// ============================================
// HELPER FUNCTIONS - FULLY FIXED
// ============================================

// Load config first
require_once 'config.php';

// Load encryption class
require_once 'encryption.php';

// ============================================
// SESSION & AUTHENTICATION FUNCTIONS
// ============================================

/**
 * Redirect to a page
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("getCurrentUser error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user by ID
 */
function getUserById(int $id): ?array {
    if (empty($id)) {
        return null;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("getUserById error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get customer by user ID
 */
function getCustomerByUserId(int $user_id): ?array {
    if (empty($user_id)) {
        return null;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("getCustomerByUserId error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all customers
 */
function getCustomers(): array {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("
            SELECT c.*, u.full_name, u.email, u.phone_number 
            FROM customers c 
            JOIN users u ON c.user_id = u.user_id 
            ORDER BY c.created_at DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getCustomers error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get customer by ID
 */
function getCustomerById(int $customer_id): ?array {
    if (empty($customer_id)) {
        return null;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name, u.email, u.phone_number 
            FROM customers c 
            JOIN users u ON c.user_id = u.user_id 
            WHERE c.customer_id = ?
        ");
        $stmt->execute([$customer_id]);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("getCustomerById error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get customer meters
 */
function getCustomerMeters(int $customer_id): array {
    if (empty($customer_id)) {
        return [];
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM water_meters WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getCustomerMeters error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all water meters
 */
function getWaterMeters(): array {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("
            SELECT m.*, c.customer_number, u.full_name as customer_name 
            FROM water_meters m 
            JOIN customers c ON m.customer_id = c.customer_id 
            JOIN users u ON c.user_id = u.user_id 
            ORDER BY m.created_at DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getWaterMeters error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get water meter by ID
 */
function getWaterMeterById(int $meter_id): ?array {
    if (empty($meter_id)) {
        return null;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT m.*, c.customer_number, u.full_name as customer_name 
            FROM water_meters m 
            JOIN customers c ON m.customer_id = c.customer_id 
            JOIN users u ON c.user_id = u.user_id 
            WHERE m.meter_id = ?
        ");
        $stmt->execute([$meter_id]);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("getWaterMeterById error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get meter readings by meter ID
 */
function getMeterReadings(int $meter_id, int $limit = 10): array {
    if (empty($meter_id)) {
        return [];
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT * FROM meter_readings 
            WHERE meter_id = ? 
            ORDER BY reading_date DESC 
            LIMIT ?
        ");
        $stmt->execute([$meter_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getMeterReadings error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get latest meter reading
 */
function getLatestMeterReading(int $meter_id): ?array {
    if (empty($meter_id)) {
        return null;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT * FROM meter_readings 
            WHERE meter_id = ? 
            ORDER BY reading_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$meter_id]);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("getLatestMeterReading error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all bills
 */
function getBills(int $limit = 50): array {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT b.*, c.customer_number, u.full_name as customer_name 
            FROM bills b 
            JOIN customers c ON b.customer_id = c.customer_id 
            JOIN users u ON c.user_id = u.user_id 
            ORDER BY b.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getBills error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get bill by ID
 */
function getBillById(int $bill_id): ?array {
    if (empty($bill_id)) {
        return null;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT b.*, c.customer_number, u.full_name as customer_name 
            FROM bills b 
            JOIN customers c ON b.customer_id = c.customer_id 
            JOIN users u ON c.user_id = u.user_id 
            WHERE b.bill_id = ?
        ");
        $stmt->execute([$bill_id]);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("getBillById error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get customer bills
 */
function getCustomerBills(int $customer_id, int $limit = 20): array {
    if (empty($customer_id)) {
        return [];
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT * FROM bills 
            WHERE customer_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$customer_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getCustomerBills error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all payments
 */
function getPayments(int $limit = 50): array {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT p.*, c.customer_number, u.full_name as customer_name, b.bill_number 
            FROM payments p 
            JOIN customers c ON p.customer_id = c.customer_id 
            JOIN users u ON c.user_id = u.user_id 
            JOIN bills b ON p.bill_id = b.bill_id 
            ORDER BY p.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getPayments error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get payment by ID
 */
function getPaymentById(int $payment_id): ?array {
    if (empty($payment_id)) {
        return null;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT p.*, c.customer_number, u.full_name as customer_name, b.bill_number 
            FROM payments p 
            JOIN customers c ON p.customer_id = c.customer_id 
            JOIN users u ON c.user_id = u.user_id 
            JOIN bills b ON p.bill_id = b.bill_id 
            WHERE p.payment_id = ?
        ");
        $stmt->execute([$payment_id]);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("getPaymentById error: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate total revenue
 */
function getTotalRevenue(): float {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("
            SELECT SUM(amount_paid) as total 
            FROM payments 
            WHERE payment_status = 'completed'
        ");
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    } catch (PDOException $e) {
        error_log("getTotalRevenue error: " . $e->getMessage());
        return 0.0;
    }
}

/**
 * Get total customers
 */
function getTotalCustomers(): int {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers WHERE customer_status = 'active'");
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    } catch (PDOException $e) {
        error_log("getTotalCustomers error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get total active meters
 */
function getTotalActiveMeters(): int {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM water_meters WHERE meter_status = 'active'");
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    } catch (PDOException $e) {
        error_log("getTotalActiveMeters error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get overdue bills count
 */
function getOverdueBills(): int {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM bills 
            WHERE bill_status = 'overdue' OR (bill_status = 'issued' AND due_date < CURDATE())
        ");
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    } catch (PDOException $e) {
        error_log("getOverdueBills error: " . $e->getMessage());
        return 0;
    }
}

// ============================================
// SECURITY FUNCTIONS
// ============================================

/**
 * Generate CSRF token
 */
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken(string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input - accepts mixed type (string or array)
 * 
 * @param string|array $input The input to sanitize
 * @return string|array The sanitized input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Tanzanian format)
 */
function validatePhone(string $phone): bool {
    return (bool)preg_match('/^(\+255|0)[0-9]{9}$/', $phone);
}

// ============================================
// FORMATTING FUNCTIONS
// ============================================

/**
 * Format currency
 */
function formatCurrency(float $amount): string {
    return number_format($amount, 2, '.', ',');
}

/**
 * Format date
 */
function formatDate(string $date): string {
    if (empty($date)) {
        return '';
    }
    return date('d M Y', strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime(string $datetime): string {
    if (empty($datetime)) {
        return '';
    }
    return date('d M Y H:i', strtotime($datetime));
}

/**
 * Get time ago
 */
function timeAgo(string $datetime): string {
    if (empty($datetime)) {
        return '';
    }
    
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return formatDate($datetime);
    }
}

// ============================================
// UI HELPER FUNCTIONS
// ============================================

/**
 * Get status badge HTML
 */
function getStatusBadge(string $status): string {
    $colors = [
        'active' => 'success',
        'inactive' => 'warning',
        'suspended' => 'danger',
        'issued' => 'info',
        'paid' => 'success',
        'overdue' => 'danger',
        'pending' => 'warning',
        'verified' => 'success',
        'rejected' => 'danger',
        'draft' => 'secondary',
        'cancelled' => 'danger',
        'completed' => 'success',
        'failed' => 'danger',
        'refunded' => 'warning',
        'resolved' => 'success',
        'closed' => 'secondary',
        'in_progress' => 'warning',
        'assigned' => 'info',
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger',
        'emergency' => 'danger'
    ];
    
    $color = $colors[$status] ?? 'secondary';
    return "<span class='badge badge-{$color}'>" . ucfirst(str_replace('_', ' ', $status)) . "</span>";
}

/**
 * Check if user has permission
 * 
 * @param string $required_role The required role (admin, staff, customer)
 * @return bool True if user has permission
 */
function hasPermission(string $required_role): bool {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    // Allow customer to access customer pages
    if ($required_role === 'customer') {
        return true;
    }
    
    $roles = [
        'admin' => 3,
        'staff' => 2,
        'customer' => 1
    ];
    
    $user_role = $user['user_type'] ?? 'customer';
    $user_level = $roles[$user_role] ?? 1;
    $required_level = $roles[$required_role] ?? 1;
    
    return $user_level >= $required_level;
}

// ============================================
// FLASH MESSAGES
// ============================================

/**
 * Display flash message
 */
function displayFlash(): string {
    if (isset($_SESSION['flash_message']) && !empty($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        $type = $message['type'] ?? 'info';
        $text = $message['text'] ?? '';
        
        return "<div class='alert alert-{$type} alert-dismissible fade show'>
                    <i class='fas fa-{$type}-circle'></i> {$text}
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                        <span aria-hidden='true'>&times;</span>
                    </button>
                </div>";
    }
    return '';
}

/**
 * Set flash message
 */
function setFlash(string $text, string $type = 'success'): void {
    $_SESSION['flash_message'] = [
        'text' => $text,
        'type' => $type
    ];
}

// ============================================
// DATABASE UTILITY FUNCTIONS
// ============================================

/**
 * Get single record by ID
 */
function getRecordById(string $table, int $id, string $id_field = 'id'): ?array {
    if (empty($table) || empty($id)) {
        return null;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$id_field} = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("getRecordById error: " . $e->getMessage());
        return null;
    }
}

/**
 * Delete record by ID
 */
function deleteRecord(string $table, int $id, string $id_field = 'id'): bool {
    if (empty($table) || empty($id)) {
        return false;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$id_field} = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("deleteRecord error: " . $e->getMessage());
        return false;
    }
}

// ============================================
// ENCRYPTION WRAPPER FUNCTIONS
// ============================================

/**
 * Encrypt data using global encryption instance
 */
function encryptData(string $data): string {
    global $encryption;
    if (!isset($encryption)) {
        $encryption = new Encryption();
    }
    return $encryption->encrypt($data);
}

/**
 * Decrypt data using global encryption instance
 */
function decryptData(string $data): string {
    global $encryption;
    if (!isset($encryption)) {
        $encryption = new Encryption();
    }
    return $encryption->decrypt($data);
}

// ============================================
// BUSINESS LOGIC FUNCTIONS
// ============================================

/**
 * Calculate bill total
 */
function calculateBillTotal(
    float $consumption,
    float $consumption_rate,
    float $base_charge = 0,
    float $tax_rate = 0
): array {
    $consumption_amount = $consumption * $consumption_rate;
    $subtotal = $base_charge + $consumption_amount;
    $tax = $subtotal * ($tax_rate / 100);
    $total = $subtotal + $tax;
    
    return [
        'consumption_amount' => $consumption_amount,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total
    ];
}

/**
 * Get customer balance
 */
function getCustomerBalance(int $customer_id): float {
    if (empty($customer_id)) {
        return 0.0;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(b.total_amount), 0) as total_billed,
                COALESCE(SUM(p.amount_paid), 0) as total_paid
            FROM bills b
            LEFT JOIN payments p ON b.bill_id = p.bill_id AND p.payment_status = 'completed'
            WHERE b.customer_id = ?
        ");
        $stmt->execute([$customer_id]);
        $result = $stmt->fetch();
        
        $total_billed = (float)($result['total_billed'] ?? 0);
        $total_paid = (float)($result['total_paid'] ?? 0);
        
        return $total_billed - $total_paid;
    } catch (PDOException $e) {
        error_log("getCustomerBalance error: " . $e->getMessage());
        return 0.0;
    }
}

/**
 * Generate unique reference number
 */
function generateReference(string $prefix = 'REF'): string {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

?>