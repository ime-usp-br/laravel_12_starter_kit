<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The password reset token.
     *
     * @var string
     */
    protected $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
        // Use high priority for password reset (security-related)
        $this->onQueue('high');
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
    public function toMail(object $notifiable): MailMessage
    {
        $url = url(config('app.url') . route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Redefinição de Senha - ' . config('app.name'))
            ->greeting('Olá!')
            ->line('Você está recebendo este email porque recebemos uma solicitação de redefinição de senha para sua conta.')
            ->action('Redefinir Senha', $url)
            ->line('Este link de redefinição de senha expirará em ' . config('auth.passwords.' . config('auth.defaults.passwords') . '.expire') . ' minutos.')
            ->line('Se você não solicitou uma redefinição de senha, nenhuma ação adicional é necessária.')
            ->salutation('Atenciosamente, ' . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'requested_at' => now()->toISOString(),
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        // More aggressive retry for security-related emails
        return [15, 30, 60, 120, 240];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        // Keep trying for 15 minutes (password reset is time-sensitive)
        return now()->addMinutes(15);
    }
}
