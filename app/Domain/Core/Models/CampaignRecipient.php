<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `status`/`notification_log_id` are deliberately not in $fillable — set
 * only via SendCampaign (CLAUDE.md §28).
 */
class CampaignRecipient extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['pending', 'sent', 'failed', 'skipped_no_consent'];

    protected $fillable = ['campaign_id', 'customer_id'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function notificationLog(): BelongsTo
    {
        return $this->belongsTo(NotificationLog::class);
    }
}
