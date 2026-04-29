-- ============================================================
-- api_logs 新增 perf_data 欄位
-- 對應 Migration: 2026-04-24-000001_AddPerfDataToApiLogs.php
--
-- 存放 AuditProfiler 各段落耗時分解（JSON）。
-- 可從 GET api/promotion/logs/detail/:id 查看。
-- ============================================================

ALTER TABLE `api_logs`
  ADD COLUMN IF NOT EXISTS `perf_data` TEXT DEFAULT NULL AFTER `duration_ms`;
