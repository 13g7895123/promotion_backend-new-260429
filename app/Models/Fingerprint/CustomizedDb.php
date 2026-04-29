<?php

namespace App\Models\Fingerprint;
use CodeIgniter\Model;

class CustomizedDb extends Model
{
    protected $db;
    protected $table;
    protected $serverCode;      // 伺服器代碼
    protected $dbInfo;
    protected $dbField;

    public function __construct($serverCode)
    {
        $this->serverCode = $serverCode;
        $this->fetchDatabase();                   // 取得資料庫連線資訊
        $this->connectDatabase();               // 連線資料庫
    }

    // 取得資料庫連線資訊
    private function fetchDatabase()
    {
        $fingerprintDb = \Config\Database::connect('fingerprint');

        $server = $fingerprintDb->table('servers')
            ->where('id', $this->serverCode)
            ->get()
            ->getRowArray();

        // 檢查資料庫連線資訊是否存在
        if (empty($server)) {
            throw new \Exception('Database connection info not found for server code: ' . $this->serverCode);
        }
        
        $this->dbInfo = $server;
    }

    // 連線資料庫
    private function connectDatabase()
    {
        try{
            // 手動連接資料庫
            $this->db = \Config\Database::connect([
                'DSN'      => '',
                'hostname' => $this->dbInfo['db_ip'],
                'username' => $this->dbInfo['db_user'],
                'password' => $this->dbInfo['db_pass'],
                'database' => $this->dbInfo['db_name'],
                'port'     => (int)$this->dbInfo['db_port'],
                'DBDriver' => 'MySQLi',
                'charset'  => 'utf8mb4',
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

    /**
     * 確認帳號是否存在
     * @param string $account 帳號
     * @return bool 檢查結果
     */
    public function checkAccountExists($account)
    {
        $table = ($this->dbInfo['game'] == 0) ? 'accounts' : 'login';
        $field = ($this->dbInfo['game'] == 0) ? 'login' : 'userid';

        $data = $this->db->table($table)
            ->where($field, $account)
            ->get()
            ->getRowArray();

        return (!empty($data)) ? true : false;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getDbInfo()
    {
        return $this->dbInfo;
    }

    public function fetchDatabaseField()
    {
        return $this->dbField;
    }
}
