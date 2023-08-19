<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class ListUsersController extends Controller
{

    /**
     * @return View
     */
    public function Get()
    {
        $users = User::query()->whereDisabled(0)->paginate(10);
        return view('Admin/UsersList',compact("users"));
    }
}
