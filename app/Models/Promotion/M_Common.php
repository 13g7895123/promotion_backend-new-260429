<?php

namespace App\Models\Promotion;
use CodeIgniter\Model;
use App\Models\M_Common as M_Model_Common;

class M_Common extends Model
{
    protected $db;
    protected $table;
    protected $M_Model_Common;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');  // 預設資料庫
        $this->M_Model_Common = new M_Model_Common();
    }

    public function index($table)
    {
        $data = $this->db->table($table)
            ->get()
            ->getResultArray();

        return $data;
    }

    /**
     * 建立基礎資料
     * @param string    $table  資料表
     * @param array     $data   資料
     */
    public function create($table, $data)
    {
        $this->db->table($table)->insert($data);
        $insertId = $this->db->insertId();        

        return $insertId;
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
                        unset($data['targetType']);

                        return $data;
                    }
                }
            ),
        );

        if (isset($specialField[$table][$field])){
            $data = $specialField[$table][$field]($data);
        }

        return $data;
    }

    /**
     * 前後端欄位轉換
     * @param string    $table  資料表
     * @param array     $data   待轉換資料
     */
    public function convertFields($table, $data)
    {
        $convertData = array(
            'server' => array(
                'promotionLimit' => 'limit_number',     // 限制次數
                'notifyEmail' => 'notify_email',        // 信箱通知
                'notifyLine' => 'notify_line',          // Line通知
            ),
        );

        // 資料轉換
        foreach ($data as $_key => $_val){
            if (isset($convertData[$table][$_key])){
                $data[$convertData[$table][$_key]] = $_val;
                unset($data[$_key]);    // 消除原先的資料
            }
        }

        return $data;
    }

    /**
     * 推廣系統會用到的資料表欄位
     * @param string $table 資料表名稱
     */
    public function fields($table)
    {
        // 該陣列中儲存所有基礎儲存資料的欄位
        $data = array(
            'server' => array('code', 'server', 'require_character', 'cycle', 'limit_number', 'notify_email', 'notify_line'),
        );

        return isset($data[$table]) ? $data[$table] : False;
    }
}