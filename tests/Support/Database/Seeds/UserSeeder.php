<?php

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $data = [
            'username' => 'testuser',
            'email'    => 'test@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'status'   => 'active'
        ];

        $this->db->table('users')->insert($data);
    }
} 