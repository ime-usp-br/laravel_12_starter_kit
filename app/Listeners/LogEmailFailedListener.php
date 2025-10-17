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
        $payload = $event->job->payload();
        $displayName = isset($payload['displayName']) && is_string($payload['displayName']) ? $payload['displayName'] : '';

        if (! Str::contains($displayName, 'Notification')) {
            return;
        }

        // Decode job payload
        $rawBody = $event->job->getRawBody();
        $decodedPayload = json_decode($rawBody, true);

        if (! is_array($decodedPayload) || ! isset($decodedPayload['data']) || ! is_array($decodedPayload['data']) || ! isset($decodedPayload['data']['command']) || ! is_string($decodedPayload['data']['command'])) {
            return;
        }

        $commandData = $decodedPayload['data']['command'];
        $command = unserialize($commandData);

        // Check if it's a notification with mail channel
        if (! is_object($command) || ! method_exists($command, 'notification')) {
            return;
        }

        try {
            $notification = property_exists($command, 'notification') ? $command->notification : null;
            $notifiables = property_exists($command, 'notifiables') && is_array($command->notifiables) ? $command->notifiables : [];
            $notifiable = $notifiables[0] ?? null;

            if (! is_object($notification) || ! is_object($notifiable)) {
                return;
            }

            // Get notification type
            $notificationType = get_class($notification);

            // Get recipient email
            $recipientEmail = property_exists($notifiable, 'email') && is_string($notifiable->email) ? $notifiable->email : null;
            $recipientName = property_exists($notifiable, 'name') && is_string($notifiable->name) ? $notifiable->name : null;

            if (! $recipientEmail) {
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
                $notifiableId = property_exists($notifiable, 'id') ? $notifiable->id : null;

                EmailLog::create([
                    'uuid' => (string) Str::uuid(),
                    'notification_type' => $notificationType,
                    'notifiable_type' => get_class($notifiable),
                    'notifiable_id' => $notifiableId,
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
