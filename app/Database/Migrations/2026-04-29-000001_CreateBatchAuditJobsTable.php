<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * batch_audit_jobs 排程任務佇列表
 *
 * 欄位說明：
 *   id              自增主鍵
 *   promotion_ids   JSON 陣列，待審核的 promotion id 清單
 *   audit_status    要審核成的目標狀態（success / reject 等）
 *   status          任務狀態：pending / processing / completed / failed
 *   total           總筆數
 *   processed       已處理筆數
 *   retry_count     已失敗嘗試次數
 *   max_retries     最大失敗嘗試次數
 *   failed_ids      JSON 陣列，處理失敗的 promotion id
 *   error_message   失敗原因（最多 2000 字）
 *   retry_errors    JSON 陣列，每次失敗嘗試的錯誤紀錄
 *   created_by      觸發人（user_id 或 IP）
 *   created_at      入列時間
 *   started_at      開始處理時間
 *   completed_at    處理完成時間
 *   next_retry_at   下次可重試時間
 */
class CreateBatchAuditJobsTable extends Migration
{
    public function up(): void
    {
        // batch_audit_jobs 屬於 promotion 資料庫，強制使用該連線
        $this->db    = \Config\Database::connect('promotion');
        $this->forge = \Config\Database::forge('promotion');

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'promotion_ids' => [
                'type' => 'JSON',
            ],
            'audit_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'processing', 'completed', 'failed'],
                'default'    => 'pending',
            ],
            'total' => [
                'type'    => 'INT',
                'default' => 0,
            ],
            'processed' => [
                'type'    => 'INT',
                'default' => 0,
            ],
            'retry_count' => [
                'type'    => 'INT',
                'default' => 0,
            ],
            'max_retries' => [
                'type'    => 'INT',
                'default' => 3,
            ],
            'failed_ids' => [
                'type'    => 'JSON',
                'null'    => true,
                'default' => null,
            ],
            'error_message' => [
                'type'       => 'VARCHAR',
                'constraint' => 2000,
                'null'       => true,
                'default'    => null,
            ],
            'retry_errors' => [
                'type'    => 'JSON',
                'null'    => true,
                'default' => null,
            ],
            'created_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'started_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'next_retry_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        $this->forge->createTable('batch_audit_jobs', true);
    }

    public function down(): void
    {
        $this->db    = \Config\Database::connect('promotion');
        $this->forge = \Config\Database::forge('promotion');

        $this->forge->dropTable('batch_audit_jobs', true);
    }
}
