<?php

namespace App\Notifications;

use App\Models\EmailVerificationToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendEmailVerification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public EmailVerificationToken $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = url(route('verify-email', [
            'token' => $this->token->token,
            'email' => $notifiable->email,
        ]));

        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->greeting("Hello {$notifiable->name}!")
            ->line('Thank you for registering. Please verify your email address to complete your account setup.')
            ->action('Verify Email', $verificationUrl)
            ->line('This verification link will expire in 24 hours.')
            ->line('If you did not register for this account, please ignore this email.');
    }
}
