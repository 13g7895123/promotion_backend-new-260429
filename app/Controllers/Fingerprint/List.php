<?
namespace App\Controllers\Fingerprint;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\Response;
use CodeIgniter\API\ResponseTrait;
use App\Models\M_Common;

class List extends BaseController
{
    use ResponseTrait;

    public $M_Common;

    public function __construct()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);  
    }

    public function index()
    {
        
    }

    /**
     * 建立推廣資料
     * @return void
     */
    public function create()
    {
        $postData = $this->request->getJson(True);
        $promotion = array(
            'user_id' => $postData['user'],
            'server' => $postData['server'],
        );        

        $M_Promotion = new M_Promotion();
        $promotionId = $M_Promotion->create($promotion);

        $result = array(
            'success' => True,
            'msg' => '上傳成功',
            'promotionId' => $promotionId,
        );

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 刪除推廣資料
     * @return void
     */
    public function delete()
    {
        $postData = $this->request->getJson(True);
        $promotionId = $postData['id'];

        $M_Promotion = new M_Promotion();
        $M_Promotion->deleteData($promotionId);

        $result = array(
            'success' => True,
            'msg' => '刪除成功',
        );

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 批次審核
     * @return void
     */
    public function batchAudit()
    {
        $postData = $this->request->getJson(True);
        $promotionId = $postData['id'];
        $status = $postData['status'];

        $M_Promotion = new M_Promotion();
        $M_Promotion->batchAuditV3($promotionId, $status);

        // 補發獎勵
        $M_Promotion->reissuanceRewards($promotionId);

        $result = array(
            'success' => True,
            'msg' => '批次審核成功',
        );

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    public function batchAuditV2()
    {
        // $postData = $this->request->getJson(True);
        // $promotionId = $postData['id'];
        // $status = $postData['status'];

        $promotionId = ["541", "540", "539", "538", "537"];
        $status = "success";

        $M_Promotion = new M_Promotion();
        $M_Promotion->batchAuditV2($promotionId, $status);

        $result = array(
            'success' => True,
            'msg' => '批次審核成功',
        );

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    public function test()
    {

    }
}