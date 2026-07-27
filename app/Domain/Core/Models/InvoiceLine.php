<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line is only ever created while its parent Invoice is `draft` (Phase 6
 * scope — lines are mutable pre-finalization, immutable after, same as the
 * invoice itself). `description`/`unit_price`/`tax_rate_percent` are
 * snapshotted from the Service/ServiceVariant at the moment the line was
 * added — never re-derived from the live catalogue later (CLAUDE.md
 * §20/§45).
 */
class InvoiceLine extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'invoice_id', 'service_id', 'service_variant_id', 'appointment_id', 'performed_by',
        'description', 'quantity', 'unit_price', 'discount_amount',
        'taxable_value', 'tax_rate_percent', 'cgst_amount', 'sgst_amount', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable_value' => 'decimal:2',
            'tax_rate_percent' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceVariant(): BelongsTo
    {
        return $this->belongsTo(ServiceVariant::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
