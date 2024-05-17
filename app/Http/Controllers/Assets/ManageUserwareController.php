<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ManageUserwareController extends Controller
{
    public function list()
    {
        return view('assets.userware.userware-list');
    }
}
