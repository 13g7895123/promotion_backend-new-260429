<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->options('(:any)', function() {
    return '';
});

$routes->group('api', ['namespace' => 'App\Controllers\Jiachu'], function($routes) {
    // 情境模擬頁面 (Bug 修復驗證)
    $routes->get('simulation', '\App\Controllers\Simulation::index');
    $routes->get('simulation/js', '\App\Controllers\Simulation::serveJs');
    $routes->get('simulation/state', '\App\Controllers\Simulation::state');
    $routes->post('simulation/rollback', '\App\Controllers\Simulation::rollback');
    $routes->post('simulation/audit', '\App\Controllers\Simulation::audit');

    /* 推廣 */
    $routes->group('promotion', ['namespace' => 'App\Controllers\Promotion'], function ($routes){
        // 前台
        $routes->match(['post'], 'login', 'User::login');                   // 登入
        $routes->match(['post'], 'server', 'Server::getServer');            // 取得伺服器資料
        $routes->match(['post'], 'player/submit', 'Player::submit');        // 驗證頁提交資料
        $routes->match(['post'], 'player/info', 'Player::getPlayerInfo');   // 取得使用者資訊

        $routes->match(['post'], 'main', 'Promotion::index');                           // 取得推廣項目資料
        $routes->match(['post'], 'main/delete', 'Promotion::delete');                   // 刪除推廣項目資料
        $routes->match(['post'], 'main/batchAudit', 'Promotion::batchAudit');           // 更新推廣項目資料
        $routes->match(['get'], 'main/batchAudit/test', 'Promotion::batchAuditV2');     // 更新推廣項目資料
        $routes->match(['get'], 'detail/(:num)', 'PromotionItem::index/$1');            // 取得推廣項目資料(id)
        $routes->match(['put'], 'detail/update/(:num)', 'PromotionItem::update/$1');    // 更新推廣項目資料
        $routes->match(['post'], 'detail/url/check', 'PromotionItem::checkUrl');         // 確認網址
        
        $routes->match(['post'], 'file', 'FileController::upload');         // 上傳檔案
        $routes->match(['get'], 'file/show/(:num)', 'FileController::show/$1');         // 上傳檔案
        $routes->match(['post'], '/', 'Promotion::create');                 // 建立推廣資料
        $routes->match(['delete'], '(:num)', 'Promotion::delete/$1');       // 刪除推廣資料
        $routes->match(['post'], 'items', 'PromotionItem::create');         // 建立推廣資料
        $routes->match(['get'], 'user/info/(:num)', 'User::getUserId/$1');  // 取得User Id(測試用)
        $routes->match(['get'], 'user/test', 'User::test');                 // 測試
        $routes->match(['post'], 'line/state/save', 'Player::saveState');
        $routes->match(['get'], 'line/callback', 'Player::callback');
        
        $routes->match(['post'], 'player/email', 'Player::updateEmailNotify');  // 更新信箱通知
        $routes->match(['get'], 'player/email/test', 'Player::testSendMail');  // 測試信箱
        $routes->match(['post'], 'player/line', 'Player::updateLineNotify');    // 更新Line通知

        // 後台
        $routes->match(['get'], 'user', 'User::index');                     // 取得使用者資料
        $routes->match(['post'], 'user', 'User::index');                    // 取得使用者資料
        $routes->match(['post'], 'user/create', 'User::create');            // 新增使用者
        $routes->match(['post'], 'user/update', 'User::update');            // 更新使用者
        $routes->match(['post'], 'user/delete', 'User::delete');            // 刪除使用者
        $routes->match(['post'], 'user/condition', 'User::condition');      // 新增使用者

        $routes->match(['get'], 'manager', 'User::getManager');             // 取得管理者資料
        $routes->match(['post'], 'manager/create', 'User::create');         // 新增管理者
        $routes->match(['post'], 'manager/update', 'User::update');         // 更新管理者

        $routes->match(['post'], 'player', 'Player::index');                // 取得玩家資料
        $routes->match(['post'], 'player/delete', 'Player::delete');        // 刪除玩家資料
        $routes->match(['post'], 'player/reward', 'Player::fetchReward');   // 取得玩家獎勵資料
        $routes->match(['post'], 'reward/missing', 'Player::missingReward');   // 查詢缺少派獎的推廣
        $routes->match(['post'], 'reward/reissue', 'Player::reissueReward');   // 補發所有缺少派獎的推廣
        
        // API Log 查看器
        $routes->match(['get'],  'logs',           'ApiLog::index');          // HTML 頁面
        $routes->match(['get'],  'logs/data',      'ApiLog::data');           // JSON 查詢
        $routes->match(['get'],  'logs/stats',     'ApiLog::stats');          // 統計資料
        $routes->match(['get'],  'logs/detail/(:num)', 'ApiLog::detail/$1'); // 單筆詳細
        $routes->match(['post'], 'logs/clean',     'ApiLog::clean');          // 清除舊 log

        // 推廣生命週期 Log
        $routes->match(['get'],  'lifecycle',               'LifecycleLog::index');        // HTML 頁面
        $routes->match(['get'],  'lifecycle/data',          'LifecycleLog::data');         // JSON 時間軸資料
        $routes->match(['get'],  'lifecycle/summary',       'LifecycleLog::summary');      // JSON 彙整統計

        // 推廣狀態追蹤器（單筆完整生命週期查詢）
        $routes->match(['get'],  'tracker',                     'PromotionTracker::index');       // HTML 頁面
        $routes->match(['get'],  'tracker/search',              'PromotionTracker::search');      // JSON 搜尋列表
        $routes->match(['get'],  'tracker/detail/(:num)',       'PromotionTracker::detail/$1');   // JSON 單筆詳細

        // 批次審核排程佇列
        $routes->match(['get'],  'batch-audit/jobs',                'BatchAuditJob::index');          // HTML 監控頁面
        $routes->match(['get'],  'batch-audit/jobs/data',           'BatchAuditJob::data');           // JSON 分頁列表
        $routes->match(['get'],  'batch-audit/jobs/stats',          'BatchAuditJob::stats');          // JSON 統計摘要
        $routes->match(['get'],  'batch-audit/jobs/(:num)',         'BatchAuditJob::detail/$1');      // JSON 單筆詳細
        $routes->match(['get'],  'batch-audit/scheduler/health',    'BatchAuditJob::schedulerHealth'); // JSON 排程健康狀態
        $routes->match(['get'],  'lifecycle/audit-events',  'LifecycleLog::auditEvents');  // JSON 批次審核事件
        
        $routes->match(['post'], 'server/single', 'Server::singleById');    
        $routes->match(['post'], 'server/create', 'Server::create');      
        $routes->match(['post'], 'server/update', 'Server::update');      
        $routes->match(['post'], 'server/delete', 'Server::delete');      
        $routes->match(['post'], 'server/database', 'Server::getDatabase');              
        $routes->match(['post'], 'server/database/update', 'Server::updateDatabase');  
        $routes->match(['post'], 'server/award/update', 'Server::updateAward');               
        $routes->match(['post'], 'server/image', 'Server::getImage');              
        $routes->match(['post'], 'server/image/upload', 'Server::uploadImage');              
        $routes->match(['post'], 'server/image/update', 'Server::updateImage');     
        $routes->match(['post'], 'server/connection/test', 'Server::testConnection');
        // $routes->match(['get'], 'server/connection/test', 'Server::testConnection');
        $routes->match(['get'], 'server/fix', 'Server::fix');

        // $routes->match(['get'], 'test', 'Test::test');
        $routes->match(['get'], 'test', 'Promotion::test');
        $routes->match(['post'], 'test', 'Test::test');
    });

    $routes->group('fingerprint', ['namespace' => 'App\Controllers\Fingerprint'], function ($routes){
        $routes->match(['get'], 'main', 'Main::index');
        $routes->match(['get'], 'test', 'Main::test');
        $routes->match(['post'], 'check', 'Main::checkFingerprint');
        // $routes->match(['get'], 'create/first', 'Main::createFingerprintAtFirst');  // 建立指紋資料(初次)
    });
});
