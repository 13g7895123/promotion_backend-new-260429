# Skill: Promotion 推廣生命週期完整分析

## 專案概述

- **框架**: CodeIgniter 4（CI4）
- **啟動方式**: Docker Compose (`docker/docker-compose.yml`)
- **資料庫連線名稱**: `promotion`（`app/Config/Database.php`）
- **基底 API 路徑**: `/api/promotion/`

---

## 資料庫表格結構（promotion 相關）

### `promotions` — 推廣主表

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | int | 推廣 ID |
| user_id | int | 玩家 ID（FK → player.id） |
| server | varchar(50) | 伺服器代碼（FK → server.code） |
| status | enum(standby, success, failed) | 審核狀態（預設 standby） |
| updated_at | datetime | 最後更新時間（AUTO UPDATE） |
| created_at | datetime | 建立時間 |

### `promotion_items` — 推廣細項

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | int | 細項 ID |
| promotion_id | int | FK → promotions.id |
| type | enum(image, text) | 類型（圖片/連結） |
| content | varchar(300) | 圖片：files.id；連結：URL |
| status | enum(standby, success, failed) | 審核狀態（預設 standby） |
| updated_at | datetime | 更新時間（AUTO UPDATE） |
| created_at | timestamp | 建立時間 |

### `batch_audit_jobs` — 批次審核佇列

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | int unsigned | Job ID |
| promotion_ids | json | 待審核的 promotion id 陣列 |
| audit_status | varchar(50) | 目標審核狀態（通常為 success/failed） |
| status | enum(pending, processing, completed, failed) | Job 執行狀態 |
| total | int | 待審核總數 |
| processed | int | 已處理數 |
| failed_ids | json | 處理失敗的 promotion id 陣列 |
| error_message | varchar(2000) | 失敗原因 |
| created_by | varchar(100) | 發起者（user_id 或 IP） |
| created_at | datetime | 入列時間 |
| started_at | datetime | 開始執行時間 |
| completed_at | datetime | 完成時間 |

### `reward` — 獎勵發送記錄

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | int | 記錄 ID |
| promotion_id | int | FK → promotions.id（注意：部分舊資料為 0） |
| player_id | int | 玩家 ID |
| server_code | varchar(50) | 伺服器代碼 |
| reward | text | 寫入遊戲 DB 的資料（JSON）|
| insert_id | int | 遊戲 DB 寫入後的 insert id |
| created_at | datetime | 發送時間 |

### `reward_log` — 派獎詳細記錄

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | int | 記錄 ID |
| promotion_id | int | FK → promotions.id |
| server_code | varchar(100) | 伺服器代碼 |
| player_data | text | 玩家資料快照（JSON）|
| insert_data | text | 寫入遊戲 DB 的資料（JSON）|
| create_at | datetime | 記錄時間（注意欄位名稱為 create_at，非 created_at）|

### `reissuance_reward` — 補發獎勵記錄

| 欄位 | 類型 | 說明 |
|------|------|------|
| id | int | 記錄 ID |
| promotion_id | int | FK → promotions.id |
| player_id | int | 玩家 ID |
| server_code | varchar(50) | 伺服器代碼 |
| reward | text | 補發資料（JSON）|
| insert_id | int | 遊戲 DB insert id |
| created_at | datetime | 補發時間 |

### `reissue_batch_log` — 補發批次記錄

| 欄位 | 類型 | 說明 |
|------|------|------|
| server_code | varchar(50) | 補發的伺服器（all = 全部） |
| total / success / failed | int | 本批次統計 |
| detail | longtext | 逐筆補發結果（JSON）|
| created_at | datetime | 執行時間 |

---

## 完整生命週期（8 個階段）

