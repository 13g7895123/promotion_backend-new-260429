<?php

namespace App\Controllers\Promotion;

use App\Controllers\BaseController;

class AdminPanel extends BaseController
{
    public function index()
    {
        return view('Promotion/admin_panel');
    }
}
