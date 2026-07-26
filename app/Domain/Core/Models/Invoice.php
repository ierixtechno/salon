<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * State machine (App\Domain\Core\Models\Invoice::TRANSITIONS):
 *
 *   draft          -> finalized
 *   finalized      -> partially_paid, paid, void
 *   partially_paid -> paid, refunded
 *   paid           -> refunded
 *
 * A `finalized` invoice with zero payments recorded is the only state void
 * is reachable from — the instant any payment lands, status moves to
 * partially_paid/paid and void is no longer reachable (money changed
 * hands; use a refund instead). CLAUDE.md §45/§48: finalized financial
 * records are never silently edited/deleted — void/refund only.
 *
 * `invoice_number`/`financial_year`/`sequence_number` are null until
 * CheckoutSale finalizes the draft — a draft never became a legal
 * document, so it's deletable outright without leaving a numbering gap to
 * account for.
 */
class Invoice extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['draft', 'finalized', 'partially_paid', 'paid', 'void', 'refunded'];

    public const TRANSITIONS = [
        'draft' => ['finalized'],
        'finalized' => ['partially_paid', 'paid', 'void'],
        'partially_paid' => ['paid', 'refunded'],
        'paid' => ['refunded'],
    ];

    // tenant_id/status/totals are deliberately not in $fillable (CLAUDE.md
    // §28) — resolved/transitioned only through CheckoutSale, RecordPayment,
    // ProcessRefund, and VoidInvoice, never mass assignment.
    protected $fillable = ['branch_id', 'customer_id', 'customer_name', 'customer_phone', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'cgst_total' => 'decimal:2',
            'sgst_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'finalized_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function totalPaid(): string
    {
        return (string) $this->payments()->sum('amount');
    }

    public function totalRefunded(): string
    {
        return (string) $this->refunds()->sum('amount');
    }
}
