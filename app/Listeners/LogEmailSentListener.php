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

        // Extract UUID from headers
        $headers = $message->getHeaders();
        $uuid = null;

        if ($headers->has('X-Email-Log-UUID')) {
            $uuid = $headers->get('X-Email-Log-UUID')?->getBodyAsString();
        }

        if ($uuid) {
            // Find email log by UUID
            $emailLog = EmailLog::where('uuid', $uuid)
                ->where('status', 'queued')
                ->first();

            if ($emailLog) {
                // Update status to sent
                $emailLog->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'attempts' => $emailLog->attempts + 1,
                ]);
            } else {
                // Log warning if UUID exists but no log found
                \Log::warning('Email sent but no queued log found', [
                    'uuid' => $uuid,
                    'subject' => $message->getSubject(),
                ]);
            }
        } else {
            // Fallback: Try to find by recipient email and subject (for emails without UUID header)
            $recipients = $message->getTo();

            if (! $recipients) {
                return;
            }

            foreach ($recipients as $email => $name) {
                $emailStr = is_string($email) ? $email : 'unknown';

                $emailLog = EmailLog::where('recipient_email', $emailStr)
                    ->where('subject', $message->getSubject() ?? 'No Subject')
                    ->where('status', 'queued')
                    ->latest()
                    ->first();

                if ($emailLog) {
                    $emailLog->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                        'attempts' => $emailLog->attempts + 1,
                    ]);
                } else {
                    // Log warning - MessageSending event was not triggered
                    \Log::warning('Email sent without prior MessageSending event', [
                        'recipient' => $emailStr,
                        'subject' => $message->getSubject(),
                    ]);
                }
            }
        }
    }
}
