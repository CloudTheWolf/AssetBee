<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;


class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $resetLink;
    public $name;

    /**
     * Create a new message instance.
     *
     * @param  string  $resetLink
     * @return void
     */
    public function __construct($resetLink,$name)
    {
        $this->resetLink = $resetLink;
        $this->name = $name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('assetbee@legacyrp.company')
            ->subject('Password Reset Request')
            ->markdown('mail.reset_password')
            ->with([
                'resetLink' => $this->resetLink,
                'name' => $this->name
            ]);
    }
}
