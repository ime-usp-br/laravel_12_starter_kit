<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Str;

class LogEmailSendingListener
{
    /**
     * Handle the event.
     */
    public function handle(MessageSending $event): void
    {
        $message = $event->message;

        // Extract recipient information
        $recipients = $message->getTo() ?? [];

        foreach ($recipients as $email => $name) {
            // Generate UUID for tracking
            $uuid = (string) Str::uuid();

            // Extract notification details if available
            $notificationType = null;
            $notifiableType = null;
            $notifiableId = null;

            // Try to extract from headers
            $headers = $message->getHeaders();
            if ($headers->has('X-Notification-Type')) {
                $notificationType = $headers->get('X-Notification-Type')?->getBodyAsString();
            }
            if ($headers->has('X-Notifiable-Type')) {
                $notifiableType = $headers->get('X-Notifiable-Type')?->getBodyAsString();
            }
            if ($headers->has('X-Notifiable-Id')) {
                $notifiableId = $headers->get('X-Notifiable-Id')?->getBodyAsString();
            }

            // Create email log entry
            EmailLog::create([
                'uuid' => $uuid,
                'notification_type' => $notificationType ?? 'Unknown',
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'recipient_email' => is_string($email) ? $email : (is_object($email) ? $email->toString() : 'unknown'),
                'recipient_name' => is_string($name) ? $name : null,
                'subject' => $message->getSubject() ?? 'No Subject',
                'status' => 'queued',
                'metadata' => [
                    'from' => $this->extractEmailAddresses($message->getFrom()),
                    'reply_to' => $this->extractEmailAddresses($message->getReplyTo()),
                ],
            ]);
        }
    }

    /**
     * Extract email addresses from address objects.
     *
     * @param  array|null  $addresses
     * @return array<string, string>
     */
    private function extractEmailAddresses(?array $addresses): array
    {
        if (!$addresses) {
            return [];
        }

        $result = [];
        foreach ($addresses as $email => $name) {
            $emailStr = is_string($email) ? $email : (is_object($email) ? $email->toString() : 'unknown');
            $result[$emailStr] = is_string($name) ? $name : $emailStr;
        }

        return $result;
    }
}
