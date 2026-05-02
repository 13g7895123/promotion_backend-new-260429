<?
namespace App\Controllers\Promotion;

use App\Controllers\BaseController;
use App\Models\M_Common as M_Model_Common;
use App\Models\Promotion\M_Common;
use App\Models\Promotion\M_Player;
use App\Models\Promotion\M_Token;
use App\Models\Promotion\M_Promotion;
use App\Models\Promotion\M_Server;
use App\Models\Promotion\M_Line;
use App\Models\Promotion\M_User;

class Player extends BaseController
{
    protected $db;
    protected $response;
    protected $M_Common;
    protected $M_Player;
    protected $M_Token;
    protected $M_Promotion;
    protected $M_Server;
    protected $M_Line;
    protected $M_Model_Common;
    protected $M_User;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');
        $this->M_Common = new M_Common();
        $this->M_Player = new M_Player();
        $this->M_Token = new M_Token();
        $this->M_Promotion = new M_Promotion();
        $this->M_Server = new M_Server();
        $this->M_Line = new M_Line();
        $this->M_Model_Common = new M_Model_Common();
        $this->M_User = new M_User();
    }

    /**
     * 取得玩家資料
     */
    public function index()
    {
        $result = array('success' => False);
        $postData = $this->request->getJSON(True);

        // 權限查詢
        $where = [];
        if (isset($postData['user_id'])){
            $userServerPermission = $this->M_User->getServerPermission($postData['user_id']);
            if (!empty($userServerPermission)){
                $where['server'] = array_column($userServerPermission, 'code');
            }else{
                $where['server'] = '';
            }

            // 管理者不適用
            $userPermission = $this->M_User->getUserPermission($postData['user_id']);
            if ($userPermission['type'] === 'admin'){
                unset($where['server']);
            }
        }

        // ----------------------------------------------------------
        // 分頁參數（opt-in：有帶 page / per_page 就進入分頁模式）
        // 未帶任一分頁參數時維持舊行為（回傳完整 array），避免既有前端壞掉。
        // ----------------------------------------------------------
        $usePagination = isset($postData['page']) || isset($postData['per_page']);
        $page    = max(1, (int) ($postData['page'] ?? 1));
        $perPage = (int) ($postData['per_page'] ?? 20);
        if ($perPage < 1)   { $perPage = 20; }
        if ($perPage > 100) { $perPage = 100; }  // 上限保護，避免濫用

        if ($usePagination) {
            $buildBase = function () use ($where) {
                $builder = $this->db->table('player');
                foreach ($where as $_key => $_val) {
                    if (is_array($_val)) {
                        $builder->whereIn($_key, $_val);
                        continue;
                    }
                    $builder->where($_key, $_val);
                }
                return $builder;
            };

            // 先算總筆數
            $total = (int) $buildBase()->countAllResults();

            if ($total === 0) {
                return $this->response->setJSON([
                    'data' => [],
                    'pagination' => [
                        'page'        => $page,
                        'per_page'    => $perPage,
                        'total'       => 0,
                        'total_pages' => 0,
                    ],
                ]);
            }

            // 再抓該頁資料（依 id DESC，對齊原本 array_reverse 的排序結果）
            $builder = $buildBase();
            $builder->orderBy('player.id', 'DESC');
            $builder->limit($perPage, ($page - 1) * $perPage);
            $data = $builder->get()->getResultArray();
        } else {
            $data = $this->M_Model_Common->getData('player', $where, [], True);
        }

        if (empty($data)) {
            $result['msg'] = '查無資料';
        }

        $temp = [];
        try{
            foreach ($data as $_key => $_val) {
                $serverData = $this->M_Server->getServer(['code' => $_val['server']]);

                if (empty($serverData)) {
                    $temp[] = $_val;
                    $result['error'] = $_val;
                    $result['msg'] = '查詢失敗: 伺服器資料不存在';
                    continue;
                }

                $data[$_key]['server_info'] = $serverData;
                $data[$_key]['line'] = $this->M_Model_Common->getData('line', ['player_id' => $_val['id']], [], False);
    
                // 已推廣次數
                $frequency = $data[$_key]['server_info']['cycle'];
                $data[$_key]['promotion_count'] = $this->M_Promotion->getPromotionByFrequency($_val['id'], $frequency);
    
                // 取得獎勵發送時間
                $rewardTime = $this->M_Player->fetchRewardTime($_val['id'], $_val['server']);
                $data[$_key]['reward_time'] = ($rewardTime === false) ? '' : $rewardTime;
            }
        }catch(\Exception $e){
            $temp[] = $_val;
            $result['error'] = $_val;
            $result['msg'] = '查詢失敗: ' . $e->getMessage();
        }       

        if ($usePagination) {
            return $this->response->setJSON([
                'data'       => array_values($data),
                'pagination' => [
                    'page'        => $page,
                    'per_page'    => $perPage,
                    'total'       => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ]);
        }

        $result['success'] = True;
        $result['msg'] = '查詢成功';
        $result['data'] = array_reverse($data);

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 刪除玩家資料
     */
    public function delete()
    {
        $postData = $this->request->getJSON(True);

        $ids = $postData['id'] ?? null;
        if (empty($ids)) {
            return $this->response->setJSON(['success' => False, 'msg' => '缺少玩家 ID']);
        }

        $idList = is_array($ids) ? array_values($ids) : [$ids];
        $playerRows = $this->db->table('player')
            ->whereIn('id', $idList)
            ->get()
            ->getResultArray();

        $hasPromotions = $this->db->table('promotions')
            ->whereIn('user_id', $idList)
            ->countAllResults() > 0;

        if ($hasPromotions) {
            $promotionRows = $this->db->table('promotions')
                ->whereIn('user_id', $idList)
                ->get()
                ->getResultArray();

            (new \App\Models\M_ApiLog())->recordOperation('delete_blocked', '刪除玩家資料已阻擋：玩家已有推廣紀錄', [
                'table' => 'player',
                'requested_ids' => $idList,
                'matched_rows' => $playerRows,
                'related_promotions' => $promotionRows,
            ]);

            return $this->response->setJSON([
                'success' => False,
                'msg' => '玩家已有推廣紀錄，無法刪除。請保留玩家資料以維持派獎與查詢關聯。',
            ]);
        }

        $this->M_Player->deleteData($ids);

        (new \App\Models\M_ApiLog())->recordOperation('delete', '刪除玩家資料：' . count($playerRows) . ' 筆', [
            'table' => 'player',
            'requested_ids' => $idList,
            'deleted_rows' => $playerRows,
        ]);

        $result = array('success' => True);

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 提交資料(身分驗證)
     */
    public function submit()
    {
        $result = array('success' => False);
        $postData = $this->request->getJSON(True);

        $checkResult = $this->M_Player->checkUser($postData);
        [$success] = $checkResult;

        if (is_array($checkResult) && count($checkResult) == 2) {
            [$success, $userData] = $checkResult;
        }        

        // 如果使用者資料不存在，則建立使用者資料
        if ($success === False) {
            $createResult = $this->M_Player->create($postData);

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
     * 取得使用者ID
     * @param string $token Token
     */ 
    public function getPlayerInfo()
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

        $userData = $this->M_Player->getPlayerInfo($tokenData['user_id']);
        $promotionData = $this->M_Promotion->getPromotion($tokenData['user_id']);
        $lineData = $this->M_Line->getLineData(array('player_id' => $tokenData['user_id']));
        $serverData = $this->M_Server->getServer(['code' => $tokenData['server']]);

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
            $result['promotion'] = array_reverse($promotionData);   // 後面的資料在上面
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
                'finished' => $this->M_Promotion->getPromotionByFrequency($tokenData['user_id'], $serverData['cycle'], 'finished'),
                'used' => $this->M_Promotion->getPromotionByFrequency($tokenData['user_id'], $serverData['cycle']),
                'max' => $serverData['limit_number'],
                'cycle' => $serverData['cycle'],
            );
            $result['server'] = $serverData;
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

        $this->M_Player->updateEmailNotify($postData['user'], $postData['server'], $postData['emailNotify'], $postData['email']);
        
        $result = array(
            'success' => True,
            'msg' => '更新成功',
        );

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 更新Line通知
     */
    public function updateLineNotify()
    {
        $postData = $this->request->getJSON(True);
        $this->M_Player->updateLineNotify($postData['user'], $postData['server'], $postData['lineNotify']);

        $result = array('success' => True);

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 儲存state
     */
    public function saveState()
    {
        $postData = $this->request->getJSON(True);
        $this->M_Line->saveState($postData['state'], $postData['userId'], $postData['token']);

        $result = array('success' => True);

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    public function callback()
    {
        $getData = $this->request->getGet();

        // 判斷是否已加好友
        if (isset($getData['friendship_status_changed'])) {
            $result = $this->M_Line->callback($getData['state'], $getData['code'], $getData['friendship_status_changed']);
        }else{
            $result = $this->M_Line->callback($getData['state'], $getData['code']);
        }

        if ($result['success'] === False){
            echo $result['msg'];
            die();
        }

        header("Location: {$result['url']}");
    }

    public function testSendMail()
    {
        print_r($this->M_Player->sendEmail('13g7895123@gmail.com', 'test', 'test content'));
        die();
    }

    public function fetchReward()
    {
        $result = array('success' => False);
        $postData = $this->request->getJSON(True);
        $where = array();

        // 權限查詢
        if (isset($postData['user_id'])){
            $userServerPermission = $this->M_User->getServerPermission($postData['user_id']);
            if (!empty($userServerPermission)){
                $where['server_code'] = array_column($userServerPermission, 'code');
            }else{
                $where['server_code'] = '';
            }

            // 管理者不適用
            $userPermission = $this->M_User->getUserPermission($postData['user_id']);
            if ($userPermission['type'] === 'admin'){
                unset($where['server_code']);
            }
        }

        $join = array(
            array(
                'table' => 'player',
                'field' => 'id',
                'source_field' => 'player_id',
            ),
            array(
                'table' => 'server',
                'field' => 'code',
                'source_field' => 'server_code',
            ),
        );

        $rewardData = $this->M_Model_Common->getData('reward', $where, ['*', 'reward.created_at'], true, $join);

        // Sort by create_at in descending order
        usort($rewardData, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        $result['success'] = True;
        $result['msg'] = '查詢成功';
        $result['data'] = $rewardData;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 查詢有問題的推廣（status=success 但無 reward 記錄）
     * POST api/promotion/reward/missing
     * body: { "server_code": "zs" }  (可選，不傳則查全部)
     */
    public function missingReward()
    {
        $postData = $this->request->getJSON(true);
        $serverCode = $postData['server_code'] ?? null;

        $missing = $this->M_Promotion->getMissingRewardPromotions($serverCode);

        $result = [
            'success' => true,
            'msg'     => '查詢成功',
            'total'   => count($missing),
            'data'    => $missing,
        ];

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 對所有缺少 reward 的成功推廣進行補發
     * POST api/promotion/reward/reissue
     * body: { "server_code": "zs" }  (可選，不傳則補發全部)
     */
    public function reissueReward()
    {
        $postData = $this->request->getJSON(true);
        $serverCode = $postData['server_code'] ?? null;

        $reissueResult = $this->M_Promotion->reissueAllMissingRewards($serverCode);

        $result = [
            'success' => true,
            'msg'     => '補發完成',
            'data'    => $reissueResult,
        ];

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }
}