<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCategoryIdToProduct extends Migration
{
    public function up()
    {
        $this->forge->addColumn('product', [
            'category_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
                'after' => 'id',
            ],
        ]);

        // 添加外鍵約束
        $this->db->query('ALTER TABLE `product` ADD CONSTRAINT `fk_category_id` FOREIGN KEY (`category_id`) REFERENCES `category`(`id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function down()
    {
        // 刪除外鍵約束
        $this->db->query('ALTER TABLE `product` DROP FOREIGN KEY `fk_category_id`');

        // 刪除欄位
        $this->forge->dropColumn('product', 'category_id');
    }
}
