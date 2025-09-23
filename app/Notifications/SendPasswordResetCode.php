<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendPasswordResetCode extends Notification
{
    use Queueable;
    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Password Reset Code')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->line('Your password reset code is:')
            ->line(new \Illuminate\Support\HtmlString('<div style="text-align: center; margin: 20px 0;">
                        <span style="
                            background-color: #f8f9fa;
                            border: 2px solid #330000;
                            border-radius: 8px;
                            padding: 15px 25px;
                            font-size: 28px;
                            font-weight: bold;
                            color: #330000;
                            letter-spacing: 3px;
                            font-family: monospace;
                            display: inline-block;
                        ">' . $this->code . '</span>
                    </div>'))
            ->line('This code will expire in **10 minutes**.')
            ->line('If you did not request a password reset, no further action is required.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
