<?php// includes/Auth.php
require_once 'Database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function register($username, $email, $password) {
        // Validate input
        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception("All fields are required");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        
        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }
        
        // Check if username or email already exists
        $existingUser = $this->db->fetch(
            "SELECT user_id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );
        
        if ($existingUser) {
            throw new Exception("Username or email already exists");
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        
        // Insert user
        $this->db->execute(
            "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)",
            [$username, $email, $passwordHash]
        );
        
        $userId = $this->db->lastInsertId();
        
        // Log activity
        $this->logActivity($userId, 'create', 'user', $userId, [
            'action' => 'user_registration'
        ]);
        
        return $userId;
    }
    
    public function login($username, $password) {
        // Get user by username or email
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1",
            [$username, $username]
        );
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new Exception("Invalid credentials");
        }
        
        // Generate JWT token
        $token = $this->generateJWT($user['user_id']);
        
        // Store session
        $this->storeSession($user['user_id'], $token);
        
        // Log activity
        $this->logActivity($user['user_id'], 'view', 'user', $user['user_id'], [
            'action' => 'login'
        ]);
        
        // Clean user data for response
        unset($user['password_hash']);
        
        return [
            'user' => $user,
            'token' => $token
        ];
    }
    
    public function validateToken($token) {
        if (!$token) {
            return false;
        }
        
        // Remove "Bearer " prefix if present
        $token = str_replace('Bearer ', '', $token);
        
        // Check if token exists in database and is not expired
        $session = $this->db->fetch(
            "SELECT s.*, u.* FROM user_sessions s 
             JOIN users u ON s.user_id = u.user_id 
             WHERE s.token_hash = ? AND s.expires_at > NOW() AND s.is_active = 1 AND u.is_active = 1",
            [hash('sha256', $token)]
        );
        
        if (!$session) {
            return false;
        }
        
        // Verify JWT structure (basic verification)
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            return false;
        }
        
        // Clean user data
        $user = [
            'user_id' => $session['user_id'],
            'username' => $session['username'],
            'email' => $session['email'],
            'first_name' => $session['first_name'],
            'last_name' => $session['last_name']
        ];
        
        return $user;
    }
    
    public function logout($token) {
        if (!$token) {
            return false;
        }
        
        $token = str_replace('Bearer ', '', $token);
        $tokenHash = hash('sha256', $token);
        
        // Deactivate session
        $this->db->execute(
            "UPDATE user_sessions SET is_active = 0 WHERE token_hash = ?",
            [$tokenHash]
        );
        
        return true;
    }
    
    private function generateJWT($userId) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload = json_encode([
            'user_id' => $userId,
            'exp' => time() + JWT_EXPIRY,
            'iat' => time()
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    private function storeSession($userId, $token) {
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + JWT_EXPIRY);
        
        // Clean up old sessions occasionally
        if (mt_rand(1, 100) <= (SESSION_CLEANUP_PROBABILITY * 100)) {
            $this->cleanupExpiredSessions();
        }
        
        // Store new session
        $this->db->execute(
            "INSERT INTO user_sessions (user_id, token_hash, expires_at, user_agent, ip_address) 
             VALUES (?, ?, ?, ?, ?)",
            [
                $userId,
                $tokenHash,
                $expiresAt,
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $this->getClientIP()
            ]
        );
    }
    
    private function cleanupExpiredSessions() {
        $this->db->execute(
            "DELETE FROM user_sessions WHERE expires_at < NOW() OR is_active = 0"
        );
    }
    
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    public function getCurrentUser($token) {
        return $this->validateToken($token);
    }
    
    public function updateProfile($userId, $data) {
        $allowedFields = ['first_name', 'last_name', 'email'];
        $updateFields = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception("No valid fields to update");
        }
        
        $params[] = $userId;
        
        $this->db->execute(
            "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = ?",
            $params
        );
        
        // Log activity
        $this->logActivity($userId, 'update', 'user', $userId, [
            'fields_updated' => array_keys($data)
        ]);
        
        return true;
    }
    
    private function logActivity($userId, $action, $resourceType, $resourceId, $details = []) {
        $this->db->execute(
            "INSERT INTO activity_log (user_id, action_type, resource_type, resource_id, details, ip_address, user_agent) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $action,
                $resourceType,
                $resourceId,
                json_encode($details),
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
    }
}
?>