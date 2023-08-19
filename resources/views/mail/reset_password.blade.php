@component('mail::message')
    Hello **{{$name}}**,<br>
    Someone has requested you password to be reset.<br>
    Click the button below to reset your password<br>
    @component('mail::button', ['url' => $resetLink ])
        Reset Password
    @endcomponent
    Button not working?<br>
    Copy the following into your browser<br>
    {{$resetLink}}<br>
    If you did not request a password reset, you can delete this email.<br>
    Sincerely,<br>
    AssetBee Team
@endcomponent
