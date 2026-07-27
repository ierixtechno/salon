<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipUsage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['customer_membership_id', 'invoice_line_id', 'discount_amount', 'applied_at', 'applied_by'];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'applied_at' => 'datetime',
        ];
    }

    public function customerMembership(): BelongsTo
    {
        return $this->belongsTo(CustomerMembership::class);
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
