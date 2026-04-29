<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\Seeds\UserSeeder;

class UserTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $seed = UserSeeder::class;
    protected $namespace = 'App';

    public function testRegister()
    {
        $result = $this->call('post', 'api/register', [
            'username' => 'testuser',
            'email'    => 'test@example.com',
            'password' => 'password123'
        ]);

        $result->assertStatus(201);
        $result->assertJSONFragment(['message' => '用戶創建成功']);
    }

    public function testLoginSuccess()
    {
        $result = $this->call('post', 'api/login', [
            'username' => 'testuser',
            'password' => 'password123'
        ]);

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => '登入成功']);
    }

    public function testLoginFailure()
    {
        $result = $this->call('post', 'api/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword'
        ]);

        $result->assertStatus(401);
        $result->assertJSONFragment(['message' => '密碼錯誤']);
    }

    public function testGetUserWithoutAuth()
    {
        $result = $this->call('get', 'api/user');
        $result->assertStatus(401);
        $result->assertJSONFragment(['message' => '請先登入']);
    }

    public function testGetUserWithAuth()
    {
        // 先登入
        $this->call('post', 'api/login', [
            'username' => 'testuser',
            'password' => 'password123'
        ]);

        // 獲取用戶資訊
        $result = $this->call('get', 'api/user');
        $result->assertStatus(200);
        $result->assertJSONFragment(['username' => 'testuser']);
    }

    public function testLogout()
    {
        // 先登入
        $this->call('post', 'api/login', [
            'username' => 'testuser',
            'password' => 'password123'
        ]);

        // 登出
        $result = $this->call('get', 'api/logout');
        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => '登出成功']);

        // 確認已登出
        $result = $this->call('get', 'api/user');
        $result->assertStatus(401);
    }
} 