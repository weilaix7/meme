/*
 Navicat Premium Dump SQL

 Source Server         : 本地
 Source Server Type    : MySQL
 Source Server Version : 50726 (5.7.26)
 Source Host           : localhost:3306
 Source Schema         : watchlist_db

 Target Server Type    : MySQL
 Target Server Version : 50726 (5.7.26)
 File Encoding         : 65001

 Date: 17/05/2026 19:36:15
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','vip','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user' COMMENT '角色: admin=管理员, vip=VIP用户, user=普通用户',
  `bark_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '预设的 Bark Key',
  `tg_chat_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '预设的 Telegram Chat ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `username`(`username`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of users
-- ----------------------------
INSERT INTO `users` VALUES (1, 'admin', '$2y$10$ZlktnKcnZYbzJN/oCXTEc.cT9gmce311dESsuq1wt.Bxfod3DJUry', 'admin', 'jE6E68aQaLtMrfNHfR6ibX', '', '2026-05-17 16:07:48');
INSERT INTO `users` VALUES (2, 'jiuka', '$2y$10$2sdA.3O.HDdDoQjtxaGsxOY5MtH1A8bSPhnyGSw0UzUKNTD1IJTLa', 'vip', '', '', '2026-05-17 16:08:29');

-- ----------------------------
-- Table structure for watchlist
-- ----------------------------
DROP TABLE IF EXISTS `watchlist`;
CREATE TABLE `watchlist`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '创建此监听的用户ID',
  `ca` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `push_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '推送类型: bark, tg',
  `push_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Bark存token, Telegram存chat_id',
  `step_value` bigint(20) NOT NULL COMMENT '提醒阶梯',
  `last_level` bigint(20) NULL DEFAULT 0 COMMENT '上次提醒档位',
  `current_marketcap` bigint(20) NULL DEFAULT 0 COMMENT '当前市值缓存',
  `status` tinyint(4) NULL DEFAULT 1 COMMENT '1=正常监听, 0=已关闭',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_id`(`user_id`) USING BTREE,
  INDEX `idx_ca`(`ca`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of watchlist
-- ----------------------------
INSERT INTO `watchlist` VALUES (1, 1, '8ryRQD6jWfxnSdvvVbQ8Tzwo1NgGP7w1X1nQPpb4pump', 'bark', '11111111111111111111111', 5000, 0, 0, 1, '2026-05-17 16:14:12');
INSERT INTO `watchlist` VALUES (2, 2, '8ryRQD6jWfxnSdvvVbQ8Tzwo1NgGP7w1X1nQPpb4pump', 'bark', '1111111111111', 10000, 0, 0, 1, '2026-05-17 16:27:00');
INSERT INTO `watchlist` VALUES (3, 2, '8ryRQD6jWfxnSdvvVbQ8Tzwo1NgGP7w1X1nQPpb4pump', 'bark', '666666666666', 50000, 0, 0, 1, '2026-05-17 16:27:10');
INSERT INTO `watchlist` VALUES (6, 1, '61sri4wxXUTNSzwAKPHpraT9bPUF1b6Xyzny8KCQpump', 'bark', 'jE6E68aQaLtMrfNHfR6ibX', 10000, 0, 0, 1, '2026-05-17 19:05:47');

SET FOREIGN_KEY_CHECKS = 1;
