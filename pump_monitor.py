#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Pump.fun 打满迁移监控脚本
=========================
功能：
1. 通过 DexScreener 搜索 Pump.fun 上的新代币
2. 检测代币是否打满（迁移到 Raydium）
3. 计算打满耗时（从创建到打满）
4. 通过 Bark 推送通知

使用方法：
    python pump_monitor.py              # 循环运行
    python pump_monitor.py --once       # 单次运行
    python pump_monitor.py --interval 30  # 自定义间隔（秒）

依赖安装：
    pip install requests
"""

import requests
import json
import os
import time
import argparse
import logging
from datetime import datetime

# ============================================
# 配置区域
# ============================================

# Bark 推送配置
BARK_TOKEN = "jE6E68aQaLtMrfNHfR6ibX"

# 监控间隔（秒）
SCAN_INTERVAL = 30

# DexScreener 搜索 Pump.fun 代币
# 通过搜索 "pump.fun" 获取所有 pump 上的代币
DEXSCREENER_SEARCH_URL = "https://api.dexscreener.com/latest/dex/search/?q=pump.fun"

# 缓存文件
CACHE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "cache")
PUMP_CACHE_FILE = os.path.join(CACHE_DIR, "pump_coins.json")
MIGRATED_CACHE_FILE = os.path.join(CACHE_DIR, "pump_migrated.json")

# 日志
LOG_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "logs")
LOG_FILE = os.path.join(LOG_DIR, f"pump_monitor_{datetime.now().strftime('%Y%m%d')}.log")

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


def send_bark_notification(title, body):
    """通过 Bark 推送通知"""
    import urllib.parse
    url = f"https://api.day.app/{BARK_TOKEN}/{urllib.parse.quote(title)}/{urllib.parse.quote(body)}"
    logger.info(f"[Bark] 推送: title='{title}'")
    try:
        resp = requests.get(url, timeout=10)
        if resp.status_code == 200:
            logger.info(f"[Bark] 推送成功")
            return True
        else:
            logger.error(f"[Bark] 推送失败: HTTP {resp.status_code}")
    except Exception as e:
        logger.error(f"[Bark] 推送异常: {e}")
    return False


def format_duration(seconds):
    """将秒数格式化为可读时间"""
    if seconds < 60:
        return f"{int(seconds)}秒"
    elif seconds < 3600:
        return f"{int(seconds / 60)}分钟{int(seconds % 60)}秒"
    elif seconds < 86400:
        hours = int(seconds / 3600)
        mins = int((seconds % 3600) / 60)
        return f"{hours}小时{mins}分钟"
    else:
        days = int(seconds / 86400)
        hours = int((seconds % 86400) / 3600)
        return f"{days}天{hours}小时"


def format_marketcap(value):
    """格式化市值"""
    if value >= 1000000:
        return f"{value / 1000000:.2f}M"
    elif value >= 1000:
        return f"{value / 1000:.2f}K"
    return str(value)


def fetch_pump_pairs():
    """
    通过 DexScreener 搜索 pump.fun 上的所有交易对
    DexScreener 的 search API 可以搜索所有包含 "pump.fun" 的交易对
    """
    logger.info(f"[DexScreener] 搜索 Pump.fun 代币: {DEXSCREENER_SEARCH_URL}")
    try:
        resp = requests.get(DEXSCREENER_SEARCH_URL, timeout=15)
        logger.info(f"[DexScreener] 响应: HTTP {resp.status_code}")
        
        if resp.status_code == 200:
            data = resp.json()
            pairs = data.get("pairs", [])
            logger.info(f"[DexScreener] 获取到 {len(pairs)} 个 Pump.fun 交易对")
            return pairs
        else:
            logger.warning(f"[DexScreener] API 返回 HTTP {resp.status_code}")
    except Exception as e:
        logger.error(f"[DexScreener] 请求异常: {e}")
    
    return []


def run_once():
    """单次扫描"""
    logger.info("=" * 60)
    logger.info(f"Pump.fun 监控扫描 - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    logger.info("=" * 60)
    
    # 1. 获取所有 Pump.fun 交易对
    pairs = fetch_pump_pairs()
    if not pairs:
        logger.warning("未获取到 Pump.fun 交易对数据")
        return
    
    # 2. 加载缓存
    known_coins = load_cache(PUMP_CACHE_FILE)
    migrated_coins = load_cache(MIGRATED_CACHE_FILE)
    
    new_coins = 0
    migrated_found = 0
    
    # 3. 遍历所有交易对
    for pair in pairs:
        # 获取基础代币信息
        base_token = pair.get("baseToken", {})
        mint = base_token.get("address", "")
        symbol = base_token.get("symbol", "UNKNOWN")
        name = base_token.get("name", symbol)
        
        if not mint:
            continue
        
        # 获取交易对信息
        dex_id = pair.get("dexId", "")
        pair_created_at = pair.get("pairCreatedAt", 0)
        market_cap = int(pair.get("marketCap", 0) or 0)
        price_usd = pair.get("priceUsd", "0")
        liquidity_usd = pair.get("liquidity", {}).get("usd", 0)
        volume_24h = pair.get("volume", {}).get("h24", 0)
        pair_address = pair.get("pairAddress", "")
        chain_id = pair.get("chainId", "solana")
        
        # 判断是否已迁移到 Raydium
        is_raydium = (dex_id == "raydium")
        
        # 如果是新代币（还没记录过）
        if mint not in known_coins:
            known_coins[mint] = {
                "symbol": symbol,
                "name": name,
                "created_at": pair_created_at,
                "first_seen": int(time.time()),
                "first_dex": dex_id,
                "notified_migrated": False
            }
            new_coins += 1
            logger.info(f"[新代币] {symbol} ({name}) | dex={dex_id} | mint={mint[:12]}...")
        
        # 检查是否打满迁移（从 pump 迁移到 raydium）
        if is_raydium and mint not in migrated_coins:
            # 计算打满耗时
            created_at = known_coins[mint].get("created_at", 0)
            if created_at and created_at > 0:
                # pairCreatedAt 是毫秒时间戳
                if created_at > 1000000000000:
                    created_at = created_at / 1000
                now = time.time()
                duration = now - created_at
            else:
                duration = 0
            
            # 记录已迁移
            migrated_coins[mint] = {
                "symbol": symbol,
                "name": name,
                "created_at": created_at,
                "migrated_at": int(time.time()),
                "duration": duration,
                "market_cap": market_cap,
                "price_usd": price_usd,
                "liquidity_usd": liquidity_usd,
                "volume_24h": volume_24h,
                "pair_address": pair_address,
                "chain_id": chain_id,
                "notified": False
            }
            
            migrated_found += 1
            
            # 推送通知
            title = f"🚀 {symbol} 打满迁移!"
            body_lines = [
                f"代币: {symbol} ({name})",
                f"Mint: {mint[:16]}...",
                f"",
                f"⏱ 打满耗时: {format_duration(duration)}",
                f"💰 市值: ${format_marketcap(market_cap)}",
                f"💵 价格: ${float(price_usd):.10f}",
                f"💧 流动性: ${format_marketcap(int(liquidity_usd))}",
                f"📊 24h交易量: ${format_marketcap(int(volume_24h))}",
                f"",
                f"🔗 DexScreener: https://dexscreener.com/{chain_id}/{pair_address}",
                f"⏰ {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}",
            ]
            body = "\n".join(body_lines)
            
            logger.info(f"[迁移] {symbol} 打满! 耗时: {format_duration(duration)} | 市值: ${format_marketcap(market_cap)}")
            send_bark_notification(title, body)
            
            # 标记已通知
            migrated_coins[mint]["notified"] = True
            known_coins[mint]["notified_migrated"] = True
    
    # 4. 保存缓存
    save_cache(PUMP_CACHE_FILE, known_coins)
    save_cache(MIGRATED_CACHE_FILE, migrated_coins)
    
    # 5. 统计
    logger.info(f"本次扫描: 新代币 {new_coins} 个 | 新迁移 {migrated_found} 个")
    logger.info(f"累计: 监控 {len(known_coins)} 个代币 | 已迁移 {len(migrated_coins)} 个")


def run_loop(interval=SCAN_INTERVAL):
    """循环运行"""
    logger.info(f"启动 Pump.fun 监控，扫描间隔 {interval} 秒")
    logger.info(f"Bark Token: {BARK_TOKEN[:8]}...")
    logger.info("按 Ctrl+C 停止")
    
    while True:
        try:
            start_time = time.time()
            run_once()
            elapsed = time.time() - start_time
            wait_time = max(0, interval - elapsed)
            logger.info(f"本次扫描耗时 {elapsed:.1f} 秒，等待 {wait_time:.1f} 秒...")
            if wait_time > 0:
                time.sleep(wait_time)
        except KeyboardInterrupt:
            logger.info("用户中断，停止监控")
            break
        except Exception as e:
            logger.error(f"扫描异常: {e}")
            time.sleep(interval)


def main():
    parser = argparse.ArgumentParser(description="Pump.fun 打满迁移监控")
    parser.add_argument("--once", action="store_true", help="单次运行")
    parser.add_argument("--interval", type=int, default=SCAN_INTERVAL, help=f"扫描间隔（秒），默认{SCAN_INTERVAL}")
    parser.add_argument("--clear-cache", action="store_true", help="清除缓存")
    args = parser.parse_args()
    
    if args.clear_cache:
        for f in [PUMP_CACHE_FILE, MIGRATED_CACHE_FILE]:
            if os.path.exists(f):
                os.remove(f)
                logger.info(f"已清除缓存: {f}")
        logger.info("缓存清除完成")
        return
    
    if args.once:
        run_once()
    else:
        run_loop(args.interval)


if __name__ == "__main__":
    main()
