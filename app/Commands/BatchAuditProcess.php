<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\Promotion\M_BatchAuditJob;
use App\Models\Promotion\M_Promotion;

/**
 * BatchAuditProcess
 *
 * 排程處理 batch_audit_jobs 佇列中的待審核任務。
 * 每次執行處理所有 pending 任務（FIFO），每筆任務完成後立即更新狀態。
 *
 * 使用方式：
 *   php spark batch-audit:process
 *
 * Docker scheduler（docker-compose.yml）每分鐘自動呼叫此指令。
 */
class BatchAuditProcess extends BaseCommand
{
    protected $group       = 'Promotion';
    protected $name        = 'batch-audit:process';
    protected $description = '處理排程中待審核的 batch_audit_jobs 任務';

    public function run(array $params): void
    {
        $model       = new M_BatchAuditJob();
        $M_Promotion = new M_Promotion();
        $jobCount    = 0;

        // 更新心跳檔，讓 API 能判斷排程是否存活
        $this->writeHeartbeat();

        CLI::write('[' . date('Y-m-d H:i:s') . '] BatchAuditProcess 啟動', 'cyan');

        while (true) {
            $job = $model->claimNextPending();

            if ($job === null) {
                break;
            }

            $jobCount++;
            $jobId = (int) $job['id'];

            CLI::write("  → Job #{$jobId} 開始處理（共 {$job['total']} 筆，狀態：{$job['audit_status']}）", 'yellow');

            try {
                $M_Promotion->batchAuditV3($job['promotion_ids'], $job['audit_status']);
                $M_Promotion->reissuanceRewards($job['promotion_ids']);

                $model->markCompleted($jobId, count($job['promotion_ids']));
                CLI::write("  ✔ Job #{$jobId} 完成", 'green');
            } catch (\Throwable $e) {
                $model->markFailed($jobId, $e->getMessage());
                CLI::write("  ✘ Job #{$jobId} 失敗：" . $e->getMessage(), 'red');
            }
        }

        if ($jobCount === 0) {
            CLI::write('  無待處理任務', 'light_gray');
        }

        CLI::write('[' . date('Y-m-d H:i:s') . '] BatchAuditProcess 結束（處理 ' . $jobCount . ' 筆）', 'cyan');
    }

    /**
     * 將目前時間寫入心跳檔，供 API 判斷排程容器是否存活。
     * 檔案路徑：writable/scheduler_heartbeat.json
     */
    private function writeHeartbeat(): void
    {
        $path = WRITEPATH . 'scheduler_heartbeat.json';
        $data = json_encode([
            'last_ping' => date('Y-m-d H:i:s'),
            'pid'       => getmypid(),
        ]);
        // 先刪除舊檔再寫入，避免不同容器使用者因 644 權限無法覆寫
        if (file_exists($path)) {
            @unlink($path);
        }
        @file_put_contents($path, $data);
    }
}
