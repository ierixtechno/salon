<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Actions\Concerns\AssertsAppointmentAvailability;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Resource;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceVariant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The concurrency-safe core of the Appointment Engine
 * (docs/modules/APPOINTMENT.md, CLAUDE.md §16/§17/§24).
 *
 * Stable facts (service available at branch, employee capable of the
 * service, resource belongs to the branch, tenant/module boundaries) are
 * validated once by StoreAppointmentRequest — they don't change between
 * page-load and submit. Everything checked here (AssertsAppointmentAvailability)
 * is time-sensitive and is deliberately re-verified fresh, inside a locked
 * transaction, immediately before insert (SKILL.md §16/§17: "availability
 * shown to a user is never a guarantee").
 *
 * Concurrency: MySQL has no native range-exclusion constraint, so two
 * simultaneous bookings for the same employee/resource are serialized by
 * taking a `lockForUpdate()` on that employee's/resource's own row first —
 * the second transaction blocks until the first commits, then re-reads
 * (not from a stale snapshot) and correctly sees the newly inserted
 * appointment. This is the standard "lock a proxy row, then check-then-act"
 * pattern for range conflicts on MySQL.
 *
 * Booked appointments start `confirmed` by default — staff/walk-in
 * bookings have no separate "customer requests, staff confirms" step.
 * Phase 13's public online-booking flow passes `initialStatus: 'pending'`
 * instead, since an anonymous request has had far less scrutiny than a
 * staff member typing it in; `pending -> confirmed`/`cancelled` are both
 * valid transitions already (see Appointment::TRANSITIONS).
 *
 * Every appointment also gets a `public_token` (ULID) regardless of
 * source — the unguessable, non-sequential identifier Phase 13's public
 * view/cancel-my-booking link uses instead of the internal id
 * (CLAUDE.md §19).
 */
class BookAppointment
{
    use AssertsAppointmentAvailability;

    public function execute(
        Branch $branch,
        Customer $customer,
        Service $service,
        ?ServiceVariant $variant,
        User $employee,
        ?Resource $resource,
        Carbon $startsAt,
        string $source = 'staff',
        ?string $notes = null,
        ?int $createdBy = null,
        ?string $groupUuid = null,
        string $initialStatus = 'confirmed',
        string $serviceMode = 'branch',
        ?string $deliveryAddress = null,
    ): Appointment {
        abort_unless(in_array($initialStatus, ['pending', 'confirmed'], true), 422, 'Invalid initial appointment status.');

        // Eloquent's plain `datetime` cast does NOT normalize timezones on
        // write — it stores whatever wall-clock the Carbon instance shows,
        // then re-hydrates naive DB strings assuming config('app.timezone')
        // (UTC) on read. Without forcing UTC here, a branch-local Carbon
        // (e.g. Asia/Kolkata) would round-trip to a completely different
        // absolute instant the next time it's read back, silently breaking
        // every conflict check. CLAUDE.md §22: canonical storage must be
        // one consistent representation.
        $startsAt = $startsAt->copy()->utc();
        $durationMinutes = $variant?->effectiveDurationMinutes() ?? $service->duration_minutes;
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);
        $bufferMinutes = $service->buffer_minutes + ($serviceMode !== 'branch' ? $service->travel_buffer_minutes : 0);
        $price = (string) ((float) ($variant?->effectivePrice() ?? $service->priceForBranch($branch)) + (float) $service->feeForMode($serviceMode));

        return DB::transaction(function () use (
            $branch, $customer, $service, $variant, $employee, $resource,
            $startsAt, $endsAt, $bufferMinutes, $price,
            $source, $notes, $createdBy, $groupUuid, $initialStatus,
            $serviceMode, $deliveryAddress,
        ) {
            // Lock proxy rows first — see class docblock.
            User::whereKey($employee->id)->lockForUpdate()->first();
            if ($resource) {
                Resource::whereKey($resource->id)->lockForUpdate()->first();
            }

            $this->assertWithinBusinessHours($branch, $startsAt, $endsAt);
            $this->assertNotOnHoliday($branch, $startsAt);
            $this->assertWithinEmployeeSchedule($employee, $branch, $startsAt, $endsAt);
            $this->assertEmployeeFree($employee, $startsAt, $endsAt, $bufferMinutes);

            if ($resource) {
                $this->assertResourceFree($resource, $startsAt, $endsAt);
            }

            // status/price are deliberately not in Appointment::$fillable
            // (CLAUDE.md §28 — never mass-assignable), so they're set
            // directly rather than through create()'s array.
            $appointment = new Appointment([
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'service_variant_id' => $variant?->id,
                'user_id' => $employee->id,
                'resource_id' => $resource?->id,
                'group_uuid' => $groupUuid,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'source' => $source,
                'notes' => $notes,
                'created_by' => $createdBy,
                'service_mode' => $serviceMode,
                'delivery_address' => $deliveryAddress,
            ]);
            $appointment->status = $initialStatus;
            $appointment->price = $price;
            $appointment->public_token = (string) Str::ulid();
            $appointment->save();

            return $appointment;
        });
    }
}
