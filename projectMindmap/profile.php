<?php
// profile.php
require_once 'config.php';
requireLogin();

$error = '';
$success = '';

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT username, email, createdAt FROM users WHERE userId = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Fetch user's mindmap count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM mindmaps WHERE userId = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $mindmapCount = $stmt->fetch()['total'];
    
} catch(PDOException $e) {
    $error = 'Error fetching user data.';
    $user = ['username' => '', 'email' => '', 'createdAt' => ''];
    $mindmapCount = 0;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($username) || empty($email)) {
        $error = 'Username and Email cannot be empty.';
    } else {
        try {
            // Check if new username or email is already taken by another user
            $stmt = $pdo->prepare("SELECT userId FROM users WHERE (username = ? OR email = ?) AND userId != ?");
            $stmt->execute([$username, $email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error = 'Username or email is already taken by another account.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE userId = ?");
                $stmt->execute([$username, $email, $_SESSION['user_id']]);
                $_SESSION['username'] = $username; // Update session
                $user['username'] = $username;   // Update for current page view
                $user['email'] = $email;         // Update for current page view
                $success = 'Profile updated successfully!';
            }
        } catch(PDOException $e) {
            $error = 'Error updating profile.';
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } else {
        try {
            // Verify current password
            $stmt = $pdo->prepare("SELECT passwordHash FROM users WHERE userId = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $currentHash = $stmt->fetchColumn();
            
            if (password_verify($current_password, $currentHash)) {
                // Hash and update new password
                $newHash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET passwordHash = ? WHERE userId = ?");
                $stmt->execute([$newHash, $_SESSION['user_id']]);
                $success = 'Password changed successfully!';
            } else {
                $error = 'Incorrect current password.';
            }
        } catch(PDOException $e) {
            $error = 'Error changing password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - MindMap Generator</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 0; }
        .nav-container { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 0 2rem; }
        .logo { font-size: 1.5rem; font-weight: bold; color: #667eea; text-decoration: none; }
        .nav-links a { color: #333; text-decoration: none; margin-left: 2rem; }
        .main-content { max-width: 800px; margin: 2rem auto; padding: 2rem; }
        .profile-header { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 2rem; text-align: center; }
        .avatar { width: 100px; height: 100px; background: #667eea; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2.5rem; margin: 0 auto 1rem; }
        .profile-stats { display: flex; justify-content: space-around; margin-top: 1.5rem; }
        .form-section { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 2rem; }
        .form-section h2 { margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-control { width: 100%; padding: 0.8rem; border: 2px solid #e9ecef; border-radius: 10px; }
        .btn { padding: 0.8rem 1.5rem; border: none; border-radius: 10px; background: #667eea; color: white; cursor: pointer; }
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1rem; text-align: center; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .alert-success { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo"><i class="fas fa-brain"></i> MindMap Generator</a>
            <div class="nav-links">
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>
    <main class="main-content">
        <div class="profile-header">
            <div class="avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
            <h1><?php echo htmlspecialchars($user['username']); ?></h1>
            <p><?php echo htmlspecialchars($user['email']); ?></p>
            <div class="profile-stats">
                <div><strong><?php echo $mindmapCount; ?></strong> MindMaps</div>
                <div>Member Since <strong><?php echo date('M Y', strtotime($user['createdAt'])); ?></strong></div>
            </div>
        </div>
        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
        <div class="form-section">
            <h2><i class="fas fa-user-edit"></i> Update Profile</h2>
            <form method="POST" action="profile.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <button type="submit" name="update_profile" class="btn"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>
        <div class="form-section">
            <h2><i class="fas fa-lock"></i> Change Password</h2>
            <form method="POST" action="profile.php">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="At least 6 characters" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" name="change_password" class="btn"><i class="fas fa-key"></i> Change Password</button>
            </form>
        </div>
    </main>
</body>
</html>