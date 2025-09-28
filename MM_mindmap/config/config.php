<?php
// config/config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mindmap_platform');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// JWT Configuration
define('JWT_SECRET', '9a64744cb58ad392bbf1f7c5e8442be7'); // Change this to a strong secret key
define('JWT_EXPIRY', 86400); // 24 hours in seconds

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['txt', 'pdf', 'docx', 'md']);

// API Configuration
define('API_VERSION', '1.0');
define('CORS_ORIGIN', '*'); // Change to your domain in production
define('DEBUG_MODE', true); // Set to false in production

// Application Settings
define('APP_NAME', 'MindMap Platform');
define('APP_URL', 'http://localhost'); // Change to your domain
define('ADMIN_EMAIL', 'admin@mindmap.com');

// Security Settings
define('BCRYPT_COST', 12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Session Settings
define('SESSION_CLEANUP_PROBABILITY', 0.1); // 10% chance to clean expired sessions


?>
