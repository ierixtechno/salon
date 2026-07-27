<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignAutomation extends Model
{
    use BelongsToTenant;

    public const TYPES = ['birthday', 'membership_expiry', 'package_expiry', 're_engagement', 'feedback_request'];

    protected $fillable = ['type', 'is_enabled', 'template_id', 'threshold_days'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function isReady(): bool
    {
        return $this->is_enabled && $this->template_id !== null;
    }
}
