<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly int $ttlMinutes,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Codigo de verificacion MusicHub')
            ->greeting('Verificacion de seguridad')
            ->line('Usa este codigo para completar tu acceso a MusicHub:')
            ->line($this->code)
            ->line("Caduca en {$this->ttlMinutes} minutos.");
    }
}
