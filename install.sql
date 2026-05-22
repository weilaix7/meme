-- ============================================
-- MEME 币市值提醒 - 数据库安装脚本
-- ============================================
-- 使用方法: mysql -u root -p < install.sql
-- 或者直接在 MySQL 命令行中执行: source install.sql;
-- ============================================

-- 创建数据库（如果不存在）
CREATE DATABASE IF NOT EXISTS watchlist_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE watchlist_db;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'vip', 'user') NOT NULL DEFAULT 'user' COMMENT '角色: admin=管理员, vip=VIP用户, user=普通用户',
    bark_key VARCHAR(255) DEFAULT '' COMMENT '预设的 Bark Key',
    tg_chat_id VARCHAR(255) DEFAULT '' COMMENT '预设的 Telegram Chat ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 监听列表表
CREATE TABLE IF NOT EXISTS watchlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL DEFAULT 0 COMMENT '创建此监听的用户ID',
    ca VARCHAR(128) NOT NULL,
    push_type VARCHAR(20) NOT NULL COMMENT '推送类型: bark, tg',
    push_key VARCHAR(255) NOT NULL COMMENT 'Bark存token, Telegram存chat_id',
    step_value BIGINT NOT NULL COMMENT '提醒阶梯',
    last_level BIGINT DEFAULT 0 COMMENT '上次提醒档位',
    current_marketcap BIGINT DEFAULT 0 COMMENT '当前市值缓存',
    status TINYINT DEFAULT 1 COMMENT '1=正常监听, 0=已关闭',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_ca (ca),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 默认管理员账号
-- 用户名: admin
-- 密  码: admin123
-- 角  色: admin（管理员）
-- 
-- 角色说明:
--   admin = 管理员（完整权限）
--   vip   = VIP用户（无限监听）
--   user  = 普通用户（最多3个监听）
-- ============================================
INSERT INTO users (username, password, role) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ============================================
-- 如果已经安装过，需要执行以下 ALTER 语句
-- ============================================
-- ALTER TABLE watchlist ADD COLUMN user_id INT NOT NULL DEFAULT 0 COMMENT '创建此监听的用户ID' AFTER id;
-- ALTER TABLE watchlist ADD INDEX idx_user_id (user_id);
-- ALTER TABLE users ADD COLUMN bark_key VARCHAR(255) DEFAULT '' COMMENT '预设的 Bark Key' AFTER role;
-- ALTER TABLE users ADD COLUMN tg_chat_id VARCHAR(255) DEFAULT '' COMMENT '预设的 Telegram Chat ID' AFTER bark_key;
