<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Str;

class LogEmailFailedListener
{
    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        // Check if the failed job is a notification
        if (!Str::contains($event->job->payload()['displayName'] ?? '', 'Notification')) {
            return;
        }

        // Decode job payload
        $payload = json_decode($event->job->getRawBody(), true);
        $command = unserialize($payload['data']['command'] ?? '');

        // Check if it's a notification with mail channel
        if (!is_object($command) || !method_exists($command, 'notification')) {
            return;
        }

        try {
            $notification = $command->notification ?? null;
            $notifiable = $command->notifiables[0] ?? null;

            if (!$notification || !$notifiable) {
                return;
            }

            // Get notification type
            $notificationType = get_class($notification);

            // Get recipient email
            $recipientEmail = $notifiable->email ?? null;
            $recipientName = $notifiable->name ?? null;

            if (!$recipientEmail) {
                return;
            }

            // Try to find existing queued/sent log
            $emailLog = EmailLog::where('recipient_email', $recipientEmail)
                ->where('notification_type', $notificationType)
                ->whereIn('status', ['queued', 'sent'])
                ->latest()
                ->first();

            if ($emailLog) {
                // Update existing log with failure info
                $emailLog->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'attempts' => $emailLog->attempts + 1,
                    'error_message' => $event->exception->getMessage(),
                    'metadata' => array_merge($emailLog->metadata ?? [], [
                        'exception_class' => get_class($event->exception),
                        'exception_trace' => $event->exception->getTraceAsString(),
                    ]),
                ]);
            } else {
                // Create new failed log entry
                EmailLog::create([
                    'uuid' => (string) Str::uuid(),
                    'notification_type' => $notificationType,
                    'notifiable_type' => get_class($notifiable),
                    'notifiable_id' => $notifiable->id ?? null,
                    'recipient_email' => $recipientEmail,
                    'recipient_name' => $recipientName,
                    'subject' => 'Failed to send',
                    'status' => 'failed',
                    'failed_at' => now(),
                    'attempts' => 1,
                    'error_message' => $event->exception->getMessage(),
                    'metadata' => [
                        'exception_class' => get_class($event->exception),
                    ],
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if we can't log the error
            // We don't want to break the queue because of logging issues
            \Log::error('Failed to log email failure: '.$e->getMessage());
        }
    }
}
