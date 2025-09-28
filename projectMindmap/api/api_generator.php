<?php
// api/ai_generator.php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

// --- AI Simulation Function ---
// In a real application, you would replace this with a call to a real AI API (like GPT, Claude, etc.)
function generateMindmapFromText($text) {
    $lines = explode("\n", trim($text));
    $nodes = [];
    $nodeIdCounter = 1;
    $parentId = null;
    $rootNodeId = null;

    // First non-empty line becomes the root node
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (!empty($trimmedLine)) {
            $rootNodeId = $nodeIdCounter;
            $nodes[] = [
                'nodeId' => $nodeIdCounter++,
                'parentId' => null,
                'content' => $trimmedLine,
                'x' => 400,
                'y' => 100,
                'color' => '#667eea' // Root node color
            ];
            break;
        }
    }

    // Subsequent lines become child nodes of the root
    $childY = 250;
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (!empty($trimmedLine) && count($nodes) > 1) { // Skip the root node line
            $nodes[] = [
                'nodeId' => $nodeIdCounter++,
                'parentId' => $rootNodeId,
                'content' => $trimmedLine,
                'x' => 200 + (count($nodes) % 3) * 200, // Arrange children
                'y' => $childY,
                'color' => '#4285f4' // Child node color
            ];
            if (count($nodes) % 3 === 0) $childY += 100;
        }
    }
    
    // The first node added (the root) is removed from this loop to prevent duplication.
    array_shift($lines); 
    
    // Now process the rest of the lines
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (!empty($trimmedLine)) {
             $nodes[] = [
                'nodeId' => $nodeIdCounter++,
                'parentId' => $rootNodeId,
                'content' => $trimmedLine,
                'x' => 200 + (count($nodes) % 3) * 200,
                'y' => $childY,
                'color' => '#4285f4'
            ];
            if (count($nodes) % 3 === 0) $childY += 100;
        }
    }


    return ['title' => $nodes[0]['content'] ?? 'AI Generated Map', 'nodes' => $nodes];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $file = $_FILES['document'];

    // Basic validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File upload error.']);
        exit;
    }
    $fileType = mime_content_type($file['tmp_name']);
    if ($fileType !== 'text/plain') {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload a .txt file.']);
        exit;
    }

    // Process the file
    $fileContent = file_get_contents($file['tmp_name']);
    $mindmapData = generateMindmapFromText($fileContent);

    // Store the generated data in the session to pass it to the editor
    $_SESSION['ai_generated_map'] = $mindmapData;

    echo json_encode(['success' => true, 'message' => 'Mindmap generated successfully!']);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>