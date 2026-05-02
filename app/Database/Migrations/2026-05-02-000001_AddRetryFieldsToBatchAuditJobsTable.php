<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRetryFieldsToBatchAuditJobsTable extends Migration
{
    public function up(): void
    {
        $this->db    = \Config\Database::connect('promotion');
        $this->forge = \Config\Database::forge('promotion');

        $fields = array_flip($this->db->getFieldNames('batch_audit_jobs'));
        $columns = [];

        if (! isset($fields['retry_count'])) {
            $columns['retry_count'] = [
                'type'    => 'INT',
                'default' => 0,
                'after'   => 'processed',
            ];
        }

        if (! isset($fields['max_retries'])) {
            $columns['max_retries'] = [
                'type'    => 'INT',
                'default' => 3,
                'after'   => 'retry_count',
            ];
        }

        if (! isset($fields['retry_errors'])) {
            $columns['retry_errors'] = [
                'type'    => 'JSON',
                'null'    => true,
                'default' => null,
                'after'   => 'error_message',
            ];
        }

        if (! isset($fields['next_retry_at'])) {
            $columns['next_retry_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'completed_at',
            ];
        }

        if ($columns !== []) {
            $this->forge->addColumn('batch_audit_jobs', $columns);
        }

        $this->db->query(
            "UPDATE batch_audit_jobs
             SET retry_count = 1,
                 next_retry_at = DATE_ADD(COALESCE(completed_at, NOW()), INTERVAL 5 MINUTE),
                 retry_errors = JSON_ARRAY(JSON_OBJECT(
                     'attempt', 1,
                     'message', error_message,
                     'failed_ids', COALESCE(failed_ids, JSON_ARRAY()),
                     'started_at', started_at,
                     'completed_at', completed_at,
                     'next_retry_at', DATE_ADD(COALESCE(completed_at, NOW()), INTERVAL 5 MINUTE),
                     'will_retry', TRUE
                 ))
             WHERE status = 'failed'
               AND retry_count = 0
               AND error_message IS NOT NULL"
        );
    }

    public function down(): void
    {
        $this->db    = \Config\Database::connect('promotion');
        $this->forge = \Config\Database::forge('promotion');

        $fields = array_flip($this->db->getFieldNames('batch_audit_jobs'));
        foreach (['next_retry_at', 'retry_errors', 'max_retries', 'retry_count'] as $field) {
            if (isset($fields[$field])) {
                $this->forge->dropColumn('batch_audit_jobs', $field);
            }
        }
    }
}
