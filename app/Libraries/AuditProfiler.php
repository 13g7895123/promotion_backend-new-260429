<?php

namespace App\Libraries;

/**
 * AuditProfiler
 *
 * 輕量靜態計時工具，專供 batchAuditV3 段落耗時分析使用。
 * 由 ApiLogFilter::after() 讀取後寫入 api_logs.perf_data。
 *
 * 使用方式：
 *   AuditProfiler::begin($logId);     // 傳入 api_logs.id，啟用 504 即時回寫防護
 *   AuditProfiler::mark('step_name'); // 在每個段落結束後呼叫
 *   AuditProfiler::collect();         // 取得 JSON 結果（同時重設狀態）
 *
 * 504 防護說明：
 *   mark() 每次被呼叫時，會立即將「最後完成的段落名稱」寫入 api_logs.last_step。
 *   若進程被 kill（504 逾時），after() 不會執行，但 last_step 仍保留在 DB，
 *   可從 logs/detail/:id 看出「卡在哪個段落之後」。
 */
class AuditProfiler
{
    /** 計時起始點（microtime） */
    private static float $start = 0.0;

    /** 上一個 mark 的時間點（用於計算「段落耗時」） */
    private static float $lastMark = 0.0;

    /** 各段落紀錄 */
    private static array $marks = [];

    /** 是否正在收集 */
    private static bool $active = false;

    /**
     * 對應的 api_logs.id（用於 504 即時回寫）；null 表示不回寫
     */
    private static ?int $logId = null;

    /**
     * 開始計時（重設所有狀態）
     *
     * @param int|null $logId  api_logs 的 id；傳入後每次 mark() 都會立即更新 last_step（504 防護）
     */
    public static function begin(?int $logId = null): void
    {
        self::$start    = microtime(true);
        self::$lastMark = self::$start;
        self::$marks    = [];
        self::$active   = true;
        self::$logId    = $logId;
    }

    /**
     * 記錄一個段落結束點，並即時回寫 last_step 至 DB（504 防護）
     *
     * @param string $label  段落名稱（英文，方便 JSON 閱讀）
     */
    public static function mark(string $label): void
    {
        if (!self::$active) {
            return;
        }

        $now = microtime(true);

        self::$marks[] = [
            'step'        => $label,
            'elapsed_ms'  => (int) round(($now - self::$start)    * 1000),
            'segment_ms'  => (int) round(($now - self::$lastMark) * 1000),
        ];

        self::$lastMark = $now;

        // 504 防護：即時更新 DB，進程被 kill 後仍可查到最後完成的段落
        if (self::$logId !== null) {
            try {
                \Config\Database::connect('promotion')
                    ->table('api_logs')
                    ->where('id', self::$logId)
                    ->update(['last_step' => mb_substr($label, 0, 100)]);
            } catch (\Throwable $e) {
                // DB 寫入失敗不可中斷主流程
            }
        }
    }

    /**
     * 收集結果並重設狀態
     *
     * @return string|null  JSON 字串；若未啟動或無 mark 則回傳 null
     */
    public static function collect(): ?string
    {
        if (!self::$active || empty(self::$marks)) {
            self::$active = false;
            self::$logId  = null;
            return null;
        }

        $total = (int) round((microtime(true) - self::$start) * 1000);

        $output = [
            'total_ms' => $total,
            'steps'    => self::$marks,
        ];

        self::$active = false;
        self::$marks  = [];
        self::$logId  = null;

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 是否正在收集中
     */
    public static function isActive(): bool
    {
        return self::$active;
    }
}
