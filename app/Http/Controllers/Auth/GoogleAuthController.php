<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateGoogleUser;
use App\Actions\Organizations\AcceptOrganizationInvitation;
use App\Actions\Organizations\EnsureUserOrganization;
use App\Http\Controllers\Controller;
use App\Support\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        $driver = Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email']);

        $hostedDomains = config('services.google.hosted_domains', []);

        if (count($hostedDomains) === 1) {
            $driver->with(['hd' => $hostedDomains[0]]);
        }

        return $driver->redirect();
    }

    /**
     * Handle the callback from Google after authentication.
     */
    public function callback(
        AuthenticateGoogleUser $authenticateGoogleUser,
        EnsureUserOrganization $ensureUserOrganization,
        AcceptOrganizationInvitation $acceptOrganizationInvitation,
    ): RedirectResponse {
        try {
            /** @var User $googleUser */
            $googleUser = Socialite::driver('google')->user();
            $user = $authenticateGoogleUser->handle($googleUser);
        } catch (InvalidStateException) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('Google authentication failed. Please try again.')]);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('login')
                ->withErrors($exception->errors());
        }

        Auth::login($user, remember: true);

        $invitation = Registration::pendingInvitation();

        if ($invitation !== null) {
            try {
                $acceptOrganizationInvitation->handle($invitation, $user);
            } catch (ValidationException $exception) {
                return redirect()
                    ->route('login')
                    ->withErrors($exception->errors());
            }
        } else {
            $ensureUserOrganization->handle($user);
        }

        return redirect()->intended(config('fortify.home'));
    }
}
