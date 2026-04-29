<?php

namespace App\Models\Promotion;
use CodeIgniter\Model;

class M_CustomizedDb extends Model
{
    protected $db;
    protected $table;
    protected $serverCode;      // 伺服器代碼
    protected $dbInfo;
    protected $dbField;

    public function __construct($serverCode)
    {
        $this->serverCode = $serverCode;
        $this->getDatabase();                   // 取得資料庫連線資訊
        $this->getDatabaseField();              // 取得資料庫欄位資訊

        if (!empty($this->dbField)){
            $this->table = $this->dbField[0]['table_name'];
        }
        
        $this->connectDatabase();               // 連線資料庫
    }

    // 取得資料庫連線資訊
    private function getDatabase()
    {
        $promotionDb = \Config\Database::connect('promotion');

        $server = $promotionDb->table('customized_db')
            ->where('server_code', $this->serverCode)
            ->get()
            ->getRowArray();

        // 檢查資料庫連線資訊是否存在
        if (empty($server)) {
            throw new \Exception('Database connection info not found for server code: ' . $this->serverCode);
        }
        
        $this->dbInfo = $server;
    }

    // 取得資料庫欄位資訊
    private function getDatabaseField()
    {
        $promotionDb = \Config\Database::connect('promotion');

        $field = $promotionDb->table('customized_field')
            ->where('server_code', $this->serverCode)
            ->get()
            ->getResultArray();

        $this->dbField = $field;
    }

    // 連線資料庫
    private function connectDatabase()
    {
        try{
            // 手動連接資料庫
            $this->db = \Config\Database::connect([
                'DSN'      => '',
                'hostname' => $this->dbInfo['host'],
                'username' => $this->dbInfo['account'],
                'password' => $this->dbInfo['password'],
                'database' => $this->dbInfo['name'],
                'port'     => (int)$this->dbInfo['port'],
                'DBDriver' => 'MySQLi',
                'charset'  => 'utf8',
            ]);

            // 檢查連線是否成功
            try {
                $this->db->connect();
                return true;
            } catch (\Exception $e) {
                throw new \Exception('Database connection failed: ' . $e->getMessage());
            }
        }catch (\Exception $e){
            return false;
        }   
    }

    // 寫入資料
    public function insertData($data)
    {
        $this->db->table($this->table)->insert($data);
        return $this->db->insertID();
    }

    public function fetchData()
    {
        return $this->db->table($this->table)
            ->get()
            ->getResultArray();
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getDbInfo()
    {
        return $this->dbInfo;
    }

    public function getDbField()
    {
        return $this->dbField;
    }

    /**
     * 測試自訂資料庫連線
     * 
     * 此函數用於測試與自訂資料庫的連線狀態，
     * 不依賴於建構函數中的連線，而是重新建立一次連線測試
     * 
     * @return array 包含連線狀態和相關訊息的陣列
     *               - status: bool 連線是否成功 (true/false)
     *               - message: string 詳細的狀態訊息
     *               - server_code: string 伺服器代碼
     *               - host: string 資料庫主機位址
     *               - database: string 資料庫名稱
     */
    public function testConnection()
    {
        try {
            // 檢查是否已有資料庫連線資訊
            if (empty($this->dbInfo)) {
                return [
                    'status' => false,
                    'message' => '無法取得資料庫連線資訊，請檢查伺服器代碼是否正確',
                    'server_code' => $this->serverCode,
                    'host' => null,
                    'database' => null
                ];
            }

            // 建立測試用的資料庫連線配置
            $testDbConfig = [
                'DSN'      => '',
                'hostname' => $this->dbInfo['host'],
                'username' => $this->dbInfo['account'],
                'password' => $this->dbInfo['password'],
                'database' => $this->dbInfo['name'],
                'port'     => (int)$this->dbInfo['port'],
                'DBDriver' => 'MySQLi',
                'charset'  => 'utf8',
            ];

            // 嘗試建立測試連線
            $testDb = \Config\Database::connect($testDbConfig);
            
            // 測試連線是否成功
            $testDb->connect();
            
            // 執行簡單的查詢來確認連線狀態
            $testDb->query('SELECT 1');
            
            // 關閉測試連線
            $testDb->close();
            
            return [
                'status' => true,
                'message' => '資料庫連線測試成功',
                'server_code' => $this->serverCode,
                'host' => $this->dbInfo['host'],
                'database' => $this->dbInfo['name']
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => '資料庫連線測試失敗：' . $e->getMessage(),
                'server_code' => $this->serverCode,
                'host' => $this->dbInfo['host'] ?? null,
                'database' => $this->dbInfo['name'] ?? null
            ];
        }
    }
}
