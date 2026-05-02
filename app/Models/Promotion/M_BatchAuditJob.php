<?php

namespace App\Models\Promotion;

use CodeIgniter\Model;

/**
 * M_BatchAuditJob
 *
 * 管理 batch_audit_jobs 排程佇列表的 CRUD 操作。
 */
class M_BatchAuditJob extends Model
{
    private const DEFAULT_MAX_RETRIES = 3;
    private const RETRY_DELAY_MINUTES = 5;

    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');
    }

    // -------------------------------------------------------------------------
    // 入列
    // -------------------------------------------------------------------------

    /**
     * 建立一筆 pending 任務，回傳新 job id。
     *
     * @param array  $promotionIds 待審核 promotion id 陣列
     * @param string $auditStatus  目標審核狀態
     * @param string $createdBy    觸發人識別（user_id 或 IP）
     */
    public function enqueue(array $promotionIds, string $auditStatus, string $createdBy = ''): int
    {
        $this->db->table('batch_audit_jobs')->insert([
            'promotion_ids' => json_encode(array_values($promotionIds)),
            'audit_status'  => $auditStatus,
            'status'        => 'pending',
            'total'         => count($promotionIds),
            'processed'     => 0,
            'retry_count'   => 0,
            'max_retries'   => self::DEFAULT_MAX_RETRIES,
            'created_by'    => $createdBy,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    // -------------------------------------------------------------------------
    // 排程端讀取
    // -------------------------------------------------------------------------

    /**
     * 取得最舊一筆 pending 任務並鎖定為 processing（防止重複執行）。
     * 若無 pending 任務，回傳 null。
     */
    public function claimNextPending(): ?array
    {
        $this->markTimedOutProcessingJobs();
        $now = date('Y-m-d H:i:s');

        // 使用交易 + SELECT … FOR UPDATE 確保並發安全
        $this->db->transStart();

        $job = $this->db->table('batch_audit_jobs')
            ->groupStart()
                ->where('status', 'pending')
                ->orGroupStart()
                    ->where('status', 'failed')
                    ->where('retry_count < max_retries', null, false)
                    ->groupStart()
                        ->where('next_retry_at <=', $now)
                        ->orWhere('next_retry_at', null)
                    ->groupEnd()
                ->groupEnd()
            ->groupEnd()
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->limit(1)
            ->get()
            ->getRowArray();

        if (empty($job)) {
            $this->db->transComplete();
            return null;
        }

        $this->db->table('batch_audit_jobs')
            ->where('id', $job['id'])
            ->update([
                'status'        => 'processing',
                'processed'     => 0,
                'failed_ids'    => null,
                'error_message' => null,
                'started_at'    => $now,
                'completed_at'  => null,
                'next_retry_at' => null,
            ]);

        $this->db->transComplete();

        $job['promotion_ids'] = json_decode($job['promotion_ids'], true) ?? [];
        $job['failed_ids']    = $job['failed_ids'] ? (json_decode($job['failed_ids'], true) ?? []) : [];

        return $job;
    }

    // -------------------------------------------------------------------------
    // 排程端更新
    // -------------------------------------------------------------------------

    public function markCompleted(int $jobId, int $processed, array $failedIds = []): void
    {
        if (! empty($failedIds)) {
            $this->markFailed($jobId, '部分 promotion id 處理失敗', $processed, $failedIds);
            return;
        }

        $status = empty($failedIds) ? 'completed' : 'failed';
        $this->db->table('batch_audit_jobs')
            ->where('id', $jobId)
            ->update([
                'status'       => $status,
                'processed'    => $processed,
                'failed_ids'   => empty($failedIds) ? null : json_encode($failedIds),
                'error_message' => null,
                'completed_at' => date('Y-m-d H:i:s'),
                'next_retry_at' => null,
            ]);
    }

    public function markFailed(int $jobId, string $errorMessage, int $processed = 0, array $failedIds = []): void
    {
        $job = $this->db->table('batch_audit_jobs')
            ->where('id', $jobId)
            ->get()
            ->getRowArray();

        if (empty($job)) {
            return;
        }

        $now          = date('Y-m-d H:i:s');
        $retryCount   = (int) ($job['retry_count'] ?? 0) + 1;
        $maxRetries   = max(1, (int) ($job['max_retries'] ?? self::DEFAULT_MAX_RETRIES));
        $willRetry    = $retryCount < $maxRetries;
        $nextRetryAt  = $willRetry ? date('Y-m-d H:i:s', strtotime('+' . self::RETRY_DELAY_MINUTES . ' minutes')) : null;
        $safeMessage  = mb_substr($errorMessage, 0, 2000);
        $retryErrors  = $this->decodeJsonArray($job['retry_errors'] ?? null);

        $retryErrors[] = [
            'attempt'       => $retryCount,
            'message'       => $safeMessage,
            'failed_ids'    => $failedIds,
            'started_at'    => $job['started_at'] ?? null,
            'completed_at'  => $now,
            'next_retry_at' => $nextRetryAt,
            'will_retry'    => $willRetry,
        ];

        $this->db->table('batch_audit_jobs')
            ->where('id', $jobId)
            ->update([
                'status'        => 'failed',
                'processed'     => $processed,
                'retry_count'   => $retryCount,
                'failed_ids'    => empty($failedIds) ? null : json_encode($failedIds),
                'error_message' => $safeMessage,
                'retry_errors'  => json_encode($retryErrors),
                'completed_at'  => $now,
                'next_retry_at' => $nextRetryAt,
            ]);
    }

    // -------------------------------------------------------------------------
    // 查詢 API 用
    // -------------------------------------------------------------------------

    /**
     * 取得單筆任務。
     */
    public function getJob(int $jobId): ?array
    {
        $row = $this->db->table('batch_audit_jobs')
            ->where('id', $jobId)
            ->get()
            ->getRowArray();

        if (empty($row)) {
            return null;
        }

        return $this->decodeRow($row);
    }

    /**
     * 分頁列出任務。
     *
     * @param int    $page
     * @param int    $perPage
     * @param array  $filters  可過濾 status / created_by / date
     * @return array{total: int, data: array}
     */
    public function listJobs(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $buildBase = function () use ($filters) {
            $builder = $this->db->table('batch_audit_jobs');
            if (!empty($filters['status'])) {
                $builder->where('status', $filters['status']);
            }
            if (!empty($filters['created_by'])) {
                $builder->like('created_by', $filters['created_by']);
            }
            if (!empty($filters['date'])) {
                $builder->where('DATE(created_at)', $filters['date']);
            }
            return $builder;
        };

        $total = (int) $buildBase()->countAllResults();
        $rows  = $buildBase()
            ->orderBy('id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        // 補充每筆 job 的第一筆 promotion 的伺服器/帳號/角色資訊
        $firstIdMap = [];
        foreach ($rows as $row) {
            $ids = json_decode($row['promotion_ids'], true) ?? [];
            if (!empty($ids)) {
                $firstIdMap[$row['id']] = (int) $ids[0];
            }
        }

        $promotionInfoMap = [];
        if (!empty($firstIdMap)) {
            $promoRows = $this->db->table('promotions')
                ->join('player', 'player.id = promotions.user_id', 'left')
                ->join('server', 'server.code = promotions.server', 'left')
                ->select('promotions.id, promotions.user_id, promotions.server, server.name as server_name, player.username, player.character_name')
                ->whereIn('promotions.id', array_values($firstIdMap))
                ->get()
                ->getResultArray();

            foreach ($promoRows as $p) {
                $promotionInfoMap[(int) $p['id']] = $p;
            }
        }

        // job_id => promotion info 對照表
        $jobInfoMap = [];
        foreach ($firstIdMap as $jobId => $promoId) {
            $jobInfoMap[$jobId] = $promotionInfoMap[$promoId] ?? null;
        }

        return [
            'total' => $total,
            'data'  => array_map(function ($row) use ($jobInfoMap) {
                $decoded = $this->decodeRow($row);
                $info    = $jobInfoMap[(int) $row['id']] ?? null;
                $decoded['server']         = $info['server']         ?? null;
                $decoded['server_name']    = $info['server_name']    ?? null;
                $decoded['user_id']        = $info['user_id']        ?? null;
                $decoded['username']       = $info['username']       ?? null;
                $decoded['character_name'] = $info['character_name'] ?? null;
                return $decoded;
            }, $rows),
        ];
    }

    /**
     * 統計各 status 筆數。
     */
    public function getStats(): array
    {
        $rows = $this->db->table('batch_audit_jobs')
            ->select('status, COUNT(*) AS cnt')
            ->groupBy('status')
            ->get()
            ->getResultArray();

        $stats = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $stats[$row['status']] = (int) $row['cnt'];
            $stats['total']       += (int) $row['cnt'];
        }

        return $stats;
    }

    // -------------------------------------------------------------------------
    // 內部工具
    // -------------------------------------------------------------------------

    private function decodeRow(array $row): array
    {
        $row['promotion_ids'] = $row['promotion_ids'] ? (json_decode($row['promotion_ids'], true) ?? []) : [];
        $row['failed_ids']    = $row['failed_ids']    ? (json_decode($row['failed_ids'],    true) ?? []) : [];
        $row['retry_errors']  = $this->decodeJsonArray($row['retry_errors'] ?? null);
        return $row;
    }

    private function markTimedOutProcessingJobs(): void
    {
        $staleJobs = $this->db->table('batch_audit_jobs')
            ->where('status', 'processing')
            ->where('started_at <', date('Y-m-d H:i:s', strtotime('-5 minutes')))
            ->get()
            ->getResultArray();

        foreach ($staleJobs as $job) {
            $this->markFailed(
                (int) $job['id'],
                'Timeout: 執行超過 5 分鐘未完成，已自動標記為失敗（可能因 PHP 被強制中止）',
                (int) ($job['processed'] ?? 0)
            );
        }
    }

    private function decodeJsonArray(?string $json): array
    {
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
