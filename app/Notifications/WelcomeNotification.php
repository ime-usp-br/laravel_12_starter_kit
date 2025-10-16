<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
     * Get the mail representation of the notification.
     *
     * @param  \App\Models\User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        /** @var string $appName */
        $appName = config('app.name') ?? 'App';

        return (new MailMessage)
            ->subject("Bem-vindo ao {$appName}")
            ->greeting("Olá, {$notifiable->name}!")
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
