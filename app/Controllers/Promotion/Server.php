<?php

namespace App\Controllers\Promotion;

use App\Controllers\BaseController;
use App\Models\Promotion\M_Common;
use App\Models\Promotion\M_Server;
use App\Models\Promotion\M_File;
use App\Models\Promotion\M_User;
use App\Models\Promotion\M_Promotion;
use App\Models\Promotion\M_CustomizedDb;
use App\Models\M_Common as M_Common_Model;

class Server extends BaseController
{
    protected $db;
    protected $response;
    protected $M_Common;
    protected $M_Server;
    protected $M_User;
    protected $M_Common_Model;
    protected $M_File;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');
        
        $this->M_Server = new M_Server();
        $this->M_Common = new M_Common();
        $this->M_Common_Model = new M_Common_Model();
        $this->M_File = new M_File();
        $this->M_User = new M_User();    
    }

    public function index()
    {
        $data = $this->M_Common->index('server');
        $result = array('success' => False);

        // 資料轉換
        foreach ($data as $_key => $_val){
            $cycle = $_val['cycle'];
            if ($cycle == 'monthly'){
                $data[$_key]['cycle'] = '月';
            }else if ($cycle == 'weekly'){
                $data[$_key]['cycle'] = '週';
            }else if ($cycle == 'daily'){
                $data[$_key]['cycle'] = '日';
            }
        }

        if (empty($data)){
            $result['msg'] = '查無資料';

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        $result['success'] = True;
        $result['data'] = $data;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    // 建立伺服器
    public function create()
    {
        $data = $this->request->getJSON(True);
        // $data = $this->M_Common->convertFields('server', $data);
        // $data = $this->M_Common->convertSpecialField('server', 'require_character', $data);

        $insertId = $this->M_Common->create('server', $data);
        $result = array('success' => False);

        if ($insertId === False){
            $result['msg'] = '建立失敗';

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        // 建立預設圖片
        $this->M_Server->createDefaultImage($data['code'], 'icon');
        $this->M_Server->createDefaultImage($data['code'], 'background');

        $result['success'] = True;
        $result['msg'] = '建立成功';

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    // 更新伺服器
    public function update()
    {
        $data = $this->request->getJSON(True);

        $updateData = $data;
        unset($updateData['id']);
        
        $updateId = $this->db->table('server')
            ->where('id', $data['id'])
            ->update($updateData);

        $result = array('success' => False);

        if ($updateId === False){
            $result['msg'] = '更新失敗';
        }

        $result['success'] = True;
        $result['msg'] = '更新成功';

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    // 刪除伺服器
    public function delete()
    {
        $data = $this->request->getJSON(True);
        $deleteId = $this->M_Server->deleteData($data['id']);

        $result = array('success' => False);

        if ($deleteId === False){
            $result['msg'] = '刪除失敗';
        }

        $result['success'] = True;
        $result['msg'] = '刪除成功';

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    // 取得伺服器資料
    public function getServer()
    {
        $postData = $this->request->getJson(True);

        $where = [];
        if (!empty($postData)){
            foreach ($postData as $_key => $_val){
                $where[$_key] = $_val;

                $unsetArray = ['multiple', 'user_id'];
                if (in_array($_key, $unsetArray)){
                    unset($where[$_key]);
                }
            }
        }

        // 權限查詢
        if (isset($postData['user_id'])){
            $userServerPermission = $this->M_User->getServerPermission($postData['user_id']);
            if (!empty($userServerPermission)){
                $where['code'] = array_column($userServerPermission, 'code');
            }else{
                $where['code'] = '';
            }

            // 管理者不適用
            $userPermission = $this->M_User->getUserPermission($postData['user_id']);
            if ($userPermission['type'] === 'admin'){
                unset($where['code']);
            }
        }

        // 是否多筆查詢
        $queryMultiple = (!empty($postData)) ? False : True;
        $queryMultiple = (isset($postData['multiple'])) ? $postData['multiple'] : $queryMultiple;
        $data = $this->M_Server->getServer($where, [], $queryMultiple);
        
        $result = array('success' => False);

        if (empty($data)){
            $result['msg'] = '查無資料';

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        if ($queryMultiple === True){
            foreach ($data as $_key => $_val){
                // 伺服器圖片
                $serverImage = $this->M_Server->getSelectedImage($_val['code'], 'icon');
                $data[$_key]['server_image'] = '';
                if (!empty($serverImage)){
                    $data[$_key]['server_image'] = base_url() . 'api/promotion/file/show/' . $serverImage['file_id'];
                }

                // 背景圖片
                $backgroundImage = $this->M_Server->getSelectedImage($_val['code'], 'background');
                $data[$_key]['background_image'] = '';
                if (!empty($backgroundImage)){
                    $data[$_key]['background_image'] = base_url() . 'api/promotion/file/show/' . $backgroundImage['file_id'];
                }
            }
        }else{
            // 伺服器圖片
            $serverImage = $this->M_Server->getSelectedImage($data['code'], 'icon');
            $data['server_image'] = '';
            if (!empty($serverImage)){
                $data['server_image'] = base_url() . 'api/promotion/file/show/' . $serverImage['file_id'];
            }

            // 背景圖片
            $backgroundImage = $this->M_Server->getSelectedImage($data['code'], 'background');
            $data['background_image'] = '';
            if (!empty($backgroundImage)){
                $data['background_image'] = base_url() . 'api/promotion/file/show/' . $backgroundImage['file_id'];
            }
        }

        // 依建立時間排序
        // usort($data, function ($a, $b) {
        //     return $b['created_at'] <=> $a['created_at'];
        // });

        $result['success'] = True;
        $result['data'] = $data;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    public function getDataTableData()
    {
        $postData = $this->request->getJson(True);
        $data = $this->M_Server->getDataTableData($postData);
        $result = array('success' => False);

        if (empty($data)){
            $result['msg'] = '查無資料';

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        $result['success'] = True;
        $result['data'] = $data;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    // 取得單筆伺服器資料
    public function singleById()
    {
        $result = array('success' => False);
        $postData = $this->request->getJson(True);

        if (empty($postData['id'])) {
            $result['msg'] = '缺少必要參數 id';
            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        $data = $this->M_Common_Model->getData('server', ['id' => $postData['id']]);

        if (empty($data)){
            $result['msg'] = '查無資料';
            
            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        $result['success'] = True;
        $result['data'] = $data;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    // 取得資料庫資料
    public function getDatabase()
    {
        $result = array('success' => False);
        $postData = $this->request->getJson(True);  

        // 取得資料庫資料
        $data = $this->M_Common_Model->getData('customized_db', ['server_code' => $postData['code']]);

        // 取得欄位資料
        $fieldData = $this->M_Common_Model->getData('customized_field', ['server_code' => $postData['code']], [], true);

        // 取得資料庫資料
        $serverData = $this->M_Common_Model->getData('server', ['code' => $postData['code']]);
        
        unset($data['password']);
        $result['success'] = empty($data) ? False : True;
        $result['data'] = $data;
        $result['field'] = $fieldData;
        $result['server'] = $serverData;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);   
    }

    // 更新資料庫資料
    public function updateDatabase()
    {
        $result = array('success' => False);
        $data = $this->request->getJSON(True);
        unset($data['code']);   // 移除code

        // 確認資料存在
        $checkData = $this->M_Server->checkDatabase($data['server_code']);

        if ($checkData === True){
            // update
            $this->M_Server->updateDatabase($data);
            $content = '更新';
        }else{
            // insert
            $this->M_Server->insertDatabase($data);
            $content = '新增';
        }        

        $result['success'] = True;
        $result['msg'] = '更新成功';
        $result['content'] = $content;
        $result['test'] = $data;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    // 更新獎勵資料
    public function updateAward()
    {
        $result = array('success' => False);
        $data = $this->request->getJSON(True);

        // 更新資料庫資料
        $updateData = array(
            'server_code' => $data['server_code'],
            'account_field' => $data['account'],
            'character_field' => $data['character'],
        );
        $this->M_Server->updateDatabase($updateData);

        // 更新欄位
        $updateFieldData = array(
            'server_code' => $data['server_code'],
            'table' => $data['table'],
            'fields' => $data['fields'],
        );
        $this->M_Server->updateDatabaseField($updateFieldData);

        // 寫入對方資料庫
        // $M_CustomizedDb = new M_CustomizedDb($data['server_code']);
        // $insertData = array();
        // foreach ($data['fields'] as $_val){
        //     $insertData[$_val['name']] = $_val['value'];
        // }
        // $M_CustomizedDb->insertData($insertData);

        $result['success'] = True;
        $result['msg'] = '更新成功';

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    // 取得圖片
    public function getImage()
    {
        $result = array('success' => False);
        $postData = $this->request->getJson(True);
        $data = $this->M_Server->getImage($postData['server_code'], $postData['type']);

        if (empty($data)){
            $result['msg'] = '查無資料';

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        foreach ($data as $_key => $_val){
            $data[$_key]['path'] = base_url() . 'api/promotion/file/show/' . $_val['file_id'];
        }

        $result['success'] = True;
        $result['data'] = $data;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    // 上傳圖片
    public function uploadImage()
    {
        $result = array('success' => False);
        $file = $this->request->getFile('file');
        $fileId = $this->M_File->saveFile($file, 'images/promotion');

        if ($fileId === false) {
            $result['msg'] = '上傳失敗';

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        $postData = $this->request->getPost();
        $this->M_Server->createServerImage($postData, $fileId);

        $result['success'] = True;
        $result['msg'] = '上傳成功';

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    // 更新圖片
    public function updateImage()
    {
        $result = array('success' => False);
        $postData = $this->request->getJson(True);

        $this->M_Server->updateServerImage($postData);

        $result['success'] = True;
        $result['msg'] = '更新成功';

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    // 測試連線
    public function testConnection()
    {
        $result = array('success' => False);
        $postData = $this->request->getJson(True);

        $M_CustomizedDb = new M_CustomizedDb($postData['server_code']);
        $result = $M_CustomizedDb->testConnection();

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    public function fix()
    {
        $this->db->table('player')
            ->where('server', 'tdb2')
            ->delete();

        print_r('success'); die();

        $promotion = $this->M_Common_Model->getData('promotions', ['server' => 'tdb2'], [], true);

        foreach ($promotion as $_key => $_val){
            $M_Promotion = new M_Promotion();
            $M_Promotion->deleteData($_val['id']);
        }

        print_r('success'); die();
    }
}