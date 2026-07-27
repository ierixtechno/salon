<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToTenant;

    public const METHODS = ['cash', 'card', 'upi', 'bank_transfer', 'wallet', 'gift_card', 'loyalty'];

    /**
     * Methods acceptable through the generic "record a payment" form
     * (StorePaymentRequest). wallet/gift_card/loyalty each need their own
     * dedicated action (RedeemWalletBalance/RedeemGiftCard/
     * RedeemLoyaltyPoints) to resolve a balance/code/point-conversion
     * server-side first — they're never accepted as a bare client-supplied
     * amount the way cash/card/upi/bank_transfer are.
     */
    public const MANUAL_METHODS = ['cash', 'card', 'upi', 'bank_transfer'];

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = [
        'invoice_id', 'method', 'amount', 'tip_amount', 'reference', 'idempotency_key',
        'gift_card_id', 'points_redeemed', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tip_amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }
}
