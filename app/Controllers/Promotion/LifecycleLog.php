<?php

namespace App\Controllers\Promotion;

use App\Controllers\BaseController;

/**
 * LifecycleLog Controller
 *
 * 推廣生命週期完整 Log 查詢
 * 涵蓋：建立推廣 → 審核 → 派發獎勵 的完整時間軸
 *
 * Routes:
 *   GET  api/promotion/lifecycle           → 取得生命週期時間軸資料
 *   GET  api/promotion/lifecycle/summary   → 取得彙整統計資料
 *   GET  api/promotion/lifecycle/audit-events → 取得審核事件清單
 */
class LifecycleLog extends BaseController
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');
    }

    /**
     * 回傳 HTML 查看頁面
     */
    public function index(): string
    {
        return view('Promotion/lifecycle_log');
    }

    /**
     * 取得推廣生命週期時間軸（JSON）
     *
     * Query Params:
     *   date_from  string  Y-m-d  開始日期（預設 7 天前）
     *   date_to    string  Y-m-d  結束日期（預設今天）
     *   server     string         伺服器代碼篩選（空=全部）
     *   page       int            頁碼（預設 1）
     *   per_page   int            每頁筆數（預設 10，最大 60）
     */
    public function data()
    {
        $get = $this->request->getGet();

        $dateTo   = $get['date_to']   ?? date('Y-m-d');
        $dateFrom = $get['date_from'] ?? date('Y-m-d', strtotime('-6 days'));
        $server   = $get['server']    ?? '';
        $page     = max(1, (int) ($get['page']     ?? 1));
        $perPage  = min(60, max(5, (int) ($get['per_page'] ?? 10)));

        // ──────────────────────────────────────────────
        // 1. 取得日期區間內所有推廣（含玩家 & 伺服器資訊）
        // ──────────────────────────────────────────────
        $builder = $this->db->table('promotions p')
            ->join('player pl', 'pl.id = p.user_id', 'left')
            ->join('server s',  's.code = p.server', 'left')
            ->select('
                p.id            AS promotion_id,
                p.user_id,
                p.server,
                p.status        AS promotion_status,
                p.created_at    AS created_at,
                p.updated_at    AS audited_at,
                pl.username,
                pl.character_name,
                s.name          AS server_name,
                s.cycle,
                s.limit_number
            ')
            ->where('DATE(p.created_at) >=', $dateFrom)
            ->where('DATE(p.created_at) <=', $dateTo);

        if (!empty($server)) {
            $builder->where('p.server', $server);
        }

        $total      = $builder->countAllResults(false);
        $offset     = ($page - 1) * $perPage;
        $promotions = $builder
            ->orderBy('p.created_at', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        if (empty($promotions)) {
            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'total'    => 0,
                    'page'     => $page,
                    'per_page' => $perPage,
                    'summary'  => $this->buildEmptySummary(),
                    'timeline' => [],
                ],
            ]);
        }

        $promotionIds = array_column($promotions, 'promotion_id');

        // ──────────────────────────────────────────────
        // 2. 批次查詢推廣細項
        // ──────────────────────────────────────────────
        $items = $this->db->table('promotion_items')
            ->whereIn('promotion_id', $promotionIds)
            ->orderBy('created_at', 'ASC')
            ->get()
            ->getResultArray();

        $itemsMap = [];
        foreach ($items as $item) {
            $itemsMap[$item['promotion_id']][] = $item;
        }

        // ──────────────────────────────────────────────
        // 3. 批次查詢獎勵發送記錄
        // ──────────────────────────────────────────────
        $rewards = $this->db->table('reward')
            ->whereIn('promotion_id', $promotionIds)
            ->get()
            ->getResultArray();

        $rewardsMap = [];
        foreach ($rewards as $reward) {
            $rewardsMap[$reward['promotion_id']] = $reward;
        }

        // ──────────────────────────────────────────────
        // 4. 組合每筆推廣的完整生命週期資訊
        // ──────────────────────────────────────────────
        $enriched = [];
        foreach ($promotions as $p) {
            $pid = $p['promotion_id'];

            $pItems  = $itemsMap[$pid]  ?? [];
            $pReward = $rewardsMap[$pid] ?? null;

            // 審核完成時間：取 promotion_items.updated_at 最新值（排除 standby 者）
            $auditedAt = null;
            foreach ($pItems as $item) {
                if ($item['status'] !== 'standby') {
                    if ($auditedAt === null || $item['updated_at'] > $auditedAt) {
                        $auditedAt = $item['updated_at'];
                    }
                }
            }

            // 審核統計
            $itemTotal   = count($pItems);
            $itemPassed  = count(array_filter($pItems, fn($i) => $i['status'] === 'success'));
            $itemFailed  = count(array_filter($pItems, fn($i) => $i['status'] === 'failed'));
            $itemPending = count(array_filter($pItems, fn($i) => $i['status'] === 'standby'));

            // 獎勵解析
            if ($pReward) {
                $rewardDecoded = json_decode($pReward['reward'] ?? '{}', true);
                $pReward['reward_detail'] = $rewardDecoded;
            }

            $enriched[] = [
                'promotion_id'        => $pid,
                'username'            => $p['username'],
                'character_name'      => $p['character_name'],
                'server'              => $p['server'],
                'server_name'         => $p['server_name'],
                'cycle'               => $p['cycle'],
                'limit_number'        => $p['limit_number'],
                'promotion_status'    => $p['promotion_status'],
                'created_at'          => $p['created_at'],
                'audited_at'          => $auditedAt,
                'reward_at'           => $pReward['created_at'] ?? null,
                'items_total'         => $itemTotal,
                'items_passed'        => $itemPassed,
                'items_failed'        => $itemFailed,
                'items_pending'       => $itemPending,
                'items'               => $pItems,
                'reward'              => $pReward,
                // 生命週期階段標記
                'lifecycle_stage'     => $this->resolveStage($p['promotion_status'], $pReward),
                // 耗時（分鐘）
                'creation_to_audit_minutes' => $this->minutesDiff($p['created_at'], $auditedAt),
                'audit_to_reward_minutes'   => $this->minutesDiff($auditedAt, $pReward['created_at'] ?? null),
            ];
        }

        // ──────────────────────────────────────────────
        // 5. 依日期分組為時間軸
        // ──────────────────────────────────────────────
        $timeline = [];
        foreach ($enriched as $row) {
            $date = substr($row['created_at'], 0, 10);
            if (!isset($timeline[$date])) {
                $timeline[$date] = [
                    'date'          => $date,
                    'created_count' => 0,
                    'passed_count'  => 0,
                    'failed_count'  => 0,
                    'pending_count' => 0,
                    'reward_count'  => 0,
                    'promotions'    => [],
                ];
            }
            $timeline[$date]['created_count']++;
            if ($row['promotion_status'] === 'success') $timeline[$date]['passed_count']++;
            if ($row['promotion_status'] === 'failed')  $timeline[$date]['failed_count']++;
            if ($row['promotion_status'] === 'standby') $timeline[$date]['pending_count']++;
            if ($row['reward'])                         $timeline[$date]['reward_count']++;
            $timeline[$date]['promotions'][] = $row;
        }

        // 日期由新至舊排序
        krsort($timeline);
        $timelineList = array_values($timeline);

        // ──────────────────────────────────────────────
        // 6. 全區間彙整統計（不受分頁影響）
        // ──────────────────────────────────────────────
        $summary = $this->buildSummary($dateFrom, $dateTo, $server);

        return $this->response->setJSON([
            'success' => true,
            'data'    => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'summary'  => $summary,
                'timeline' => $timelineList,
            ],
        ]);
    }

    /**
     * 取得彙整統計（單一端點，供儀表板使用）
     *
     * Query Params: date_from, date_to, server
     */
    public function summary()
    {
        $get      = $this->request->getGet();
        $dateTo   = $get['date_to']   ?? date('Y-m-d');
        $dateFrom = $get['date_from'] ?? date('Y-m-d', strtotime('-6 days'));
        $server   = $get['server']    ?? '';

        $summary = $this->buildSummary($dateFrom, $dateTo, $server);

        return $this->response->setJSON([
            'success' => true,
            'data'    => $summary,
        ]);
    }

    /**
     * 取得批次審核事件清單
     * 以「批次審核時間點」為維度，顯示每次審核了哪些推廣
     *
     * Query Params: date_from, date_to, server
     */
    public function auditEvents()
    {
        $get      = $this->request->getGet();
        $dateTo   = $get['date_to']   ?? date('Y-m-d');
        $dateFrom = $get['date_from'] ?? date('Y-m-d', strtotime('-6 days'));
        $server   = $get['server']    ?? '';

        // 取得非 standby 的 promotion_items（代表已審核）
        // 以 updated_at 秒數相近者歸為同一批次
        $builder = $this->db->table('promotion_items pi')
            ->join('promotions p', 'p.id = pi.promotion_id', 'left')
            ->join('player pl',    'pl.id = p.user_id', 'left')
            ->join('server s',     's.code = p.server', 'left')
            ->select('
                pi.id             AS item_id,
                pi.promotion_id,
                pi.type,
                pi.status         AS item_status,
                pi.updated_at     AS audited_at,
                p.status          AS promotion_status,
                p.server,
                p.created_at      AS promotion_created_at,
                pl.username,
                pl.character_name,
                s.name            AS server_name
            ')
            ->where('pi.status !=', 'standby')
            ->where('DATE(pi.updated_at) >=', $dateFrom)
            ->where('DATE(pi.updated_at) <=', $dateTo);

        if (!empty($server)) {
            $builder->where('p.server', $server);
        }

        $rows = $builder
            ->orderBy('pi.updated_at', 'DESC')
            ->get()
            ->getResultArray();

        // 以「分鐘」為單位分組（同一分鐘內被審核的視為同一批次）
        $batches = [];
        foreach ($rows as $row) {
            // 取到分鐘精度作為 batch key
            $batchKey = substr($row['audited_at'], 0, 16); // "Y-m-d H:i"
            if (!isset($batches[$batchKey])) {
                $batches[$batchKey] = [
                    'batch_time'    => $batchKey,
                    'total'         => 0,
                    'passed'        => 0,
                    'failed'        => 0,
                    'promotions'    => [],
                ];
            }
            $batches[$batchKey]['total']++;
            if ($row['item_status'] === 'success') $batches[$batchKey]['passed']++;
            if ($row['item_status'] === 'failed')  $batches[$batchKey]['failed']++;

            // 同 promotion_id 不重複列入
            $pid = $row['promotion_id'];
            if (!isset($batches[$batchKey]['promotions'][$pid])) {
                $batches[$batchKey]['promotions'][$pid] = [
                    'promotion_id'         => $pid,
                    'promotion_status'     => $row['promotion_status'],
                    'server'               => $row['server'],
                    'server_name'          => $row['server_name'],
                    'username'             => $row['username'],
                    'character_name'       => $row['character_name'],
                    'promotion_created_at' => $row['promotion_created_at'],
                    'audited_at'           => $row['audited_at'],
                ];
            }
        }

        // 整理 promotions 為索引陣列
        foreach ($batches as &$batch) {
            $batch['promotions'] = array_values($batch['promotions']);
            $batch['unique_promotions'] = count($batch['promotions']);
        }
        unset($batch);

        krsort($batches);

        return $this->response->setJSON([
            'success' => true,
            'data'    => array_values($batches),
        ]);
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    private function buildSummary(string $dateFrom, string $dateTo, string $server): array
    {
        $builder = $this->db->table('promotions p')
            ->where('DATE(p.created_at) >=', $dateFrom)
            ->where('DATE(p.created_at) <=', $dateTo);

        if (!empty($server)) {
            $builder->where('p.server', $server);
        }

        $totalCreated = $builder->countAllResults(false);
        $totalPassed  = (clone $builder)->where('p.status', 'success')->countAllResults(false);
        $totalFailed  = (clone $builder)->where('p.status', 'failed')->countAllResults(false);
        $totalPending = (clone $builder)->where('p.status', 'standby')->countAllResults(false);

        // 獎勵統計（有推廣對應的 reward 紀錄）
        $rewardBuilder = $this->db->table('reward r')
            ->join('promotions p', 'p.id = r.promotion_id', 'left')
            ->where('DATE(p.created_at) >=', $dateFrom)
            ->where('DATE(p.created_at) <=', $dateTo);

        if (!empty($server)) {
            $rewardBuilder->where('r.server_code', $server);
        }

        $totalRewards = $rewardBuilder->countAllResults();

        // 平均從建立到審核完成耗時（分鐘）
        $avgBuilder = $this->db->table('promotions p')
            ->join('promotion_items pi', 'pi.promotion_id = p.id', 'left')
            ->select('AVG(TIMESTAMPDIFF(MINUTE, p.created_at, pi.updated_at)) AS avg_minutes')
            ->where('p.status !=', 'standby')
            ->where('pi.status !=', 'standby')
            ->where('DATE(p.created_at) >=', $dateFrom)
            ->where('DATE(p.created_at) <=', $dateTo);

        if (!empty($server)) {
            $avgBuilder->where('p.server', $server);
        }

        $avgRow = $avgBuilder->get()->getRowArray();
        $avgAuditMinutes = $avgRow['avg_minutes'] !== null ? round((float) $avgRow['avg_minutes'], 1) : null;

        return [
            'date_from'           => $dateFrom,
            'date_to'             => $dateTo,
            'total_created'       => $totalCreated,
            'total_passed'        => $totalPassed,
            'total_failed'        => $totalFailed,
            'total_pending'       => $totalPending,
            'total_rewards'       => $totalRewards,
            'pass_rate'           => $totalCreated > 0 ? round($totalPassed / $totalCreated * 100, 1) : 0,
            'reward_rate'         => $totalPassed > 0 ? round($totalRewards / $totalPassed * 100, 1) : 0,
            'avg_audit_minutes'   => $avgAuditMinutes,
        ];
    }

    private function buildEmptySummary(): array
    {
        return [
            'total_created'     => 0,
            'total_passed'      => 0,
            'total_failed'      => 0,
            'total_pending'     => 0,
            'total_rewards'     => 0,
            'pass_rate'         => 0,
            'reward_rate'       => 0,
            'avg_audit_minutes' => null,
        ];
    }

    private function resolveStage(string $status, ?array $reward): string
    {
        if ($status === 'standby') return 'pending';       // 等待審核
        if ($status === 'failed')  return 'failed';        // 審核失敗
        if ($status === 'success' && empty($reward)) return 'audited'; // 已審核，尚未派獎
        return 'rewarded';                                 // 已派獎
    }

    private function minutesDiff(?string $from, ?string $to): ?int
    {
        if (!$from || !$to) return null;
        $diff = (strtotime($to) - strtotime($from)) / 60;
        return $diff >= 0 ? (int) $diff : null;
    }
}
