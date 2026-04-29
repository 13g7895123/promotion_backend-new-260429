<?php

namespace App\Models\Promotion;

use CodeIgniter\Model;
use App\Models\M_Common as M_Model_Common;
use CodeIgniter\Email\Email;

class M_Player extends Model
{
    protected $db;
    protected $table = 'player';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['username', 'server', 'character_name', 'email', 'line_id', 'notify_mail', 'notify_line'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'username' => 'required|min_length[6]|max_length[100]|is_unique[player.username,id,{id}]',
    ];

    protected $validationMessages = [
        'username' => [
            'required' => '帳號為必填項',
            'min_length' => '名稱最少需要2個字元',
            'max_length' => '名稱最多100個字元',
        ],
    ];

    protected $M_Model_Common;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');  // 預設資料庫
        $this->M_Model_Common = new M_Model_Common();
    }

    /**
     * 取得使用者資訊
     * @param int $userId 使用者ID
     * @return array
     */
    public function getPlayerInfo($userId)
    {
        $userData = $this->db->table('player')
            ->where('id', $userId)
            ->get()
            ->getRowArray();

        return $userData;
    }

    /**
     * 建立使用者
     * @param array $data 使用者資料
     */
    public function create(array $data): array
    {
        try {
            $this->db->transStart();

            if (isset($data['characterName'])) {
                $data['character_name'] = $data['characterName'];
                unset($data['characterName']);
            }            

            if ($this->db->table('player')->insert($data) === false) {
                return ['error' => 'Failed to create user data'];
            }
            $userId = $this->db->insertID();

            $this->db->transComplete();
            return ['success' => true, 'message' => 'User created successfully', 'user_id' => $userId, 'server' => $data['server']];
        } catch (\Exception $e) {
            return ['error' => 'Failed to create user: ' . $e->getMessage()];
        }
    }

    /**
     * 刪除使用者
     * @param int $userId 使用者ID
     */
    public function deleteData($userId)
    {
        $builder = $this->db->table('player');

        if (is_array($userId)){
            $builder->whereIn('id', $userId);
        } else {
            $builder->where('id', $userId);
        }

        $builder->delete();
    }

    /**
     * 確認使用者資料
     */
    public function checkUser(array $data): array
    {
        $builder = $this->db->table('player');
        $builder->where('server', $data['server']);
        $builder->where('username', $data['username']);

        if (isset($data['characterName'])){
            $builder->where('character_name', $data['characterName']);
        }

        $userData = $builder->get()->getRowArray();

        if (empty($userData)) {
            return array(False);
        }

        return [True, $userData];
    }

    /**
     * 建立身分驗證紀錄
     */
    public function identifySubmitLog($data)
    {
        $builder = $this->db->table('identify_submit_log');
        $builder->insert($data);
    }

    /**
     * 更新信箱通知
     */
    public function updateEmailNotify($userId, $server, $emailNotify, $email=null)
    {
        $builder = $this->db->table('player');
        $builder->where('id', $userId);
        $builder->where('server', $server);
        $builder->update(['notify_email' => $emailNotify, 'email' => $email]);

        return True;
    }

    /**
     * 更新Line通知
     */
    public function updateLineNotify($userId, $server, $lineNotify)
    {
        $builder = $this->db->table('player');
        $builder->where('id', $userId);
        $builder->update(['notify_line' => $lineNotify]);
    }

    /**
     * 新增通知結果
     * @param int $promotionId 推廣資料Id
     * @param array $notifyData 通知資料
     */
    public function createNotifyResult($promotionId, $notifyData)
    {
        $insertData = array('promotion_id' => $promotionId);

        if ($notifyData['email']['status'] === True 
        && !($notifyData['email']['data'] === null)){
            $insertData['type'] = 'email';
            $insertData['content'] = $notifyData['email']['data'];
        }

        if ($notifyData['line']['status'] === True){
            $insertData['line'] = $notifyData['line']['data'];
        }
    }

    /**
     * 寄送Email
     * @param string $toEmail 收件者Email
     * @param string $subject 主旨
     * @param string $content 內容
     * @return bool 發送結果
     */
    public function sendEmail($toEmail, $subject, $content)
    {
        // 載入Email
        $email = \Config\Services::email();

        // $email->setDebug(true);
        // $config = $email->initialize();
        // var_dump($config);

        // $email->SMTPKeepAlive = true; // 保持連線

        // print_r(getenv('email.hostname')); die();

        // 設置Email
        $email->setTo($toEmail);
        $email->setFrom(getenv('email.fromEmail'), getenv('email.fromName'));        
        $email->setSubject($subject);
        $email->setMessage($content);
        $email->setHeader('Host', getenv('email.hostname'));
        
        // 紀錄Email
        $this->mailLog($toEmail, $subject, $content, $email->printDebugger(['headers']));

        // 發送郵件並檢查是否成功
        if ($email->send()) {
            return "信件發送成功！";
        } else {
            // 顯示錯誤資訊
            return "發送失敗：" . print_r($email->printDebugger(['headers']), true);
        }
    }

    private function mailLog($toEmail, $subject, $content, $status)
    {
        $data = array(
            'to_email' => $toEmail,
            'subject' => $subject,
            'content' => $content,
            'status' => $status,
        );

        $builder = $this->db->table('mail_log');
        $builder->insert($data);
    }

    /**
     * 取得使用者推廣狀態
     * @param int $playerId
     * @return void
     */
    public function fetchPromotionStatus($playerId){

        $result = array(
            'isFinished' => false,  // 是否完成推廣
            'cycle' => 0,           // 伺服器要求的推廣次數
            'nowFinished' => 0,     // 當前完成的推廣次數
            'serverCode' => '',     // 伺服器代碼
        );

        $playerData = $this->M_Model_Common->getData('player', ['id' => $playerId]);
        $serverCode = $playerData['server'];
        $serverData = $this->M_Model_Common->getData('server', ['code' => $serverCode]);
        $result['cycle'] = $serverData['cycle'];
        $result['serverCode'] = $serverCode;

        switch ($serverData['cycle']) {
            case 'daily':
                $where = "DATE(created_at) = CURDATE()"; // 當日
                break;    
            case 'weekly':
                $where = "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)"; // 當週
                break;    
            case 'monthly':
                $where = "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"; // 當月
                break;    
            default:
                return []; // 如果頻率無效，返回空陣列
        }

        $promotionData = $this->db->table('promotions')
            ->where('user_id', $playerId)
            ->where($where)
            ->where('status', 'success')
            ->get()
            ->getResultArray();

        if (empty($promotionData)){
            return $result;
        }

        $promotionIds = array_column($promotionData, 'id');

        $promotionItemData = $this->db->table('promotion_items')
            ->whereIn('promotion_id', $promotionIds)
            ->where('status', 'success')
            ->get()
            ->getResultArray();

        $result['nowFinished'] = count($promotionItemData);
        $result['isFinished'] = ($result['nowFinished'] >= $result['cycle']) ? true : false;

        return $result;
    }

    /**
     * 獎勵發送時間
     * @return void
     */
    public function fetchRewardTime($playerId, $serverCode)
    {
        // 當前時間
        $currentTime = date('Y-m-d');

        $rewardData = $this->db->table('reward')
            ->where('player_id', $playerId)
            ->where('server_code', $serverCode)
            ->where('DATE(created_at)', $currentTime)
            ->get()
            ->getRowArray();

        if (empty($rewardData)){
            return false;
        }

        $rewardTime = $rewardData['created_at'];

        return $rewardTime;
    }
}