<?php
// api/mindmap_api.php
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
require_once '../includes/Database.php';

class MindMapAPI {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
    }
    
    public function handleRequest() {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $action = $_GET['action'] ?? '';
            
            switch ($action) {
                case 'create':
                    if ($method !== 'POST') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->createMindMap();
                    
                case 'list':
                    if ($method !== 'GET') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->listMindMaps();
                    
                case 'get':
                    if ($method !== 'GET') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->getMindMap();
                    
                case 'update':
                    if ($method !== 'PUT') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->updateMindMap();
                    
                case 'delete':
                    if ($method !== 'DELETE') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->deleteMindMap();
                    
                case 'create-node':
                    if ($method !== 'POST') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->createNode();
                    
                case 'update-node':
                    if ($method !== 'PUT') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->updateNode();
                    
                case 'delete-node':
                    if ($method !== 'DELETE') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->deleteNode();
                    
                case 'export':
                    if ($method !== 'GET') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->exportMindMap();
                    
                case 'share':
                    if ($method === 'POST') {
                        return $this->createShare();
                    } elseif ($method === 'GET') {
                        return $this->getSharedMindMap();
                    }
                    throw new Exception('Method not allowed', 405);
                    
                case 'categories':
                    if ($method !== 'GET') {
                        throw new Exception('Method not allowed', 405);
                    }
                    return $this->getCategories();
                    
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
    
    private function createMindMap() {
        $user = $this->requireAuth();
        $input = $this->getJsonInput();
        
        $requiredFields = ['title', 'central_node'];
        $this->validateRequiredFields($input, $requiredFields);
        
        $this->db->beginTransaction();
        
        try {
            // Insert mind map
            $sql = "INSERT INTO mind_maps (user_id, category_id, title, description, central_node, theme, is_public) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->execute($sql, [
                $user['user_id'],
                $input['category_id'] ?? null,
                $input['title'],
                $input['description'] ?? '',
                $input['central_node'],
                $input['theme'] ?? 'default',
                $input['is_public'] ?? false
            ]);
            
            $mapId = $this->db->lastInsertId();
            
            // Create central node
            $this->db->execute(
                "INSERT INTO nodes (map_id, node_text, node_type, color, background_color, position_x, position_y) 
                 VALUES (?, ?, 'central', '#667eea', '#ffffff', 600, 400)",
                [$mapId, $input['central_node']]
            );
            
            $this->db->commit();
            
            // Log activity
            $this->logActivity($user['user_id'], $mapId, 'create', 'map', $mapId);
            
            return [
                'success' => true,
                'message' => 'MindMap created successfully',
                'data' => [
                    'map_id' => (int)$mapId
                ]
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    private function listMindMaps() {
        $user = $this->requireAuth();
        $page = (int)($_GET['page'] ?? 1);
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $category_id = $_GET['category_id'] ?? null;
        $search = $_GET['search'] ?? '';
        
        $baseQuery = "SELECT * FROM user_mindmaps WHERE user_id = ?";
        $params = [$user['user_id']];
        
        if ($category_id) {
            $baseQuery .= " AND category_id = ?";
            $params[] = $category_id;
        }
        
        if ($search) {
            $baseQuery .= " AND (title LIKE ? OR description LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $baseQuery .= " ORDER BY updated_at DESC";
        
        $result = $this->db->paginate($baseQuery, $params, $page, $limit);
        
        return [
            'success' => true,
            'data' => $result['data'],
            'pagination' => $result['pagination']
        ];
    }
    
    private function getMindMap() {
        $mapId = $_GET['map_id'] ?? null;
        if (!$mapId) {
            throw new Exception('Map ID is required', 400);
        }
        
        $user = $this->requireAuth();
        
        // Get mind map
        $mindMap = $this->db->fetch(
            "SELECT * FROM mind_maps WHERE map_id = ? AND user_id = ?",
            [$mapId, $user['user_id']]
        );
        
        if (!$mindMap) {
            throw new Exception('MindMap not found or access denied', 404);
        }
        
        // Get nodes
        $nodes = $this->db->fetchAll(
            "SELECT * FROM nodes WHERE map_id = ? ORDER BY node_type, sort_order",
            [$mapId]
        );
        
        // Get links
        $links = $this->db->fetchAll(
            "SELECT * FROM node_links WHERE map_id = ?",
            [$mapId]
        );
        
        // Increment view count
        $this->db->execute(
            "UPDATE mind_maps SET view_count = view_count + 1 WHERE map_id = ?",
            [$mapId]
        );
        
        // Log activity
        $this->logActivity($user['user_id'], $mapId, 'view', 'map', $mapId);
        
        return [
            'success' => true,
            'data' => [
                'map_id' => (int)$mindMap['map_id'],
                'title' => $mindMap['title'],
                'description' => $mindMap['description'],
                'theme' => $mindMap['theme'],
                'canvas_width' => (float)$mindMap['canvas_width'],
                'canvas_height' => (float)$mindMap['canvas_height'],
                'zoom_level' => (float)$mindMap['zoom_level'],
                'center_x' => (float)$mindMap['center_x'],
                'center_y' => (float)$mindMap['center_y'],
                'node_count' => (int)$mindMap['node_count'],
                'created_at' => $mindMap['created_at'],
                'updated_at' => $mindMap['updated_at'],
                'nodes' => $nodes,
                'links' => $links
            ]
        ];
    }
    
    private function updateMindMap() {
        $mapId = $_GET['map_id'] ?? null;
        if (!$mapId) {
            throw new Exception('Map ID is required', 400);
        }
        
        $user = $this->requireAuth();
        $input = $this->getJsonInput();
        
        // Verify ownership
        $mindMap = $this->db->fetch(
            "SELECT * FROM mind_maps WHERE map_id = ? AND user_id = ?",
            [$mapId, $user['user_id']]
        );
        
        if (!$mindMap) {
            throw new Exception('MindMap not found or access denied', 404);
        }
        
        $allowedFields = [
            'title', 'description', 'category_id', 'theme', 'is_public',
            'canvas_width', 'canvas_height', 'zoom_level', 'center_x', 'center_y'
        ];
        
        $updateFields = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "{$field} = ?";
                $params[] = $input[$field];
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception('No valid fields to update', 400);
        }
        
        $params[] = $mapId;
        
        $this->db->execute(
            "UPDATE mind_maps SET " . implode(', ', $updateFields) . " WHERE map_id = ?",
            $params
        );
        
        // Log activity
        $this->logActivity($user['user_id'], $mapId, 'update', 'map', $mapId);
        
        return [
            'success' => true,
            'message' => 'MindMap updated successfully'
        ];
    }
    
    private function deleteMindMap() {
        $mapId = $_GET['map_id'] ?? null;
        if (!$mapId) {
            throw new Exception('Map ID is required', 400);
        }
        
        $user = $this->requireAuth();
        
        // Verify ownership
        $mindMap = $this->db->fetch(
            "SELECT * FROM mind_maps WHERE map_id = ? AND user_id = ?",
            [$mapId, $user['user_id']]
        );
        
        if (!$mindMap) {
            throw new Exception('MindMap not found or access denied', 404);
        }
        
        // Delete mind map (cascade will handle nodes and links)
        $this->db->execute("DELETE FROM mind_maps WHERE map_id = ?", [$mapId]);
        
        // Log activity
        $this->logActivity($user['user_id'], $mapId, 'delete', 'map', $mapId);
        
        return [
            'success' => true,
            'message' => 'MindMap deleted successfully'
        ];
    }
    
    private function createNode() {
        $mapId = $_GET['map_id'] ?? null;
        if (!$mapId) {
            throw new Exception('Map ID is required', 400);
        }
        
        $user = $this->requireAuth();
        $input = $this->getJsonInput();
        
        // Verify map ownership
        $mindMap = $this->db->fetch(
            "SELECT * FROM mind_maps WHERE map_id = ? AND user_id = ?",
            [$mapId, $user['user_id']]
        );
        
        if (!$mindMap) {
            throw new Exception('MindMap not found or access denied', 404);
        }
        
        $requiredFields = ['node_text'];
        $this->validateRequiredFields($input, $requiredFields);
        
        $sql = "INSERT INTO nodes (
            map_id, parent_id, node_text, node_type, color, background_color, text_color,
            position_x, position_y, width, height, font_size, icon, priority, status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, [
            $mapId,
            $input['parent_id'] ?? null,
            $input['node_text'],
            $input['node_type'] ?? 'main',
            $input['color'] ?? '#667eea',
            $input['background_color'] ?? '#ffffff',
            $input['text_color'] ?? '#000000',
            $input['position_x'] ?? 0,
            $input['position_y'] ?? 0,
            $input['width'] ?? 150,
            $input['height'] ?? 50,
            $input['font_size'] ?? 14,
            $input['icon'] ?? null,
            $input['priority'] ?? 'medium',
            $input['status'] ?? 'pending',
            $input['notes'] ?? null
        ]);
        
        $nodeId = $this->db->lastInsertId();
        
        // Log activity
        $this->logActivity($user['user_id'], $mapId, 'create', 'node', $nodeId);
        
        return [
            'success' => true,
            'message' => 'Node created successfully',
            'data' => [
                'node_id' => (int)$nodeId
            ]
        ];
    }
    
    private function updateNode() {
        $nodeId = $_GET['node_id'] ?? null;
        if (!$nodeId) {
            throw new Exception('Node ID is required', 400);
        }
        
        $user = $this->requireAuth();
        $input = $this->getJsonInput();
        
        // Verify node ownership through map
        $node = $this->db->fetch(
            "SELECT n.*, m.user_id FROM nodes n 
             JOIN mind_maps m ON n.map_id = m.map_id 
             WHERE n.node_id = ? AND m.user_id = ?",
            [$nodeId, $user['user_id']]
        );
        
        if (!$node) {
            throw new Exception('Node not found or access denied', 404);
        }
        
        $allowedFields = [
            'node_text', 'node_type', 'color', 'background_color', 'text_color',
            'position_x', 'position_y', 'width', 'height', 'font_size', 'icon',
            'priority', 'status', 'notes', 'sort_order'
        ];
        
        $updateFields = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "{$field} = ?";
                $params[] = $input[$field];
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception('No valid fields to update', 400);
        }
        
        $params[] = $nodeId;
        
        $this->db->execute(
            "UPDATE nodes SET " . implode(', ', $updateFields) . " WHERE node_id = ?",
            $params
        );
        
        // Log activity
        $this->logActivity($user['user_id'], $node['map_id'], 'update', 'node', $nodeId);
        
        return [
            'success' => true,
            'message' => 'Node updated successfully'
        ];
    }
    
    private function deleteNode() {
        $nodeId = $_GET['node_id'] ?? null;
        if (!$nodeId) {
            throw new Exception('Node ID is required', 400);
        }
        
        $user = $this->requireAuth();
        
        // Verify node ownership through map
        $node = $this->db->fetch(
            "SELECT n.*, m.user_id FROM nodes n 
             JOIN mind_maps m ON n.map_id = m.map_id 
             WHERE n.node_id = ? AND m.user_id = ?",
            [$nodeId, $user['user_id']]
        );
        
        if (!$node) {
            throw new Exception('Node not found or access denied', 404);
        }
        
        // Delete node (cascade will handle children)
        $this->db->execute("DELETE FROM nodes WHERE node_id = ?", [$nodeId]);
        
        // Log activity
        $this->logActivity($user['user_id'], $node['map_id'], 'delete', 'node', $nodeId);
        
        return [
            'success' => true,
            'message' => 'Node deleted successfully'
        ];
    }
    
    private function exportMindMap() {
        $mapId = $_GET['map_id'] ?? null;
        $format = $_GET['format'] ?? 'json';
        
        if (!$mapId) {
            throw new Exception('Map ID is required', 400);
        }
        
        $user = $this->requireAuth();
        
        // Get mind map with nodes
        $mindMap = $this->db->fetch(
            "SELECT * FROM mind_maps WHERE map_id = ? AND user_id = ?",
            [$mapId, $user['user_id']]
        );
        
        if (!$mindMap) {
            throw new Exception('MindMap not found or access denied', 404);
        }
        
        $nodes = $this->db->fetchAll(
            "SELECT * FROM nodes WHERE map_id = ? ORDER BY node_type, sort_order",
            [$mapId]
        );
        
        $links = $this->db->fetchAll(
            "SELECT * FROM node_links WHERE map_id = ?",
            [$mapId]
        );
        
        $exportData = [
            'mindmap' => $mindMap,
            'nodes' => $nodes,
            'links' => $links,
            'exported_at' => date('Y-m-d H:i:s'),
            'format_version' => '1.0'
        ];
        
        // Log activity
        $this->logActivity($user['user_id'], $mapId, 'export', 'map', $mapId, [
            'format' => $format
        ]);
        
        switch ($format) {
            case 'json':
                return [
                    'success' => true,
                    'data' => json_encode($exportData, JSON_PRETTY_PRINT)
                ];
                
            case 'xml':
                return [
                    'success' => true,
                    'data' => $this->arrayToXml($exportData, 'mindmap')
                ];
                
            default:
                throw new Exception('Unsupported export format', 400);
        }
    }
    
    private function createShare() {
        $mapId = $_GET['map_id'] ?? null;
        if (!$mapId) {
            throw new Exception('Map ID is required', 400);
        }
        
        $user = $this->requireAuth();
        $input = $this->getJsonInput();
        
        // Verify ownership
        $mindMap = $this->db->fetch(
            "SELECT * FROM mind_maps WHERE map_id = ? AND user_id = ?",
            [$mapId, $user['user_id']]
        );
        
        if (!$mindMap) {
            throw new Exception('MindMap not found or access denied', 404);
        }
        
        $shareToken = bin2hex(random_bytes(16));
        $accessLevel = $input['access_level'] ?? 'view';
        $expiresAt = isset($input['expires_at']) ? date('Y-m-d H:i:s', strtotime($input['expires_at'])) : null;
        
        $this->db->execute(
            "INSERT INTO shared_maps (map_id, shared_by_user_id, share_token, access_level, expires_at) 
             VALUES (?, ?, ?, ?, ?)",
            [$mapId, $user['user_id'], $shareToken, $accessLevel, $expiresAt]
        );
        
        // Log activity
        $this->logActivity($user['user_id'], $mapId, 'share', 'map', $mapId);
        
        return [
            'success' => true,
            'message' => 'Share link created successfully',
            'data' => [
                'share_token' => $shareToken,
                'share_url' => APP_URL . "/share/{$shareToken}"
            ]
        ];
    }
    
    private function getCategories() {
        $categories = $this->db->fetchAll(
            "SELECT category_id, name, color, description FROM categories ORDER BY name"
        );
        
        return [
            'success' => true,
            'data' => $categories
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
    
    private function validateRequiredFields($input, $requiredFields) {
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                throw new Exception("Field '{$field}' is required", 400);
            }
        }
    }
    
    private function logActivity($userId, $mapId, $action, $resourceType, $resourceId, $details = []) {
        $this->db->execute(
            "INSERT INTO activity_log (user_id, map_id, action_type, resource_type, resource_id, details, ip_address, user_agent) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $mapId,
                $action,
                $resourceType,
                $resourceId,
                json_encode($details),
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
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
    
    private function arrayToXml($array, $rootElement = 'root', $xml = null) {
        if ($xml === null) {
            $xml = new SimpleXMLElement("<{$rootElement}/>");
        }
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->arrayToXml($value, $key, $xml->addChild($key));
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
        
        return $xml->asXML();
    }
}

try {
    $api = new MindMapAPI();
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
// In mindmap_api.php handleRequest()
case 'ai-suggestions':
    if ($method !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }
    return $this->getAISuggestions();

// New private method
// In api/mindmap_api.php
// ... (Make sure the 'ai-suggestions' case exists in the handleRequest switch statement)

// Replace the old getAISuggestions function with this corrected one
private function getAISuggestions() {
    $user = $this->requireAuth();
    $nodeId = $_GET['node_id'] ?? null;
    if (!$nodeId) {
        throw new Exception('Node ID is required', 400);
    }

    // Fetch the node and verify ownership through the map
    $node = $this.db->fetch(
        "SELECT n.node_text, n.map_id FROM nodes n 
         JOIN mind_maps m ON n.map_id = m.map_id 
         WHERE n.node_id = ? AND m.user_id = ?",
        [$nodeId, $user['user_id']]
    );

    if (!$node) {
        throw new Exception('Node not found or access denied', 404);
    }

    // Now call the AIProcessor with the correct parameters
    require_once '../includes/AIProcessor.php'; // Ensure AIProcessor is included
    $aiProcessor = new AIProcessor();
    $suggestions = $aiProcessor->generateSuggestions($node['map_id'], $node['node_text']);

    return [
        'success' => true,
        'data' => $suggestions
    ];
}
?>
