<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /**
     * @param Request $request
     * @param $id
     * @return View
     */
    public function Get(Request $request, $id)
    {
        $user = (new User)::query()->whereId($id)->first();

        if(session('status') === 'profile-updated')
        {
            notify()->preset('user-updated');
        }
        return view('Admin/profile/edit',compact("user"));
    }

    public function Patch(AdminUserUpdateRequest $request): RedirectResponse
    {
        $user = User::query()->whereId($request->validated()['id'])->first();

        $user->fill($request->validated());
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::back()->with('status', 'profile-updated');
    }
}
