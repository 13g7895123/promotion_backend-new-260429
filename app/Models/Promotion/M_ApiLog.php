<?php

namespace App\Models\Promotion;

use CodeIgniter\Model;

class M_ApiLog extends Model
{
    protected $db;
    protected $table      = 'api_logs';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
        $this->db = \Config\Database::connect('promotion');
    }

    /**
     * 寫入 API log
     */
    public function writeLog(array $data): int
    {
        $this->db->table('api_logs')->insert($data);
        return (int) $this->db->insertId();
    }

    /**
     * 查詢 log 清單（含篩選、分頁）
     */
    public function getLogs(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $builder = $this->db->table('api_logs');

        if (!empty($filters['method'])) {
            $builder->where('method', strtoupper($filters['method']));
        }

        if (!empty($filters['endpoint'])) {
            $builder->like('endpoint', $filters['endpoint']);
        }

        if (!empty($filters['controller'])) {
            $builder->like('controller', $filters['controller']);
        }

        if (!empty($filters['action'])) {
            $builder->like('action', $filters['action']);
        }

        if (isset($filters['is_success']) && $filters['is_success'] !== '') {
            $builder->where('is_success', (int) $filters['is_success']);
        }

        if (!empty($filters['date_from'])) {
            $builder->where('created_at >=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $builder->where('created_at <=', $filters['date_to']);
        }

        if (!empty($filters['ip_address'])) {
            $builder->where('ip_address', $filters['ip_address']);
        }

        $total   = $builder->countAllResults(false);
        $offset  = ($page - 1) * $perPage;
        $records = $builder->orderBy('created_at', 'DESC')
                           ->limit($perPage, $offset)
                           ->get()
                           ->getResultArray();

        return [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'data'     => $records,
        ];
    }

    /**
     * 取得單筆 log
     */
    public function getLog(int $id): array
    {
        return $this->db->table('api_logs')
            ->where('id', $id)
            ->get()
            ->getRowArray() ?? [];
    }

    /**
     * 取得統計資料
     */
    public function getStats(): array
    {
        $total   = (int) $this->db->table('api_logs')->countAll();
        $success = (int) $this->db->table('api_logs')->where('is_success', 1)->countAllResults();
        $fail    = $total - $success;

        $avgDuration = $this->db->table('api_logs')
            ->selectAvg('duration_ms')
            ->get()
            ->getRowArray();

        $endpointStats = $this->db->table('api_logs')
            ->select('endpoint, controller, action, COUNT(*) as count, SUM(is_success) as success_count, AVG(duration_ms) as avg_ms')
            ->groupBy('endpoint, controller, action')
            ->orderBy('count', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();

        return [
            'total'        => $total,
            'success'      => $success,
            'fail'         => $fail,
            'avg_duration' => round((float) ($avgDuration['duration_ms'] ?? 0), 2),
            'endpoints'    => $endpointStats,
        ];
    }

    /**
     * 刪除指定天數前的舊 log
     */
    public function cleanOldLogs(int $days = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $this->db->table('api_logs')->where('created_at <', $cutoff)->delete();
        return $this->db->affectedRows();
    }
}
