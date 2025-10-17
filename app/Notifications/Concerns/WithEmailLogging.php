<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Trait WithEmailLogging
 *
 * Adds custom headers to email notifications for tracking purposes.
 * This trait should be used in notification classes that send emails.
 *
 * The trait expects the notification class to have a toMailMessage() method
 * that returns a MailMessage instance. It will wrap this method and add
 * tracking headers before the email is sent.
 */
trait WithEmailLogging
{
    /**
     * Cached notification metadata for serialization
     */
    public ?string $emailLogUuid = null;

    public ?string $emailNotificationType = null;

    public ?string $emailNotifiableType = null;

    public ?string $emailNotifiableId = null;

    /**
     * Get the mail representation of the notification.
     *
     * This method wraps the toMailMessage() method and adds custom headers
     * that are used by the email logging system to track notifications.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        // Call the notification's mail message builder
        $message = $this->toMailMessage($notifiable);

        // Generate and cache metadata (will be serialized with notification)
        $this->emailLogUuid = (string) Str::uuid();
        $this->emailNotificationType = static::class;

        if (is_object($notifiable)) {
            $this->emailNotifiableType = get_class($notifiable);
            $this->emailNotifiableId = isset($notifiable->id) ? (string) $notifiable->id : null;
        }

        // Add as Symfony headers when the message is being built
        $message->withSymfonyMessage(function ($email) {
            $headers = $email->getHeaders();

            // Add headers using cached properties (available after deserialization)
            $headers->addTextHeader('X-Email-Log-UUID', $this->emailLogUuid);
            $headers->addTextHeader('X-Notification-Type', $this->emailNotificationType);

            if ($this->emailNotifiableType) {
                $headers->addTextHeader('X-Notifiable-Type', $this->emailNotifiableType);
            }

            if ($this->emailNotifiableId) {
                $headers->addTextHeader('X-Notifiable-Id', $this->emailNotifiableId);
            }
        });

        return $message;
    }

    /**
     * Build the mail message for the notification.
     *
     * This method should be implemented by the notification class
     * and return a MailMessage instance.
     *
     * @param  mixed  $notifiable
     */
    abstract protected function toMailMessage($notifiable): MailMessage;
}
