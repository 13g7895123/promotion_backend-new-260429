<?
namespace App\Controllers\Fingerprint;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\Fingerprint\ListModel;
use App\Models\M_Common;

class Main extends BaseController
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
        $ListModel = new ListModel();
        $data = $ListModel->fetchFingerprint();

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($data);
    }

    /**
     * 建立資料
     * @return void
     */
    public function create()
    {
        
    }

    /**
     * 刪除資料
     * @return void
     */
    public function delete()
    {
        
    }

    public function test()
    {
        $ListModel = new ListModel();
        $ListModel->fetchPayed();
    }

    public function checkFingerprint()
    {
        $result = array('success' => false);
        $postData = $this->request->getJson(true);

        $ListModel = new ListModel();
        $updateFingerprint = $ListModel->updateFingerprint($postData['server'], $postData['account']);

        if ($updateFingerprint === false) {
            $result['success'] = true;
            $result['code'] = 99;
            $result['msg'] = '帳號不存在';

            $this->response->noCache();
            $this->response->setContentType('application/json');
            return $this->response->setJSON($result);
        }

        $checkAccountByFingerprint = $ListModel->checkAccountByFingerprint($postData['server'], $postData['account'], $postData['fingerprint']);
        
        $result['success'] = true;
        $result['is_paid'] = $checkAccountByFingerprint['is_paid'];
        $result['submit_time'] = $checkAccountByFingerprint['submit_time'];
        $result['continue'] = $checkAccountByFingerprint['continue'];
        $result['msg'] = $checkAccountByFingerprint['msg'] ?? '';
        $result['code'] = $checkAccountByFingerprint['code'];

        $this->response->noCache();
        $this->response->setContentType('application/json');
        return $this->response->setJSON($result);
    }

    /**
     * 建立指紋資料(初次)
     * @return void
     */
    public function createFingerprintAtFirst()
    {
        $ListModel = new ListModel();
        $ListModel->createFingerprint();
    }
}