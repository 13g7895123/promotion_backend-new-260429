<?php

namespace App\Models\Promotion;
use CodeIgniter\Model;
use App\Models\M_Common as M_Model_Common;

class M_Token extends Model
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
     * 建立新Token
     * @param   string    $server 伺服器
     * @param   int       $userId 使用者ID
     * @param   string    $page   頁面
     * @return  string    $token
     */
    public function getToken($server, $userId, $page)
    {
        $length = 20;
        $characters = 'abcdefghjklmnpqrstuvwxyz23456789';  // 排除 I, O, 1, 0
        $maxIndex = strlen($characters) - 1;
        
        do{
            $token = '';

            for ($i = 0; $i < $length; $i++) {
                $randomIndex = mt_rand(0, $maxIndex);
                $token .= $characters[$randomIndex];
            }

            $checkToken = $this->checkTokenExist($token);
        }while($checkToken === False);
        
        $insertData = array(
            'token' => $token,
            'server' => $server,
            'user_id' => $userId,
            'page' => $page,
        );
        $this->db->table('token')->insert($insertData);

        return $token;
    }

    /**
     * 確認Token是否存在
     * @param string $token Token
     * @return boolean
     */
    private function checkTokenExist($token, $isAdmin=False)
    {
        $table = ($isAdmin) ? 'admin_token' : 'token';

        $tokenData = $this->db->table($table)
            ->where('token', $token)
            ->get()
            ->getRowArray();

        return (empty($tokenData)) ? True : False;
    }

    /**
     * 取得Token資料
     * @param string $token Token
     * @return array
     */
    public function getTokenInfo($token)
    {
        $tokenData = $this->db->table('token')
            ->where('token', $token)
            ->get()
            ->getRowArray();

        return $tokenData;
    }

    /**
     * 建立後台Token
     * @param string $type 類型
     * @return string
     */
    public function createAdminToken($type, $refreshTokenId=null)
    {
        $length = 20;
        $characters = 'abcdefghjklmnpqrstuvwxyz23456789';  // 排除 I, O, 1, 0
        $maxIndex = strlen($characters) - 1;
        
        do{
            $token = '';

            for ($i = 0; $i < $length; $i++) {
                $randomIndex = mt_rand(0, $maxIndex);
                $token .= $characters[$randomIndex];
            }

            $checkToken = $this->checkTokenExist($token, True);
        }while($checkToken === False);

        $createTime = date('Y-m-d H:i:s');
        $expireTime = (strtolower($type) === 'access') ? date('Y-m-d H:i:s', strtotime('+1 hour')) : date('Y-m-d H:i:s', strtotime('+1 day'));

        $insertData = array(
            'type' => $type,
            'token' => $token,
            'created_at' => $createTime,
            'expired_at' => $expireTime,   
        );
        if ($type === 'access'){
            $insertData['refresh_token_id'] = $refreshTokenId;
        }
        $this->db->table('admin_token')->insert($insertData);
        $insertId = $this->db->insertID();

        return [$token, $expireTime, $insertId];
    }

    // public function checkAdminToken($authHeader)
    public function checkAdminToken($type, $token, $expireInFive=false)
    {
        // $authHeader = $this->request->getHeaderLine('Authorization');
        //     print_r(123); die();
        // }

        // public function checkAdminTokenExpired($type, $token, $expireInFive=false)
        // {
        $tokenData = $this->db->table('admin_token')
            ->where('token', $token)
            ->where('type', $type)
            ->get()
            ->getRowArray();

        if (empty($tokenData)){
            return true;
        }

        $expireTime = strtotime($tokenData['expired_at']);
        $currentTime = time();

        // 5分鐘內到期
        if ($expireInFive === true){
            if ($expireTime - $currentTime <= 5 * 60){
                return true;
            }

            return false;
        }

        if ($expireTime < $currentTime){
            return true;
        }
        
        return false;
    }

    /**
     * 取得新的Access Token資料
     */
    public function fetchNewAccessToken($accessToken)
    {
        $tokenData = $this->db->table('admin_token')
            ->where('token', $accessToken)
            ->where('type', 'access')
            ->get()
            ->getRowArray();

        if (empty($refreshTokenData)){
            return false;
        }

        // 取得Refresh Token
        $refreshTokenData = $this->db->table('admin_token')
            ->where('id', $tokenData['refresh_token_id'])
            ->get()
            ->getRowArray();

        $refreshToken = $refreshTokenData['token'];

        // 檢查Refresh Token是否到期
        $isExpired = $this->checkAdminToken('refresh', $refreshToken);

        if ($isExpired === true){
            return false;
        }
        
        // 建立新的Access Token
        $newAccessToken = $this->createAdminToken('access', $refreshTokenData['id']);

        return $newAccessToken;
    }
}