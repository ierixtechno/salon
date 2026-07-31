<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deliberately NOT BelongsToTenant — same reasoning as Quotation. Immutable
 * once created (CLAUDE.md §45): only ever produced by PayQuotation, no
 * update/destroy route exists.
 */
class PlatformInvoice extends Model
{
    protected $fillable = [
        'tenant_id', 'quotation_id', 'subscription_plan_id',
        'invoice_number', 'amount', 'payment_method', 'payment_reference', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
