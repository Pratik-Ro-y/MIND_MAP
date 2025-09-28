<?php
// editor.php
require_once 'config.php';
requireLogin();

$mapId = null;
$mindmapData = null;
$nodes = [];
$isAIMap = false;
$isTemplate = false;

// Check if we are loading a template
if (isset($_GET['template_id']) && is_numeric($_GET['template_id'])) {
    $isTemplate = true;
    $templateId = intval($_GET['template_id']);
    try {
        $stmt = $pdo->prepare("SELECT title, nodeStructure FROM templates WHERE templateId = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch();
        if ($template) {
            $mindmapData = ['title' => $template['title']];
            $nodes = json_decode($template['nodeStructure'], true);
        } else {
            // Template not found, redirect to avoid errors
            redirect('templates.php');
        }
    } catch (PDOException $e) {
        redirect('templates.php');
    }
}
// (The rest of the PHP logic for AI maps and existing maps remains the same)
elseif (isset($_GET['ai']) && isset($_SESSION['ai_generated_map'])) {
    $isAIMap = true;
    $ai_map = $_SESSION['ai_generated_map'];
    $mindmapData = ['title' => $ai_map['title']];
    $nodes = $ai_map['nodes'];
    unset($_SESSION['ai_generated_map']);
} 
elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $mapId = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM mindmaps WHERE mapId = ? AND userId = ?");
        $stmt->execute([$mapId, $_SESSION['user_id']]);
        $mindmapData = $stmt->fetch();
        if (!$mindmapData) redirect('dashboard.php');

        $stmt = $pdo->prepare("SELECT * FROM nodes WHERE mapId = ? ORDER BY nodeId");
        $stmt->execute([$mapId]);
        $nodes = $stmt->fetchAll();
    } catch (PDOException $e) {
        redirect('dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mapId ? 'Edit' : 'Create'; ?> MindMap</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; height: 100vh; overflow: hidden; }
        .editor-header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; z-index: 1000; }
        .header-left { display: flex; align-items: center; gap: 1rem; }
        .mindmap-title { font-size: 1.2rem; border: 1px solid transparent; padding: 0.5rem; border-radius: 5px; }
        .mindmap-title:focus { border-color: #ddd; }
        .btn { padding: 0.7rem 1.2rem; border: none; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .canvas-container { height: calc(100vh - 70px); position: relative; overflow: hidden; }
        .mindmap-canvas { width: 100%; height: 100%; position: relative; }
        .node { position: absolute; background: white; border: 3px solid #667eea; border-radius: 25px; padding: 15px 20px; cursor: move; user-select: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; }
        .node.root { background: #667eea; color: white; }
        .node-content { background: transparent; border: none; outline: none; width: 100%; text-align: center; }
        .connection-line { stroke: #aaa; stroke-width: 2; fill: none; }
        .status-indicator { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.8); color: white; padding: 0.8rem 1.5rem; border-radius: 25px; display: none; z-index: 3000; }
    </style>
</head>
<body>
    <div class="editor-header">
        <div class="header-left">
            <a href="dashboard.php" class="btn"><i class="fas fa-arrow-left"></i></a>
            <input type="text" class="mindmap-title" placeholder="Enter mindmap title..." value="<?php echo htmlspecialchars($mindmapData['title'] ?? 'New MindMap'); ?>">
        </div>
        <div class="toolbar">
            <button class="btn btn-primary" onclick="addNode()"><i class="fas fa-plus"></i> Add Node</button>
            <button class="btn btn-success" onclick="saveMindMap()"><i class="fas fa-save"></i> Save</button>
            <button class="btn btn-secondary" onclick="exportMindMap()"><i class="fas fa-download"></i> Export</button>
        </div>
    </div>
    <div class="canvas-container">
        <div class="mindmap-canvas" id="canvas">
            <svg id="connections" style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none;"></svg>
        </div>
    </div>
    <div id="statusIndicator" class="status-indicator"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        let nodes = <?php echo json_encode($nodes, JSON_NUMERIC_CHECK); ?>;
        let mapId = <?php echo $mapId ? $mapId : 'null'; ?>;
        let isAIMap = <?php echo $isAIMap ? 'true' : 'false'; ?>;
        let isTemplate = <?php echo $isTemplate ? 'true' : 'false'; ?>;
        
        let selectedNode = null, draggedNode = null, isDragging = false;
        let dragOffset = { x: 0, y: 0 };
        let nodeIdCounter = nodes.length > 0 ? Math.max(...nodes.map(n => n.nodeId)) + 1 : 1;

        document.addEventListener('DOMContentLoaded', () => {
            if (nodes.length === 0) {
                createRootNode();
            } else {
                renderExistingNodes();
            }
            setupEventListeners();
        });

        function createRootNode() {
            const node = { nodeId: nodeIdCounter++, parentId: null, content: 'Central Idea', x: 400, y: 100, color: '#667eea' };
            nodes.push(node);
            renderNode(node);
        }

        function renderExistingNodes() {
            nodes.forEach(renderNode);
            updateConnections();
        }

        function renderNode(node) {
            const el = document.createElement('div');
            el.className = 'node' + (node.parentId === null ? ' root' : '');
            el.id = 'node-' + node.nodeId;
            el.style.left = node.x + 'px';
            el.style.top = node.y + 'px';
            el.style.borderColor = node.color;
            el.innerHTML = `<input type="text" class="node-content" value="${node.content.replace(/"/g, '&quot;')}">`;
            el.querySelector('input').addEventListener('blur', (e) => { node.content = e.target.value; });
            el.addEventListener('mousedown', (e) => onNodeMouseDown(e, node));
            document.getElementById('canvas').appendChild(el);
        }

        function addNode() {
            const parent = selectedNode || nodes.find(n => n.parentId === null);
            if (!parent) return;
            const node = { nodeId: nodeIdCounter++, parentId: parent.nodeId, content: 'New Node', x: parent.x, y: parent.y + 100, color: '#4285f4' };
            nodes.push(node);
            renderNode(node);
            updateConnections();
        }

        function updateConnections() {
            const svg = document.getElementById('connections');
            svg.innerHTML = '';
            nodes.forEach(node => {
                if (node.parentId !== null) {
                    const parent = nodes.find(n => n.nodeId === node.parentId);
                    if (parent) drawConnection(parent, node);
                }
            });
        }

        function drawConnection(p, c) {
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            const pEl = document.getElementById('node-' + p.nodeId);
            const cEl = document.getElementById('node-' + c.nodeId);
            const d = `M ${p.x + pEl.offsetWidth / 2} ${p.y + pEl.offsetHeight / 2} L ${c.x + cEl.offsetWidth / 2} ${c.y + cEl.offsetHeight / 2}`;
            line.setAttribute('d', d);
            line.classList.add('connection-line');
            svg.appendChild(line);
        }

        function onNodeMouseDown(e, node) {
            e.stopPropagation();
            selectedNode = draggedNode = node;
            isDragging = true;
            const rect = e.currentTarget.getBoundingClientRect();
            dragOffset = { x: e.clientX - rect.left, y: e.clientY - rect.top };
        }

        function onCanvasMouseMove(e) {
            if (!isDragging || !draggedNode) return;
            const canvas = document.getElementById('canvas').getBoundingClientRect();
            draggedNode.x = e.clientX - canvas.left - dragOffset.x;
            draggedNode.y = e.clientY - canvas.top - dragOffset.y;
            document.getElementById('node-' + draggedNode.nodeId).style.left = draggedNode.x + 'px';
            document.getElementById('node-' + draggedNode.nodeId).style.top = draggedNode.y + 'px';
            updateConnections();
        }

        function onCanvasMouseUp() {
            isDragging = false;
            draggedNode = null;
        }

        function setupEventListeners() {
            const canvas = document.getElementById('canvas');
            canvas.addEventListener('mousemove', onCanvasMouseMove);
            canvas.addEventListener('mouseup', onCanvasMouseUp);
        }
        
        function saveMindMap() {
            const title = document.querySelector('.mindmap-title').value.trim();
            if (!title) { showStatus('Title is required!'); return; }
            showStatus('Saving...');
            fetch('api/save_mindmap.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title, nodes, mapId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (!mapId) {
                        window.history.replaceState({}, '', `editor.php?id=${data.mapId}`);
                        mapId = data.mapId;
                    }
                    showStatus('Mindmap saved!');
                } else { showStatus('Error: ' + data.message); }
            })
            .catch(() => showStatus('Save failed!'));
        }

        function exportMindMap() {
             html2canvas(document.getElementById('canvas')).then(canvas => {
                const link = document.createElement('a');
                link.download = `mindmap-${Date.now()}.png`;
                link.href = canvas.toDataURL();
                link.click();
            });
        }
        
        function showStatus(message) {
            const indicator = document.getElementById('statusIndicator');
            indicator.textContent = message;
            indicator.style.display = 'block';
            setTimeout(() => { indicator.style.display = 'none'; }, 2000);
        }
    </script>
</body>
</html>