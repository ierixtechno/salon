<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    use BelongsToTenant;

    public const TYPES = ['percentage', 'fixed'];

    protected $fillable = ['user_id', 'type', 'rate', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Never trust a client-submitted commission value (CLAUDE.md §22) —
     * this is the one place the amount for a given line is computed from.
     */
    public function amountFor(float $taxableValue): float
    {
        return $this->type === 'percentage'
            ? round($taxableValue * ((float) $this->rate / 100), 2)
            : (float) $this->rate;
    }
}
