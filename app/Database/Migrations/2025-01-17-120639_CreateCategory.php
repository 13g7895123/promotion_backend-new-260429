<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCatpgory extends Migration
{
    public function up()
    {
        // 建立 files 資料表
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'comment' => '分類代碼',
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'comment' => '分類名稱',
            ],
            'description' => [
                'type' => 'VARCHAR',
                'constraint' => 300,
                'comment' => '分類描述',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('category');
    }

    public function down()
    {
        $this->forge->dropTable('category');
    }
}
