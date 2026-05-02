<?php

namespace App\Controllers\Promotion;

use App\Controllers\BaseController;

/**
 * PromotionTracker Controller
 *
 * 推廣完整狀態追蹤：查詢每筆推廣的完整生命週期資料
 * 涵蓋：promotions、promotion_items、batch_audit_jobs、reward、reward_log、reissuance_reward
 *
 * Routes（位於 api/promotion 群組下）：
 *   GET  tracker                → HTML 查詢頁面
 *   GET  tracker/search         → JSON 搜尋推廣列表
 *   GET  tracker/detail/(:num)  → JSON 單筆完整生命週期資料
 */
class PromotionTracker extends BaseController
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');
    }

    public function index(): string
    {
        return view('Promotion/promotion_tracker');
    }

    /**
     * GET api/promotion/tracker/search
     *
     * Query Params:
     *   q          string  關鍵字（username / promotion_id 數字時精確比對）
     *   server     string  伺服器代碼
     *   status     string  standby | success | failed
     *   date_from  string  Y-m-d
     *   date_to    string  Y-m-d
     *   page       int
     *   per_page   int
     */
    public function search()
    {
        $get      = $this->request->getGet();
        $q        = trim($get['q']        ?? '');
        $server   = $get['server']   ?? '';
        $status   = $get['status']   ?? '';
        $dateFrom = $get['date_from'] ?? '';
        $dateTo   = $get['date_to']   ?? '';
        $page     = max(1, (int) ($get['page']     ?? 1));
        $perPage  = min(100, max(10, (int) ($get['per_page'] ?? 20)));

        $buildBase = function () use ($q, $server, $status, $dateFrom, $dateTo) {
            $builder = $this->db->table('promotions p')
                ->join('player pl', 'pl.id = p.user_id', 'left')
                ->join('server s',  's.code = p.server', 'left')
                ->select('p.id AS promotion_id, p.user_id, p.server, p.status, p.created_at, p.updated_at, pl.username, pl.character_name, s.name AS server_name');

            if ($q !== '') {
                if (is_numeric($q)) {
                    $builder->where('p.id', (int) $q);
                } else {
                    $escaped = addcslashes($q, '\\%_');
                    $builder->groupStart()
                        ->like('pl.username', $escaped, 'both', null, false)
                        ->orLike('pl.character_name', $escaped, 'both', null, false)
                        ->groupEnd();
                }
            }
            if (!empty($server))   $builder->where('p.server', $server);
            if (!empty($status))   $builder->where('p.status', $status);
            if (!empty($dateFrom)) $builder->where('DATE(p.created_at) >=', $dateFrom);
            if (!empty($dateTo))   $builder->where('DATE(p.created_at) <=', $dateTo);

            return $builder;
        };

        $total = (int) $buildBase()->countAllResults(false);
        $rows  = $buildBase()
            ->orderBy('p.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        if (!empty($rows)) {
            $ids = array_column($rows, 'promotion_id');

            // 細項狀態統計
            $itemStats = $this->db->table('promotion_items')
                ->select('promotion_id, status, COUNT(*) AS cnt')
                ->whereIn('promotion_id', $ids)
                ->groupBy(['promotion_id', 'status'])
                ->get()->getResultArray();

            $itemsMap = [];
            foreach ($itemStats as $item) {
                $itemsMap[$item['promotion_id']][$item['status']] = (int) $item['cnt'];
            }

            // 是否有 reward 記錄
            $rewardRows = $this->db->table('reward')
                ->select('promotion_id')
                ->whereIn('promotion_id', $ids)
                ->get()->getResultArray();
            $rewardSet = array_flip(array_column($rewardRows, 'promotion_id'));

            // 取每個 promotion_id 最新的 job 資訊
            // json_encode() 確保傳入的是 JSON 字串型別 "16065"，與資料庫存的 ["16065"] 型別一致
            $jobMap = [];
            foreach ($ids as $pid) {
                $job = $this->db->query(
                    'SELECT status AS job_status, error_message, created_at AS job_created_at,
                            started_at AS job_started_at, completed_at AS job_completed_at,
                            retry_count, max_retries, next_retry_at, retry_errors
                     FROM batch_audit_jobs
                     WHERE JSON_CONTAINS(promotion_ids, ?, "$")
                     ORDER BY id DESC LIMIT 1',
                    [json_encode((string) $pid)]
                )->getRowArray();
                if ($job) {
                    $job['retry_errors'] = $job['retry_errors'] ? (json_decode($job['retry_errors'], true) ?? []) : [];
                    $jobMap[$pid] = $job;
                }
            }

            foreach ($rows as &$row) {
                $pid = $row['promotion_id'];
                $row['items_standby'] = $itemsMap[$pid]['standby'] ?? 0;
                $row['items_success'] = $itemsMap[$pid]['success'] ?? 0;
                $row['items_failed']  = $itemsMap[$pid]['failed']  ?? 0;
                $row['items_total']   = $row['items_standby'] + $row['items_success'] + $row['items_failed'];
                $row['has_reward']    = isset($rewardSet[$pid]);
                $row['latest_job']    = $jobMap[$pid] ?? null;
            }
            unset($row);
        }

        return $this->response->setJSON([
            'success'     => true,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
            'data'        => $rows,
        ]);
    }

    /**
     * GET api/promotion/tracker/detail/:id
     *
     * 回傳單筆推廣的完整生命週期資料：
     *   promotion    推廣主資料（含玩家、伺服器資訊）
     *   items        promotion_items 細項列表
     *   audit_jobs   包含此 promotion_id 的 batch_audit_jobs（含 JSON 解碼）
     *   rewards      reward 發送記錄（含 JSON 解碼）
     *   reward_logs  reward_log 詳細記錄（含 JSON 解碼）
     *   reissuance   reissuance_reward 補發記錄（含 JSON 解碼）
     */
    public function detail(int $id)
    {
        // 1. 推廣主資料
        $promotion = $this->db->table('promotions p')
            ->join('player pl', 'pl.id = p.user_id', 'left')
            ->join('server s',  's.code = p.server',  'left')
            ->select('p.id AS promotion_id, p.user_id, p.server, p.status, p.created_at, p.updated_at, pl.username, pl.character_name, s.name AS server_name, s.cycle, s.limit_number')
            ->where('p.id', $id)
            ->get()->getRowArray();

        if (empty($promotion)) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'msg' => 'Promotion not found']);
        }

        // 2. 推廣細項
        $items = $this->db->table('promotion_items')
            ->where('promotion_id', $id)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        // 3. 關聯的 batch_audit_jobs（搜尋包含此 promotion_id 的 JSON 陣列）
        // json_encode() 確保傳入的是 JSON 字串型別 "16065"，與資料庫存的 ["16065"] 型別一致
        $auditJobs = $this->db->query(
            'SELECT * FROM batch_audit_jobs WHERE JSON_CONTAINS(promotion_ids, ?, "$") ORDER BY id DESC',
            [json_encode((string) $id)]
        )->getResultArray();

        foreach ($auditJobs as &$job) {
            $job['promotion_ids'] = json_decode($job['promotion_ids'], true) ?? [];
            $job['failed_ids']    = $job['failed_ids'] ? (json_decode($job['failed_ids'], true) ?? []) : [];
            $job['retry_errors']  = ! empty($job['retry_errors']) ? (json_decode($job['retry_errors'], true) ?? []) : [];
        }
        unset($job);

        // 4. reward 發送記錄
        $rewards = $this->db->table('reward')
            ->where('promotion_id', $id)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        foreach ($rewards as &$r) {
            $r['reward_decoded'] = json_decode($r['reward'], true);
        }
        unset($r);

        // 5. reward_log 詳細記錄
        $rewardLogs = $this->db->table('reward_log')
            ->where('promotion_id', $id)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        foreach ($rewardLogs as &$rl) {
            $rl['player_data_decoded'] = json_decode($rl['player_data'], true);
            $rl['insert_data_decoded'] = json_decode($rl['insert_data'], true);
        }
        unset($rl);

        // 6. reissuance_reward 補發記錄
        $reissuance = $this->db->table('reissuance_reward')
            ->where('promotion_id', $id)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        foreach ($reissuance as &$re) {
            $re['reward_decoded'] = json_decode($re['reward'], true);
        }
        unset($re);

        return $this->response->setJSON([
            'success' => true,
            'data'    => [
                'promotion'   => $promotion,
                'items'       => $items,
                'audit_jobs'  => $auditJobs,
                'rewards'     => $rewards,
                'reward_logs' => $rewardLogs,
                'reissuance'  => $reissuance,
            ],
        ]);
    }
}
