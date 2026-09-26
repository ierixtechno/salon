<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Deliberately NOT BelongsToTenant — this is a platform-owned record that
 * references a tenant, viewed from two different guards (Platform Admin
 * sees all tenants'; a Tenant admin sees only their own). Every tenant-
 * facing controller must explicitly check tenant_id ownership (CLAUDE.md
 * §32) since there's no automatic scope here.
 */
class Quotation extends Model
{
    public const STATUSES = ['pending', 'paid', 'cancelled'];

    protected $fillable = [
        'tenant_id', 'subscription_plan_id', 'branch_count', 'platform_admin_id',
        'quotation_number', 'amount', 'notes', 'status', 'is_upgrade',
        'cgst_amount', 'sgst_amount', 'igst_amount', 'gst_rate_percent', 'total_amount',
        'razorpay_order_id', 'paid_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2',
            'gst_rate_percent' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'is_upgrade' => 'boolean',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(PlatformInvoice::class);
    }
}
