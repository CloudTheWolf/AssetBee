<?php

namespace App\Http\Middleware;

use App\Support\Registration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationIsOpen
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Registration::isOpen(Registration::pendingInvitation())) {
            return $next($request);
        }

        return redirect()
            ->route('login')
            ->withErrors(['email' => __('Public registration is closed. Ask an organization owner for an invite.')]);
    }
}
