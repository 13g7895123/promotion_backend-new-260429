<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFoodCategoryFilesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'category_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'file_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['category_id', 'file_id']);
        $this->forge->addForeignKey('category_id', 'food_categories', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('file_id', 'files', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('food_category_files');
    }

    public function down()
    {
        $this->forge->dropTable('food_category_files');
    }
} 