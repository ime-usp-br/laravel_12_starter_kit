<?php

namespace App\Notifications;

use App\Notifications\Concerns\WithEmailLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @use WithEmailLogging<\App\Models\User> */
    use WithEmailLogging;

    /**
     * Number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

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
    public $maxExceptions = 2;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        // Define queue priority: high, default, or low
        $this->onQueue('default');
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
     * Build the mail message for the notification.
     *
     * @param  mixed  $notifiable
     */
    protected function toMailMessage($notifiable): MailMessage
    {
        /** @var string $appName */
        $appName = config('app.name') ?? 'App';

        $name = is_object($notifiable) && property_exists($notifiable, 'name') && is_string($notifiable->name) ? $notifiable->name : 'Usuário';

        return (new MailMessage)
            ->subject("Bem-vindo ao {$appName}")
            ->greeting("Olá, {$name}!")
            ->line('Seja bem-vindo ao nosso sistema.')
            ->line('Estamos felizes em tê-lo conosco.')
            ->action('Acessar o Sistema', url('/'))
            ->line('Obrigado por se cadastrar!');
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

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        // Retry after 30 seconds, then 60 seconds, then 120 seconds
        return [30, 60, 120];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        // Stop retrying after 10 minutes
        return now()->addMinutes(10);
    }
}
