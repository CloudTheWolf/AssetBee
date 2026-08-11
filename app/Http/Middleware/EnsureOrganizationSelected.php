<?php

namespace App\Http\Middleware;

use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationSelected
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $organization = CurrentOrganization::ensureSelected($user);

        if ($organization === null) {
            if ($user->hasSystemAccess()) {
                return redirect()->route('system.customers');
            }

            if ($request->routeIs('organizations.create', 'organizations.store')) {
                return $next($request);
            }

            return redirect()->route('organizations.create');
        }

        return $next($request);
    }
}
