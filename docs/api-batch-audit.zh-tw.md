# 批次審核排程 API 文件

> 版本更新日期：2026-04-30
> 模組：批次審核（Batch Audit）
> 說明：推廣批次審核採非同步排程機制，前端送出審核請求後立即取得 `job_id`，實際的狀態更新與派獎由 Docker scheduler 容器每分鐘自動執行。

---

## 整體流程

```
前端送出批次審核
      ↓
POST /api/promotion/main/batchAudit
      ↓ 立即回傳 job_id（任務入列，status = pending）
      ↓
（等待最多 60 秒）
      ↓
Docker scheduler 每分鐘執行 php spark batch-audit:process
      ↓
┌─ 更新 promotion_items.status（standby → success / reject）
├─ 更新 promotions.status
├─ 寫入派獎記錄（reward）
└─ 發送玩家通知（LINE / Email）
      ↓
job.status = completed / failed
```

---

## API 清單

| 方法 | 端點 | 說明 |
|---|---|---|
| POST | `/api/promotion/main/batchAudit` | 提交批次審核（入列） |
| GET  | `/api/promotion/batch-audit/jobs` | 排程監控 HTML 頁面 |
| GET  | `/api/promotion/batch-audit/jobs/data` | 分頁查詢 Job 列表 |
| GET  | `/api/promotion/batch-audit/jobs/stats` | 各狀態統計摘要 |
| GET  | `/api/promotion/batch-audit/jobs/{id}` | 單筆 Job 詳細資料 |
| GET  | `/api/promotion/batch-audit/scheduler/health` | 排程容器健康狀態 |

---

## 1. 提交批次審核

### Endpoint

```
POST /api/promotion/main/batchAudit
Content-Type: application/json
```

### Request Body

| 欄位 | 型別 | 必填 | 說明 |
|---|---|---|---|
| `id` | array\<int\|string\> | ✅ | 要審核的 promotion ID 陣列（不可為空） |
| `status` | string | ✅ | 目標審核狀態，如 `success`、`reject` |
| `user_id` | int\|string | 否 | 操作者 ID，不帶則記錄呼叫方 IP |

### Request 範例

```json
{
  "id": [101, 102, 103],
  "status": "success",
  "user_id": 5
}
```

### Response（成功）

```json
{
  "success": true,
  "msg": "批次審核已入列，排程將於下一分鐘內執行",
  "job_id": 42
}
```

| 欄位 | 說明 |
|---|---|
| `job_id` | 可用於輪詢 `GET /batch-audit/jobs/{job_id}` 查詢進度 |

### Response（失敗）

```json
{
  "success": false,
  "msg": "id 必須為非空陣列"
}
```

| HTTP 狀態 | 原因 |
|---|---|
| 400 | `id` 為空或非陣列 |
| 400 | `status` 為空 |

---

## 2. 排程監控 HTML 頁面

### Endpoint

```
GET /api/promotion/batch-audit/jobs
```

開啟瀏覽器即可查看，提供：

- 各狀態統計卡（Total / Pending / Processing / Completed / Failed）
- 排程容器健康狀態指示燈
- 可依狀態、日期、觸發人篩選的 Job 列表
- 每 30 秒自動刷新
- 點擊任一列查看 Job 詳細資料（含 promotion_ids、failed_ids）

---

## 3. 分頁查詢 Job 列表

### Endpoint

```
GET /api/promotion/batch-audit/jobs/data
```

### Query 參數

| 參數 | 型別 | 必填 | 預設 | 說明 |
|---|---|---|---|---|
| `page` | int | 否 | `1` | 頁碼（從 1 開始） |
| `per_page` | int | 否 | `20` | 每頁筆數，最小 5，最大 100 |
| `status` | string | 否 | - | 過濾狀態：`pending` / `processing` / `completed` / `failed` |
| `created_by` | string | 否 | - | 觸發人模糊搜尋（user_id 或 IP） |
| `date` | string | 否 | - | 過濾入列日期，格式 `YYYY-MM-DD` |

### Request 範例

```
GET /api/promotion/batch-audit/jobs/data?status=pending&page=1&per_page=20
```

### Response

```json
{
  "success": true,
  "page": 1,
  "per_page": 20,
  "total": 87,
  "total_pages": 5,
  "data": [
    {
      "id": 42,
      "promotion_ids": [101, 102, 103],
      "audit_status": "success",
      "status": "completed",
      "total": 3,
      "processed": 3,
      "failed_ids": [],
      "error_message": null,
      "created_by": "5",
      "created_at": "2026-04-30 20:30:00",
      "started_at": "2026-04-30 20:30:07",
      "completed_at": "2026-04-30 20:30:21"
    }
  ]
}
```

### Job 物件欄位說明

