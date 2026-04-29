<?
namespace App\Controllers\Promotion;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\Response;
use CodeIgniter\API\ResponseTrait;
use App\Models\Promotion\M_File;

class FileController extends BaseController
{
    use ResponseTrait;

    private $M_File;

    public function __construct()
    {
        $this->M_File = new M_File();
    }

    // 上傳檔案
    public function upload()
    {
        $file = $this->request->getFile('file');
        $fileId = $this->M_File->saveFile($file, 'images');

        if ($fileId === false) {
            return $this->fail('上傳失敗', 500);
        }

        return $this->respond(['id' => $fileId], 200);
    }
}
