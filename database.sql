-- TikTok Campaign Launcher User Management Database Schema

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('master', 'subuser') DEFAULT 'subuser',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Advertiser accounts table (stores TikTok advertiser accounts)
CREATE TABLE IF NOT EXISTS advertiser_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    advertiser_id VARCHAR(50) UNIQUE NOT NULL,
    advertiser_name VARCHAR(255),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by INT,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
);

-- User permissions table (links users to advertiser accounts)
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    advertiser_account_id INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INT,
    UNIQUE KEY unique_permission (user_id, advertiser_account_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (advertiser_account_id) REFERENCES advertiser_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Activity logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    advertiser_id VARCHAR(50),
    action VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create default master user (password: admin123 - change this!)
INSERT INTO users (username, email, password_hash, role) 
VALUES ('admin', 'admin@example.com', '$2y$10$YourHashedPasswordHere', 'master')
ON DUPLICATE KEY UPDATE id=id;

-- Indexes for better performance
CREATE INDEX idx_user_role ON users(role);
CREATE INDEX idx_permissions_user ON user_permissions(user_id);
CREATE INDEX idx_permissions_advertiser ON user_permissions(advertiser_account_id);
CREATE INDEX idx_logs_user ON activity_logs(user_id);
CREATE INDEX idx_logs_date ON activity_logs(created_at);