| 欄位 | 型別 | 說明 |
|---|---|---|
| `id` | int | Job 唯一識別碼 |
| `promotion_ids` | array | 本次批次審核的 promotion ID 陣列 |
| `audit_status` | string | 目標審核狀態（`success` / `reject` 等） |
| `status` | string | Job 當前狀態（見下表） |
| `total` | int | 待處理總筆數 |
| `processed` | int | 已處理筆數 |
| `failed_ids` | array | 處理失敗的 promotion ID |
| `error_message` | string\|null | 失敗原因（最多 2000 字元） |
| `created_by` | string\|null | 觸發人（user_id 或 IP） |
| `created_at` | string | 入列時間（`YYYY-MM-DD HH:mm:ss`） |
| `started_at` | string\|null | 開始處理時間 |
| `completed_at` | string\|null | 處理完成時間 |

### Job status 說明

| status | 說明 |
|---|---|
| `pending` | 等待排程執行 |
| `processing` | 排程正在執行中 |
| `completed` | 全部處理完成（含部分失敗） |
| `failed` | 執行時發生例外，任務中斷 |

---

## 4. 各狀態統計摘要

### Endpoint

```
GET /api/promotion/batch-audit/jobs/stats
```

### Response

```json
{
  "success": true,
  "data": {
    "pending": 2,
    "processing": 1,
    "completed": 150,
    "failed": 3,
    "total": 156
  }
}
```

---

## 5. 單筆 Job 詳細資料

### Endpoint

```
GET /api/promotion/batch-audit/jobs/{id}
```

### Path 參數

| 參數 | 型別 | 說明 |
|---|---|---|
| `id` | int | Job ID |

### Response（成功）

```json
{
  "success": true,
  "data": {
    "id": 42,
    "promotion_ids": [101, 102, 103],
    "audit_status": "success",
    "status": "completed",
    "total": 3,
    "processed": 3,
    "failed_ids": [],
    "error_message": null,
    "created_by": "5",
    "created_at": "2026-04-30 20:30:00",
    "started_at": "2026-04-30 20:30:07",
    "completed_at": "2026-04-30 20:30:21"
  }
}
```

### Response（找不到）

```json
{
  "success": false,
  "msg": "Job not found"
}
```

| HTTP 狀態 | 原因 |
|---|---|
| 404 | 指定的 job_id 不存在 |

---

## 6. 排程容器健康狀態

### Endpoint

```
GET /api/promotion/batch-audit/scheduler/health
```

排程每次啟動時會更新心跳檔（`writable/scheduler_heartbeat.json`）。此 API 讀取心跳檔並計算距上次執行的秒數，判斷排程容器是否存活。

### Response

```json
{
  "success": true,
  "data": {
    "is_alive": true,
    "last_ping": "2026-04-30 20:38:21",
    "seconds_since_ping": 45,
    "pid": 89,
    "status": "healthy"
  }
}
```

### 回傳欄位說明

| 欄位 | 型別 | 說明 |
|---|---|---|
| `is_alive` | bool | `true` = 排程視為存活 |
| `last_ping` | string\|null | 最後一次心跳時間 |
| `seconds_since_ping` | int\|null | 距上次心跳的秒數 |
| `pid` | int\|null | 最後執行的 Process ID |
| `status` | string | 健康狀態（見下表） |

### status 判斷規則

| status | is_alive | 條件 | 說明 |
|---|---|---|---|
| `healthy` | `true` | 心跳在 120 秒內 | 排程正常運行 |
| `warning` | `true` | 心跳在 121–300 秒內 | 排程可能延遲，需觀察 |
| `dead` | `false` | 超過 300 秒無心跳 | 排程容器已停止，需重啟 |
| `unknown` | `false` | 心跳檔不存在 | 排程從未啟動過 |

> 排程正常情況每 60 秒執行一次，若 `warning` 持續出現請執行 `docker-compose logs --tail=50 scheduler` 確認原因。

---

## 前端輪詢建議

提交審核後，若需即時顯示進度，建議以下輪詢方式：

```javascript
async function pollJobStatus(jobId, interval = 3000) {
  while (true) {
    const res = await fetch(`/api/promotion/batch-audit/jobs/${jobId}`);
    const json = await res.json();
    const status = json.data?.status;

    if (status === 'completed' || status === 'failed') {
      // 處理完成
      break;
    }

    await new Promise(r => setTimeout(r, interval));
  }
}
```

---

## 常見問題排查

| 症狀 | 可能原因 | 處置方式 |
|---|---|---|
| Job 長時間停在 `pending` | scheduler 容器未啟動 | `docker-compose up -d scheduler` |
| 頁面顯示「排程已停止」 | scheduler 容器 crash 或心跳檔無法寫入 | `docker-compose logs scheduler` 查看錯誤 |
| Job 狀態為 `failed` | `batchAuditV3` 執行例外 | 查看 `error_message` 欄位 |
| 派獎未寫入但 Job 為 `completed` | promotion 不符合派獎條件 | 檢查 promotion_items 的 status 及 limit_number |
