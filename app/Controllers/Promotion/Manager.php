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

class Manager extends BaseController
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
        $data = $this->M_Model_Common->getData('users', [], [], True);

        if (empty($data)) {
            $result['msg'] = '查無資料';
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
     * 新增使用者
     */
    public function create()
    {
        $result = array('success' => False);
        $postData = $this->request->getJSON(True);        

        $userId = $this->M_User->create($postData);

        $result['success'] = True;
        $result['msg'] = '新增成功';
        $result['user_id'] = $userId;

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

}