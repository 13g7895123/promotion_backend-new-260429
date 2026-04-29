<?php

namespace App\Models\Fingerprint;
use CodeIgniter\Model;
use App\Models\Fingerprint\CustomizedDb;

class ListModel extends Model
{
    protected $db;
    protected $table;

    public function __construct()
    {
        $this->db = \Config\Database::connect('fingerprint');  // 預設資料庫
    }

    public function fetchFingerprint()
    {
        $data = $this->db->table('fingerprint')
            ->join('servers', 'servers.id = fingerprint.server', 'left')
            ->join('fingerprint_items', 'fingerprint_items.fingerprint_id = fingerprint.id', 'left')
            ->select('fingerprint.*, servers.names as server_name, fingerprint_items.submit_time, fingerprint_items.fingerprint')
            ->get()
            ->getResultArray();

        return $data;
    }

    /**
     * 更新該帳號指紋狀態(流程2.0)
     * @param string $server 伺服器
     * @param string $account 帳號
     * @return bool 更新結果
     */
    public function updateFingerprint($server, $account)
    {
        log_message('debug', 'updateFingerprint: ' . $server . ' - ' . $account);

        // 確認帳號是否存在
        $checkAccountExists = $this->checkAccountExists($server, $account);

        if ($checkAccountExists === false) {
            return false;
        }

        // 該帳號是否已繳費
        $isPaid = $this->fetchAccountPaid($server, $account);

        // 該帳號是否已建立指紋
        $checkFingerprintExists = $this->checkFingerprintExists($server, $account);

        // 該帳號未建立指紋
        if ($checkFingerprintExists === false) {
            log_message('debug', 'updateFingerprint: ' . $server . ' - ' . $account . ' - 未建立指紋');

            $insertData = array(
                'server' => $server,
                'account' => $account,
                'is_paid' => ($isPaid === true) ? '1' : '0',
            );

            $this->db->table('fingerprint')
                ->insert($insertData);

            return;
        }

        // 該帳號已建立指紋
        log_message('debug', 'updateFingerprint: ' . $server . ' - ' . $account . ' - 已建立指紋');
        $updateData = array(
            'is_paid' => ($isPaid === true) ? '1' : '0',
        );

        $this->db->table('fingerprint')
            ->where('server', $server)
            ->where('account', $account)
            ->update($updateData);

        return true;
    }

    public function checkAccountByFingerprint($server, $account, $fingerprint)
    {
        $result = array(
            'is_paid' => false,
            'submit_time' => '',
            'continue' => false,    // 可否繼續
        );

        $fingerprintData = $this->db->table('fingerprint')
            ->where('server', $server)
            ->where('account', $account)
            ->get()
            ->getRowArray();

        $fingerprintItem = $this->db->table('fingerprint_items')
            ->where('fingerprint_id', $fingerprintData['id'])
            ->get()
            ->getRowArray();

        // 空的則建立資料
        if (empty($fingerprintItem)) {
            $insertData = array(
                'fingerprint_id' => $fingerprintData['id'],
                'fingerprint' => $fingerprint,
            );

            // 未有繳費資料要加入送出時間
            if ($fingerprintData['is_paid'] === '0') {
                $insertData['submit_time'] = date('Y-m-d H:i:s');
                $result['submit_time'] = $insertData['submit_time'];
                $result['code'] = 1;
            }

            $this->db->table('fingerprint_items')->insert($insertData);

            $result['is_paid'] = ($fingerprintData['is_paid'] == '0') ? false : true;
            $result['continue'] = true;
            $result['code'] = 2;

            return $result;
        }

        // 有資料先判斷細項是否有送出時間
        if (!empty($fingerprintItem['submit_time'])) {
            if ($fingerprintData['is_paid'] === '0') {
                $result['is_paid'] = false;
                
                $checkExpired = $this->checkExpired($fingerprintItem['submit_time']);
                $result['submit_time'] = $fingerprintItem['submit_time'];
                $result['msg'] = '限制時間內，禁止再次送出表單，請於' . date('Y-m-d H:i:s', strtotime($fingerprintItem['submit_time'] . ' +30 minutes')) . '後再送出';
                $result['code'] = 3;
                

                // 過期了給新的時間
                if ($checkExpired === true) {
                    $result['submit_time'] = date('Y-m-d H:i:s');
                    $result['continue'] = true;
                    $result['msg'] = '';
                    $result['code'] = 4;

                    $this->db->table('fingerprint_items')
                        ->where('fingerprint_id', $fingerprintData['id'])
                        ->update(array('submit_time' => $result['submit_time']));
                }

                return $result;
            }

            $result['is_paid'] = true;
            return $result;
        }

        if ($fingerprintData['is_paid'] === '0') {
            $result['is_paid'] = false;

            $checkExpired = $this->checkExpired($fingerprintItem['submit_time']);
            $result['submit_time'] = $fingerprintItem['submit_time'];
            if ($checkExpired === true) {
                $result['submit_time'] = date('Y-m-d H:i:s');
            }

            return $result;
        }

        return $result;
    }

