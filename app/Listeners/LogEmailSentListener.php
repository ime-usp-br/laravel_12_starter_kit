<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;

class LogEmailSentListener
{
    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        // Extract recipient email
        $recipients = $message->getTo() ?? [];

        foreach ($recipients as $email => $name) {
            $emailStr = is_string($email) ? $email : (is_object($email) ? $email->toString() : 'unknown');

            // Find the most recent queued email log for this recipient
            $emailLog = EmailLog::where('recipient_email', $emailStr)
                ->where('subject', $message->getSubject() ?? 'No Subject')
                ->where('status', 'queued')
                ->latest()
                ->first();

            if ($emailLog) {
                // Update status to sent
                $emailLog->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'attempts' => $emailLog->attempts + 1,
                ]);
            } else {
                // If no queued log found, create a new sent log
                // This might happen if MessageSending wasn't triggered
                EmailLog::create([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'notification_type' => 'Unknown',
                    'recipient_email' => $emailStr,
                    'recipient_name' => is_string($name) ? $name : null,
                    'subject' => $message->getSubject() ?? 'No Subject',
                    'status' => 'sent',
                    'sent_at' => now(),
                    'attempts' => 1,
                    'metadata' => [],
                ]);
            }
        }
    }
}
