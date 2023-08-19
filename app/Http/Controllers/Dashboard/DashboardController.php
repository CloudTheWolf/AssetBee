<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function Get()
    {
        $userCount = (new User)->whereDisabled(0)->count();
        $siteCount = (new Site)->whereDeleted(0)->count();

        return view('dashboard',
            compact('userCount',
                'siteCount'));
    }
}