```
[1] 玩家建立推廣
    POST /api/promotion/
    Controller: Promotion::create()
    寫入：promotions（status=standby）
    邏輯：同一玩家同一伺服器同一天只能有一筆，重複提交重置 status=standby

[2] 上傳推廣細項
    POST /api/promotion/file       → 上傳圖片（FileController::upload）
    POST /api/promotion/items      → 建立細項（PromotionItem::create）
    寫入：promotion_items（status=standby）

[3] 管理者發起批次審核（入列）
    POST /api/promotion/main/batchAudit
    Controller: Promotion::batchAudit()
    寫入：batch_audit_jobs（status=pending）
    回傳：job_id（立即回應，非同步執行）

[4] 排程取出 Job（processing）
    觸發：Docker scheduler 每 60 秒執行 php spark batch-audit:process
    呼叫：M_BatchAuditJob::claimNextPending()
    更新：batch_audit_jobs（status=processing, started_at=NOW）
    防護：超過 5 分鐘未完成的 processing 任務自動標記 failed

[5] 執行細項審核
    呼叫：M_Promotion::batchAuditV3($promotionIds, $status)
    更新：promotion_items.status（standby → success/failed）

[6] 更新推廣主狀態
    呼叫：M_Promotion::getPromotionAudit($promotionId)
    邏輯：所有細項審核完成（無 standby）→ 有任一 success → promotions.status=success
          全部 failed → promotions.status=failed
    更新：promotions.status, promotions.updated_at

[7] 達標判斷 & 獎勵派發
    確認條件：server.cycle (daily/weekly/monthly) 期間內 success 細項 >= server.limit_number
    呼叫：M_Promotion::sendRewards($promotionId, $serverCode, $playerData)
    動作：
      1. 連線至遊戲伺服器 DB（customized_db 設定）
      2. 寫入遊戲 DB（按 customized_field 欄位設定）
      3. 寫入 reward 表
      4. 寫入 reward_log 表
    同時：sendNotification() → Email / Line 通知玩家

[8] Job 完成標記
    呼叫：M_BatchAuditJob::markCompleted() 或 markFailed()
    更新：batch_audit_jobs（status=completed/failed, completed_at=NOW）

[補發流程（可選）]
    觸發：POST /api/promotion/reward/reissue（Player::reissueReward）
    條件：promotions.status=success 但 reward 表無對應記錄
    呼叫：M_Promotion::reissueAllMissingRewards($serverCode)
    寫入：reissuance_reward、reissue_batch_log
```

---

## 已知錯誤與處理

### 1. `MySQL server has gone away`
- **原因**：批次審核時間過長，資料庫連線逾時
- **批次審核記錄**：batch_audit_jobs.status=failed，error_message 含此訊息
- **影響**：promotion_items 未更新，promotions.status 仍為 standby
- **處理**：重新發起批次審核

### 2. `Database connection info not found for server code: Shuihuo`
- **原因**：customized_db 表中找不到指定 server_code 的連線資訊
- **影響**：無法寫入遊戲 DB，reward 未派發
- **處理**：在 customized_db 表新增該伺服器的連線設定

### 3. processing 任務卡死
- **原因**：PHP process 被強制中止，任務卡在 processing 狀態
- **防護**：`claimNextPending()` 自動將超過 5 分鐘的 processing 任務標記為 failed
- **欄位**：`error_message = 'Timeout: 執行超過 5 分鐘未完成，已自動標記為失敗'`

### 4. `promotion_id=0` 的 reward 記錄
- **原因**：舊版本的 batchAudit 未正確傳入 promotion_id
- **影響**：reward 記錄存在但無法對應到推廣，tracker 會顯示「無獎勵記錄」
- **注意**：reward_log 表亦有此問題

---

## API 路由總覽

```
# 前台（玩家操作）
POST   /api/promotion/login                → 登入
POST   /api/promotion/server               → 取得伺服器
POST   /api/promotion/player/submit        → 驗證提交
POST   /api/promotion/player/info          → 取得玩家資訊
POST   /api/promotion/                     → 建立推廣
POST   /api/promotion/file                 → 上傳圖片
POST   /api/promotion/items                → 新增細項

# 後台（管理者操作）
POST   /api/promotion/main                 → 取得推廣列表（支援分頁、搜尋）
POST   /api/promotion/main/batchAudit      → 批次審核（非同步入列）
GET    /api/promotion/detail/:id           → 推廣細項
PUT    /api/promotion/detail/update/:id    → 更新細項
POST   /api/promotion/main/delete          → 刪除推廣

# 獎勵查詢 & 補發
POST   /api/promotion/player/reward        → 取得玩家獎勵
POST   /api/promotion/reward/missing       → 查詢缺少派獎的推廣
POST   /api/promotion/reward/reissue       → 補發獎勵

# 監控頁面
GET    /api/promotion/lifecycle            → 推廣生命週期 Log（時間軸視圖）
GET    /api/promotion/lifecycle/data       → 生命週期 JSON 資料
GET    /api/promotion/lifecycle/summary    → 彙整統計
GET    /api/promotion/lifecycle/audit-events → 批次審核事件清單

GET    /api/promotion/batch-audit/jobs     → 批次審核佇列監控
GET    /api/promotion/batch-audit/jobs/data     → Job 列表 JSON
GET    /api/promotion/batch-audit/jobs/stats    → Job 統計
GET    /api/promotion/batch-audit/jobs/:id      → 單筆 Job 詳細
GET    /api/promotion/batch-audit/scheduler/health → 排程健康狀態

GET    /api/promotion/tracker              → 推廣狀態追蹤器（本頁面）
GET    /api/promotion/tracker/search       → 搜尋推廣 JSON
GET    /api/promotion/tracker/detail/:id   → 單筆完整生命週期 JSON

GET    /api/promotion/logs                 → API Log 查看器
```

