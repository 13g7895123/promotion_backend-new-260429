<?php

namespace App\Controllers\Promotion;

use App\Controllers\BaseController;
use App\Models\M_ApiLog;

class OperationLog extends BaseController
{
    protected M_ApiLog $model;

    public function __construct()
    {
        $this->model = new M_ApiLog();
    }

    public function index()
    {
        return view('Promotion/operation_log');
    }

    public function data()
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(10, (int) ($this->request->getGet('per_page') ?? 50)));

        $filters = array_filter([
            'operation_type'      => $this->request->getGet('operation_type'),
            'method'              => $this->request->getGet('method'),
            'uri'                 => $this->request->getGet('uri'),
            'date'                => $this->request->getGet('date'),
            'keyword'             => $this->request->getGet('keyword'),
            'only_with_operation' => $this->request->getGet('only_with_operation') === '1',
        ]);

        $result = $this->model->getOperationLogs($page, $perPage, $filters);

        return $this->response->setJSON([
            'success'     => true,
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $result['total'],
            'total_pages' => $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 0,
            'data'        => $result['data'],
        ]);
    }

    public function detail(int $id)
    {
        $row = $this->model->getDetail($id);

        if ($row === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['success' => false, 'message' => 'Operation log not found']);
        }

        return $this->response->setJSON(['success' => true, 'data' => $row]);
    }
}