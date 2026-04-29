<?
namespace App\Controllers\Promotion;

use App\Controllers\BaseController;
use App\Models\M_Common as M_Model_Common;
use App\Models\Promotion\M_Common;
use App\Models\Promotion\M_User;
use App\Models\Promotion\M_Token;
use App\Models\Promotion\M_Promotion;
use App\Models\Promotion\M_Server;
use App\Models\Promotion\M_Line;

class User extends BaseController
{
    protected $db;
    protected $response;
    protected $M_Common;
    protected $M_User;
    protected $M_Token;
    protected $M_Promotion;
    protected $M_Server;
    protected $M_Line;
    protected $M_Model_Common;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');
        $this->M_Common = new M_Common();
        $this->M_User = new M_User();
        $this->M_Token = new M_Token();
        $this->M_Promotion = new M_Promotion();
        $this->M_Server = new M_Server();
        $this->M_Line = new M_Line();
        $this->M_Model_Common = new M_Model_Common();
    }

    /**
     * 取得使用者資料
     */
    public function index()
    {
        $result = array('success' => False);
        $postData = $this->request->getJSON(True);

        $where = array('type !=' => 'admin');
        if (isset($postData['id'])){
            $where['id'] = $postData['id'];
        }

        $data = $this->M_Model_Common->getData('users', $where, [], True);

        foreach ($data as $_key => $_val) {
            $data[$_key]['server'] = [];
            $server = $this->M_User->getServerPermission($_val['id']);

            if (!empty($server)) {
                $data[$_key]['server'] = $server;
            }
        }

        if (empty($data)) {
            $result['msg'] = '查無資料';
        }

        foreach ($data as $_key => $_val) {
            unset($data[$_key]['password']);
        }

        // 依建立時間排序
        usort($data, function ($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });

        $result['success'] = True;
        $result['msg'] = '查詢成功';
        $result['data'] = $data;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 取得使用者資料(透過條件查詢)
     * 說明：後台使用者編輯使用，取得第一個使用者資料與對應伺服器權限
     */
    public function condition()
    {
        $result = array('success' => False);
        $postData = $this->request->getJSON(True);

        $data = $this->M_Model_Common->getData('users', $postData, []);
        $permissionServer = $this->M_Model_Common->getData('user_server_permissions', ['user_id' => $postData['id']], [], True);

        // 取得使用者對應伺服器權限
        $data['server'] = (!empty($permissionServer)) ? array_column($permissionServer, 'server_code') : [];

        // 移除密碼
        unset($data['password']);

        $result['success'] = True;
        $result['msg'] = '查詢成功';
        $result['data'] = $data;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 新增使用者
     */
    public function create()
    {
        $result = array('success' => False);
        $postData = $this->request->getJSON(True);        

        $userId = $this->M_User->create($postData);

        if ($userId === 0) {
            $result['msg'] = '帳號已被使用';
            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        $result['success'] = true;
        $result['msg'] = '新增成功';
        $result['user_id'] = $userId;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 更新使用者
     */
    public function update()
    {
        $result = array('success' => False);
        $postData = $this->request->getJSON(True);        

        $userId = $this->M_User->updateData($postData);

        $result['success'] = True;
        $result['msg'] = '更新成功';

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 刪除使用者
     */
    public function delete()
    {
        $result = array('success' => False);
        $postData = $this->request->getJSON(True);

        $userId = $this->M_User->deleteData($postData['id']);

        if ($userId === 0) {
            $result['msg'] = '刪除失敗';
            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        $result['success'] = True;
        $result['msg'] = '刪除成功';

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 取得管理者資料
     */
    public function getManager()
    {
        $result = array(
            'success' => false,
            'logout' => false,
        );
        $authHeader = $this->request->getHeaderLine('Authorization');
        $accessToken = '';

        if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $accessToken = $matches[1];
        }

        if (empty($accessToken) || $accessToken == '' || $accessToken == 'null') {
            $result['msg'] = 'Token不存在';
            $result['logout'] = true;

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        $isExpired = $this->M_Token->checkAdminToken('access', $accessToken);

        if ($isExpired === true){
            $result['msg'] = 'Token已過期';
            $result['logout'] = true;

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        // 確認是否五分內過期，是的話要驗證refresh token，並重新取得access token
        $isExpiredInFive = $this->M_Token->checkAdminToken('access', $accessToken, true);

        if ($isExpiredInFive === true){
            $newToken = $this->M_Token->fetchNewAccessToken($accessToken);

            if ($newToken !== false){
                $result['accessToken'] = $newToken;
            }
        }

        $data = $this->M_Model_Common->getData('users', ['type' => 'admin'], [], true);

        if (empty($data)) {
            $result['msg'] = '查無資料';

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        foreach ($data as $_key => $_val) {
            unset($data[$_key]['password']);
        }

        $result['success'] = True;
        $result['msg'] = '查詢成功';
        $result['data'] = $data;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 更新管理者
     */
    public function updateManager()
    {
        $result = array('success' => False);
        $postData = $this->request->getJSON(True);        

        $userId = $this->M_User->updateManager($postData);

        $result['success'] = True;
        $result['msg'] = '更新成功';

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 提交資料(身分驗證)
     */
    public function submit()
    {
        $postData = $this->request->getJSON(True);
        $checkResult = $this->M_User->checkUser($postData);
        $result = array('success' => False);
        [$success] = $checkResult;

        if (is_array($checkResult) && count($checkResult) == 2) {
            [$success, $userData] = $checkResult;
        }        

        // 如果使用者資料不存在，則建立使用者資料
        if ($success === False) {
            $createResult = $this->M_User->create($postData);

            // 如果建立使用者資料失敗，則回傳錯誤訊息
            if (isset($createResult['error'])) {
                $result['msg'] = $createResult['error'];

                $this->response->noCache();
                $this->response->setContentType('application/json');
                return $this->response->setJSON($result);
            }

            // 如果建立使用者資料成功，則回傳使用者ID
            $result['success'] = True;
            $result['msg'] = '建立成功';
            $result['user_id'] = $createResult['user_id'];
            $result['token'] = $this->M_Token->getToken($postData['server'], $createResult['user_id'], 'promotion');

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        // 如果使用者資料存在，則回傳使用者ID
        $result['success'] = True;
        $result['msg'] = '使用者資料已存在';
        $result['user_id'] = $userData['id'];
        $result['server'] = $userData['server'];
        $result['token'] = $this->M_Token->getToken($userData['server'], $userData['id'], 'promotion');

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 確認使用者資料
     * @param array $data 使用者資料
     */
    public function checkUser($data)
    {
        // 確認使用者資料是否存在
        $checkResult = $this->M_User->checkUser($data);

        // 如果使用者資料不存在，則建立使用者資料
        if ($checkResult === False) {
            $this->M_User->createUser($data);
        }

        $result = array(
            'success' => True,
            'message' => 'User data is valid',
        );

        return $this->response->setJSON($result);
    }

    /**
     * 取得使用者ID
     * @param string $token Token
     */ 
    public function getUserInfo()
    {
        $result = array('success' => False);
        $postData = $this->request->getJSON(True);
        $token = $postData['token'];

        $tokenData = $this->M_Token->getTokenInfo($token);

        if (empty($tokenData)) {
            $result['msg'] = 'Token不存在';

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        $userData = $this->M_User->getUserInfo($tokenData['user_id']);
        $promotionData = $this->M_Promotion->getPromotion($tokenData['user_id']);
        $lineData = $this->M_Line->getLineData(array('user_id' => $tokenData['user_id']));
        $serverData = $this->M_Server->getServer($tokenData['server']);

        $result['success'] = True;
        $result['msg'] = 'Token存在';

        if (!empty($userData)) {
            $result['user'] = array(
                'id' => $userData['id'],
                'email' => empty($userData['email']) ? '' : $userData['email'],
                'notify_email' => $userData['notify_email'],
                'notify_line' => $userData['notify_line'],
            );
        }

        if (!empty($promotionData)) {
            $result['promotion'] = $promotionData;
        }

        if (!empty($lineData)) {
            // 移除要隱藏的資料
            $unsetFields = array('id', 'created_at', 'uid', 'email');
            foreach ($unsetFields as $_val) {
                unset($lineData[$_val]);
            }

            $result['line'] = $lineData;
        }

        if (!empty($serverData)) {
            // 取得使用者推廣狀態
            $result['promotion_status'] = array(
                'used' => $this->M_Promotion->getPromotionByFrequency($tokenData['user_id'], $serverData['cycle']),
                'max' => $serverData['limit_number'],
                'cycle' => $serverData['cycle'],
            );
        }

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 更新信箱通知
     */
    public function updateEmailNotify()
    {
        $postData = $this->request->getJSON(True);

        $this->M_User->updateEmailNotify($postData['user'], $postData['server'], $postData['emailNotify'], $postData['email']);
        
        $result = array('success' => True);

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    public function login()
    {
        $result = array('success' => False);
        $postData = $this->request->getJSON(True);
        $loginResult = $this->M_User->login($postData['account'], $postData['password']);

        if ($loginResult['success'] === False) {
            $result['msg'] = $loginResult['message'];

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        $refreshTokenData = $this->M_Token->createAdminToken('refresh');
        $accessTokenData = $this->M_Token->createAdminToken('access', $refreshTokenData[2]);

        $result['success'] = True;
        $result['msg'] = '登入成功';
        $result['user'] = $loginResult['user'];
        $result['token'] = array(
            'access' => $accessTokenData[0],
            'access_expired_at' => $accessTokenData[1],
            'refresh' => $refreshTokenData[0],
            'refresh_expired_at' => $refreshTokenData[1],
        );

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    public function test()
    {
        $content = "<h1>Promotion Test</h1><p>test</p>";
        print_r($this->M_User->sendEmail('13gt7895123@gmail.com', 'Promotion Test', $content)); die();

        $result = array('success' => True);
        
        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }
}