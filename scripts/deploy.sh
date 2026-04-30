#!/bin/bash
# =============================================================================
# deploy.sh — 後端部署腳本
#
# 功能：
#   1. git pull 拉取最新代碼
#   2. 在 php 容器內執行 migrate（自動套用新 migration）
#
# 用法：
#   cd /path/to/promotion_backend-new
#   bash scripts/deploy.sh
#
# 需求：
#   - 在專案根目錄下執行
#   - Docker Compose 服務已啟動（php + mysql 容器需存活）
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
COMPOSE_FILE="${PROJECT_ROOT}/docker/docker-compose.yml"
PHP_SERVICE="php"

echo "=============================="
echo " Promotion Backend Deploy"
echo "=============================="
echo ""

# ── 1. git pull ────────────────────────────────────────────────
echo "[1/3] git pull..."
cd "${PROJECT_ROOT}"
git pull
echo ""

# ── 2. 確認 php 容器存活 ───────────────────────────────────────
echo "[2/3] 確認 Docker 容器狀態..."
if ! docker compose -f "${COMPOSE_FILE}" ps --services --filter "status=running" | grep -q "^${PHP_SERVICE}$"; then
    echo "ERROR: '${PHP_SERVICE}' 容器未運行，請先啟動 Docker Compose。"
    echo "       docker compose -f docker/docker-compose.yml up -d"
    exit 1
fi
echo "  ✔ ${PHP_SERVICE} 容器正常運行"
echo ""

# ── 3. 執行 Migration ──────────────────────────────────────────
echo "[3/3] 執行 migrate..."
docker compose -f "${COMPOSE_FILE}" exec -T "${PHP_SERVICE}" php spark migrate --all
echo ""

echo "=============================="
echo " Deploy 完成！"
echo "=============================="
