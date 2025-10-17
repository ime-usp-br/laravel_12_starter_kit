<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'notification_type',
        'notifiable_type',
        'notifiable_id',
        'recipient_email',
        'recipient_name',
        'subject',
        'status',
        'sent_at',
        'failed_at',
        'attempts',
        'error_message',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Get the notifiable entity that the email was sent to.
     *
     * @phpstan-return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include sent emails.
     *
     * @param  Builder<EmailLog>  $query
     * @return Builder<EmailLog>
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope a query to only include failed emails.
     *
     * @param  Builder<EmailLog>  $query
     * @return Builder<EmailLog>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include queued emails.
     *
     * @param  Builder<EmailLog>  $query
     * @return Builder<EmailLog>
     */
    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', 'queued');
    }

    /**
     * Scope a query to only include recent emails.
     *
     * @param  Builder<EmailLog>  $query
     * @return Builder<EmailLog>
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get the formatted status attribute.
     */
    public function getFormattedStatusAttribute(): string
    {
        return match ($this->status) {
            'sent' => 'Enviado',
            'failed' => 'Falhado',
            'queued' => 'Na Fila',
        };
    }

    /**
     * Get the status color for badges.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'sent' => 'success',
            'failed' => 'danger',
            'queued' => 'warning',
        };
    }

    /**
     * Get the notification type name (short class name).
     */
    public function getNotificationTypeNameAttribute(): string
    {
        return class_basename($this->notification_type);
    }
}
