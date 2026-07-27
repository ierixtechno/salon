<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `status`/`sent_at` are deliberately not in $fillable — set only via
 * SendCampaign (CLAUDE.md §28).
 */
class Campaign extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['draft', 'scheduled', 'sending', 'sent', 'cancelled'];

    public const TRANSITIONS = [
        'draft' => ['scheduled', 'sending', 'cancelled'],
        'scheduled' => ['sending', 'cancelled'],
        'sending' => ['sent'],
    ];

    protected $fillable = ['name', 'type', 'channel', 'template_id', 'segment_id', 'scheduled_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(CustomerSegment::class, 'segment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
