<?php

namespace App\Http\Livewire;

use App\Mail\PasswordResetMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class PasswordResetComponent extends Component
{
    use SendsPasswordResetEmails;
    /**
     * @var string $email
     */
    public $email;

    /**
     * @return View
     */
    public function render()
    {
        return view('livewire.password-reset-component');
    }

    public function ResetPassword()
    {


        $validateData = $this->validate([
            'email' => 'required|email'
        ]);

        $user = User::whereEmail($this->email)->first();

        if ($user) {
            $token = Str::random(64);

            $resetToken = PasswordResetToken::query()->whereEmail($this->email)->firstOrNew();

            $resetToken->email = $this->email;
            $resetToken->token = Hash::Make($token);
            $resetToken->created_at = Carbon::now(new \DateTimeZone('UTC'));
            $resetToken->save();

            $resetLink = route('password.reset', ['email' => $this->email,'token' => $token]);

            Mail::to($user->email)->send(new PasswordResetMail($resetLink, $user->name));
            session()->flash('message','Instruction have been sent to your email');
        }
        else
        {
            session()->flash('error','User not found');
        }
    }

    public function Login()
    {
        return $this->redirect(route('login'));
    }
}
