<?php

namespace App\Models\Promotion;

use CodeIgniter\Model;

class M_File extends Model
{
    protected $db;
    protected $table      = 'files';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = ['name', 'path', 'type', 'size', 'uploaded_at'];

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');  // 預設資料庫
    }

    /**
     * 取得檔案路徑
     * @param int $fileId
     * @return array
     */
    public function getPath($fileId)
    {
        return $this->db->table('files')
            ->where('id', $fileId)
            ->get()
            ->getRowArray();
    }

    /**
     * 儲存檔案
     *
     * @param object $file 檔案物件
     * @param string $path 檔案路徑
     * @param string $type 檔案類型
     * @param int $size 檔案大小
     * @return int 新增的檔案 ID
     */
    public function saveFile(object $file, string $path): int
    {
        if (!$file->isValid()) {
            // log_message('error', 'Invalid file upload');
            return false;
        }

        $newName = $file->getRandomName();

        $file->move(WRITEPATH . 'uploads/' . $path, $newName);
        if (!$file->hasMoved()) {
            // log_message('error', 'Failed to move uploaded file');
            return false;
        }

        $this->insert([
            'name'        => $file->getClientName(),
            'path'        => 'uploads/' . $path . '/' . $newName,
            'type'        => $file->getClientMimeType(),
            'size'        => $file->getSize(),
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->getInsertID();
    }
}
