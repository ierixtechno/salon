<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * State machine (docs/modules/APPOINTMENT.md extends the simplified
 * diagram there — cancellation is legitimately needed from `confirmed` too,
 * e.g. a customer calling to cancel the day before, and `no_show` only
 * makes sense once an appointment was `confirmed`):
 *
 *   pending    -> confirmed, cancelled
 *   confirmed  -> checked_in, cancelled, no_show
 *   checked_in -> in_service
 *   in_service -> completed
 *
 * No other transition is valid — CLAUDE.md §32/SKILL.md §32. Reschedule
 * does not change status; it re-validates and moves starts_at/ends_at via a
 * fresh availability check (RescheduleAppointment).
 */
class Appointment extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUSES = ['pending', 'confirmed', 'checked_in', 'in_service', 'completed', 'cancelled', 'no_show'];

    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['checked_in', 'cancelled', 'no_show'],
        'checked_in' => ['in_service'],
        'in_service' => ['completed'],
    ];

    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
    }

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    // `price` and `status` are likewise excluded — resolved/transitioned
    // only through BookAppointment and the lifecycle Actions, never mass
    // assignment.
    protected $fillable = [
        'branch_id', 'customer_id', 'service_id', 'service_variant_id',
        'user_id', 'resource_id', 'group_uuid', 'starts_at', 'ends_at',
        'source', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'no_show_at' => 'datetime',
            'price' => 'decimal:2',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceVariant(): BelongsTo
    {
        return $this->belongsTo(ServiceVariant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether this appointment has already been billed — an appointment
     * can only ever appear on one invoice line (StoreInvoiceLineRequest).
     */
    public function invoiceLine(): HasOne
    {
        return $this->hasOne(InvoiceLine::class);
    }
}
