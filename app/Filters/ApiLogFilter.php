<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\M_ApiLog;

/**
 * ApiLogFilter
 *
 * before : 插入一筆 status=pending 的 log 紀錄，記下觸發時間與請求資訊。
 * after  : 更新為 completed / error，填入完成時間與耗時 (ms)。
 *
 * 若請求 504 逾時，after 不會被呼叫，
 * 該筆 log 將永遠停在 pending → 儀表板可直接看出超時的 API。
 *
 * 排除路徑：
 *   - api/promotion/logs*   (log 查看器本身，避免自我記錄造成無限迴圈)
 *   - api/promotion/lifecycle*
 *   - OPTIONS preflight
 */
class ApiLogFilter implements FilterInterface
{
    /** 目前請求的 log id（static，在 before→after 之間傳遞） */
    protected static ?int $currentLogId = null;

    /**
     * 讓 Model 層取得目前的 log id，用於 AuditProfiler::begin() 傳入以啟用 504 防護。
     */
    public static function getCurrentLogId(): ?int
    {
        return self::$currentLogId;
    }

    /** 請求開始的微秒時間戳 */
    protected static float $startTime = 0.0;

    /** 不需記錄的 URI 片段（部分比對） */
    private const SKIP_PATTERNS = [
        '/logs',
        '/operations',
        '/lifecycle',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        // OPTIONS preflight 不記錄
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return;
        }

        $uri = '/' . ltrim($request->getUri()->getPath(), '/');

        // 跳過 log 查看器本身
        foreach (self::SKIP_PATTERNS as $pattern) {
            if (str_contains($uri, $pattern)) {
                return;
            }
        }

        self::$startTime   = microtime(true);
        self::$currentLogId = null;

        try {
            $router     = service('router');
            $controller = $router->controllerName();
            $action     = $router->methodName();

            // 取得 request body，截斷至 2000 字元避免佔用過多空間
            $rawBody   = $request->getBody();
            $bodyTrunc = ($rawBody !== null && $rawBody !== '')
                ? mb_substr($rawBody, 0, 2000, 'UTF-8')
                : null;

            $model = new M_ApiLog();
            self::$currentLogId = $model->startLog([
                'method'       => strtoupper($request->getMethod()),
                'uri'          => $uri,
                'controller'   => $controller,
                'action'       => $action,
                'request_data' => $bodyTrunc,
                'ip_address'   => $request->getIPAddress(),
                'status'       => 'pending',
                'triggered_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // log 失敗不可影響正常 API 回應
            self::$currentLogId = null;
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (self::$currentLogId === null) {
            return $response;
        }

        try {
            $durationMs = (int) round((microtime(true) - self::$startTime) * 1000);
            $statusCode = $response->getStatusCode();

            // 2xx / 3xx → completed；其餘 → error
            $status = ($statusCode >= 200 && $statusCode < 400) ? 'completed' : 'error';

            // 收集 AuditProfiler 段落耗時（batchAuditV3 等有計時的 API 才會有值）
            $perfData = \App\Libraries\AuditProfiler::collect();

            $finishData = [
                'status'        => $status,
                'response_code' => $statusCode,
                'completed_at'  => date('Y-m-d H:i:s'),
                'duration_ms'   => $durationMs,
            ];

            if ($perfData !== null) {
                $finishData['perf_data'] = $perfData;
            }

            $model = new M_ApiLog();
            $model->finishLog(self::$currentLogId, $finishData);
        } catch (\Throwable $e) {
            // 同上，不影響正常回應
        }

        self::$currentLogId = null;
        return $response;
    }
}
