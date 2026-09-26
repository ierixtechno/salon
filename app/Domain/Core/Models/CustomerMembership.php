<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `status`/`usage_count` are deliberately not in $fillable — set only via
 * SellMembershipToCustomer/CancelCustomerMembership/AddInvoiceLine's
 * membership-discount path (CLAUDE.md §28).
 */
class CustomerMembership extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['active', 'expired', 'cancelled'];

    protected $fillable = [
        'customer_id', 'membership_plan_id', 'branch_id', 'invoice_id', 'price_paid',
        'purchase_method', 'purchase_reference', 'starts_at', 'expires_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price_paid' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(MembershipUsage::class);
    }

    public function isUsable(): bool
    {
        if ($this->status !== 'active' || $this->expires_at->isPast()) {
            return false;
        }

        $limit = $this->membershipPlan->usage_limit;

        return $limit === null || $this->usage_count < $limit;
    }
}