    /**
     * 檢查是否過期
     * @param [type] $submitTime
     * @return void
     */
    public function checkExpired($submitTime)
    {
        // 取得現在時間
        $now = strtotime(date('Y-m-d H:i:s'));
        // 取得提交時間
        $submit = strtotime($submitTime);
        // 計算時間差(秒)
        $diff = $now - $submit;
        // 30分鐘 = 1800秒
        if ($diff > 30 * 60) {
            return true;
        }

        return false;
    }

    /**
     * 確認帳號是否存在
     * @param string $server 伺服器
     * @param string $account 帳號
     * @return bool 檢查結果
     */
    public function checkAccountExists($server, $account)
    {
        $CustomizedDb = new CustomizedDb($server);
        return $CustomizedDb->checkAccountExists($account);
    }

    /**
     * 取得該帳號已繳費資料
     * @param string $server 伺服器
     * @param string $account 帳號
     * @return array 該帳號已繳費資料
     */
    public function fetchAccountPaid($server, $account)
    {
        $data = $this->db->table('servers_log')
            ->where('serverid', $server)
            ->where('gameid', $account)
            ->where('RtnCode', '1')
            ->where('RtnMsg', '交易成功')
            ->get()
            ->getRowArray();

        return (!empty($data)) ? true : false;
    }

    /**
     * 檢查指紋
     * 說明: 確認該筆指紋資料存在
     * @param string $server 伺服器
     * @param string $account 帳號
     * @return bool 檢查結果
     */
    public function checkFingerprintExists($server, $account)
    {
        $data = $this->db->table('fingerprint')
            ->where('server', $server)
            ->where('account', $account)
            ->get()
            ->getRowArray();

        return (!empty($data)) ? true : false;
    }

    /**
     * 已成功繳費資料
     * @return array 已成功繳費資料
     */
    public function fetchPayed()
    {
        $data = $this->db->table('servers_log')
            ->where('RtnCode', '1')
            ->where('RtnMsg', '交易成功')
            ->get()
            ->getResultArray();

        if (empty($data)) {
            return false;
        }

        try{
            $temp = array();
            $payedData = array();
            foreach ($data as $_val) {
                $unique = $_val['serverid'] . '_' . $_val['gameid'];
                if (!in_array($unique, $temp)) {
                    $temp[] = $unique;
                    $payedData[] = array(
                        'server' => $_val['serverid'],
                        'account' => $_val['gameid'],
                        'fingerprint' => '',
                    );
                }
            }

            return $payedData;
        }catch(\Exception $e){
            print_r($e->getMessage()); die();
        }
    }

    /**
     * 建立指紋資料
     * @return bool 建立結果
     */
    public function createFingerprint()
    {
        // $payedData = $this->fetchPayed();
        // if ($payedData === false) {
        //     return false;
        // }

        // $this->db->table('fingerprint_list')
        //     ->insertBatch($payedData);

        // return true;
    }
}