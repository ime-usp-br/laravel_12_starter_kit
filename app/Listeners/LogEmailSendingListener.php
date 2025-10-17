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

        // Extract notification details from headers (once per message)
        $headers = $message->getHeaders();

        // Get UUID from header (added by WithEmailLogging trait)
        $uuid = null;
        if ($headers->has('X-Email-Log-UUID')) {
            $uuid = $headers->get('X-Email-Log-UUID')?->getBodyAsString();
        }

        // If no UUID header, generate one (for non-notification emails)
        if (! $uuid) {
            $uuid = (string) Str::uuid();
        }

        // Extract notification metadata
        $notificationType = null;
        $notifiableType = null;
        $notifiableId = null;

        if ($headers->has('X-Notification-Type')) {
            $notificationType = $headers->get('X-Notification-Type')?->getBodyAsString();
        }
        if ($headers->has('X-Notifiable-Type')) {
            $notifiableType = $headers->get('X-Notifiable-Type')?->getBodyAsString();
        }
        if ($headers->has('X-Notifiable-Id')) {
            $notifiableId = $headers->get('X-Notifiable-Id')?->getBodyAsString();
        }

        // Extract recipient information
        $recipients = $message->getTo() ?? [];
        $recipientEmails = $this->extractEmailAddresses($recipients);

        // Get the first (and usually only) recipient
        $recipientEmail = !empty($recipientEmails) ? array_key_first($recipientEmails) : 'unknown';
        $recipientName = !empty($recipientEmails) ? $recipientEmails[$recipientEmail] : null;

        $subject = $message->getSubject() ?? 'No Subject';

        // Check for duplicate log within last 5 seconds (same recipient + subject)
        // This prevents duplicate logs when Laravel's SendEmailVerificationNotification
        // listener is triggered multiple times
        $recentLog = EmailLog::where('recipient_email', $recipientEmail)
            ->where('subject', $subject)
            ->where('created_at', '>=', now()->subSeconds(5))
            ->first();

        if ($recentLog) {
            // Recent identical log exists, skip creation to prevent duplicates
            \Log::debug('Skipping duplicate email log', [
                'recipient' => $recipientEmail,
                'subject' => $subject,
                'recent_log_id' => $recentLog->id,
            ]);

            return;
        }

        // Create email log entry (one per message, not per recipient)
        EmailLog::create([
            'uuid' => $uuid,
            'notification_type' => $notificationType ?? 'Unknown',
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject' => $subject,
            'status' => 'queued',
            'metadata' => [
                'from' => $this->extractEmailAddresses($message->getFrom()),
                'reply_to' => $this->extractEmailAddresses($message->getReplyTo()),
                'all_recipients' => $recipientEmails,
            ],
        ]);
    }

    /**
     * Extract email addresses from address objects.
     *
     * @return array<string, string>
     */
    private function extractEmailAddresses(?array $addresses): array
    {
        if (! $addresses) {
            return [];
        }

        $result = [];
        foreach ($addresses as $address) {
            // Symfony Address object has getAddress() and getName() methods
            if (is_object($address) && method_exists($address, 'getAddress')) {
                $email = $address->getAddress();
                $name = method_exists($address, 'getName') ? $address->getName() : null;
                $result[$email] = $name ?? $email;
            } else {
                // Fallback for unexpected format
                $result['unknown'] = 'unknown';
            }
        }

        return $result;
    }
}