---

## 關鍵 Controller / Model 對照

| 功能 | Controller | Model |
|------|-----------|-------|
| 推廣 CRUD | `Promotion/Promotion.php` | `Promotion/M_Promotion.php` |
| 細項 CRUD | `Promotion/PromotionItem.php` | `Promotion/M_PromotionItem.php` |
| 批次審核佇列 | `Promotion/BatchAuditJob.php` | `Promotion/M_BatchAuditJob.php` |
| 排程執行 | `Commands/BatchAuditProcess.php` | — |
| 生命週期 Log | `Promotion/LifecycleLog.php` | — |
| 狀態追蹤器 | `Promotion/PromotionTracker.php` | — |
| 玩家 | `Promotion/Player.php` | `Promotion/M_Player.php` |
| 伺服器 | `Promotion/Server.php` | `Promotion/M_Server.php` |

---

## 推廣狀態追蹤器使用說明

**路徑**：`GET /api/promotion/tracker`

### 功能
- 左側搜尋列表：支援帳號/推廣ID關鍵字搜尋、伺服器篩選、狀態篩選、日期範圍篩選
- 右側詳細面板：點選任一推廣，即顯示其完整生命週期資料

### 詳細資料包含
1. **推廣主資料** - status、玩家帳號、伺服器、週期設定
2. **生命週期時間軸** - 可視化顯示 5 個關鍵時間節點（建立 → 入列 → 執行 → 審核 → 派獎）
3. **推廣細項（promotion_items）** - 每個圖片/連結的審核狀態
4. **批次審核佇列（batch_audit_jobs）** - 哪個 Job 處理了此推廣，Job 的完整狀態與錯誤訊息
5. **獎勵發送記錄（reward）** - 派發內容的 JSON 詳細
6. **派獎詳細記錄（reward_log）** - 寫入遊戲 DB 的完整資料快照
7. **補發記錄（reissuance_reward）** - 是否有補發及補發內容

### 關鍵 API Endpoints
```
GET /api/promotion/tracker/search
  ?q=帳號或ID
  &server=伺服器代碼
  &status=standby|success|failed
  &date_from=YYYY-MM-DD
  &date_to=YYYY-MM-DD
  &page=1&per_page=20

GET /api/promotion/tracker/detail/{promotion_id}
  回傳：promotion, items, audit_jobs, rewards, reward_logs, reissuance
```

---

## 常用診斷查詢（SQL）

```sql
-- 查詢 status=success 但無 reward 的推廣（缺少派獎）
SELECT p.id, p.user_id, p.server, p.status, p.created_at
FROM promotions p
WHERE p.status = 'success'
  AND NOT EXISTS (SELECT 1 FROM reward r WHERE r.promotion_id = p.id);

-- 查詢某推廣的完整 job 歷史
SELECT * FROM batch_audit_jobs
WHERE JSON_CONTAINS(promotion_ids, '{promotion_id}', '$')
ORDER BY id DESC;

-- 查詢近 7 天失敗的 Job
SELECT * FROM batch_audit_jobs
WHERE status = 'failed'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY id DESC;

-- 查詢卡在 processing 的 Job
SELECT * FROM batch_audit_jobs
WHERE status = 'processing';

-- 查詢某玩家的所有推廣歷史
SELECT p.id, p.server, p.status, p.created_at,
       COUNT(pi.id) AS items_total,
       SUM(pi.status = 'success') AS items_success
FROM promotions p
LEFT JOIN promotion_items pi ON pi.promotion_id = p.id
WHERE p.user_id = {player_id}
GROUP BY p.id
ORDER BY p.id DESC;
```

---

## Docker 環境

- 容器啟動：`docker compose -f docker/docker-compose.yml up -d`
- 排程 log：`docker compose -f docker/docker-compose.yml logs scheduler`
- 手動執行排程：`docker exec {php_container} php spark batch-audit:process`
- 排程健康狀態：`GET /api/promotion/batch-audit/scheduler/health`
