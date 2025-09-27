
<?php
// admin/dashboard.php
session_start();

// Simple admin authentication (expand as needed)
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/config.php';
require_once '../includes/Database.php';

$db = new Database();

// Get statistics
$userCount = $db->fetch("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'];
$mindmapCount = $db->fetch("SELECT COUNT(*) as count FROM mind_maps")['count'];
$nodeCount = $db->fetch("SELECT COUNT(*) as count FROM nodes")['count'];
$todaySignups = $db->fetch("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()")['count'];

// Get recent activity
$recentActivity = $db->fetchAll(
    "SELECT a.*, u.username, m.title as mindmap_title 
     FROM activity_log a 
     LEFT JOIN users u ON a.user_id = u.user_id 
     LEFT JOIN mind_maps m ON a.map_id = m.map_id 
     ORDER BY a.created_at DESC 
     LIMIT 20"
);

// Get top users
$topUsers = $db->fetchAll(
    "SELECT u.username, u.email, COUNT(m.map_id) as mindmap_count,
            u.created_at
     FROM users u 
     LEFT JOIN mind_maps m ON u.user_id = m.user_id 
     WHERE u.is_active = 1
     GROUP BY u.user_id 
     ORDER BY mindmap_count DESC 
     LIMIT 10"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MindMap Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .header {
            background: #667eea;
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            display: inline-block;
        }
        
        .logout-btn {
            float: right;
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .section h2 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-user {
            font-weight: bold;
            color: #667eea;
        }
        
        .activity-action {
            margin: 0.25rem 0;
            color: #666;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #999;
        }
        
        .user-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .user-item:last-child {
            border-bottom: none;
        }
        
        .user-info h4 {
            margin-bottom: 0.25rem;
            color: #333;
        }
        
        .user-email {
            font-size: 0.9rem;
            color: #666;
        }
        
        .user-stats {
            text-align: right;
            font-size: 0.9rem;
            color: #667eea;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0.25rem;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            text-align: left;
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            color: white;
        }
        
        .badge-success {
            background: #28a745;
        }
        
        .badge-info {
            background: #17a2b8;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #333;
        }
        
        .badge-danger {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>MindMap Platform - Admin Dashboard</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>

    <div class="container">
        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($userCount); ?></div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($mindmapCount); ?></div>
                <div class="stat-label">Total MindMaps</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($nodeCount); ?></div>
                <div class="stat-label">Total Nodes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($todaySignups); ?></div>
                <div class="stat-label">New Users Today</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <!-- Recent Activity -->
            <div class="section">
                <h2>Recent Activity</h2>
                <?php if (empty($recentActivity)): ?>
                    <p>No recent activity</p>
                <?php else: ?>
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-details">
                                <div class="activity-user">
                                    <?php echo htmlspecialchars($activity['username'] ?? 'Unknown User'); ?>
                                </div>
                                <div class="activity-action">
                                    <?php 
                                    $actionText = ucfirst($activity['action_type']) . ' ' . $activity['resource_type'];
                                    if ($activity['mindmap_title']) {
                                        $actionText .= ': ' . htmlspecialchars($activity['mindmap_title']);
                                    }
                                    echo $actionText;
                                    ?>
                                </div>
                                <div class="activity-time">
                                    <?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Top Users -->
            <div class="section">
                <h2>Top Users</h2>
                <?php if (empty($topUsers)): ?>
                    <p>No users found</p>
                <?php else: ?>
                    <?php foreach ($topUsers as $user): ?>
                        <div class="user-item">
                            <div class="user-info">
                                <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <div class="user-stats">
                                <?php echo $user['mindmap_count']; ?> mindmaps<br>
                                <small>Joined <?php echo date('M Y', strtotime($user['created_at'])); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Management Links -->
        <div class="section">
            <h2>Management</h2>
            <a href="users.php" class="btn">Manage Users</a>
            <a href="mindmaps.php" class="btn">Manage MindMaps</a>
            <a href="categories.php" class="btn">Manage Categories</a>
            <a href="logs.php" class="btn">View Logs</a>
            <a href="settings.php" class="btn">System Settings</a>
        </div>
    </div>
</body>
</html>
