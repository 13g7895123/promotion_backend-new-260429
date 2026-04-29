<?php

namespace App\Controllers\Promotion;

use App\Controllers\BaseController;
use App\Models\Promotion\M_BatchAuditJob;

/**
 * BatchAuditJob Controller
 *
 * 查詢批次審核排程佇列的狀態。
 *
 * Routes（位於 api/promotion 群組下）：
 *   GET  batch-audit/jobs           → index()       HTML 監控頁面
 *   GET  batch-audit/jobs/data      → data()        JSON 分頁列表
 *   GET  batch-audit/jobs/stats     → stats()       JSON 統計摘要
 *   GET  batch-audit/jobs/(:num)    → detail($id)   JSON 單筆詳細
 */
class BatchAuditJob extends BaseController
{
    protected M_BatchAuditJob $model;

    public function __construct()
    {
        $this->model = new M_BatchAuditJob();
    }

    // -------------------------------------------------------------------------
    // HTML Dashboard
    // -------------------------------------------------------------------------

    /**
     * GET api/promotion/batch-audit/jobs
     */
    public function index(): string
    {
        return view('Promotion/batch_audit_jobs');
    }

    // -------------------------------------------------------------------------
    // JSON Endpoints
    // -------------------------------------------------------------------------

    /**
     * GET api/promotion/batch-audit/jobs/data
     *
     * Query params：
     *   page        int     頁碼（預設 1）
     *   per_page    int     每頁筆數（預設 20，上限 100）
     *   status      string  pending | processing | completed | failed
     *   created_by  string  觸發人關鍵字（模糊）
     *   date        string  YYYY-MM-DD
     */
    public function data()
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(5, (int) ($this->request->getGet('per_page') ?? 20)));

        $filters = array_filter([
            'status'     => $this->request->getGet('status'),
            'created_by' => $this->request->getGet('created_by'),
            'date'       => $this->request->getGet('date'),
        ]);

        $result = $this->model->listJobs($page, $perPage, $filters);

        return $this->response->setJSON([
            'success'    => true,
            'page'       => $page,
            'per_page'   => $perPage,
            'total'      => $result['total'],
            'total_pages'=> (int) ceil($result['total'] / $perPage),
            'data'       => $result['data'],
        ]);
    }

    /**
     * GET api/promotion/batch-audit/jobs/stats
     */
    public function stats()
    {
        return $this->response->setJSON([
            'success' => true,
            'data'    => $this->model->getStats(),
        ]);
    }

    /**
     * GET api/promotion/batch-audit/jobs/:id
     */
    public function detail(int $id)
    {
        $job = $this->model->getJob($id);

        if ($job === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['success' => false, 'msg' => 'Job not found']);
        }

        return $this->response->setJSON([
            'success' => true,
            'data'    => $job,
        ]);
    }
}
