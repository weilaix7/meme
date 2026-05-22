<?php
/**
 * 数据库修复脚本 - 给 watchlist 表添加 user_id 字段
 * 访问此文件后请删除
 */
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    
    // 检查 user_id 字段是否已存在
    $stmt = $db->query("SHOW COLUMNS FROM watchlist LIKE 'user_id'");
    if ($stmt->fetch()) {
        echo "✅ user_id 字段已存在，无需修改\n";
    } else {
        // 添加 user_id 字段
        $db->exec("ALTER TABLE watchlist ADD COLUMN user_id INT NOT NULL DEFAULT 0 COMMENT '创建此监听的用户ID' AFTER id");
        $db->exec("ALTER TABLE watchlist ADD INDEX idx_user_id (user_id)");
        echo "✅ 已成功添加 user_id 字段\n";
    }
    
    echo "\n数据库修复完成！\n";
    echo "请删除此文件 (fix_db.php) 以确保安全。\n";
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}
