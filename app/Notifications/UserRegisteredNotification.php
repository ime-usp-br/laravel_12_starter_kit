<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var int
     */
    public $tries = 3;

    /**
     * @var int
     */
    public $timeout = 60;

    /**
     * @var int
     */
    public $maxExceptions = 2;

    /**
     * @var array<string, mixed>
     */
    protected $userData;

    /**
     * Create a new notification instance.
     *
     * @param  array<string, mixed>  $userData
     */
    public function __construct(array $userData = [])
    {
        $this->userData = $userData;
        // Use high priority queue for important notifications
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
     *
     * @param  \App\Models\User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        /** @var string $appName */
        $appName = config('app.name') ?? 'App';

        return (new MailMessage)
            ->subject("Novo Usuário Registrado - {$appName}")
            ->greeting('Novo Usuário Cadastrado')
            ->line('Um novo usuário se registrou no sistema:')
            ->line("Nome: {$notifiable->name}")
            ->line("Email: {$notifiable->email}")
            ->line('Data: '.now()->format('d/m/Y H:i:s'))
            ->action('Ver Usuários', url('/admin/users'))
            ->line('Esta é uma notificação automática do sistema.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  \App\Models\User  $notifiable
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_name' => $notifiable->name,
            'user_email' => $notifiable->email,
            'registered_at' => now()->toISOString(),
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 30, 60];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(5);
    }
}
