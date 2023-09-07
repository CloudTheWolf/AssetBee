<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ManageHardwareController extends Controller
{
    public function list()
    {
        return view('assets.hardware-list');
    }
}
