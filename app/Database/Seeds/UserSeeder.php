<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Faker\Factory;

class UserSeeder extends Seeder
{
    public function run()
    {
        $faker = Factory::create('zh_TW');

        for ($i = 0; $i < 3; $i++) {
            $data = [
                'username' => $faker->unique()->userName,
                'email'    => $faker->unique()->safeEmail,
                'password' => password_hash('password123', PASSWORD_DEFAULT),
                'status'   => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->db->table('users')->insert($data);
        }
    }
} 