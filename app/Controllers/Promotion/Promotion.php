<?
namespace App\Controllers\Promotion;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\Response;
use CodeIgniter\API\ResponseTrait;
use App\Models\Promotion\M_Promotion;
use App\Models\Promotion\M_User;
use App\Models\M_Common;
use App\Models\Promotion\M_BatchAuditJob;

class Promotion extends BaseController
{
    use ResponseTrait;

    public $M_Promotion;
    public $M_Common;
    public $M_User;

    public function __construct()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $this->M_Common = new M_Common();
        $this->M_User = new M_User();
        $this->M_Promotion = new M_Promotion();        
    }

    public function index()
    {
        $postData = $this->request->getJson(true);
        $type = (isset($postData['type']) && $postData['type'] == 'all') ? 'all' : 'finished'; 
        $where = ($type == 'all') ? ['promotions.status' => 'standby'] : ['promotions.status !=' => 'standby'];

        // 權限查詢
        if (isset($postData['user_id'])){
            $userServerPermission = $this->M_User->getServerPermission($postData['user_id']);
            if (!empty($userServerPermission)){
                $where['promotions.server'] = array_column($userServerPermission, 'code');
            }else{
                $where['promotions.server'] = [''];
            }

            // 管理者不適用
            $userPermission = $this->M_User->getUserPermission($postData['user_id']);
            if ($userPermission['type'] === 'admin'){
                unset($where['promotions.server']);
            }
        }

        // ----------------------------------------------------------
        // 分頁參數（opt-in：有帶 page / per_page / search 就進入分頁模式）
        // 未帶任一分頁/搜尋參數時維持舊行為（回傳完整 array），避免既有前端壞掉。
        // ----------------------------------------------------------
        $usePagination = isset($postData['page']) || isset($postData['per_page']) || isset($postData['search']);
        $page     = max(1, (int) ($postData['page'] ?? 1));
        $perPage  = (int) ($postData['per_page'] ?? 20);
        if ($perPage < 1)   { $perPage = 20; }
        if ($perPage > 100) { $perPage = 100; }  // 上限保護，避免濫用

        // ----------------------------------------------------------
        // 搜尋參數（白名單制，詳見 docs/api-promotion-main-search.zh-tw.md）
        // ----------------------------------------------------------
        $searchAllowed = ['server', 'username', 'character_name', 'name'];
        $searchKeyword = trim((string) ($postData['search'] ?? ''));

        // 關鍵字長度上限 50
        if (mb_strlen($searchKeyword) > 50) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'msg'     => 'search keyword too long',
            ]);
        }

        // search_fields 驗證 + 白名單過濾
        $searchFieldsInput = $postData['search_fields'] ?? $searchAllowed;
        if (!is_array($searchFieldsInput)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'msg'     => 'search_fields must be an array',
            ]);
        }
        $searchFields = array_values(array_intersect($searchFieldsInput, $searchAllowed));
        if (empty($searchFields)) {
            $searchFields = $searchAllowed;
        }

        $useSearch  = ($searchKeyword !== '');
        $searchHitsName = $useSearch && in_array('name', $searchFields, true);

        $join = array(
            array(
                'table' => 'player',
                'field' => 'id',
                'source_field' => 'user_id',
            ),
        );

        if ($usePagination) {
            // 直接使用 Query Builder 以支援 LIMIT/OFFSET + COUNT
            $db = \Config\Database::connect('promotion');

            $buildBase = function () use ($db, $where, $join, $useSearch, $searchKeyword, $searchFields, $searchHitsName) {
                $builder = $db->table('promotions');
                foreach ($join as $item) {
                    $builder->join($item['table'], "{$item['table']}.{$item['field']} = promotions.{$item['source_field']}");
                }

                // 若搜尋命中 server 名稱，額外 LEFT JOIN server 表
                if ($searchHitsName) {
                    $builder->join('server', 'server.code = promotions.server', 'left');
                }

                foreach ($where as $_key => $_val) {
                    if (is_array($_val)) {
                        $builder->whereIn($_key, $_val);
                        continue;
                    }
                    $builder->where($_key, $_val);
                }

                // 搜尋條件：對白名單內欄位做 OR LIKE
                if ($useSearch) {
                    // 轉義 LIKE 的特殊字元（\, %, _）
                    $escaped = addcslashes($searchKeyword, '\\%_');
                    $fieldMap = [
                        'server'         => 'promotions.server',
                        'username'       => 'promotions.username',
                        'character_name' => 'promotions.character_name',
                        'name'           => 'server.name',
                    ];
                    $builder->groupStart();
                    $first = true;
                    foreach ($searchFields as $f) {
                        if (!isset($fieldMap[$f])) {
                            continue;
                        }
                        // 第 5 個參數 false：不讓 CI 再次 escape（已手動 escape）
                        if ($first) {
                            $builder->like($fieldMap[$f], $escaped, 'both', null, false);
                            $first = false;
                        } else {
                            $builder->orLike($fieldMap[$f], $escaped, 'both', null, false);
                        }
                    }
                    $builder->groupEnd();
                }

                return $builder;
            };

            // 先算總筆數
            $total = (int) $buildBase()->countAllResults();

            // 再抓該頁資料（依建立時間 DESC，對齊原本 array_reverse 的排序結果）
            $builder = $buildBase();
            $builder->select('*, promotions.id, promotions.created_at');
            $builder->orderBy('promotions.created_at', 'DESC');
            $builder->orderBy('promotions.id', 'DESC');
            $builder->limit($perPage, ($page - 1) * $perPage);
            $data = $builder->get()->getResultArray();
        } else {
            $data = $this->M_Common->getData('promotions', $where, ['*, promotions.id, promotions.created_at'], true, $join);
        }

        if (empty($data)){
            if ($usePagination) {
                return $this->response->setJSON([
                    'data' => [],
                    'pagination' => [
                        'page'        => $page,
                        'per_page'    => $perPage,
                        'total'       => $total ?? 0,
                        'total_pages' => 0,
                    ],
                ]);
            }
            return $this->response->setJSON([]);
        }

        $promotionIds = array_column($data, 'id');
        $promotionDetail = $this->M_Common->getData('promotion_items', ['promotion_id' => $promotionIds], [], True);

        $groupedPromotionDetail = [];
        foreach ($promotionDetail as $_val){
            $groupedPromotionDetail[$_val['promotion_id']][] = $_val;
        }

        $promotionServers = array_column($data, 'server');
        $server = $this->M_Common->getData('server', ['code' => $promotionServers], [], True);

        $groupedServer = [];
        foreach ($server as $_val){
            $groupedServer[$_val['code']] = $_val;
        }

        foreach ($data as $_key => $_val){
            unset($data[$_key]['password']);

            $promotionDetail = $groupedPromotionDetail[$_val['id']] ?? [];

            $links = $images = [];
            if (!empty($promotionDetail)){
                foreach ($promotionDetail as $d_key => $d_val){
                    // 取得第一個連結
                    if ($d_val['type'] == 'text'){
                        $links[] = array(
                            'link' => $d_val['content'],
                            'status' => $d_val['status'],
                        );
                        $firstLink = $d_val['content'];
                    }
                    // 取得第一個圖片
                    if ($d_val['type'] == 'image'){
                        $images[] = base_url() . 'api/promotion/file/show/' . $d_val['content'];
                    }
                }
            }

            $data[$_key]['promotion_detail']['link'] = $links;
            $data[$_key]['promotion_detail']['image'] = $images;

            $server = $groupedServer[$data[$_key]['server']] ?? [];
            if (!empty($server)){
                $data[$_key]['name'] = $server['name'];
                $data[$_key]['require_character'] = $server['require_character'];
            }
        }

        // 依時間排序
        usort($data, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']); 
        });
        $data = array_reverse($data);

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

        return $this->response->setJSON($data);
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
     * 批次審核（非同步入列）
     *
     * 將審核任務寫入 batch_audit_jobs 佇列，由 docker scheduler
     * 每分鐘執行 `php spark batch-audit:process` 來消費。
     * API 立即回傳 job_id，前端可透過 GET batch-audit/jobs/:id 查詢進度。
     */
    public function batchAudit()
    {
        $postData    = $this->request->getJson(true);
        $promotionId = $postData['id']     ?? [];
        $status      = $postData['status'] ?? '';

        if (empty($promotionId) || !is_array($promotionId)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'msg'     => 'id 必須為非空陣列',
            ]);
        }

        if (empty($status)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'msg'     => 'status 不可為空',
            ]);
        }

        $createdBy = (string) ($postData['user_id'] ?? $this->request->getIPAddress());

        $model = new M_BatchAuditJob();
        $jobId = $model->enqueue($promotionId, $status, $createdBy);

        $result = [
            'success' => true,
            'msg'     => '批次審核已入列，排程將於下一分鐘內執行',
            'job_id'  => $jobId,
        ];

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
        // $check = $this->M_Promotion->checkReward('312', 'ga', '2025-06-13 00:24:00');

        // if ($check === true){
        //     print_r(1); die();
        // }else{
        //     print_r(0); die();
        // }

        $test = ['5535', '5536', '5548'];
        $this->M_Promotion->reissuanceRewards($test);
    }
}