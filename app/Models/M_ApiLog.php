<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * M_ApiLog
 *
 * 操作 api_logs 資料表（位於本地 default/jiachu 資料庫）。
 * 提供 startLog / finishLog 供 Filter 呼叫，
 * 以及 getLogs / getStats / getDetail / cleanOld 供 Controller 呼叫。
 */
class M_ApiLog extends Model
{
    protected $db;
    protected $table = 'api_logs';

    public function __construct()
    {
        parent::__construct();
        $this->db = \Config\Database::connect('promotion');
    }

    /**
     * 插入一筆 pending log，回傳新插入的 id。
     */
    public function startLog(array $data): int
    {
        $this->db->table($this->table)->insert($data);
        return (int) $this->db->insertID();
    }

    /**
     * 將指定 id 的 log 更新為完成狀態。
     */
    public function finishLog(int $id, array $data): void
    {
        $this->db->table($this->table)
                 ->where('id', $id)
                 ->update($data);
    }

    /**
     * 分頁取得 log 列表，支援 status / method / uri / date 篩選。
     *
     * @return array{total: int, data: array}
     */
    public function getLogs(int $page = 1, int $perPage = 50, array $filters = []): array
    {
        $builder = $this->db->table($this->table);

        if (!empty($filters['status'])) {
            $builder->where('status', $filters['status']);
        }
        if (!empty($filters['method'])) {
            $builder->where('method', strtoupper($filters['method']));
        }
        if (!empty($filters['uri'])) {
            $builder->like('uri', $filters['uri']);
        }
        if (!empty($filters['date'])) {
            $builder->where('DATE(triggered_at)', $filters['date']);
        }

        $total  = (int) $builder->countAllResults(false);
        $offset = ($page - 1) * $perPage;

        $rows = $builder
            ->orderBy('id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return ['total' => $total, 'data' => $rows];
    }

    /**
     * 回傳統計摘要。
     *
     * @return array{total:int, completed:int, pending:int, error:int, avg_ms:float, max_ms:int}
     */
    public function getStats(): array
    {
        $db = $this->db;

        $total     = (int) $db->table($this->table)->countAllResults();
        $completed = (int) $db->table($this->table)->where('status', 'completed')->countAllResults();
        $pending   = (int) $db->table($this->table)->where('status', 'pending')->countAllResults();
        $error     = (int) $db->table($this->table)->where('status', 'error')->countAllResults();

        $avgRow = $db->query(
            "SELECT AVG(duration_ms) AS avg_ms, MAX(duration_ms) AS max_ms FROM {$this->table} WHERE status = 'completed'"
        )->getRowArray();

        return [
            'total'     => $total,
            'completed' => $completed,
            'pending'   => $pending,
            'error'     => $error,
            'avg_ms'    => $avgRow ? (float) round((float) $avgRow['avg_ms']) : 0,
            'max_ms'    => $avgRow ? (int) $avgRow['max_ms'] : 0,
        ];
    }

    /**
     * 取得單筆 log 詳細資料（含 request_data）。
     */
    public function getDetail(int $id): ?array
    {
        $row = $this->db->table($this->table)->where('id', $id)->get()->getRowArray();
        return $row ?: null;
    }

    /**
     * 刪除 N 天前的舊 log，回傳刪除筆數。
     */
    public function cleanOld(int $days = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $this->db->table($this->table)
                 ->where('triggered_at <', $cutoff)
                 ->delete();
        return $this->db->affectedRows();
    }
}
