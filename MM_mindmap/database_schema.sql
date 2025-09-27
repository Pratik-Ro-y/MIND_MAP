-- MindMap Platform Database Schema
CREATE DATABASE IF NOT EXISTS mindmap_platform;
USE mindmap_platform;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    profile_picture VARCHAR(255),
    email_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_active (is_active)
);

-- Categories table
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#667eea',
    description TEXT,
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_name (name)
);

-- Insert default categories
INSERT INTO categories (name, color, description, is_system) VALUES
('Business', '#667eea', 'Business and professional mind maps', TRUE),
('Education', '#4facfe', 'Educational and learning content', TRUE),
('Personal', '#43e97b', 'Personal projects and ideas', TRUE),
('Project', '#fa709a', 'Project planning and management', TRUE),
('Creative', '#f093fb', 'Creative and artistic endeavors', TRUE),
('Research', '#feca57', 'Research and analysis', TRUE);

-- Mind maps table
CREATE TABLE mind_maps (
    map_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    central_node TEXT NOT NULL,
    theme VARCHAR(50) DEFAULT 'default',
    is_public BOOLEAN DEFAULT FALSE,
    canvas_width DECIMAL(10,2) DEFAULT 1200.00,
    canvas_height DECIMAL(10,2) DEFAULT 800.00,
    zoom_level DECIMAL(5,2) DEFAULT 1.00,
    center_x DECIMAL(10,2) DEFAULT 600.00,
    center_y DECIMAL(10,2) DEFAULT 400.00,
    node_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    shared_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_category_id (category_id),
    INDEX idx_public (is_public),
    INDEX idx_created (created_at),
    INDEX idx_updated (updated_at),
    INDEX idx_title (title)
);

-- Nodes table
CREATE TABLE nodes (
    node_id INT AUTO_INCREMENT PRIMARY KEY,
    map_id INT NOT NULL,
    parent_id INT NULL,
    node_text TEXT NOT NULL,
    node_type ENUM('central', 'main', 'sub', 'leaf') DEFAULT 'main',
    color VARCHAR(7) DEFAULT '#667eea',
    background_color VARCHAR(7) DEFAULT '#ffffff',
    text_color VARCHAR(7) DEFAULT '#000000',
    position_x DECIMAL(10,2) DEFAULT 0.00,
    position_y DECIMAL(10,2) DEFAULT 0.00,
    width DECIMAL(8,2) DEFAULT 150.00,
    height DECIMAL(8,2) DEFAULT 50.00,
    font_size INT DEFAULT 14,
    icon VARCHAR(50) NULL,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT NULL,
    tags JSON NULL,
    metadata JSON NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (map_id) REFERENCES mind_maps(map_id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES nodes(node_id) ON DELETE CASCADE,
    
    INDEX idx_map_id (map_id),
    INDEX idx_parent_id (parent_id),
    INDEX idx_node_type (node_type),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_sort_order (sort_order)
);

-- Links table (for custom connections between nodes)
CREATE TABLE node_links (
    link_id INT AUTO_INCREMENT PRIMARY KEY,
    map_id INT NOT NULL,
    from_node_id INT NOT NULL,
    to_node_id INT NOT NULL,
    link_type ENUM('parent_child', 'reference', 'dependency', 'custom') DEFAULT 'custom',
    color VARCHAR(7) DEFAULT '#667eea',
    stroke_width INT DEFAULT 2,
    stroke_style ENUM('solid', 'dashed', 'dotted') DEFAULT 'solid',
    label VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (map_id) REFERENCES mind_maps(map_id) ON DELETE CASCADE,
    FOREIGN KEY (from_node_id) REFERENCES nodes(node_id) ON DELETE CASCADE,
    FOREIGN KEY (to_node_id) REFERENCES nodes(node_id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_link (from_node_id, to_node_id, link_type),
    INDEX idx_map_id (map_id),
    INDEX idx_from_node (from_node_id),
    INDEX idx_to_node (to_node_id)
);

-- User sessions table for JWT token management
CREATE TABLE user_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    user_agent TEXT,
    ip_address VARCHAR(45),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires (expires_at),
    INDEX idx_active (is_active)
);

-- Shared mind maps table
CREATE TABLE shared_maps (
    share_id INT AUTO_INCREMENT PRIMARY KEY,
    map_id INT NOT NULL,
    shared_by_user_id INT NOT NULL,
    share_token VARCHAR(100) UNIQUE NOT NULL,
    access_level ENUM('view', 'comment', 'edit') DEFAULT 'view',
    password_protected BOOLEAN DEFAULT FALSE,
    password_hash VARCHAR(255) NULL,
    expires_at TIMESTAMP NULL,
    access_count INT DEFAULT 0,
    max_access_count INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (map_id) REFERENCES mind_maps(map_id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    
    INDEX idx_map_id (map_id),
    INDEX idx_share_token (share_token),
    INDEX idx_expires (expires_at),
    INDEX idx_active (is_active)
);

-- Activity log table
CREATE TABLE activity_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    map_id INT NULL,
    action_type ENUM('create', 'update', 'delete', 'view', 'share', 'export') NOT NULL,
    resource_type ENUM('map', 'node', 'link', 'user', 'category') NOT NULL,
    resource_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (map_id) REFERENCES mind_maps(map_id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_map_id (map_id),
    INDEX idx_action_type (action_type),
    INDEX idx_resource_type (resource_type),
    INDEX idx_created (created_at)
);

-- Trigger to update node count in mind_maps
DELIMITER //
CREATE TRIGGER update_node_count_insert 
AFTER INSERT ON nodes 
FOR EACH ROW 
BEGIN
    UPDATE mind_maps 
    SET node_count = (
        SELECT COUNT(*) 
        FROM nodes 
        WHERE map_id = NEW.map_id
    ) 
    WHERE map_id = NEW.map_id;
END//

CREATE TRIGGER update_node_count_delete 
AFTER DELETE ON nodes 
FOR EACH ROW 
BEGIN
    UPDATE mind_maps 
    SET node_count = (
        SELECT COUNT(*) 
        FROM nodes 
        WHERE map_id = OLD.map_id
    ) 
    WHERE map_id = OLD.map_id;
END//
DELIMITER ;

-- Views for better performance
CREATE VIEW user_mindmaps AS
SELECT 
    m.map_id,
    m.user_id,
    m.title,
    m.description,
    m.theme,
    m.is_public,
    m.node_count,
    m.view_count,
    m.created_at,
    m.updated_at,
    c.name as category_name,
    c.color as category_color,
    u.username
FROM mind_maps m
LEFT JOIN categories c ON m.category_id = c.category_id
LEFT JOIN users u ON m.user_id = u.user_id
WHERE m.user_id IS NOT NULL;

CREATE VIEW public_mindmaps AS
SELECT 
    m.map_id,
    m.title,
    m.description,
    m.theme,
    m.node_count,
    m.view_count,
    m.created_at,
    m.updated_at,
    c.name as category_name,
    c.color as category_color,
    u.username
FROM mind_maps m
LEFT JOIN categories c ON m.category_id = c.category_id
LEFT JOIN users u ON m.user_id = u.user_id
WHERE m.is_public = TRUE AND m.user_id IS NOT NULL;