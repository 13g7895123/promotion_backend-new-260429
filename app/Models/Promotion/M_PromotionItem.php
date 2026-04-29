<?php

namespace App\Models\Promotion;
use CodeIgniter\Model;
use App\Models\M_Common as M_Model_Common;

class M_PromotionItem extends Model
{
    protected $db;
    protected $table;
    protected $M_Model_Common;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');  // 預設資料庫
        $this->M_Model_Common = new M_Model_Common();
    }

    /**
     * 取得推廣項目資料
     * @param array $where
     * @param array $field
     * @param bool $queryMultiple
     * @return array
     */ 
    public function getData($where = [], $field = [], $queryMultiple = False)
    {
        $builder = $this->db->table('promotion_items');

        if (!empty($where)){
            $builder->where($where);
        }       

        $data = ($queryMultiple) ? $builder->get()->getResultArray() : $builder->get()->getRowArray();

        return $data;
    }

    /**
     * 建立推廣項目
     * @param array $data
     * @return int
     */
    public function create($data){
        $this->db->table('promotion_items')->insert($data);
        return $this->db->insertID();
    }

    /**
     * 更新推廣項目
     * @param array $data
     * @param array $where
     * @return void
     */
    public function updateData($data, $where){
        $this->db->table('promotion_items')
            ->where($where)
            ->update($data);
    }

    public function updateDataNew($data, $where){
        $builder = $this->db->table('promotion_items');

        foreach ($where as $_key => $_val){
            if (is_array($_val)){
                $builder->whereIn($_key, $_val);
                continue;
            }

            $builder->where($_key, $_val);
        }

        $builder->update($data);
    }

    /**
     * 刪除推廣項目
     * @param int $promotionId 推廣Id
     * @return void
     */
    public function deleteData($promotionId)
    {
        $builder = $this->db->table('promotion_items');

        if (is_array($promotionId)){
            $builder->whereIn('promotion_id', $promotionId);
        } else {
            $builder->where('promotion_id', $promotionId);
        }

        $builder->delete();
    }

    /**
     * 取得推廣項目資料
     * @param int $promotionId 推廣Id
     * @return array
     */
    public function getPromotionItem($promotionId)
    {
        $promotionItemData = $this->db->table('promotion_items')
            ->where('promotion_id', $promotionId)
            ->get()
            ->getResultArray();

        return $promotionItemData;
    }

    /**
     * 確認網址是否存在
     * @param string $url
     * @return void
     */
    public function checkUrl($url)
    {
        $data = $this->db->table('promotion_items')
            ->where('type', 'text')
            ->where('content', $url)
            ->get()
            ->getRowArray();            

        return !empty($data) ? true : false;
    }
}