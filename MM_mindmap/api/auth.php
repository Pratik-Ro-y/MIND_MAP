<?php
// api/auth.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/config.php';
require_once '../includes/Auth.php';

class AuthAPI {
    private $auth;
    
    public function __construct() {
        $this->auth = new Auth();
    }
    
    public function handleRequest() {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $action = $_GET['action'] ?? '';
            
            switch ($action) {
                case 'register':
                    if ($method !== 'POST') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->register();
                    
                case 'login':
                    if ($method !== 'POST') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->login();
                    
                case 'logout':
                    if ($method !== 'POST') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->logout();
                    
                case 'profile':
                    if ($method === 'GET') {
                        return $this->getProfile();
                    } elseif ($method === 'PUT') {
                        return $this->updateProfile();
                    }
                    throw new Exception('Method not allowed', 405);
                    
                case 'validate':
                    if ($method !== 'GET') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->validateToken();
                    
                default:
                    throw new Exception('Invalid action', 400);
            }
            
        } catch (Exception $e) {
            http_response_code($e->getCode() ?: 500);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode() ?: 500
            ];
        }
    }
    
    private function register() {
        $input = $this->getJsonInput();
        
        if (!isset($input['username']) || !isset($input['email']) || !isset($input['password'])) {
            throw new Exception('Missing required fields', 400);
        }
        
        $userId = $this->auth->register(
            trim($input['username']),
            trim($input['email']),
            $input['password']
        );
        
        return [
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user_id' => $userId
            ]
        ];
    }
    
    private function login() {
        $input = $this->getJsonInput();
        
        if (!isset($input['username']) || !isset($input['password'])) {
            throw new Exception('Missing username or password', 400);
        }
        
        $result = $this->auth->login(
            trim($input['username']),
            $input['password']
        );
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $result['user'],
                'token' => $result['token']
            ]
        ];
    }
    
    private function logout() {
        $token = $this->getBearerToken();
        
        if (!$token) {
            throw new Exception('No token provided', 401);
        }
        
        $this->auth->logout($token);
        
        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }
    
    private function getProfile() {
        $user = $this->requireAuth();
        
        return [
            'success' => true,
            'data' => $user
        ];
    }
    
    private function updateProfile() {
        $user = $this->requireAuth();
        $input = $this->getJsonInput();
        
        $this->auth->updateProfile($user['user_id'], $input);
        
        return [
            'success' => true,
            'message' => 'Profile updated successfully'
        ];
    }
    
    private function validateToken() {
        $token = $this->getBearerToken();
        
        if (!$token) {
            return [
                'success' => false,
                'message' => 'No token provided'
            ];
        }
        
        $user = $this->auth->validateToken($token);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid token'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Token is valid',
            'data' => $user
        ];
    }
    
    private function getJsonInput() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input', 400);
        }
        
        return $input ?: [];
    }
    
    private function getBearerToken() {
        $headers = apache_request_headers();
        
        if (isset($headers['Authorization'])) {
            return str_replace('Bearer ', '', $headers['Authorization']);
        }
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        }
        
        return null;
    }
    
    private function requireAuth() {
        $token = $this->getBearerToken();
        
        if (!$token) {
            throw new Exception('Authentication required', 401);
        }
        
        $user = $this->auth->validateToken($token);
        
        if (!$user) {
            throw new Exception('Invalid or expired token', 401);
        }
        
        return $user;
    }
}

try {
    $api = new AuthAPI();
    $response = $api->handleRequest();
    echo json_encode($response, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => DEBUG_MODE ? $e->getMessage() : 'An error occurred'
    ], JSON_PRETTY_PRINT);
}
?>