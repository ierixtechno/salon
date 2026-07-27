<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * `status`/`provider`/`error_message`/`sent_at`/`read_at` are deliberately
 * not in $fillable — set only via SendNotification/DeliverNotification job
 * (CLAUDE.md §28).
 */
class NotificationLog extends Model
{
    use BelongsToTenant;

    public const CHANNELS = ['email', 'sms', 'whatsapp', 'in_app'];

    public const STATUSES = ['queued', 'sent', 'failed', 'skipped'];

    protected $fillable = [
        'channel', 'recipient_type', 'recipient_id', 'to_address',
        'subject', 'body', 'reference_type', 'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }
}
