<?php

namespace App\Models\Promotion;

use CodeIgniter\Model;

class M_Line extends Model
{
    protected $db;
    private $tokenUrl;
    private $profileUrl;
    private $verifyUrl;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');  // 預設資料庫

        // Line API URL
        $config = $this->config();
        $lineUrl = $config['line']['url'];
        $lineBaseUrl = $lineUrl['base'];
        $this->tokenUrl = "{$lineBaseUrl}{$lineUrl['token']}";
        $this->profileUrl = "{$lineBaseUrl}{$lineUrl['profile']}";
        $this->verifyUrl = "{$lineBaseUrl}{$lineUrl['verify']}";
    }

    /* 新增資料 */
    public function createData($data)
    {
        $this->db->table('line')->insert($data);
        return $this->db->insertID();
    }

    /* 更新資料 */
    public function updateData($uid, $data)
    {
        $this->db->table('line')
            ->where('uid', $uid)
            ->update($data);
    }

    /* 保存Line資料 */
    public function saveData($data)
    {
        // 取得Line資料
        $condition = array(
            'player_id' => $data['player_id'],
            'uid' => $data['uid']
        );
        $lineData = $this->getLineData($condition);

        // 轉換Array Key
        $data['image_url'] = $data['image-url'];
        unset($data['image-url']);

        // 如果資料不存在，則新增資料
        if (empty($lineData)){
            $id = $this->createData($data);
            return $id;
        }

        // 如果資料存在，則更新資料
        $this->updateData($data['uid'], $data);

        // 回傳已存在資料ID
        return $lineData['id'];
    }

    /* 取得Line資料 */
    public function getLineData($condition, $multiData=False)
    {
        $builder = $this->db->table('line');
        $builder->where($condition);

        // 取得資料
        $data = ($multiData === True) ? $builder->get()->getResultArray() : $builder->get()->getRowArray();

        return $data;
    }

    /* 保存State碼 */
    public function saveState($state, $userId, $token)
    {
        // print_r($userId); die();
        $insertData = array(
            'state' => $state,
            'player_id' => $userId,
            'token' => $token,
        );
        // print_r($insertData); die();
        // $sql = $this->db->table('line_state')->set($insertData)->getCompiledInsert();
        // print_r($sql); die();
        $this->db->table('line_state')->insert($insertData);
    }

    /* 接收Line Callback */
    public function callback($state, $code, $friendshipStatusChanged = null)
    {
        $result = array('success' => False);

        try {
            /* 驗證state是否存在 */
            $lineState = $this->getLineState($state);
            if ($lineState === False){
                $result = array(
                    'success' => False,
                    'msg' => 'state不存在',
                );

                return $result;
            }

            // 前後端路徑
            $config = $this->config();
            $frontend = $config['frontend'];
            $backend = $config['backend'];
            $domainUrl = $frontend['linkMethod'] . '://' . $frontend['domain'];
            $apiDomainUrl = $backend['linkMethod'] . '://' . $backend['domain'];

            $frontendUrl = "{$domainUrl}/promotion/{$lineState['server']}/{$lineState['token']}";   // 導回前端
            $redirectUrl = $apiDomainUrl . '/api/promotion/line/callback';                          // Line導向路徑

            // 已加入好友，直接導回結束後頁面
            if ($friendshipStatusChanged !== null && $friendshipStatusChanged === false) {
                $result['success'] = True;
                $result['url'] = $frontendUrl;

                return $result;
            }

            // Line相關參數
            $lineCustomInfo = $config['line']['customInfo'];
            $robotInfo = array(
                'clientId' => $lineCustomInfo['clientId'],
                'clientSecret' => $lineCustomInfo['clientSecret'],
            );

            // CurlRequest
            $client = \Config\Services::curlrequest();

            // 取得Line AccessToken
            $response = $this->getAccessToken($code, $redirectUrl, $robotInfo, $client);

            if (!isset($response['access_token'])){
                $result['msg'] = '取得AccessToken失敗';
                return $result;
            }

            // 使用者Line資訊
            $lineInfo = array('player_id' => $lineState['player_id']);

            // 取得Line Profile
            [$uid, $name, $imageUrl] = $this->getProfile($response['access_token'], $client);
            $lineInfo['uid'] = $uid;
            $lineInfo['name'] = $name;
            $lineInfo['image-url'] = $imageUrl;

            // 取得Line Email
            $lineInfo['email'] = $this->getEmail($response['id_token'], $robotInfo['clientId'], $client);

            if ($lineInfo['uid'] == ''){
                $result['msg'] = '取得Line Profile失敗';
                return $result;
            }

            // 儲存Line資訊
            $lindId = $this->saveData($lineInfo);

            if ($lindId === False){
                $result['msg'] = '儲存Line資訊失敗';
                return $result;
            }

            // 更新資料
            $this->db->table('player')
                ->where('id', $lineState['player_id'])
                ->update(array('line_id' => $lindId));

            $result['success'] = True;
            $result['url'] = $frontendUrl;

            return $result;

        } catch (\Exception $e) {
            log_message('error', 'Line callback error: ' . $e->getMessage());
            $result['msg'] = '系統處理異常：' . $e->getMessage();
            return $result;
        }
    }

    /**
     * 取得LineAccessToken
     * @param str $code 授權碼
     * @param str $redirectUrl 重導向網址
     * @param array $robotInfo Line機器人資訊
     * @param object $client CurlRequest
     */
    public function getAccessToken($code, $redirectUrl, $robotInfo, $client)
    {
        $params = array(
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUrl,
            'client_id' => $robotInfo['clientId'],
            'client_secret' => $robotInfo['clientSecret'],
        );

        // 呼叫 api
        $response = $client->post($this->tokenUrl, [
            'form_params' => $params,
        ]);

        // api 回應
        $responseData = json_decode($response->getBody(), true);

        return $responseData;
    }

    /**
     * 取得Line Profile
     * @param str $accessToken AccessToken
     * @param object $client CurlRequest
     */
    public function getProfile($accessToken, $client)
    {
        $response = $client->get($this->profileUrl, [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
            ],
        ]);

        $responseData = json_decode($response->getBody(), true);

        $uid = $responseData['userId'] ?? '';
        $name = $this->sanitizeName($responseData['displayName'] ?? '');
        $imageUrl = $responseData['pictureUrl'] ?? '';

        return array($uid, $name, $imageUrl);
    }

    /**
     * 清理使用者名稱中的特殊字元
     * @param str $name 原始名稱
     * @return str 清理後的名稱
     */
    private function sanitizeName($name)
    {
        if (empty($name)) {
            return 'Line用戶';
        }

        // 移除 emoji 和特殊 Unicode 字元
        $name = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $name); // 表情符號
        $name = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $name); // 符號和圖示
        $name = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $name); // 交通和地圖符號
        $name = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $name);   // 雜項符號
        $name = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $name);   // 裝飾符號
        $name = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', $name);   // 變體選擇器
        $name = preg_replace('/[\x{1F900}-\x{1F9FF}]/u', '', $name); // 補充符號和圖示
        $name = preg_replace('/[\x{200D}]/u', '', $name);            // 零寬度連接符

        // 移除控制字元和不可見字元
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        // 去除多餘空白並限制長度
        $name = trim($name);
        $name = mb_substr($name, 0, 50, 'UTF-8'); // 限制最大長度為 50 字元

        // 如果清理後為空，使用預設名稱
        if (empty($name)) {
            $name = 'Line用戶';
        }

        return $name;
    }

    /**
     * 取得Line Email
     * @param str $idToken 身分驗證碼
     * @param str $clientId 機器人ID
     * @param object $client CurlRequest
     */
    public function getEmail($idToken, $clientId, $client)
    {
        $params = array(
            'id_token' => $idToken,
            'client_id' => $clientId,
        );

        $response = $client->post($this->verifyUrl, [
            'form_params' => $params,
        ]);

        $responseData = json_decode($response->getBody(), true);

        return $responseData['email'] ?? '';
    }

    /**
     * 推送訊息
     * @param str $userId 使用者ID
     * @param str $message 訊息
     */
    public function pushMessage($userId, $message)
    {
        $url = 'https://api.line.me/v2/bot/message/push';

        $config = $this->config();
        $channelAccessToken = $config['line']['customInfo']['channelAccessToken'];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $channelAccessToken
        ];

        $postData = [
            'to' => $userId,
            'messages' => [[
                'type' => 'text',
                'text' => $message
            ]]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("LINE API Error: " . $response);
        }

        return json_decode($response, true);
    }

    /**
     * Line相關參數
     */
    public function config()
    {
        $config = array(
            'frontend' => array(
                'linkMethod' => 'https',
                'domain' => 'cs.pcgame.tw',
            ),
            'backend' => array(
                'linkMethod' => 'https',
                'domain' => 'backend.pcgame.tw',
            ),            
            'line' => array(
                'customInfo' => array(
                    'clientId' => '2006388875',
                    'clientSecret' => '3bb67d9cca0ed5a34b28c21ebbc27281',
                    'channelAccessToken' => '0OLFZioGsoISKYxb1P8Aq2P1yTtS/MZU4/Hg4uoTRKR2hf/Uw40gJniI7uTeVMKtlG742jdWU105iRM09oR35tVuVH9owpDFpA6fuGNjmO+9ZTwYN/fTm+6qIYP3PwpfshllSxDQS+b+M/G6sGKXTAdB04t89/1O/w1cDnyilFU=',
                ),
                'url' => array(
                    'base' => 'https://api.line.me',
                    'token' => '/oauth2/v2.1/token',
                    'profile' => '/v2/profile',
                    'verify' => '/oauth2/v2.1/verify',
                ),
            ),
        );

        return $config;
    }

    /**
     * 取得Line State
     */
    public function getLineState($state)
    {
        $data = $this->db->table('line_state')
            ->join('player', 'player.id = line_state.player_id')
            ->select('*, player.id as uid')
            ->where('state', $state)
            ->get()
            ->getRowArray();

        return (!empty($data)) ? $data : False;
    }
}
