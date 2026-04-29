<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProduct extends Migration
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
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'comment' => '名稱',
            ],
            'description' => [
                'type' => 'VARCHAR',
                'constraint' => 300,
                'comment' => '描述',
            ],
            'weight' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'comment' => '重量',
            ],
            'shelf_life' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'comment' => '保存期限',
            ],
            'main_ingredients' => [
                'type' => 'VARCHAR',
                'constraint' => 300,
                'comment' => '主要成分',
            ],
            'storage' => [
                'type' => 'VARCHAR',
                'constraint' => 300,
                'comment' => '保存方式',
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
        $this->forge->createTable('product');
    }

    public function down()
    {
        $this->forge->dropTable('product');
    }
}
