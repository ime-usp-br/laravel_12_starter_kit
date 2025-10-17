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
 *
 * @template TNotifiable of object
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
     * @param  TNotifiable  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        // Call the notification's mail message builder
        $message = $this->toMailMessage($notifiable);

        // Generate and cache metadata (will be serialized with notification)
        $this->emailLogUuid = (string) Str::uuid();
        $this->emailNotificationType = static::class;
        $this->emailNotifiableType = get_class($notifiable);
        $this->emailNotifiableId = property_exists($notifiable, 'id') ? (string) $notifiable->id : null;

        // Add as Symfony headers when the message is being built
        $message->withSymfonyMessage(function ($email): void {
            if (! is_object($email) || ! method_exists($email, 'getHeaders')) {
                return;
            }

            $headers = $email->getHeaders();

            if (! is_object($headers) || ! method_exists($headers, 'addTextHeader')) {
                return;
            }

            // Add headers using cached properties (available after deserialization)
            if ($this->emailLogUuid !== null) {
                $headers->addTextHeader('X-Email-Log-UUID', $this->emailLogUuid);
            }

            if ($this->emailNotificationType !== null) {
                $headers->addTextHeader('X-Notification-Type', $this->emailNotificationType);
            }

            if ($this->emailNotifiableType !== null) {
                $headers->addTextHeader('X-Notifiable-Type', $this->emailNotifiableType);
            }

            if ($this->emailNotifiableId !== null) {
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
     * @param  TNotifiable  $notifiable
     */
    abstract protected function toMailMessage($notifiable): MailMessage;
}
