<?php

namespace App\Controllers\Promotion;

use App\Controllers\BaseController;
use App\Models\Promotion\M_CustomizedDb;
use App\Models\Promotion\M_User;

class Test extends BaseController
{
    public function __construct()
    {

    }

    public function index()
    {
        $dbInfo = array(
            'id' => 2,
            'server_code' => 'tdb',
            'name' => 'maple-db',
            'host' => '139.162.15.125',
            'port' => 9903,
            'account' => 'maple_user',
            'password' => 'v94176w6',
            'table_name' => 'promotion'
        );

        $db = \Config\Database::connect([
            'DSN'      => '',
            'hostname' => $dbInfo['host'],
            'username' => $dbInfo['account'],
            'password' => $dbInfo['password'],
            'database' => $dbInfo['name'],
            'port'     => $dbInfo['port'],
            'DBDriver' => 'MySQLi',
            'charset'  => 'utf8mb4',  
        ]);

        // 檢查連線是否成功
        try {
            $db->connect();
            
            $db->table('promotion')->insert([
                'user_id' => 1,
                'product_id' => 2,
                'number' => '3',
            ]);
            return true;
        } catch (\Exception $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    public function test()
    {
        print_r(123); die();

        $db = \Config\Database::connect('promotion');
        $data = $db->table('reward')
            ->get()->getResultArray();

        $temp = array();
        foreach ($data as $_key => $_val) {
            $playerData = $db->table('player')
                ->where('id', $_val['player_id'])
                ->get()
                ->getRowArray();

            if (empty($playerData)) {
                $promotionData = $db->table('promotions')
                    ->where('id', $_val['player_id'])
                    ->get()
                    ->getRowArray();

                $playerId = $promotionData['user_id'] ?? 0;

                // $temp[] = array(
                //     'id' => $_val['id'],
                //     'wrong_player_id' => $_val['player_id'],
                //     'player_id' => $playerId,
                // );
                $db->table('reward')
                    ->where('id', $_val['id'])
                    ->update(['player_id' => $playerId]);
            }
        }

        // print_r($temp); die();
    }
}