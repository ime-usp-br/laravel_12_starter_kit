<?php

namespace App\Notifications;

use App\Notifications\Concerns\WithEmailLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use WithEmailLogging;

    /**
     * @var int
     */
    public $tries = 5;

    /**
     * @var int
     */
    public $timeout = 90;

    /**
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * @var string
     */
    protected $alertType;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var array<string, mixed>
     */
    protected $details;

    /**
     * Create a new notification instance.
     *
     * @param  array<string, mixed>  $details
     */
    public function __construct(string $alertType, string $message, array $details = [])
    {
        $this->alertType = $alertType;
        $this->message = $message;
        $this->details = $details;

        // Critical alerts use high priority queue
        $this->onQueue($alertType === 'critical' ? 'high' : 'default');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Build the mail message for the notification.
     *
     * @param  \App\Models\User  $notifiable
     */
    protected function toMailMessage($notifiable): MailMessage
    {
        $alertTypeUpper = strtoupper($this->alertType);
        $mailMessage = (new MailMessage)
            ->subject("[{$alertTypeUpper}] Alerta do Sistema")
            ->greeting('Alerta do Sistema');

        if ($this->alertType === 'critical') {
            $mailMessage->error();
        }

        $mailMessage->line($this->message);

        if (! empty($this->details)) {
            $mailMessage->line('Detalhes:');
            foreach ($this->details as $key => $value) {
                $valueStr = is_scalar($value) ? (string) $value : json_encode($value);
                $mailMessage->line("- {$key}: {$valueStr}");
            }
        }

        return $mailMessage
            ->line('Data/Hora: '.now()->format('d/m/Y H:i:s'))
            ->line('Esta é uma notificação automática do sistema.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_type' => $this->alertType,
            'message' => $this->message,
            'details' => $this->details,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        // More aggressive retry for critical alerts
        if ($this->alertType === 'critical') {
            return [10, 30, 60, 120, 300];
        }

        return [30, 60, 120, 240, 480];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        // Critical alerts try for 30 minutes, others for 15 minutes
        $minutes = $this->alertType === 'critical' ? 30 : 15;

        return now()->addMinutes($minutes);
    }
}
