#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
监听管理系统 - Python 监控脚本
===============================
功能：
1. 从 PHP 网站 API 获取需要监控的 CA 列表
2. 对每个 CA 获取监听用户配置
3. 从 DexScreener 获取当前市值
4. 计算档位变化（上涨/下跌都触发）
5. 本地缓存市值（用于对比涨幅）
6. 首次读取时推送当前市值
7. 通过 Bark/Telegram 推送通知
8. 多线程并发处理多个 CA

使用方法：
    python monitor.py              # 循环运行（每30秒）
    python monitor.py --interval 15  # 自定义间隔
    python monitor.py --once       # 单次运行
    python monitor.py --clear-cache      # 清除本地缓存

依赖安装：
    pip install requests
"""

import requests
import json
import os
import sys
import time
import argparse
import logging
import threading
import concurrent.futures
from datetime import datetime
from typing import Optional

# ============================================
# 配置区域
# ============================================

API_BASE_URL = "https://goudanba.com"
API_CA_LIST = f"{API_BASE_URL}/api/ca_list.php"
API_WATCHERS = f"{API_BASE_URL}/api/watchers.php"
API_UPDATE_LEVEL = f"{API_BASE_URL}/api/update_level.php"
TELEGRAM_BOT_TOKEN = "8761383326:AAG4p1c72qjBaBJdq-LdYitLssHpb5TZCvE"

# 最大并发线程数
MAX_WORKERS = 5

CACHE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "cache")
MARKETCAP_CACHE_FILE = os.path.join(CACHE_DIR, "marketcap_cache.json")
PRICE_CACHE_FILE = os.path.join(CACHE_DIR, "price_cache.json")
# 首次推送标记缓存 - 按 watch_id 维度记录，每个监听用户独立
FIRST_NOTIFY_CACHE_FILE = os.path.join(CACHE_DIR, "first_notify_cache.json")

LOG_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "logs")
LOG_FILE = os.path.join(LOG_DIR, f"monitor_{datetime.now().strftime('%Y%m%d')}.log")

os.makedirs(LOG_DIR, exist_ok=True)
os.makedirs(CACHE_DIR, exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler(LOG_FILE, encoding='utf-8'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# 线程锁，用于保护缓存文件写入
cache_lock = threading.Lock()


def load_cache(filepath):
    try:
        if os.path.exists(filepath):
            with open(filepath, 'r', encoding='utf-8') as f:
                return json.load(f)
    except Exception as e:
        logger.warning(f"加载缓存失败: {e}")
    return {}


def save_cache(filepath, data):
    try:
        with open(filepath, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
    except Exception as e:
        logger.error(f"保存缓存失败: {e}")


def get_cached_marketcap(ca):
    cache = load_cache(MARKETCAP_CACHE_FILE)
    return cache.get(ca)


def update_marketcap_cache(ca, marketcap):
    with cache_lock:
        cache = load_cache(MARKETCAP_CACHE_FILE)
        cache[ca] = marketcap
        save_cache(MARKETCAP_CACHE_FILE, cache)


def get_cached_price(ca):
    cache = load_cache(PRICE_CACHE_FILE)
    return cache.get(ca)


def update_price_cache(ca, price):
    with cache_lock:
        cache = load_cache(PRICE_CACHE_FILE)
        cache[ca] = price
        save_cache(PRICE_CACHE_FILE, cache)


def has_first_notified(watch_id):
    """检查某个监听用户是否已经发送过首次通知"""
    cache = load_cache(FIRST_NOTIFY_CACHE_FILE)
    return cache.get(str(watch_id), False)


def mark_first_notified(watch_id):
    """标记某个监听用户已发送首次通知"""
    with cache_lock:
        cache = load_cache(FIRST_NOTIFY_CACHE_FILE)
        cache[str(watch_id)] = True
        save_cache(FIRST_NOTIFY_CACHE_FILE, cache)


def api_get_ca_list():
    """从 PHP 获取所有需要监控的 CA 列表"""
    logger.info(f"[API] 请求 CA 列表: {API_CA_LIST}")
    try:
        resp = requests.get(API_CA_LIST, timeout=10)
        logger.info(f"[API] CA列表响应: HTTP {resp.status_code}")
        if resp.status_code == 200:
            data = resp.json()
            if isinstance(data, list):
                logger.info(f"[API] 获取到 {len(data)} 个需要监控的 CA")
                if len(data) > 0:
                    for ca in data:
                        logger.info(f"[API]   - CA: {ca[:16]}...")
                return data
            logger.error(f"[API] CA列表返回格式异常: {data}")
        else:
            logger.error(f"[API] 获取CA列表失败, HTTP {resp.status_code}")
    except Exception as e:
        logger.error(f"[API] 获取CA列表异常: {e}")
    return []


def api_get_watchers(ca):
    """获取某个 CA 的监听用户列表"""
    logger.info(f"[API] 请求监听用户: {API_WATCHERS}?ca={ca[:16]}...")
    try:
        resp = requests.get(API_WATCHERS, params={"ca": ca}, timeout=10)
        logger.info(f"[API] 监听用户响应: HTTP {resp.status_code}")
        if resp.status_code == 200:
            data = resp.json()
            if isinstance(data, list):
                logger.info(f"[API] CA {ca[:12]}... 有 {len(data)} 个监听用户")
                if len(data) > 0:
                    for w in data:
                        logger.info(f"[API]   - ID={w['id']} | 推送={w['push_type']} | 档位={w['step_value']} | last_level={w['last_level']}")
                return data
            logger.error(f"[API] 监听用户列表返回格式异常: {data}")
        else:
            logger.error(f"[API] 获取监听用户失败, HTTP {resp.status_code}")
    except Exception as e:
        logger.error(f"[API] 获取监听用户异常: {e}")
    return []


def api_update_level(watch_id, last_level, marketcap):
    """更新监听记录的 last_level 和 marketcap"""
    logger.info(f"[API] 更新档位: ID={watch_id} | last_level={last_level} | marketcap={marketcap}")
    try:
        resp = requests.post(API_UPDATE_LEVEL, json={
            "id": watch_id,
            "last_level": last_level,
            "marketcap": marketcap
        }, timeout=10)
        logger.info(f"[API] 更新档位响应: HTTP {resp.status_code}")
        if resp.status_code == 200:
            data = resp.json()
            success = data.get("success", False)
            if success:
                logger.info(f"[API] 更新档位成功: ID={watch_id} → last_level={last_level}")
            else:
                logger.error(f"[API] 更新档位失败: {data}")
            return success
        else:
            logger.error(f"[API] 更新档位失败, HTTP {resp.status_code}")
    except Exception as e:
        logger.error(f"[API] 更新档位异常: {e}")
    return False


def safe_float(value, default=0.0):
    if value is None:
        return default
    if isinstance(value, (int, float)):
        return float(value)
    if isinstance(value, str):
        try:
            return float(value)
        except (ValueError, TypeError):
            return default
    return default


def safe_int(value, default=0):
    if value is None:
        return default
    if isinstance(value, (int, float)):
        return int(value)
    if isinstance(value, str):
        try:
            return int(float(value))
        except (ValueError, TypeError):
            return default
    return default


def fetch_dexscreener_data(ca):
    """从 DexScreener 获取代币数据"""
    url = f"https://api.dexscreener.com/latest/dex/tokens/{ca}"
    logger.info(f"[DexScreener] 请求数据: {ca[:16]}...")
    try:
        resp = requests.get(url, timeout=15)
        logger.info(f"[DexScreener] 响应: HTTP {resp.status_code}")
        if resp.status_code == 200:
            data = resp.json()
            if data.get("pairs") and len(data["pairs"]) > 0:
                pair = data["pairs"][0]
                result = {
                    "price": safe_float(pair.get("priceUsd")),
                    "marketCap": safe_int(pair.get("marketCap")),
                    "volume": safe_int(pair.get("volume")),
                    "pairAddress": pair.get("pairAddress", ""),
                    "baseToken": pair.get("baseToken", {}).get("symbol", ""),
                    "quoteToken": pair.get("quoteToken", {}).get("symbol", ""),
                    "chainId": pair.get("chainId", ""),
                    "dexId": pair.get("dexId", ""),
                }
                logger.info(f"[DexScreener] 数据: {result['baseToken']} | 价格={result['price']} | 市值={result['marketCap']} | 链={result['chainId']}")
                return result
            else:
                logger.warning(f"[DexScreener] CA {ca[:12]}... 无数据 (pairs为空)")
        else:
            logger.warning(f"[DexScreener] API 返回 HTTP {resp.status_code}")
    except Exception as e:
        logger.error(f"[DexScreener] 请求异常: {e}")
    return None


def send_bark_notification(token, title, body):
    """通过 Bark 推送通知"""
    import urllib.parse
    url = f"https://api.day.app/{token}/{urllib.parse.quote(title)}/{urllib.parse.quote(body)}"
    logger.info(f"[推送] Bark: title='{title}' | token={token[:8]}...")
    try:
        resp = requests.get(url, timeout=10)
        if resp.status_code == 200:
            logger.info(f"[推送] Bark 成功")
            return True
        else:
            logger.error(f"[推送] Bark失败: HTTP {resp.status_code}")
    except Exception as e:
        logger.error(f"[推送] Bark异常: {e}")
    return False


def send_telegram_notification(chat_id, message, bot_token=None):
    """通过 Telegram 推送通知"""
    bot_token = bot_token or TELEGRAM_BOT_TOKEN or os.environ.get("TELEGRAM_BOT_TOKEN")
    if not bot_token:
        logger.error("[推送] Telegram 需要设置 TELEGRAM_BOT_TOKEN")
        return False
    url = f"https://api.telegram.org/bot{bot_token[:8]}.../sendMessage"
    logger.info(f"[推送] Telegram: chat_id={chat_id} | bot={bot_token[:8]}...")
    try:
        resp = requests.post(f"https://api.telegram.org/bot{bot_token}/sendMessage", json={
            "chat_id": chat_id,
            "text": message,
            "parse_mode": "HTML"
        }, timeout=10)
        if resp.status_code == 200:
            logger.info(f"[推送] Telegram 成功")
            return True
        else:
            logger.error(f"[推送] Telegram失败: HTTP {resp.status_code}, {resp.text}")
    except Exception as e:
        logger.error(f"[推送] Telegram异常: {e}")
    return False


def send_notification(push_type, push_key, title, body):
    """统一推送接口"""
    logger.info(f"[推送] 开始推送: type={push_type} | title='{title}'")
    if push_type == "bark":
        return send_bark_notification(push_key, title, body)
    elif push_type == "tg":
        message = f"<b>{title}</b>\n\n{body}"
        return send_telegram_notification(push_key, message)
    else:
        logger.warning(f"[推送] 未知的推送类型: {push_type}")
        return False


def format_marketcap(value):
    if value >= 1000000:
        return f"{value / 1000000:.2f}M"
    elif value >= 1000:
        return f"{value / 1000:.2f}K"
    return str(value)


def format_price(value):
    if value >= 1:
        return f"${value:.4f}"
    elif value >= 0.001:
        return f"${value:.6f}"
    elif value >= 0.000001:
        return f"${value:.8f}"
    else:
        return f"${value:.10f}"


def calculate_level(marketcap, step_value):
    if step_value <= 0:
        return 0
    return marketcap // step_value


def process_ca(ca):
    """
    处理单个 CA 的监控逻辑
    返回: 触发的通知数量
    """
    logger.info(f"[处理] 开始处理 CA: {ca[:16]}...")

    # 1. 获取该 CA 的监听用户
    watchers = api_get_watchers(ca)
    if not watchers:
        logger.info(f"[处理] CA {ca[:12]}... 无活跃监听，跳过")
        return 0

    # 2. 从 DexScreener 获取当前数据
    dex_data = fetch_dexscreener_data(ca)
    if not dex_data:
        logger.warning(f"[处理] CA {ca[:12]}... 获取 DexScreener 数据失败，跳过")
        return 0

    current_marketcap = dex_data["marketCap"]
    current_price = dex_data["price"]
    token_symbol = dex_data.get("baseToken", "TOKEN")
    chain = dex_data.get("chainId", "")

    # 3. 获取缓存数据
    cached_marketcap = get_cached_marketcap(ca)
    cached_price = get_cached_price(ca)
    logger.info(f"[缓存] 上次市值: {cached_marketcap} | 上次价格: {cached_price}")

    # 4. 更新缓存
    update_marketcap_cache(ca, current_marketcap)
    update_price_cache(ca, current_price)
    logger.info(f"[缓存] 已更新: 市值={current_marketcap} | 价格={current_price}")

    # 5. 计算涨幅
    price_change_str = ""
    if cached_price and cached_price > 0:
        change_pct = ((current_price - cached_price) / cached_price) * 100
        if abs(change_pct) >= 0.1:
            direction = "📈" if change_pct > 0 else "📉"
            price_change_str = f"{direction} {change_pct:+.2f}%"
            logger.info(f"[价格] 变化: {price_change_str}")

    logger.info(f"[处理] {token_symbol} | 市值: {format_marketcap(current_marketcap)} | 价格: {format_price(current_price)} {price_change_str}")

    processed = 0

    # 6. 遍历每个监听用户
    for watcher in watchers:
        watch_id = watcher["id"]
        step_value = watcher["step_value"]
        last_level = watcher["last_level"]
        push_type = watcher["push_type"]
        push_key = watcher["push_key"]
        current_level = calculate_level(current_marketcap, step_value)

        logger.info(f"[处理] 用户 ID={watch_id} | 档位={step_value} | last_level={last_level} | current_level={current_level}")

        # 判断是否首次通知（按 watch_id 维度，每个监听用户独立）
        is_first_time = not has_first_notified(watch_id)

        if is_first_time:
            # 首次读取：推送当前市值
            title = f"🆕 {token_symbol} 开始监控"
            body_lines = [
                f"代币: {token_symbol} ({chain})",
                f"CA: {ca[:16]}...",
                f"",
                f"当前市值: {format_marketcap(current_marketcap)}",
                f"当前价格: {format_price(current_price)}",
                f"",
                f"提醒档位: 每 {format_marketcap(step_value)}",
                f"当前档位: {current_level}",
                f"",
                f"📌 市值每跨越一个档位将触发提醒",
                f"⏰ {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}",
            ]
            body = "\n".join(body_lines)
            logger.info(f"[通知] 首次监控! ID={watch_id} | 推送当前市值: {format_marketcap(current_marketcap)}")
            send_notification(push_type, push_key, title, body)
            api_update_level(watch_id, current_level, current_marketcap)
            mark_first_notified(watch_id)
            processed += 1
            continue

        # 非首次：判断档位变化
        if current_level != last_level:
            direction = "📈 上涨" if current_level > last_level else "📉 下跌"
            level_diff = abs(current_level - last_level)
            title = f"{token_symbol} 市值提醒"
            body_lines = [
                f"代币: {token_symbol} ({chain})",
                f"CA: {ca[:16]}...",
                f"",
                f"当前市值: {format_marketcap(current_marketcap)}",
                f"当前价格: {format_price(current_price)}",
                f"",
                f"提醒档位: 每 {format_marketcap(step_value)}",
                f"上次档位: {last_level}",
                f"当前档位: {current_level}",
                f"变化方向: {direction}",
            ]
            if level_diff > 1:
                body_lines.append(f"跨越档位: {level_diff} 档")
            if price_change_str:
                body_lines.append(f"价格变化: {price_change_str}")
            body_lines.append(f"")
            body_lines.append(f"⏰ {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
            body = "\n".join(body_lines)
            logger.info(f"[通知] 触发提醒! ID={watch_id} | {direction} | 档位 {last_level}→{current_level}")
            send_notification(push_type, push_key, title, body)
            api_update_level(watch_id, current_level, current_marketcap)
            processed += 1
        else:
            logger.info(f"[处理] ID={watch_id} 档位无变化 (last_level={last_level} = current_level={current_level})，仅更新市值")
            api_update_level(watch_id, current_level, current_marketcap)

    logger.info(f"[处理] CA {ca[:12]}... 处理完成，触发 {processed} 个通知")
    return processed


def run_once():
    """单次运行 - 多线程并发处理多个 CA"""
    logger.info("=" * 60)
    logger.info("开始监控扫描...")
    logger.info(f"时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    logger.info("=" * 60)

    ca_list = api_get_ca_list()
    if not ca_list:
        logger.warning("没有需要监控的 CA")
        return

    logger.info(f"获取到 {len(ca_list)} 个需要监控的 CA，使用 {min(MAX_WORKERS, len(ca_list))} 个线程并发处理")

    total_alerts = 0

    # 使用线程池并发处理多个 CA
    with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        # 提交所有任务
        future_to_ca = {executor.submit(process_ca, ca): ca for ca in ca_list}

        # 收集结果
        for future in concurrent.futures.as_completed(future_to_ca):
            ca = future_to_ca[future]
            try:
                alerts = future.result()
                total_alerts += alerts
            except Exception as e:
                logger.error(f"处理 CA {ca[:12]}... 时出错: {e}")

    logger.info("=" * 60)
    logger.info(f"扫描完成!")
    logger.info(f"处理 CA 数量: {len(ca_list)}")
    logger.info(f"触发提醒次数: {total_alerts}")
    logger.info("=" * 60)


def run_loop(interval=30):
    """循环运行"""
    logger.info(f"启动循环监控模式，间隔 {interval} 秒")
    logger.info(f"最大并发线程数: {MAX_WORKERS}")
    logger.info("按 Ctrl+C 停止")
    while True:
        try:
            run_once()
            logger.info(f"等待 {interval} 秒后下次扫描...")
            time.sleep(interval)
        except KeyboardInterrupt:
            logger.info("用户中断，停止监控")
            break
        except Exception as e:
            logger.error(f"循环运行出错: {e}")
            logger.info(f"等待 {interval} 秒后重试...")
            time.sleep(interval)


def main():
    """
    主入口 - 默认循环运行，每次循环都会重新从 PHP 获取 CA 列表和监听用户
    所以用户新增/删除监听后，下次循环自动生效，无需重启脚本
    """
    parser = argparse.ArgumentParser(description="监听管理系统 - Python 监控脚本")
    parser.add_argument("--once", action="store_true", help="单次运行模式（默认是循环模式）")
    parser.add_argument("--interval", type=int, default=30, help="循环间隔（秒），默认30秒")
    parser.add_argument("--clear-cache", action="store_true", help="清除本地缓存")
    args = parser.parse_args()

    if args.clear_cache:
        for f in [MARKETCAP_CACHE_FILE, PRICE_CACHE_FILE, FIRST_NOTIFY_CACHE_FILE]:
            if os.path.exists(f):
                os.remove(f)
                logger.info(f"已清除缓存: {f}")
        logger.info("缓存清除完成")
        return

    if args.once:
        run_once()
    else:
        # 默认循环模式，每次循环都重新获取数据
        # 用户新增/删除监听后，下次循环自动生效
        run_loop(args.interval)


if __name__ == "__main__":
    main()
