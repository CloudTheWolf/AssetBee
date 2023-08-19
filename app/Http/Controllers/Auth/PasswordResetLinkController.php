<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

class PasswordResetLinkController extends Controller
{
    use SendsPasswordResetEmails;
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->get('email'))->first();

        if (!$user) {
            return back()->withErrors(['email' => 'User not found']);
        }

        $token = Str::random(64);

        $resetToken = PasswordResetToken::query()->whereEmail($request->get('email'))->firstOrNew();

        $resetToken->email = $request->get('email');
        $resetToken->token = Hash::Make($token);
        $resetToken->created_at = Carbon::now(new \DateTimeZone('UTC'));
        $resetToken->save();

        $resetLink = route('password.reset', ['email' => $request->get('email'),'token' => $token]);

        $status = Mail::to($user->email)->send(new PasswordResetMail($resetLink, $user->name));

        return back()->with('status', __("An email has been set with instructions on how to reset your password"));
    }
}
