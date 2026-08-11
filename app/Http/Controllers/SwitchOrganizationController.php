<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\CurrentOrganization;
use App\Support\SystemAuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchOrganizationController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        SystemAuditRecorder $auditRecorder,
    ): RedirectResponse {
        abort_unless(CurrentOrganization::canSelect($request->user(), $organization), 403);

        CurrentOrganization::set($organization, $request->user());
        $auditRecorder->record('customer_context.entered', $organization, $organization->id);

        if ($request->user()->hasSystemAccess()) {
            return redirect()->route('dashboard');
        }

        return back();
    }

    public function exit(Request $request, SystemAuditRecorder $auditRecorder): RedirectResponse
    {
        abort_unless($request->user()?->hasSystemAccess(), 403);

        $organization = CurrentOrganization::get();

        if ($organization !== null) {
            $auditRecorder->record('customer_context.exited', $organization, $organization->id);
        }

        CurrentOrganization::clear();

        return redirect()->route('system.customers');
    }
}
