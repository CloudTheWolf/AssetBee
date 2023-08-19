<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Site;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminMainController extends Controller
{
    public function Get()
    {
        $userCount = (new User)->whereDisabled(0)->count();
        $siteCount = (new Site)->whereDeleted(0)->count();

        return view('Admin/AdminMain',
            compact('userCount',
                'siteCount'));
    }
}
