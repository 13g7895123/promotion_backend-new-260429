<?php

namespace App\Controllers\Promotion;

use App\Controllers\BaseController;
use App\Models\M_ApiLog;

/**
 * ApiLog Controller
 *
 * 路由（位於 api/promotion 群組下）：
 *   GET  logs                → index()      HTML 儀表板
 *   GET  logs/data           → data()       JSON 分頁列表
 *   GET  logs/stats          → stats()      JSON 統計
 *   GET  logs/detail/:id     → detail($id)  JSON 單筆詳細
 *   POST logs/clean          → clean()      刪除舊 log
 */
class ApiLog extends BaseController
{
    protected M_ApiLog $model;

    public function __construct()
    {
        $this->model = new M_ApiLog();
    }

    // -------------------------------------------------------------------------
    // HTML Dashboard
    // -------------------------------------------------------------------------

    /**
     * GET api/promotion/logs
     * 回傳 API Log 儀表板 HTML 頁面。
     */
    public function index()
    {
        return view('api_log');
    }

    // -------------------------------------------------------------------------
    // JSON Endpoints（供 Dashboard 的前端 JS 呼叫）
    // -------------------------------------------------------------------------

    /**
     * GET api/promotion/logs/data
     *
     * Query params：
     *   page      int    頁碼（預設 1）
     *   per_page  int    每頁筆數（預設 50，上限 100）
     *   status    string pending | completed | error
     *   method    string GET | POST | PUT | DELETE …
     *   uri       string URI 關鍵字（模糊比對）
     *   date      string YYYY-MM-DD
     */
    public function data()
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(10, (int) ($this->request->getGet('per_page') ?? 50)));

        $filters = array_filter([
            'status' => $this->request->getGet('status'),
            'method' => $this->request->getGet('method'),
            'uri'    => $this->request->getGet('uri'),
            'date'   => $this->request->getGet('date'),
        ]);

        $result = $this->model->getLogs($page, $perPage, $filters);

        return $this->response->setJSON([
            'success' => true,
            'page'    => $page,
            'perPage' => $perPage,
            'total'   => $result['total'],
            'data'    => $result['data'],
        ]);
    }

    /**
     * GET api/promotion/logs/stats
     * 回傳統計摘要（total / completed / pending / error / avg_ms / max_ms）。
     */
    public function stats()
    {
        return $this->response->setJSON([
            'success' => true,
            'data'    => $this->model->getStats(),
        ]);
    }

    /**
     * GET api/promotion/logs/detail/:id
     * 回傳單筆 log 完整資料（含 request_data）。
     */
    public function detail(int $id)
    {
        $row = $this->model->getDetail($id);

        if ($row === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['success' => false, 'message' => 'Log not found']);
        }

        return $this->response->setJSON(['success' => true, 'data' => $row]);
    }

    /**
     * POST api/promotion/logs/clean
     *
     * Body (JSON)：
     *   days  int  刪除幾天前的 log（預設 30，最少 1）
     */
    public function clean()
    {
        $body = $this->request->getJSON(true) ?? [];
        $days = max(1, (int) ($body['days'] ?? 30));

        $deleted = $this->model->cleanOld($days);

        return $this->response->setJSON([
            'success' => true,
            'deleted' => $deleted,
            'message' => "已刪除 {$deleted} 筆 {$days} 天前的 log",
        ]);
    }
}
