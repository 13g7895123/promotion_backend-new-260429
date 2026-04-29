-- ============================================================
-- api_logs 完整結構（含 perf_data + last_step）
-- 版本：2026-04-24
--
-- 欄位說明：
--   id            : 自增主鍵
--   method        : HTTP 方法 (GET / POST / PUT / DELETE …)
--   uri           : 請求路徑
--   controller    : CI4 router 解析到的 Controller 類別名
--   action        : CI4 router 解析到的 Method 名稱
--   request_data  : 請求 body（截斷 2000 字元）
--   ip_address    : 來源 IP
--   status        : pending（進行中/逾時）/ completed（正常回應）/ error（HTTP ≥ 400）
--   response_code : HTTP 狀態碼
--   triggered_at  : 請求進入時間
--   completed_at  : 回應送出時間（504 逾時時為 NULL）
--   duration_ms   : 整體耗時毫秒（504 逾時時為 NULL）
--   perf_data     : AuditProfiler 各段落耗時 JSON
--                   - 正常完成 / Exception(500) 時由 ApiLogFilter::after() 寫入
--                   - 504 逾時時為 NULL（但可靠 last_step 判斷卡點）
--   last_step     : AuditProfiler 最後成功執行的 mark 名稱
--                   - 每次 mark() 立即寫入 DB（504 逾時後仍可查）
--                   - 可精確定位「進程被 kill 前走到哪個段落」
--
-- 使用場景：
--   GET  api/promotion/logs             : 儀表板（status=pending 即逾時 API）
--   GET  api/promotion/logs/detail/:id  : 查 perf_data + last_step 定位慢點
--
-- 目標資料庫：promotion
-- ============================================================


-- ════════════════════════════════════════════════════════════
-- 情境 A：資料表尚未存在，全新建立
-- ════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `api_logs` (
  `id`            INT(11) UNSIGNED     NOT NULL AUTO_INCREMENT,
  `method`        VARCHAR(10)          NOT NULL,
  `uri`           VARCHAR(500)         NOT NULL,
  `controller`    VARCHAR(200)         DEFAULT NULL,
  `action`        VARCHAR(100)         DEFAULT NULL,
  `request_data`  TEXT                 DEFAULT NULL,
  `ip_address`    VARCHAR(45)          DEFAULT NULL,
  `status`        ENUM('pending','completed','error') NOT NULL DEFAULT 'pending',
  `response_code` SMALLINT UNSIGNED    DEFAULT NULL,
  `triggered_at`  DATETIME             NOT NULL,
  `completed_at`  DATETIME             DEFAULT NULL,
  `duration_ms`   INT UNSIGNED         DEFAULT NULL,
  `perf_data`     TEXT                 DEFAULT NULL COMMENT 'AuditProfiler 各段落耗時 JSON；正常/Error 時由 after() 寫入',
  `last_step`     VARCHAR(100)         DEFAULT NULL COMMENT '最後一個 AuditProfiler::mark() 名稱；504 進程被 kill 後仍可查',
  PRIMARY KEY (`id`),
  KEY `idx_triggered_at` (`triggered_at`),
  KEY `idx_status`       (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ════════════════════════════════════════════════════════════
-- 情境 B：資料表已存在（舊版，無 perf_data / last_step）
--         逐欄補上，已有欄位的 ALTER 會報錯，可忽略
-- ════════════════════════════════════════════════════════════

-- 補 perf_data
-- 若欄位已存在會報 "Duplicate column name"，忽略該錯誤繼續執行下一行即可
ALTER TABLE `api_logs`
  ADD COLUMN `perf_data` TEXT DEFAULT NULL
    COMMENT 'AuditProfiler 各段落耗時 JSON；正常/Error 時由 after() 寫入'
    AFTER `duration_ms`;

-- 補 last_step
-- 若欄位已存在會報 "Duplicate column name"，忽略該錯誤繼續執行下一行即可
ALTER TABLE `api_logs`
  ADD COLUMN `last_step` VARCHAR(100) DEFAULT NULL
    COMMENT '最後一個 AuditProfiler::mark() 名稱；504 進程被 kill 後仍可查'
    AFTER `perf_data`;

-- 補索引
-- 若索引已存在會報 "Duplicate key name"，忽略該錯誤即可
ALTER TABLE `api_logs`
  ADD INDEX `idx_triggered_at` (`triggered_at`);
ALTER TABLE `api_logs`
  ADD INDEX `idx_status` (`status`);


-- ════════════════════════════════════════════════════════════
-- 驗證查詢（執行後確認欄位是否到位）
-- ════════════════════════════════════════════════════════════

-- SHOW COLUMNS FROM `api_logs`;
