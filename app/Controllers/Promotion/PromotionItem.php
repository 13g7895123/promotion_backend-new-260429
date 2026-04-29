<?
namespace App\Controllers\Promotion;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\Response;
use CodeIgniter\API\ResponseTrait;
use App\Models\Promotion\M_Player;
use App\Models\Promotion\M_Promotion;
use App\Models\Promotion\M_PromotionItem;
use App\Models\Promotion\M_Line;
use App\Models\M_Common as M_Model_Common;
use App\Models\Promotion\M_CustomizedDb;

class PromotionItem extends BaseController
{
    use ResponseTrait;

    private $M_Promotion;
    private $M_PromotionItem;
    private $M_Model_Common;

    public function __construct()
    {
        $this->M_Promotion = new M_Promotion();
        $this->M_PromotionItem = new M_PromotionItem();
        $this->M_Model_Common = new M_Model_Common();
    }

    public function index($promotionId)
    {
        $data = $this->M_PromotionItem->getData(['promotion_id' => $promotionId], [], True);

        foreach ($data as $_key => $_val) {
            if ($_val['type'] === 'image') {
                $data[$_key]['content'] = base_url() . 'api/promotion/file/show/' . $_val['content'];
            }
        }

        return $this->response->setJSON($data);
    }

    public function create()
    {
        $postData = $this->request->getJson(True);

        $insertData = array(
            'promotion_id' => $postData['promotionId'],
            'type' => 'text',
            'content' => $postData['content'],
        );        

        $M_PromotionItem = new M_PromotionItem();
        $M_PromotionItem->create($insertData);

        $result = array(
            'success' => True,
            'msg' => '上傳成功',
        );

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    public function update($id)
    {
        $result = array('success' => False);
        $postData = $this->request->getJson(True);

        $updateData = array(
            'status' => $postData['status'],
        );

        // 取得推廣ID
        $detailData = $this->M_PromotionItem->getData(['id' => $id], [], False);
        $promotionId = $detailData['promotion_id'];

        // 推廣資料
        $promotionData = $this->M_Model_Common->getData('promotions', ['id' => $promotionId]);

        // 伺服器資料
        $serverData = $this->M_Model_Common->getData('server', ['code' => $promotionData['server']]);

        // 玩家資料
        $playerData = $this->M_Model_Common->getData('player', ['id' => $promotionData['user_id']]);

        // 確認資料庫存在
        $customizedDb = $this->M_Model_Common->getData('customized_db', ['server_code' => $serverData['code']]);
        if (empty($customizedDb)){
            $result = array(
                'success' => False,
                'msg' => '資料庫不存在',
            );

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        // 更新資料
        $this->M_PromotionItem->updateData($updateData, ['id' => $id]);

        // 推廣審核狀況
        $M_Promotion = new M_Promotion();
        $auditData = $M_Promotion->getPromotionAudit($promotionId);
        [$isFinished, $auditResult] = $auditData;

        // 完成審核
        if ($isFinished === True){
            if ($auditResult === true){
                // 進一步確認是否達標
                $promotion = $this->M_Model_Common->getData('promotions', ['id' => $promotionId]);
                $userId = $promotion['user_id'];
                $serverCode = $promotion['server'];
                $server = $this->M_Model_Common->getData('server', ['code' => $serverCode]);
                
                // 當前進度
                $nowSchedule = $this->M_Promotion->getPromotionByFrequency($userId, $server['cycle'], 'finished');

                // 達標送禮
                if ($nowSchedule >= $server['limit_number']){
                    $playerData = $this->M_Model_Common->getData('player', ['id' => $userId]);
                    // $isReward = $this->M_Promotion->checkReward($playerData['id'], $serverCode);

                    // if ($isReward === false){
                        $this->M_Promotion->sendRewards($promotionId, $serverCode, $playerData);

                        // ### 發送通知 ###
                        $this->M_Promotion->sendNotification($promotionId, $auditResult, $playerData['id']);
                    // }                    
                }
            }            
            
            // 更新推廣結果
            $promotionStatus = ($auditResult === True) ? 'success' : 'failed';
            $M_Promotion->updateData($promotionId, ['status' => $promotionStatus]);
        } 

        $result = array(
            'success' => True,
            'msg' => '更新成功',
            'is_finished' => $isFinished,
        );

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    public function checkUrl()
    {
        $postData = $this->request->getJson(True);
        $url = $postData['url'];

        $checkResult = $this->M_PromotionItem->checkUrl($url);

        $result = array(
            'success' => True,
            'msg' => '確認成功',
            'isExist' => $checkResult,
        );

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }
}