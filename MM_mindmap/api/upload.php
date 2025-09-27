<?php
// api/upload.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/config.php';
require_once '../includes/Auth.php';
require_once '../includes/FileHandler.php';
require_once '../includes/AIProcessor.php';

class UploadAPI {
    private $auth;
    private $fileHandler;
    private $aiProcessor;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->fileHandler = new FileHandler();
        $this->aiProcessor = new AIProcessor();
    }
    
    public function handleRequest() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $action = $_GET['action'] ?? 'upload';
            
            switch ($action) {
                case 'upload':
                    return $this->handleFileUpload();
                case 'analyze':
                    return $this->analyzeUploadedFile();
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
    
    private function handleFileUpload() {
        $user = $this->requireAuth();
        
        if (!isset($_FILES['file']) || empty($_FILES['file'])) {
            throw new Exception('No file uploaded', 400);
        }
        
        $fileInfo = $this->fileHandler->handleUpload($_FILES['file']);
        
        // Extract text content from file
        $textContent = $this->fileHandler->extractTextContent(
            $fileInfo['file_path'], 
            $fileInfo['extension']
        );
        
        return [
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => [
                'file_info' => $fileInfo,
                'content_preview' => substr($textContent, 0, 500),
                'content_length' => strlen($textContent),
                'ready_for_analysis' => true
            ]
        ];
    }
    
    private function analyzeUploadedFile() {
        $user = $this->requireAuth();
        $input = $this->getJsonInput();
        
        if (!isset($input['filename'])) {
            throw new Exception('Filename is required', 400);
        }
        
        $fileInfo = $this->fileHandler->getFileInfo($input['filename']);
        $extension = pathinfo($fileInfo['filename'], PATHINFO_EXTENSION);
        
        // Extract text content
        $textContent = $this->fileHandler->extractTextContent(
            $fileInfo['file_path'], 
            $extension
        );
        
        // Analyze content with AI
        $analysis = $this->aiProcessor->analyzeContent($textContent);
        
        return [
            'success' => true,
            'message' => 'File analyzed successfully',
            'data' => [
                'analysis' => $analysis,
                'content_stats' => [
                    'word_count' => str_word_count($textContent),
                    'character_count' => strlen($textContent),
                    'estimated_reading_time' => ceil(str_word_count($textContent) / 200) // words per minute
                ]
            ]
        ];
    }
    
    private function getJsonInput() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input', 400);
        }
        
        return $input ?: [];
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
}

try {
    $api = new UploadAPI();
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

