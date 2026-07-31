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
        'invoice_number', 'amount', 'subtotal', 'cgst_amount', 'sgst_amount', 'igst_amount', 'gst_rate_percent',
        'payment_method', 'payment_reference', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2',
            'gst_rate_percent' => 'decimal:2',
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
