<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateGoogleUser;
use App\Actions\Auth\LinkGoogleAccount;
use App\Actions\Organizations\AcceptOrganizationInvitation;
use App\Actions\Organizations\EnsureUserOrganization;
use App\Http\Controllers\Controller;
use App\Support\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController extends Controller
{
    public const INTENT_SESSION_KEY = 'google_oauth_intent';

    public const INTENT_LINK = 'link';

    /**
     * Redirect the guest to Google's OAuth consent screen for sign-in.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        Session::forget(self::INTENT_SESSION_KEY);

        return $this->googleRedirect();
    }

    /**
     * Redirect the authenticated user to Google to link their account.
     */
    public function linkRedirect(): SymfonyRedirectResponse
    {
        abort_if(
            config('app.demo_mode'),
            403,
            __('Google account linking is disabled in demo mode.'),
        );

        Session::put(self::INTENT_SESSION_KEY, self::INTENT_LINK);

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');
        $driver->scopes(['openid', 'profile', 'email']);

        $parameters = [
            'login_hint' => Auth::user()->email,
            'prompt' => 'select_account',
        ];

        $hostedDomains = config('services.google.hosted_domains', []);

        if (is_array($hostedDomains) && count($hostedDomains) === 1) {
            $parameters['hd'] = $hostedDomains[0];
        }

        return $driver->with($parameters)->redirect();
    }

    /**
     * Handle the callback from Google after authentication or account linking.
     */
    public function callback(
        AuthenticateGoogleUser $authenticateGoogleUser,
        LinkGoogleAccount $linkGoogleAccount,
        EnsureUserOrganization $ensureUserOrganization,
        AcceptOrganizationInvitation $acceptOrganizationInvitation,
    ): RedirectResponse {
        $intent = Session::pull(self::INTENT_SESSION_KEY);

        try {
            /** @var User $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->oauthFailureRedirect($intent, __('Google authentication failed. Please try again.'));
        }

        if ($intent === self::INTENT_LINK) {
            return $this->completeLink($linkGoogleAccount, $googleUser);
        }

        try {
            $user = $authenticateGoogleUser->handle($googleUser);
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

    private function completeLink(LinkGoogleAccount $linkGoogleAccount, User $googleUser): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('Sign in to link your Google account.')]);
        }

        try {
            $linkGoogleAccount->handle(Auth::user(), $googleUser);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('security.edit')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('security.edit')
            ->with('status', 'google-account-linked');
    }

    private function googleRedirect(): SymfonyRedirectResponse
    {
        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');
        $driver->scopes(['openid', 'profile', 'email']);

        $hostedDomains = config('services.google.hosted_domains', []);

        if (is_array($hostedDomains) && count($hostedDomains) === 1) {
            $driver->with(['hd' => $hostedDomains[0]]);
        }

        return $driver->redirect();
    }

    private function oauthFailureRedirect(?string $intent, string $message): RedirectResponse
    {
        if ($intent === self::INTENT_LINK && Auth::check()) {
            return redirect()
                ->route('security.edit')
                ->withErrors(['google' => $message]);
        }

        return redirect()
            ->route('login')
            ->withErrors(['email' => $message]);
    }
}
