<?php
// (The PHP code at the top remains the same)
require_once 'config.php';
requireLogin();

try {
    $stmt = $pdo->prepare("SELECT mapId, title FROM mindmaps WHERE userId = ? ORDER BY updatedAt DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $mindmaps = $stmt->fetchAll();
} catch (PDOException $e) {
    $mindmaps = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MindMap Platform</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-brain logo-icon"></i>
                <span class="logo-text">MindMap</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="editor.php" class="active"><i class="fas fa-plus"></i> New MindMap</a></li>
                    <li><a href="templates.php"><i class="fas fa-th-large"></i> Templates</a></li>
                </ul>
                <div class="sidebar-section-title">AI Generator</div>
                <form id="ai-upload-form" enctype="multipart/form-data">
                    <label for="ai-file-input" class="upload-card">
                        <i class="fas fa-robot"></i>
                        <div>Upload & Generate</div>
                        <small>Drop a .txt file or click</small>
                    </label>
                    <input type="file" id="ai-file-input" name="document" accept=".txt" style="display: none;">
                </form>
                <div class="sidebar-section-title">My MindMaps</div>
                <ul>
                    <?php foreach (array_slice($mindmaps, 0, 4) as $mindmap) : ?>
                        <li><a href="editor.php?id=<?php echo $mindmap['mapId']; ?>"><i class="fas fa-project-diagram"></i> <?php echo htmlspecialchars($mindmap['title']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>
        <div class="main-content">
            <header class="top-navbar">
                <a href="profile.php" class="nav-item"><i class="fas fa-user-circle"></i> Profile</a>
                <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </header>
            <main class="mindmap-list-section">
                <h2>Your MindMaps</h2>
                <p>
                    <?php echo empty($mindmaps) ? 'You haven\'t created any mindmaps yet. Click "New MindMap" or upload a file to get started!' : 'Here are your most recent mindmaps.'; ?>
                </p>
                <div class="mindmaps-grid">
                    <?php if (!empty($mindmaps)): ?>
                        <?php foreach ($mindmaps as $mindmap): ?>
                            <div class="mindmap-card">
                                <h3 class="mindmap-card-title"><?php echo htmlspecialchars($mindmap['title']); ?></h3>
                                <div class="mindmap-card-actions">
                                    <a href="editor.php?id=<?php echo $mindmap['mapId']; ?>">Open Map</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <script>
        // (The script at the bottom remains the same)
        document.getElementById('ai-file-input').addEventListener('change', function() {
            if (this.files.length > 0) {
                document.getElementById('ai-upload-form').requestSubmit();
            }
        });
        document.getElementById('ai-upload-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('api/ai_generator.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'editor.php?ai=true';
                } else {
                    alert('Error: ' .concat(data.message));
                }
            })
            .catch(error => {
                alert('An unexpected error occurred.');
            });
        });
    </script>
</body>
</html>