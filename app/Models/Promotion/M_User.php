<?php

namespace App\Models\Promotion;

use CodeIgniter\Model;
use CodeIgniter\Email\Email;

class M_User extends Model
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect('promotion');  // 預設資料庫
    }

    public function index()
    {
        $users = $this->db->table('users')->get()->getResultArray();
        return $users;
    }

    /**
     * 建立使用者
     * @param array $data 使用者資料
     */
    public function create(array $data): int
    {
        // 帳號類型
        $type = (isset($data['is_admin']) && $data['is_admin'] == 1) ? 'admin' : 'user';

        // 檢查帳號是否存在
        $user = $this->db->table('users')
            ->where('account', $data['account'])
            ->get()
            ->getRowArray();

        if (!empty($user)){
            return false;
        }

        $insertData = array(
            'account' => $data['account'],
            'password' => hash('sha256', $data['password']),
            'type' => $type,
        );
        $this->db->table('users')->insert($insertData);
        $insertId = $this->db->insertID();

        // 建立使用者伺服器權限
        if (count($data['server']) > 0){
            foreach ($data['server'] as $server) {
                $this->db->table('user_server_permissions')->insert([
                    'user_id' => $insertId,
                    'server_code' => $server,
                ]);
            }
        }

        return $insertId;
    }

    /**
     * 更新使用者
     * @param array $data 使用者資料
     */
    public function updateData(array $data)
    {
        $updateData = array('switch' => $data['switch']);

        if (isset($data['password'])){
            $updateData['password'] = hash('sha256', $data['password']);
        }

        $this->db->table('users')->where('id', $data['id'])->update($updateData);

        if (count($data['server']) > 0){
            // 清空權限
            $this->db->table('user_server_permissions')->where('user_id', $data['id'])->delete();

            foreach ($data['server'] as $server) {
                $this->db->table('user_server_permissions')->insert([
                    'user_id' => $data['id'],
                    'server_code' => $server,
                ]);
            }
        }

        return true;
    }

    /**
     * 更新管理者
     */
    public function updateManager(array $data)
    {
        $updateData = array('switch' => $data['switch']);

        if (isset($data['password'])){
            $updateData['password'] = hash('sha256', $data['password']);
        }

        $this->db->table('users')->where('id', $data['id'])->update($updateData);

        return true;
    }

    /**
     * 刪除使用者
     * @param int/array $userId 使用者ID
     */
    public function deleteData($userId)
    {
        // 刪除使用者伺服器權限
        $builder = $this->db->table('user_server_permissions');
        if (is_array($userId)){
            $builder->whereIn('user_id', $userId);
        } else {
            $builder->where('user_id', $userId);
        }
        $builder->delete();

        // 刪除使用者
        $builder = $this->db->table('users');
        if (is_array($userId)){
            $builder->whereIn('id', $userId);
        } else {
            $builder->where('id', $userId);
        }
        $builder->delete();

        return True;
    }

    /**
     * 取得使用者伺服器權限
     */
    public function getServerPermission(int $userId): array
    {
        $serverPermission = $this->db->table('user_server_permissions')
            ->join('server', 'server.code = user_server_permissions.server_code')
            ->select('server.code, server.name')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        // print_r($this->db->); die();

        return $serverPermission;
    }

    /**
     * 登入
     * @param string $account 帳號
     * @param string $password 密碼
     */
    public function login(string $account, string $password)
    {
        $user = $this->db->table('users')
            ->where('account', $account)
            ->where('password', hash('sha256', $password))
            ->get()
            ->getRowArray();

        if (empty($user)){
            return array('success' => False, 'message' => '帳號或密碼錯誤');
        }

        unset($user['password']);

        return array('success' => True, 'message' => '登入成功', 'user' => $user);
    }

    public function getUserPermission(int $userId): array
    {
        $userPermission = $this->db->table('users')
            ->where('id', $userId)
            ->get()
            ->getRowArray();

        return (!empty($userPermission)) ? $userPermission : False;
    }

    /**
     * 檢查 token 是否到期
     * @param string $type 類型
     * @param string $token token
     */
    public function checkTokenExpired($type, string $token, $expireInFive = false)
    {
        $token = $this->db->table('admin_token')
            ->where('token', $token)
            ->where('type', $type)
            ->get()
            ->getRowArray();

        if (empty($token)){
            return false;
        }

        $expireTime = strtotime($token['expired_at']);
        $currentTime = time();

        // 5分鐘內到期
        if ($expireInFive === true ){
            if ($expireTime - $currentTime <= 5 * 60){
                return true;
            }

            return false;
        }

        if ($expireTime < $currentTime){
            return true;
        }
        
        return false;
    }
}