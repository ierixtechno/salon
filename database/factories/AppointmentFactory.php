<?php

namespace Database\Factories;

use App\Domain\Core\Models\Appointment;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 *
 * Deliberately does NOT auto-provision branch_id/customer_id/service_id/
 * user_id via nested factories the way ResourceFactory/CustomerFactory do —
 * those would each get their own unrelated auto-created Tenant, producing an
 * appointment whose relations don't actually share a tenant. Callers must
 * always pass a consistent, already-tenant-scoped set of IDs (mirroring how
 * every appointment test builds its own branch/employee/customer/service
 * fixture first, the same way BookAppointment itself requires).
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'status' => 'pending',
            'source' => 'staff',
            'price' => 500,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->afterMaking(function (Appointment $appointment) use ($tenant) {
            $appointment->tenant_id = $tenant->id;
        });
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed']);
    }
}
