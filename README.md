一、PHP 网站整体逻辑

PHP 网站其实只做三件事：

1. 用户提交监听
2. 存数据库
3. 给 Python 提供 API
二、最核心数据库结构

你现在真正核心其实只有：

一张表：
watchlist
SQL 结构（推荐第一版）
CREATE TABLE watchlist (
    id INT PRIMARY KEY AUTO_INCREMENT,

    ca VARCHAR(128) NOT NULL,

    push_type VARCHAR(20) NOT NULL,

    push_key VARCHAR(255) NOT NULL,

    step_value BIGINT NOT NULL,

    last_level BIGINT DEFAULT 0,

    current_marketcap BIGINT DEFAULT 0,

    status TINYINT DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
三、字段解释（非常重要）
id

监听ID。

ca
合约地址

例如：

7xKXtg2CW...
push_type

推送类型：

bark
tg
push_key
Bark

存：

token
Telegram

存：

chat_id
step_value

提醒阶梯：

10000
50000
100000
last_level（核心）

这个非常重要。

例如：

用户：

每50k提醒

当前市值：

120000

Python 计算：

120000 // 50000
= 2

数据库：

last_level = 1

说明：

跨档了。

需要提醒。

然后：

更新：

last_level = 2
current_marketcap

这个是：

当前最新市值缓存。

为什么建议存？

因为：

网站前端可以直接显示。

例如：

当前市值:
120k
Python 每次获取价格：

顺便更新。

status
1 = 正常监听
0 = 已关闭
四、为什么不用单独价格表？

因为：

你现在不是做K线系统。

你只是：

“提醒工具”。
所以：
没必要存历史价格。

否则：

数据量会暴涨
你真正需要的：

只有：

当前市值
上次提醒档位
五、PHP 页面逻辑
1. 添加监听页
用户输入：
CA
推送方式
Key
提醒档位
2. PHP 验证 CA

请求：

DexScreener API
如果存在：

允许提交。

不存在：
无效CA
3. 存数据库

例如：

INSERT INTO watchlist
(
  ca,
  push_type,
  push_key,
  step_value
)
4. 监听列表页面

显示：

CA
当前市值
提醒档位
状态
六、最核心 API（给 Python）
API 1：
获取唯一 CA
/api/ca_list.php
SQL：
SELECT DISTINCT ca
FROM watchlist
WHERE status=1
返回：
[
  "CA1",
  "CA2"
]
API 2：
获取某个 CA 的监听用户
/api/watchers.php?ca=xxx
SQL：
SELECT *
FROM watchlist
WHERE ca=?
AND status=1
返回：
[
  {
    "id":1,
    "push_type":"bark",
    "push_key":"xxx",
    "step_value":50000,
    "last_level":2
  }
]
API 3：
Python 更新 last_level
/api/update_level.php
Python 提交：
{
  "id":1,
  "last_level":3,
  "marketcap":180000
}
PHP：
UPDATE watchlist
SET
last_level=?,
current_marketcap=?
WHERE id=?
API 4：
关闭死盘
/api/disable_ca.php
Python：
{
  "ca":"xxxx"
}
PHP：
UPDATE watchlist
SET status=0
WHERE ca=?
