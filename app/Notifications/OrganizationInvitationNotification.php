<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public OrganizationInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->invitation->loadMissing(['organization', 'inviter']);

        return (new MailMessage)
            ->subject(__('You\'re invited to join :organization', [
                'organization' => $this->invitation->organization->name,
            ]))
            ->markdown('mail.organization-invitation', [
                'invitation' => $this->invitation,
                'acceptUrl' => route('invitations.show', $this->invitation->token),
            ]);
    }
}
