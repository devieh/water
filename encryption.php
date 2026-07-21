<?php
// ============================================
// ENCRYPTION CLASS - WITH TYPE HINTS
// ============================================

// Check if constants are defined, if not, define them
if (!defined('ENCRYPTION_KEY')) {
    define('ENCRYPTION_KEY', 'water1234567890123456789012345678');
}

if (!defined('ENCRYPTION_METHOD')) {
    define('ENCRYPTION_METHOD', 'AES-256-CBC');
}

class Encryption {
    private string $key;
    private string $method;
    
    /**
     * Constructor
     */
    public function __construct(?string $key = null, ?string $method = null) {
        $this->key = $key ?? ENCRYPTION_KEY;
        $this->method = $method ?? ENCRYPTION_METHOD;
    }
    
    /**
     * Encrypt data before storing in database
     */
    public function encrypt(string $data): string {
        // Return empty data as is
        if (empty($data)) {
            return $data;
        }
        
        try {
            // Get IV length for the cipher method
            $ivLength = openssl_cipher_iv_length($this->method);
            if ($ivLength === false) {
                throw new Exception("Invalid cipher method: {$this->method}");
            }
            
            // Generate random IV
            $iv = openssl_random_pseudo_bytes($ivLength);
            
            // Encrypt the data
            $encrypted = openssl_encrypt(
                $data,
                $this->method,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($encrypted === false) {
                throw new Exception("Encryption failed");
            }
            
            // Combine IV and encrypted data, then base64 encode
            return base64_encode($iv . $encrypted);
            
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            return $data;
        }
    }
    
    /**
     * Decrypt data retrieved from database
     */
    public function decrypt(string $encryptedData): string {
        // Return empty data as is
        if (empty($encryptedData)) {
            return $encryptedData;
        }
        
        try {
            // Decode from base64
            $combined = base64_decode($encryptedData);
            
            if ($combined === false) {
                throw new Exception("Invalid base64 data");
            }
            
            // Get IV length
            $ivLength = openssl_cipher_iv_length($this->method);
            if ($ivLength === false) {
                throw new Exception("Invalid cipher method: {$this->method}");
            }
            
            // Check if data is valid
            if (strlen($combined) < $ivLength) {
                return $encryptedData;
            }
            
            // Extract IV and encrypted data
            $iv = substr($combined, 0, $ivLength);
            $encrypted = substr($combined, $ivLength);
            
            // Decrypt the data
            $decrypted = openssl_decrypt(
                $encrypted,
                $this->method,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            // Return decrypted data or original if decryption failed
            return $decrypted !== false ? $decrypted : $encryptedData;
            
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return $encryptedData;
        }
    }
    
    /**
     * Hash password using bcrypt
     */
    public function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password against hash
     */
    public function verifyPassword(string $password, string $hash): bool {
        if (empty($password) || empty($hash)) {
            return false;
        }
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random key
     */
    public function generateKey(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }
}

// Create global encryption instance
$encryption = new Encryption();
?>