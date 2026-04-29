<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Files\File;
use CodeIgniter\API\ResponseTrait;

class Test extends BaseController
{
    use ResponseTrait;

    public function __construct()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }

    public function index()
    {
        print_r(123); die();
        $test_db = \Config\Database::connect('promotion');

        $data = $test_db->table('reward')
            ->get()->getResultArray();

        foreach ($data as $_key => $_val) {
            $playerData = $test_db->table('player')
                ->where('id', $_val['player_id'])
                ->get()
                ->getRowArray();

            if (empty($playerData)) {
                $promotionData = $test_db->table('promotions')
                    ->where('id', $_val['player_id'])
                    ->get()
                    ->getRowArray();

                $playerId = $promotionData['user_id'] ?? 0;

                $test_db->table('reward')
                    ->where('id', $_val['id'])
                    ->update(['player_id' => $playerId]);
            }
        }
    }
} 