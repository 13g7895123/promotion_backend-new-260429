<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOperationFieldsToApiLogs extends Migration
{
    public function up()
    {
        $this->db = \Config\Database::connect('promotion');
        $this->forge = \Config\Database::forge('promotion');

        $fields = array_flip($this->db->getFieldNames('api_logs'));
        $columns = [];

        if (! isset($fields['operation_type'])) {
            $columns['operation_type'] = [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'last_step',
            ];
        }

        if (! isset($fields['operation_summary'])) {
            $columns['operation_summary'] = [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'after'      => 'operation_type',
            ];
        }

        if (! isset($fields['operation_data'])) {
            $columns['operation_data'] = [
                'type' => 'LONGTEXT',
                'null' => true,
                'after' => 'operation_summary',
            ];
        }

        if (! empty($columns)) {
            $this->forge->addColumn('api_logs', $columns);
        }
    }

    public function down()
    {
        $this->db = \Config\Database::connect('promotion');
        $this->forge = \Config\Database::forge('promotion');

        $fields = array_flip($this->db->getFieldNames('api_logs'));
        foreach (['operation_data', 'operation_summary', 'operation_type'] as $field) {
            if (isset($fields[$field])) {
                $this->forge->dropColumn('api_logs', $field);
            }
        }
    }
}