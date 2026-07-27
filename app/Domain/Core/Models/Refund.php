<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use BelongsToTenant;

    /**
     * 'gift_card' is deliberately excluded — see ProcessRefund's docblock:
     * crediting an unrelated invoice refund into an arbitrary gift card is
     * an ambiguous business rule nobody has specified.
     */
    public const METHODS = ['cash', 'card', 'upi', 'bank_transfer', 'wallet', 'loyalty'];

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = ['invoice_id', 'amount', 'method', 'reason', 'points_credited', 'refunded_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
