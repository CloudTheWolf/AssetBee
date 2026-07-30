<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchOrganizationController extends Controller
{
    public function __invoke(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless(
            $request->user()->organizations()->where('organizations.id', $organization->id)->exists(),
            403,
        );

        CurrentOrganization::set($organization);

        return back();
    }
}
