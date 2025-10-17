<?php

namespace App\Notifications;

use App\Notifications\Concerns\WithEmailLogging;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class EmailVerificationNotification extends VerifyEmail implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    use WithEmailLogging;

    /**
     * @var int
     */
    public $tries = 3;

    /**
     * @var int
     */
    public $timeout = 60;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 10;

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        // Make notification unique per user email
        return 'email-verification-' . ($this->emailNotifiableId ?? 'unknown');
    }

    /**
     * Get the tags that should be assigned to the queued job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['email-verification'];
    }

    /**
     * Build the mail message for the notification.
     *
     * @param  mixed  $notifiable
     */
    protected function toMailMessage($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verificação de Email - ' . config('app.name'))
            ->greeting('Olá!')
            ->line('Por favor, clique no botão abaixo para verificar seu endereço de email.')
            ->action('Verificar Email', $verificationUrl)
            ->line('Se você não criou uma conta, nenhuma ação adicional é necessária.')
            ->salutation('Atenciosamente, ' . config('app.name'));
    }
}
