<?
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFilesTable extends Migration
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
            'path' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'comment' => '檔案類型 (e.g., image, document, etc.)',
            ],
            'size' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'comment' => '檔案大小 (bytes)',
            ],
            'uploaded_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('files');

        // 建立 category_files 關聯表
        $this->forge->addField([
            'category_id' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
            'file_id' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
        ]);
        $this->forge->addKey(['category_id', 'file_id'], true);
        $this->forge->createTable('category_files');

        // 建立 product_files 關聯表
        $this->forge->addField([
            'product_id' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
            'file_id' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
        ]);
        $this->forge->addKey(['product_id', 'file_id'], true);
        $this->forge->createTable('product_files');
    }

    public function down()
    {
        $this->forge->dropTable('product_files');
        $this->forge->dropTable('category_files');
        $this->forge->dropTable('files');
    }
}
