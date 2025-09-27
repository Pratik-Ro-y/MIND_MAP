
<?php
// admin/logout.php
session_start();
session_destroy();
header('Location: login.php');
exit();
?>

<?php
// .htaccess
RewriteEngine On
// 
# API Routes
RewriteRule ^api/auth/?$ api/auth.php [L]
RewriteRule ^api/mindmap/?$ api/mindmap_api.php [L]
RewriteRule ^api/upload/?$ api/upload.php [L]

# Admin Routes
RewriteRule ^admin/?$ admin/dashboard.php [L]
RewriteRule ^admin/login/?$ admin/login.php [L]
RewriteRule ^admin/logout/?$ admin/logout.php [L]

# Frontend
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.html [L]

# Security Headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' cdnjs.cloudflare.com"

?>

<?php
// install.php - Installation script
require_once 'config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? DB_HOST;
    $dbName = $_POST['db_name'] ?? DB_NAME;
    $dbUser = $_POST['db_user'] ?? DB_USER;
    $dbPass = $_POST['db_pass'] ?? DB_PASS;
    
    try {
        // Test database connection
        $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$dbName}");
        $pdo->exec("USE {$dbName}");
        
        // Read and execute SQL schema
        $sql = file_get_contents('database_schema.sql');
        $pdo->exec($sql);
        
        // Create necessary directories
        $dirs = ['uploads', 'logs', 'exports'];
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        // Create sample admin user (optional)
        if (isset($_POST['create_admin']) && $_POST['create_admin']) {
            $adminUsername = $_POST['admin_username'] ?? 'admin';
            $adminEmail = $_POST['admin_email'] ?? 'admin@mindmap.com';
            $adminPassword = password_hash($_POST['admin_password'] ?? 'admin123', PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$adminUsername, $adminEmail, $adminPassword]);
        }
        
        $success = true;
        $message = "Installation completed successfully!";
        
    } catch (Exception $e) {
        $success = false;
        $message = "Installation failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install MindMap Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .section h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MindMap Platform Installation</h1>
            <p>Configure your database and system settings</p>
        </div>
        
        <?php if (isset($success)): ?>
            <?php if ($success): ?>
                <div class="success">
                    <?php echo htmlspecialchars($message); ?><br><br>
                    <strong>Next steps:</strong><br>
                    1. Delete this install.php file for security<br>
                    2. Update your config/config.php with the correct database credentials<br>
                    3. Set up your web server to point to this directory<br>
                    4. Access the admin panel at /admin/<br>
                    5. Start using your MindMap Platform!
                </div>
            <?php else: ?>
                <div class="error"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (!isset($success) || !$success): ?>
        <form method="POST">
            <div class="section">
                <h3>Database Configuration</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="db_host">Database Host:</label>
                        <input type="text" id="db_host" name="db_host" class="form-control" value="<?php echo DB_HOST; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_name">Database Name:</label>
                        <input type="text" id="db_name" name="db_name" class="form-control" value="<?php echo DB_NAME; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_user">Database Username:</label>
                        <input type="text" id="db_user" name="db_user" class="form-control" value="<?php echo DB_USER; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_pass">Database Password:</label>
                        <input type="password" id="db_pass" name="db_pass" class="form-control" value="<?php echo DB_PASS; ?>">
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h3>Admin Account (Optional)</h3>
                <div class="checkbox-group">
                    <input type="checkbox" id="create_admin" name="create_admin" value="1" checked>
                    <label for="create_admin">Create admin account</label>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="admin_username">Admin Username:</label>
                        <input type="text" id="admin_username" name="admin_username" class="form-control" value="admin">
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">Admin Email:</label>
                        <input type="email" id="admin_email" name="admin_email" class="form-control" value="admin@mindmap.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password">Admin Password:</label>
                        <input type="password" id="admin_password" name="admin_password" class="form-control" value="admin123">
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn">Install MindMap Platform</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>