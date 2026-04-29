<?php

namespace App\Models\Promotion;
use CodeIgniter\Model;
use App\Models\M_Common as M_Model_Common;

class M_Server extends Model
{
    protected $db;
    protected $table;
    protected $M_Model_Common;
    protected $primaryKey;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');  // 預設資料庫
        $this->M_Model_Common = new M_Model_Common();
    }

    /**
     * 取得伺服器資料
     * @param string $code
     */
    public function getServer($where = [], $field = [], $queryMultiple = False)
    {
        $builder = $this->db->table('server');

        if (!empty($where)){
            foreach ($where as $_key => $_val){
                if (is_array($_val)){
                    $builder->whereIn($_key, $_val);
                    continue;
                }

                $builder->where($_key, $_val);
            }
        }

        $data = ($queryMultiple) ? $builder->get()->getResultArray() : $builder->get()->getRowArray();

        return $data;
    }

    /**
     * 刪除伺服器
     */
    public function deleteData($id)
    {
        // 伺服器資料
        $server = $this->M_Model_Common->getData('server', ['id' => $id]);

        if (empty($server)){
            return false;
        }

        // 玩家資料
        $this->db->table('player')
            ->whereIn('server', [$server['code']])
            ->delete();

        // 刪除推廣資料
        $promotion = $this->M_Model_Common->getData('promotions', ['server' => $id], [], true);

        if (!empty($promotion)){
            foreach ($promotion as $_val){
                $this->M_Promotion->deleteData($_val['id']);
            }
        }
        
        $this->db->table('server_image')
            ->where('server_code', $server['code'])
            ->delete();

        $this->db->table('server')
            ->where('id', $id)
            ->delete();

        return True;
    }

    /**
     * 特殊欄位轉換
     * @param string $table 資料表 
     * @param string $field 資料欄位
     * @param array  $data  資料
     */
    public function convertSpecialField($table, $field, $data)
    {
        $specialField = array(
            'server' => array(
                'require_character' => function () use ($data){
                    if (isset($data['targetType'])){
                        $data['require_character'] = ($data['targetType'] == 'character') ? 1 : 0;
                    }
                }
            ),
        );

        if (isset($specialField[$table][$field])){
            $specialField[$table][$field]($data);
        }

        return $data;
    }

    /**
     * 取得過濾後的總筆數
     */
    public function getFilteredCount($conditions = [])
    {
        $builder = $this->db->table($this->table);
        
        if (!empty($conditions['search'])) {
            $builder->groupStart()
                ->like('name', $conditions['search'])
                ->orLike('code', $conditions['search'])
                ->groupEnd();
        }
        
        return $builder->countAllResults();
    }

    /**
     * 取得 DataTable 資料與統計
     */
    public function getDataTableData($params)
    {
        $builder = $this->db->table($this->table);
        
        // 搜尋條件
        if (!empty($params['search']) && !empty($params['searchColumn'])) {
            $builder->like($params['searchColumn'], $params['search']);
        }

        // 取得過濾後的總筆數
        $filteredRecords = $builder->countAllResults(false);
        
        // 排序
        $builder->orderBy($params['orderColumn'], $params['orderDir']);
        
        // 分頁
        $builder->limit($params['length'], $params['start']);
        
        // 取得資料
        $data = $builder->get()->getResultArray();

        // 取得總筆數
        $totalRecords = $this->db->table($this->table)->countAllResults();
        
        return [
            'total' => $totalRecords,
            'filtered' => $filteredRecords,
            'data' => $data
        ];
    }

    /**
     * 確認資料庫資料是否存在
     */
    public function checkDatabase($serverCode)
    {
        $builder = $this->db->table('customized_db');
        $builder->where('server_code', $serverCode);
        $data = $builder->get()->getRowArray();

        return (!empty($data)) ? True : False;
    }

    /**
     * 更新資料庫資料
     */
    public function updateDatabase($data)
    {
        $updateData = $data;
        unset($updateData['server_code']);

        $builder = $this->db->table('customized_db');
        $builder->where('server_code', $data['server_code']);
        $builder->update($updateData);

        return True;
    }

    /**
     * 新增資料庫資料
     */
    public function insertDatabase($data)
    {
        $builder = $this->db->table('customized_db');
        $builder->insert($data);

        return True;
    }

    // 更新資料庫欄位資料
    public function updateDatabaseField($data)
    {
        $fieldData = $this->db->table('customized_field')
            ->where('server_code', $data['server_code'])
            ->where('table_name', $data['table'])
            ->get()
            ->getResultArray();

        // 先刪除資料
        if (!empty($fieldData)){
            $this->db->table('customized_field')
            ->where('server_code', $data['server_code'])
            ->delete();
        }

        // 再新增資料
        foreach ($data['fields'] as $_val){
            if ($_val['name'] == '' && $_val['value'] == ''){
                continue; // 跳過空欄位
            }
            
            $insertData = array(
                'server_code' => $data['server_code'],
                'table_name' => $data['table'],
                'field' => $_val['name'],
                'value' => $_val['value'],
            );
            $this->db->table('customized_field')->insert($insertData);
        }

        return True;
    }
    /**
     * 取得圖片
     */
    public function getImage($code, $type)
    {
        $data = $this->db->table('server_image')
            ->where('server_code', $code)
            ->where('type', $type)
            ->get()
            ->getResultArray();

        return $data;
    }

    /**
     * 新增圖片
     */
    public function createServerImage($data, $fileId)
    {
        $insertData = array(
            'server_code' => $data['server_code'],
            'type' => $data['type'],
            'file_id' => $fileId,
        );

        $this->db->table('server_image')->insert($insertData);

        return true;
    }

    /**
     * 更新圖片
     */
    public function updateServerImage($data)
    {
        $updateData = array('is_selected' => 0);

        $this->db->table('server_image')
            ->where('server_code', $data['code'])
            ->where('type', $data['type'])
            ->update($updateData);

        $updateData = array('is_selected' => 1);

        $this->db->table('server_image')
            ->where('server_code', $data['code'])
            ->where('type', $data['type'])
            ->where('file_id', $data['file_id'])
            ->update($updateData);

        return True;
    }

    /**
     * 取得選取的圖片
     */
    public function getSelectedImage($code, $type)
    {
        $data = $this->db->table('server_image')
            ->where('server_code', $code)
            ->where('type', $type)
            ->where('is_selected', 1)
            ->get()
            ->getRowArray();

        return $data;
    }

    public function createDefaultImage($code, $type)
    {
        $insertData = array(
            'server_code' => $code,
            'type' => $type,
            'is_selected' => 0,
        );

        $defaultImage = array(
            'icon' => array(
                'id' => array(12, 13, 14),
                'default' => 12,
            ),
            'background' => array(
                'id' => array(15, 16, 17, 18, 19, 20),
                'default' => 20,
            ),
        );

        foreach ($defaultImage[$type]['id'] as $_key => $_val){
            // 設定預設圖片
            $insertData['is_selected'] = ($_val == $defaultImage[$type]['default']) ? 1 : 0;

            $insertData['file_id'] = $_val;
            $this->db->table('server_image')->insert($insertData);
        }

        return True;
    }
}