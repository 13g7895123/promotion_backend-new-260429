<?php

namespace App\Models;
use CodeIgniter\Model;

class M_Common extends Model
{
    protected $db;
    protected $table;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');  // 預設資料庫
    }

    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * 取得資料
     * @param string $table 資料表名稱
     * @param array $where 查詢條件
     * @param array $field 查詢欄位
     * @param bool $queryMultiple 是否查詢多筆
     * @param array $join 查詢聯結
     * @return array
     */
    public function getData($table, $where = [], $field = [], $queryMultiple = False, $join = [], $isTest = false)
    {
        $builder = $this->db->table($table);

        // 設置查詢條件
        if (!empty($where)){
            foreach ($where as $_key => $_val){
                if (is_array($_val)){
                    $builder->whereIn($_key, $_val);
                    continue;
                }

                $builder->where($_key, $_val);
            }
        }

        // 設置查詢欄位
        if (!empty($field)){
            $builder->select(implode(',', $field));
        }

        // 設置查詢聯結
        if (!empty($join)){
            foreach ($join as $item){
                // $builder->join($item['table'], "{$item['table']}.{$item['field']} = {$table}.{$item['source_field']}", 'left');
                $builder->join($item['table'], "{$item['table']}.{$item['field']} = {$table}.{$item['source_field']}");
            }
        }

        if ($isTest){
            print_r($builder->getCompiledSelect()); die();
        }

        // 取得資料
        $data = ($queryMultiple) ? $builder->get()->getResultArray() : $builder->get()->getRowArray();
        
        return $data;
    }   

    /* 上傳檔案 */
    public function upload($file, $path, $fileName)
    {
        try{
            // 檢查目錄是否存在，不存在則建立
            if (!is_dir($path)){
                mkdir($path, 0755, true);
            }

            // 移動檔案到上傳目錄
            $file->move($path, $fileName);

            return True;
        }catch (\Exception $e) {
            return False;
        }
    }

    /* 更新資料進圖片上傳資料表 */
    public function uploadData($data)
    {
        $this->db->table('image-upload')->insert($data);
        $insertId = $this->db->insertId();

        return $insertId;
    }

    /* 取得網域 */
    public function getDomain()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'] . '/';

        return "{$protocol}{$domainName}";
    }

    /* 確認資料表中是否有該欄位 */
    public function checkFieldExist($field, $table)
    {
        $fieldExist = False;
        $fields = $this->db->getFieldData($table);
        
        foreach ($fields as $_val){
            if ($_val->name == $field){
                $fieldExist = True;
                break;
            }
        }

        return $fieldExist;
    }

    /* 駝峰轉 Snake */
    public function camelToSnake($input)
    {
        // 使用正則表達式將大寫字母替換為底線+小寫字母
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));
        
        return $snake;
    }

    /* 透過Gmail發mail */
    public function sendMail($to, $subject, $message)
    {
        // 加載 Email 服務
        $email = \Config\Services::email();

        // 設置收件人及內容
        $email->setTo($to); // 收件人
        $email->setFrom('13gt7895123@gmail.com', 'Jarvis test'); // 發件人
        $email->setSubject($subject);
        $email->setMessage($message);

        // 嘗試發送郵件
        if ($email->send()) {
            return "郵件已成功發送！";
        } else {
            // 顯示錯誤信息
            $data = $email->printDebugger(['headers', 'subject', 'body']);
            return "發送失敗：" . $data;
        }
    }

    public function test()
    {
        echo 12345;
        print_r($this->logType); die();
        return 12345;
    }
}