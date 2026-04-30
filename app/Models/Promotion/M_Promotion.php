<?php

namespace App\Models\Promotion;
use CodeIgniter\Model;
use App\Models\M_Common as M_Model_Common;
use App\Models\Promotion\M_PromotionItem;
use App\Models\Promotion\M_CustomizedDb;
use App\Models\Promotion\M_Line;
use App\Models\Promotion\M_Player;
use App\Models\Promotion\M_Mail;

class M_Promotion extends Model
{
    protected $db;
    protected $table;
    protected $M_Model_Common;
    protected $M_PromotionItem;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');  // 預設資料庫
        $this->M_Model_Common = new M_Model_Common();
        $this->M_PromotionItem = new M_PromotionItem();
    }

    public function getData($where = [], $field = [], $queryMultiple = False, $join = [])
    {
        $table = 'promotions';
        $builder = $this->db->table($table);

        if (!empty($field)){
            $select = implode(',', $field);
            $builder->select($select);
        }
        
        if (!empty($join)){
            foreach ($join as $item) {
                $builder->join($item['table'], "{$item['table']}.{$item['field']} = {$table}.{$item['source_field']}");
            }
        }

        if (!empty($where)){
            $builder->where($where);
        }

        $data = ($queryMultiple) ? $builder->get()->getResultArray() : $builder->get()->getRowArray();

        return $data;
    }   

    /**
     * 建立推廣資料
     * @param array $data
     */
    public function create($data)
    {
        $where = array(
            'user_id' => $data['user_id'],
            'server' => $data['server'],
            'DATE(created_at)' => date('Y-m-d'),
        );
        $promotionData = $this->M_Model_Common->getData('promotions', $where, [], false);

        if (!empty($promotionData)){
            $promotionId = $promotionData['id'];

            $updateData = array(
                'status' => 'standby',
            );
            $this->db->table('promotions')
                ->where('id', $promotionId)
                ->update($updateData);

            // 如果已經有當天的推廣資料，則不再新增
            return $promotionId;
        }

        $this->db->table('promotions')
            ->insert($data);

        return $this->db->insertID();
    }

    /**
     * 更新推廣資料
     * @param int $promotionId 推廣資料Id
     * @param array $data
     */
    public function updateData($promotionId, $data)
    {
        $this->db->table('promotions')
            ->where('id', $promotionId)
            ->update($data);
    }

    /**
     * 刪除推廣資料
     * @param int $promotionId 推廣資料Id
     * @return void
     */
    public function deleteData($promotionId)
    {
        // 先刪除細項
        $this->M_PromotionItem->deleteData($promotionId);

        // 再刪除主資料
        $builder = $this->db->table('promotions');

        if (is_array($promotionId)){
            $builder->whereIn('id', $promotionId);
        } else {
            $builder->where('id', $promotionId);
        }

        $builder->delete();
    
        return True;
    }

    /**
     * 取得推廣資料(透過使用者ID)
     * @param int $userId User Id
     * @return array
     */
    public function getPromotion($userId)
    {
        $promotionData = $this->db->table('promotions')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        foreach ($promotionData as $_key => $_val) {
            $promotionData[$_key]['detail'] = $this->M_PromotionItem->getPromotionItem($_val['id']);
        }

        return $promotionData;  
    }

    public function getPromotionByFrequency($userId, $frequency, $type=null, $time=null)
    {
        $builder = $this->db->table('promotions');

        // 預設為現在時間
        if (empty($time)) {
            $time = date('Y-m-d H:i:s');
        }

        // 解析傳入時間
        $timestamp = strtotime($time);
        if ($timestamp === false) {
            return []; // 無效時間格式
        }

        $date = date('Y-m-d', $timestamp);
        $year = date('Y', $timestamp);
        $month = date('m', $timestamp);
        $week = date('oW', $timestamp); // o = ISO year, W = ISO week

        switch ($frequency) {
            case 'daily':
                $builder->where('DATE(created_at)', $date);
                break;

            case 'weekly':
                $builder->where("YEARWEEK(created_at, 1) = ", $week);  // 例如 202420
                break;

            case 'monthly':
                $builder->where('YEAR(created_at)', $year);
                $builder->where('MONTH(created_at)', $month);
                break;

            default:
                return []; // 頻率無效
        }
        
        $builder->where('user_id', $userId);

        if ($type === 'finished'){
            $builder->where('status', 'success');
        }

        $promotion = $builder->get()->getResultArray();

        if ($userId == 113){
            // print_r($type); die();
        }

        if (empty($promotion)){
            return 0;
        }

        $promotionIds = array_column($promotion, 'id');

        $builder = $this->db->table('promotion_items');
        $builder->whereIn('promotion_id', $promotionIds);

        if ($type === 'finished'){
            $builder->where('status', 'success');
        }
        
        $detail = $builder->get()->getResultArray();
        
        return count($detail);
    }

    /**
     * 取得推廣審核狀況
     * @param int $promotionId 推廣資料Id
     * @return array
     */
    public function getPromotionAudit($promotionId, $updateStatus = true)
    {
        // 取得該推廣項目細項
        $promotionDetail = $this->M_PromotionItem->getData(['promotion_id' => $promotionId], [], true);
        
        if (empty($promotionDetail)) {
            return [false, false, 'standby']; // 沒有推廣項目
        }
        
        // 該推廣項目審核結果
        $status = array_column($promotionDetail, 'status');

        // 審核結果
        $isFinished = false;    // 是否審核完成
        $auditResult = false;   // 審核結果
        $recommendedStatus = 'standby'; // 建議的狀態
        
        // 檢查是否還有待審核的項目
        if (!in_array('standby', $status)) {
            $isFinished = true;
            
            // 檢查是否有成功的項目
            if (in_array('success', $status)) {
                $auditResult = true;
                $recommendedStatus = 'success';
            } else {
                // 所有項目都是失敗狀態
                $auditResult = false;
                $recommendedStatus = 'failed';
            }
            
            // 只有在明確要求時才更新推廣狀態 (向後相容)
            if ($updateStatus) {
                $this->updateData($promotionId, ['status' => $recommendedStatus]);
            }
        }

        return [$isFinished, $auditResult, $recommendedStatus];
    }

    /**
     * 取得推廣通知資料
     * @param int $promotionId 推廣資料Id
     * @return array
     */
    public function getNotification($promotionId)
    {
        $where = array('promotions.id' => $promotionId);
        $join = array(
            array(
                'table' => 'server',
                'field' => 'code',
                'source_field' => 'server',
            ),
            array(
                'table' => 'player',
                'field' => 'id',
                'source_field' => 'user_id',
            ),
            // array(
            //     'table' => 'line',
            //     'field' => 'player_id',
            //     'source_field' => 'user_id',
            // ),
        );
        $field = array('*', 'promotions.id');
        $promotionData = $this->getData($where, $field, False, $join);

        // 取得Line資料，不使用JOIN是因為有可能玩家尚未綁定Line
        $line = $this->M_Model_Common->getData('line', ['player_id' => $promotionData['user_id']]);

        if (!empty($line)){
            $promotionData = array_merge($promotionData, $line);
        }

        // 通知資訊
        $notification = array(
            'email' => array(
                'status' => False,
                'data' => null,
            ),
            'line' => array(
                'status' => False,
                'data' => null,
            ),
        );

        // 判斷是否有開啟
        $notifyType = array('email', 'line');
        foreach ($notifyType as $type){
            if ($promotionData["notify_{$type}"] == '1'){
                $notification[$type]['status'] = True;

                // 使用者是否有提供資訊
                $type = ($type === 'email') ? 'email' : 'uid';
                if (isset($promotionData[$type]) && $promotionData[$type] != ''){
                    if ($type == 'uid'){
                        $notification['line']['data'] = $promotionData[$type];
                        continue;
                    }

                    $notification[$type]['data'] = $promotionData[$type];
                }
            }
        }

        return $notification;
    }

    /**
     * 批次審核推廣資料
     * @param array $promotionId 推廣資料Id
     * @param string $status 審核狀態
     * @return void
     */
    public function batchAudit($promotionId, $status)
    {
        // 取得未更新細項
        $where = array(
            'promotion_id' => $promotionId,
            'status' => 'standby',
        );
        $promotionDetails = $this->M_Model_Common->getData('promotion_items', $where, [], True);

        // 更新各細項資料
        foreach ($promotionDetails as $_val){
            $this->M_PromotionItem->updateData(['status' => $status], ['id' => $_val['id']]);
        }

        // 取得資料唯一值(帳號 + 角色)
        $promotionData = array();
        foreach ($promotionId as $_val){
            $promotion = $this->M_Model_Common->getData('promotions', ['id' => $_val]);
            $promotionData[] = array(
                'id' => $_val,
                'player_id' => $promotion['user_id'],
                'created_at' => $promotion['created_at'],
            );
        }

        // 更新主資料狀態
        $counts = array_count_values(array_column($promotionData, 'player_id'));      // 各資料筆數
        foreach ($promotionData as $_key => $_val){
            $auditData = $this->getPromotionAudit($_val['id']);
            [$isFinished, $auditResult] = $auditData;

            if ($isFinished === true){
                // 更新推廣結果
                $promotionStatus = ($auditResult === True) ? 'success' : 'failed';
                $this->updateData($_val['id'], ['status' => $promotionStatus]); 

                if ($auditResult === true){
                    // 進一步確認是否達標
                    $promotion = $this->M_Model_Common->getData('promotions', ['id' => $_val['id']]);
                    $userId = $promotion['user_id'];
                    $serverCode = $promotion['server'];
                    $server = $this->M_Model_Common->getData('server', ['code' => $serverCode]);
                    
                    // 當前進度
                    $nowSchedule = $this->getPromotionByFrequency($userId, $server['cycle'], 'finished', $_val['created_at']);

                    // 達標送禮
                    if ($nowSchedule >= $server['limit_number']){
                        $playerData = $this->M_Model_Common->getData('player', ['id' => $userId]);
                        $isReward = $this->checkReward($playerData['id'], $serverCode, $_val['created_at']);

                        if ($isReward === false){
                            $this->sendRewards($_val['id'], $serverCode, $playerData);
                            $this->sendNotification($_val['id'], $auditResult, $_val['player_id']);
                        }
                    }
                }

                // 發送通知
                // $playId = $_val['player_id'];
                // if ($counts[$playId] > 1){
                //     $lastIndex = array_search($playId, array_reverse($promotionId, true));
                //     $lastIndex = array_keys($promotionId)[$lastIndex];

                //     // 最後一筆才通知
                //     if ($_key == $lastIndex){
                //         $this->sendNotification($_val['id'], $auditResult, $playId);
                //     }
                // }else{
                //     $this->sendNotification($_val['id'], $auditResult, $playId);
                // }               
            }
        }
    }

    public function batchAuditV2($promotionId, $status)
    {
        // 引用Model
        $M_Player = new M_Player();

        // 推廣資料
        $where = array(
            'id' => $promotionId,
            'status' => 'standby',
        );
        $promotionData = $this->M_Model_Common->getData('promotions', $where, [], true);

        $playerIds = array_column($promotionData, 'user_id');
        $playerIds = array_unique($playerIds); // 取得唯一的玩家ID
        sort($playerIds); // 排序玩家ID

        // 依使用者分類推廣
        $data = array();
        foreach ($playerIds as $_key => $_val){
            foreach ($promotionData as $p_key => $p_val){
                if ($_val == $p_val['user_id']){
                    $data[$_val][] = $p_val['id'];
                }
            }
        }

        // 依使用者逐項推廣審核
        foreach ($data as $_key => $_val){
            // 各使用者的個別主項資料
            // foreach ($_val as $__key => $__val){
            //     // 取得明細資料
            //     $where = array(
            //         'promotion_id' => $__val,
            //         'status' => 'standby',
            //     );
            //     $promotionDetails = $this->M_Model_Common->getData('promotion_items', $where, [], true);

            //     // 更新各細項資料
            //     // foreach ($promotionDetails as $_val){
            //     //     $this->M_PromotionItem->updateData(['status' => $status], ['id' => $_val['id']]);
            //     // }

            //     // 取得主項資料
            //     $where = array(
            //         'id' => $__val,
            //         'status' => 'standby',
            //     );
            //     $promotionData = $this->M_Model_Common->getData('promotions', $where, [], true);

            //     // 更新主項資料
            //     foreach ($promotionData as $_key => $_val){
            //         $this->updateData($_val['id'], ['status' => $status]); 
            //     }
            // }

            // 確認使用者審核是否已完成
            $playerId = $_key;
            $promotionStatus = $M_Player->fetchPromotionStatus($playerId);

            if ($promotionStatus['isFinished'] === true){
                // 達標送禮
                $playerData = $this->M_Model_Common->getData('player', ['id' => $playerId]);
                $isReward = $this->checkReward($playerId, $promotionStatus['serverCode']);

                if ($isReward === false){
                    $this->sendRewards($_val[0], $promotionStatus['serverCode'], $playerData);
                }
            }
        }
    }

    public function batchAuditV3($promotionId, $status)
    {
        // 先確認為有效的promotion id
        $allPromotions = $this->M_Model_Common->getData('promotions', [], [], true);
        $allPromosionIds = array_column($allPromotions, 'id');

        // 有效ID
        $validPromotionIds = array_intersect($promotionId, $allPromosionIds);

        $tempLog = array();

        // 取得未更新細項
        $where = array(
            'promotion_id' => $validPromotionIds,
            'status' => 'standby',
        );
        $promotionDetails = $this->M_Model_Common->getData('promotion_items', $where, [], true);

        // 沒有資料不繼續動作
        if (empty($promotionDetails)){
            // 沒有需要更新的細項
            return array('code' => 0);
        }

        $promotionDetailIds = array_column($promotionDetails, 'id');
        $tempLog['promotionDetails'] = $promotionDetails;

        // 更新各細項資料
        $updateData = array('status' => $status);
        $where = array('id' => $promotionDetailIds);
        $this->M_PromotionItem->updateDataNew($updateData, $where);

        // 取得更新後細項資料
        // 修復 Bug1: 改為查詢「該推廣所有」success 細項（不限本批次），
        // 避免 limit_number > 本批次筆數時誤判為未達標
        $where = array(
            'promotion_id' => $validPromotionIds,
            'status' => 'success',
        );
        $updatedPromotionDetails = $this->M_Model_Common->getData('promotion_items', $where, [], true);

        // 修復: 不論成功或失敗，都需要繼續執行狀態更新邏輯，不能提前返回

        $checkPromotionData = array();      // 確認用的資料(主資料對細項資料)
        foreach ($validPromotionIds as $_val){
            $checkPromotionData[] = array(
                'id' => $_val,
                'detail' => array_filter($updatedPromotionDetails, function($item) use ($_val){
                    return $item['promotion_id'] == $_val;
                }),
            );
        }

        // 取得server資料
        $promotion = $this->db->table('promotions as p')
            ->join('server as s', 's.code = p.server')
            ->select('*, p.id')
            ->whereIn('p.id', $validPromotionIds)
            ->get()
            ->getResultArray();

        // 已完成的推廣資料
        $successPromotion = array();
        foreach ($checkPromotionData as $_val){
            $currentPromotionId = $_val['id'];
            $promotionData = array_filter($promotion, function($item) use ($currentPromotionId){
                return $item['id'] == $currentPromotionId;
            });
           
            $promotionData = reset($promotionData);
            $limit = $promotionData['limit_number'];

            if (count($_val['detail']) >= $limit){
                // 如果細項資料數量大於等於限制數量，則更新主項資料狀態
                $successPromotion[] = $_val;
            }
        }

        // 檢查各推廣的審核狀況並更新狀態 (修復: 所有審核完成的推廣都要更新狀態)
        $finalSuccessPromotionIds = [];
        $finalFailedPromotionIds = [];
        foreach ($validPromotionIds as $promId) {
            [$isFinished, $auditResult, $recommendedStatus] = $this->getPromotionAudit($promId, true);
            if ($isFinished) {
                if ($auditResult) {
                    $finalSuccessPromotionIds[] = $promId;
                } else {
                    $finalFailedPromotionIds[] = $promId;
                }
            }
        }
        
        // 成功推廣的主項ID 
        $successPromotionIds = $finalSuccessPromotionIds;   

        if (empty($successPromotionIds)){
            // 沒有成功的推廣資料
            return array('code' => 0);
        }

        // 主項目資料（使用明確 alias 避免 SELECT * JOIN 時 id 欄位被覆蓋）
        $promotionDataRaw = $this->db->table('promotions')
            ->join('server', 'server.code = promotions.server')
            ->join('player', 'player.id = promotions.user_id')
            ->select('promotions.id as promotion_id, promotions.user_id, promotions.server, promotions.status, promotions.created_at, player.username, player.character_name, server.limit_number, server.cycle')
            ->whereIn('promotions.id', $successPromotionIds)
            ->where('promotions.status', 'success')
            ->get()->getResultArray();

        // 建立 promotion_id => 資料 的快速查詢 map
        $promotionDataMap = [];
        foreach ($promotionDataRaw as $row) {
            $promotionDataMap[$row['promotion_id']] = $row;
        }

        // 處理成功的推廣資料 (發送獎勵與通知)
        // 修復 Bug2: 改為遍歷 $promotionDataMap（來源為 getPromotionAudit 的權威結果），
        // 避免 $successPromotion 與 $promotionDataMap 不一致時靜默跳過
        foreach ($promotionDataMap as $id => $filterData) {
            // 只有審核成功的推廣才處理後續邏輯
            if ($filterData['status'] === 'success'){
                // 發送獎勵（第一個參數傳 promotion_id 以便 rewardLog 正確記錄）
                $this->sendRewards($id, $filterData['server'], $filterData);
                // 只有審核成功時才發送通知
                $this->sendNotification($id, true, $filterData['user_id']);
            }
        }

        // 修復完成: 失敗的推廣項目狀態已經在 getPromotionAudit() 中自動更新，不需要額外處理

        return $tempLog;
    }

    // public function batchAuditV3($promotionId, $status)
    // {
    //     // ── 效能分析：開始計時（傳入 logId 啟用 504 即時回寫防護）──
    //     $logId = \App\Filters\ApiLogFilter::getCurrentLogId();
    //     \App\Libraries\AuditProfiler::begin($logId);

    //     // 確認輸入的 promotion id
    //     $allPromotions = $this->M_Model_Common->getData('promotions', [], [], true);
    //     $allPromosionIds = array_column($allPromotions, 'id');

    //     // 有效ID
    //     $validPromotionIds = array_intersect($promotionId, $allPromosionIds);

    //     \App\Libraries\AuditProfiler::mark('validate_ids');  // [區段 1] 全表掃 promotions + intersect

    //     $tempLog = array();

    //     // 取得未更新細項
    //     $where = array(
    //         'promotion_id' => $validPromotionIds,
    //         'status' => 'standby',
    //     );
    //     $promotionDetails = $this->M_Model_Common->getData('promotion_items', $where, [], true);

    //     \App\Libraries\AuditProfiler::mark('fetch_standby_items');  // [區段 2] 撈 promotion_items(standby)

    //     // 沒有資料無須更新
    //     if (empty($promotionDetails)){
    //         // 沒有需要審核的推廣項目
    //         return array('code' => 0);
    //     }

    //     $promotionDetailIds = array_column($promotionDetails, 'id');
    //     $tempLog['promotionDetails'] = $promotionDetails;

    //     // 更新細項資料
    //     $updateData = array('status' => $status);
    //     $where = array('id' => $promotionDetailIds);
    //     $this->M_PromotionItem->updateDataNew($updateData, $where);

    //     \App\Libraries\AuditProfiler::mark('update_items');  // [區段 3] batch UPDATE promotion_items

    //     // 取得更新後的細項資料（檢查是否為成功狀態，更新前後狀態）
    //     $where = array(
    //         'id' => $promotionDetailIds,
    //         'status' => 'success',
    //     );
    //     $updatedPromotionDetails = $this->M_Model_Common->getData('promotion_items', $where, [], true);

    //     \App\Libraries\AuditProfiler::mark('fetch_updated_items');  // [區段 4] 重撈 promotion_items(success)

    //     // 保留：下方的成功資料篩選會用到細項清單，此處不需再撈

    //     $checkPromotionData = array();      // 確認用的資料（含推廣項目細項資料）
    //     foreach ($validPromotionIds as $_val){
    //         $checkPromotionData[] = array(
    //             'id' => $_val,
    //             'detail' => array_filter($updatedPromotionDetails, function($item) use ($_val){
    //                 return $item['promotion_id'] == $_val;
    //             }),
    //         );
    //     }

    //     // 取得server資料
    //     $promotion = $this->db->table('promotions as p')
    //         ->join('server as s', 's.code = p.server')
    //         ->select('*, p.id')
    //         ->whereIn('p.id', $validPromotionIds)
    //         ->get()
    //         ->getResultArray();

    //     \App\Libraries\AuditProfiler::mark('fetch_server_data');  // [區段 5] JOIN promotions+server

    //     // 已通過審核的推廣資料
    //     $successPromotion = array();
    //     foreach ($checkPromotionData as $_val){
    //         $currentPromotionId = $_val['id'];
    //         $promotionData = array_filter($promotion, function($item) use ($currentPromotionId){
    //             return $item['id'] == $currentPromotionId;
    //         });

    //         $promotionData = reset($promotionData);
    //         $limit = $promotionData['limit_number'];

    //         if (count($_val['detail']) >= $limit){
    //             // 如果細項資料數大於等於限制，則視為通過審核的推廣資料
    //             $successPromotion[] = $_val;
    //         }
    //     }

    //     \App\Libraries\AuditProfiler::mark('calc_success_threshold');  // [區段 6] 計算門檻

    //     // 檢查各推廣審核邏輯並更新狀態（保留：向後相容於呼叫端依賴的狀態更新行為）
    //     // 流程：getPromotionAudit() 會針對一個推廣做一次 promotion_items 掃描及一次 UPDATE promotions
    //     $finalSuccessPromotionIds = [];
    //     $finalFailedPromotionIds = [];
    //     foreach ($validPromotionIds as $promId) {
    //         [$isFinished, $auditResult, $recommendedStatus] = $this->getPromotionAudit($promId, true);
    //         if ($isFinished) {
    //             if ($auditResult) {
    //                 $finalSuccessPromotionIds[] = $promId;
    //             } else {
    //                 $finalFailedPromotionIds[] = $promId;
    //             }
    //         }
    //     }

    //     \App\Libraries\AuditProfiler::mark('audit_loop');  // [區段 7] N+1：逐筆 getPromotionAudit (含 UPDATE)

    //     // 成功的推廣主 ID
    //     $successPromotionIds = $finalSuccessPromotionIds;

    //     if (empty($successPromotionIds)){
    //         // 沒有成功的推廣資料
    //         return array('code' => 0);
    //     }

    //     // 一次撈取所需資料，使用明確 alias 避免 SELECT * JOIN 後 id 欄位被覆蓋
    //     $promotionDataRaw = $this->db->table('promotions')
    //         ->join('server', 'server.code = promotions.server')
    //         ->join('player', 'player.id = promotions.user_id')
    //         ->select('promotions.id as promotion_id, promotions.user_id, promotions.server, promotions.status, promotions.created_at, player.username, player.character_name, server.limit_number, server.cycle')
    //         ->whereIn('promotions.id', $successPromotionIds)
    //         ->where('promotions.status', 'success')
    //         ->get()->getResultArray();

    //     \App\Libraries\AuditProfiler::mark('fetch_reward_data');  // [區段 8] JOIN promotions+server+player

    //     // 建立 promotion_id => 資料 的快取 map
    //     $promotionDataMap = [];
    //     foreach ($promotionDataRaw as $row) {
    //         $promotionDataMap[$row['promotion_id']] = $row;
    //     }

    //     // 對每筆成功的推廣資料發送獎勵（發通知並發獎）
    //     // 流程：逐筆並非建立批次，外部 DB 寫入及外部 HTTP Email/LINE 呼叫
    //     foreach ($successPromotion as $_val){
    //         $id = $_val['id'];

    //         if (!isset($promotionDataMap[$id])) {
    //             continue;
    //         }

    //         $filterData = $promotionDataMap[$id];

    //         // 審核通過才發送獎勵與通知
    //         if ($filterData['status'] === 'success'){
    //             // 發送獎勵（第一個參數為 promotion_id 以讓 rewardLog 正確記錄）
    //             $this->sendRewards($id, $filterData['server'], $filterData);
    //             \App\Libraries\AuditProfiler::mark("send_reward_{$id}");  // [區段 9+] 每筆外部 DB 寫入

    //             // 審核通過發送通知
    //             $this->sendNotification($id, true, $filterData['user_id']);
    //             \App\Libraries\AuditProfiler::mark("send_notify_{$id}");  // [區段 9+] 每筆 Email/LINE HTTP
    //         }
    //     }

    //     // 保留結論：失敗的推廣狀態已由 getPromotionAudit() 中處理，此處不需額外處理

    //     return $tempLog;
    // }

    /**
     * 補發獎勵
     */
    public function reissuanceRewards($promotionId)
    {
        $record = array();

        $where = array(
            'status' => 'success',
            'id' => $promotionId,
        );
        $promotion = $this->M_Model_Common->getData('promotions', $where, [], true);
        $record['promotion'] = $promotion;

        if (empty($promotion)){
            return false; // 沒有成功的推廣資料
        }

        $promotionIds = array_column($promotion, 'id');
        $reward = $this->M_Model_Common->getData('reward', ['promotion_id' => $promotionIds], [], true);
        $record['reward'] = $reward;

        $rewardPromotionIds = empty($reward) ? [] : array_column($reward, 'promotion_id');

        // 要補發的Promotion ids（尚未有 reward 記錄的）
        $reissuance = array_diff($promotionIds, $rewardPromotionIds);

        if (empty($reissuance)){
            return false; // 沒有需要補發的資料
        }

        foreach ($reissuance as $_val){
            $this->sendReissuanceRewards($_val);
        }

        return $record;
    }

    public function checkDb($code)
    {
        $M_CustomizedDb = new M_CustomizedDb($code);
        $check = $M_CustomizedDb->fetchData();

        return count($check) > 0 ? true : false;
    }

    /**
     * 寄送獎勵
     * @param string $serverCode 伺服器代碼
     * @param array $playerData 玩家資料
     * @return void
     */
    public function sendRewards($promotionId, $serverCode, $playerData)
    {
        // 連線至對方資料庫
        $M_CustomizedDb = new M_CustomizedDb($serverCode);
        $databaseData = $M_CustomizedDb->getDbInfo();

        // 寫入資料，預設一定有帳號
        $insertData = array($databaseData['account_field'] => $playerData['username']);

        // 角色欄位
        if ($databaseData['character_field'] != ''){
            $insertData[$databaseData['character_field']] = $playerData['character_name'];
        }

        // 寫入資料
        foreach ($M_CustomizedDb->getDbField() as $_val){
            $insertData[$_val['field']] = $_val['value'];
        }
        $insertId = $M_CustomizedDb->insertData($insertData); 

        // 紀錄獎勵發送紀錄
        $this->rewardLog($promotionId, $serverCode, $playerData, $insertData, $insertId);

        return true;
    }

    /**
     * 補發獎勵
     * @param array $promotionId
     * @return void
     */
    public function sendReissuanceRewards($promotionId)
    {
        // 推廣資料
        $promotion = $this->db->table('promotions')
            ->where('id', $promotionId)
            ->get()
            ->getRowArray();

        $serverCode = $promotion['server'];

        // 玩家資料
        $playerData = $this->db->table('player')
            ->where('id', $promotion['user_id'])
            ->get()
            ->getRowArray();

        // 連線至對方資料庫
        $M_CustomizedDb = new M_CustomizedDb($serverCode);
        $databaseData = $M_CustomizedDb->getDbInfo();

        // 寫入資料，預設一定有帳號
        $insertData = array($databaseData['account_field'] => $playerData['username']);

        // 角色欄位
        if ($databaseData['character_field'] != ''){
            $insertData[$databaseData['character_field']] = $playerData['character_name'];
        }

        // 寫入資料
        foreach ($M_CustomizedDb->getDbField() as $_val){
            $insertData[$_val['field']] = $_val['value'];
        }
        $insertId = $M_CustomizedDb->insertData($insertData); 

        // 紀錄獎勵發送紀錄
        $this->reissuanceRewardLog($promotionId, $playerData['id'], $serverCode, $insertData, $insertId);

        return true;
    }

    /**
     * 發送通知
     * @param int $promotionId 推廣資料Id
     * @param bool $auditResult 審核結果
     * @param int $playerId 玩家Id
     */
    public function sendNotification($promotionId, $auditResult, $playerId)
    {
        // 玩家資訊
        $playerData = $this->M_Model_Common->getData('player', ['id' => $playerId]);
        $account = $playerData['username'];
        $character = $playerData['character_name'];

        // 伺服器資訊
        $serverData = $this->M_Model_Common->getData('server', ['code' => $playerData['server']]);
        $server = $serverData['name'];

        // 預設通知結果
        $notifyResult = array(
            'email' => array(
                'status' => False,
                'msg' => '',
                'isFinished' => False,
            ),
            'line' => array(
                'status' => False,
                'msg' => '',
                'isFinished' => True,
            )
        );

        // 取得通知資訊
        $notificationData = $this->getNotification($promotionId);

        // 通知內容
        $mailText = ($auditResult === true) ? '已通過' : '未通過';

        // 通知
        $content = array();
        $content['line'] = "伺服器: {$server}\n";
        $content['line'] .= "帳號: {$account}\n";
        $content['line'] .= ($character != '') ? "角色: {$character}\n" : '';
        $content['line'] .= "您的推廣審核{$mailText}，請至PCGame 推廣審核系統查看審核結果";

        $content['mail'] = "<h3>伺服器: {$server}</h3>";
        $content['mail'] .= "<h3>帳號: {$account}</h3>";
        $content['mail'] .= ($character != '') ? "<h3>角色: {$character}</h3>" : '';
        $content['mail'] .= "<h3>您的推廣審核{$mailText}，請至PCGame 推廣審核系統查看審核結果</h3>";

        // Email 通知
        if ($notificationData['email']['status'] === true){
            if (!($notificationData['email']['data'] === null)){
                // 發送Email                
                $M_Mail = new M_Mail();
                $subject = '推廣審核完畢';
                $sendResult = $M_Mail->mailJet($notificationData['email']['data'], $subject, $content['mail']);

                // 更新通知結果
                $notifyResult['email']['status'] = true;
                $notifyResult['email']['msg'] = 'Email 發送成功';
                $notifyResult['email']['isFinished'] = ($sendResult === true) ? true : false;
            }
        }

        // Line 通知
        if ($notificationData['line']['status'] === true){
            if (!($notificationData['line']['data'] === null)){
                // 發送Line
                $M_Line = new M_Line();
                $M_Line->pushMessage($notificationData['line']['data'], $content['line']);

                // 更新通知結果
                $notifyResult['line']['status'] = true;
                $notifyResult['line']['msg'] = 'Line 發送成功';
                $notifyResult['line']['isFinished'] = true;
            }
        }
    }

    private function rewardLog($promotionId, $serverCode, $playerData, $insertData, $insertId)
    {
        // promotion_id: 直接用傳入的 $promotionId
        // player_id: 從 $playerData 中取 user_id（因為 batchAuditV3 使用明確 alias 的查詢結果）
        $playerId = isset($playerData['user_id']) ? $playerData['user_id'] : $playerData['id'];

        $rewardData = array(
            'promotion_id' => $promotionId,
            'player_id' => $playerId,
            'server_code' => $serverCode,
            'reward' => json_encode($insertData),
            'insert_id' => $insertId
        );

        $this->db->table('reward')
            ->insert($rewardData);

        $logData = array(
            'promotion_id' => $promotionId,
            'server_code' => $serverCode,
            'player_data' => json_encode($playerData),
            'insert_data' => json_encode($insertData),
        );

        $this->db->table('reward_log')
            ->insert($logData);
    }

    private function reissuanceRewardLog($promotionId, $playerId, $serverCode, $insertData, $insertId)
    {
        $insertData = array(
            'promotion_id' => $promotionId,
            'player_id' => $playerId,
            'server_code' => $serverCode,
            'reward' => json_encode($insertData),
            'insert_id' => $insertId
        );

        $this->db->table('reward')
            ->insert($insertData);
    }

    /**
     * 檢查玩家是否已領取獎勵
     * @param int $playerId 玩家Id
     * @param string $serverCode 伺服器代碼
     * @return bool
     */
    public function checkReward($playerId, $serverCode, $time=null)
    {
        $serverData = $this->M_Model_Common->getData('server', ['code' => $serverCode]);
        $frequency = $serverData['cycle'];

        $builder = $this->db->table('reward');

        // 預設為現在時間
        if (empty($time)) {
            $time = date('Y-m-d H:i:s');
        }

        // 解析傳入時間
        $timestamp = strtotime($time);
        if ($timestamp === false) {
            return []; // 無效時間格式
        }

        $date = date('Y-m-d', $timestamp);
        $year = date('Y', $timestamp);
        $month = date('m', $timestamp);
        $week = date('oW', $timestamp); // o = ISO year, W = ISO week

        switch ($frequency) {
            case 'daily':
                $builder->where('DATE(created_at)', $date);
                break;

            case 'weekly':
                $builder->where("YEARWEEK(created_at, 1) = ", $week);  // 例如 202420
                break;

            case 'monthly':
                $builder->where('YEAR(created_at)', $year);
                $builder->where('MONTH(created_at)', $month);
                break;

            default:
                return []; // 頻率無效
        }

        $builder->where('player_id', $playerId);
        $builder->where('server_code', $serverCode);

        $reward = $builder->get()->getRowArray();

        return (empty($reward)) ? false : true;     // true為已領取，false為未領取
    }

    /**
     * 查詢有問題的推廣（status=success 但無 reward 記錄）
     * @param string|null $serverCode 指定伺服器（null 表示全部）
     * @return array
     */
    public function getMissingRewardPromotions($serverCode = null)
    {
        $builder = $this->db->table('promotions p')
            ->join('player pl', 'pl.id = p.user_id')
            ->join('server s', 's.code = p.server')
            ->select('p.id as promotion_id, p.user_id, p.server, p.status, p.created_at as promotion_created_at, pl.username, pl.character_name, s.name as server_name')
            ->where('p.status', 'success')
            ->where('NOT EXISTS (SELECT 1 FROM reward r WHERE r.promotion_id = p.id)', null, false);

        if (!empty($serverCode)) {
            $builder->where('p.server', $serverCode);
        }

        return $builder->orderBy('p.created_at', 'ASC')->get()->getResultArray();
    }

    /**
     * 針對所有缺少 reward 的成功推廣進行補發
     * @param string|null $serverCode 指定伺服器（null 表示全部）
     * @return array 補發結果紀錄
     */
    public function reissueAllMissingRewards($serverCode = null)
    {
        $missing = $this->getMissingRewardPromotions($serverCode);

        $result = [
            'total'   => count($missing),
            'success' => 0,
            'failed'  => 0,
            'details' => [],
        ];

        foreach ($missing as $row) {
            $promotionId = $row['promotion_id'];
            $log = [
                'promotion_id'    => $promotionId,
                'username'        => $row['username'],
                'character_name'  => $row['character_name'],
                'server'          => $row['server'],
                'server_name'     => $row['server_name'],
                'promotion_date'  => $row['promotion_created_at'],
                'status'          => 'failed',
                'message'         => '',
            ];

            try {
                $this->sendReissuanceRewards($promotionId);
                $log['status']  = 'success';
                $log['message'] = '補發成功';
                $result['success']++;
            } catch (\Exception $e) {
                $log['message'] = $e->getMessage();
                $result['failed']++;
            }

            $result['details'][] = $log;
        }

        // 寫入補發批次紀錄
        if ($result['total'] > 0) {
            $this->db->table('reissue_batch_log')->insert([
                'total'      => $result['total'],
                'success'    => $result['success'],
                'failed'     => $result['failed'],
                'server_code'=> $serverCode ?? 'all',
                'detail'     => json_encode($result['details'], JSON_UNESCAPED_UNICODE),
            ]);
        }

        return $result;
    }
}