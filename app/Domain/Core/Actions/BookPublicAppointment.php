<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceVariant;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Public booking never lets a customer pick a specific staff member
 * (CLAUDE.md §75 — no staff roster exposed). This tries each employee
 * capable of the service, in turn, through the real BookAppointment lock +
 * checks (GetAvailableSlots' preview is never trusted as a guarantee) —
 * the first one that's actually still free at submit time wins. If every
 * candidate has been taken since the slot was shown (a genuine race), the
 * customer gets one clean "no longer available" error rather than a
 * confusing 409 from a single arbitrarily-chosen employee.
 */
class BookPublicAppointment
{
    public function execute(
        Branch $branch,
        Customer $customer,
        Service $service,
        ?ServiceVariant $variant,
        Carbon $startsAt,
    ): Appointment {
        $candidates = $service->capableEmployees()->get();
        abort_if($candidates->isEmpty(), 409, 'No staff are available to perform this service.');

        foreach ($candidates as $employee) {
            try {
                return app(BookAppointment::class)->execute(
                    branch: $branch,
                    customer: $customer,
                    service: $service,
                    variant: $variant,
                    employee: $employee,
                    resource: null,
                    startsAt: $startsAt,
                    source: 'online',
                    initialStatus: 'pending',
                );
            } catch (HttpException $e) {
                if ($e->getStatusCode() !== 409) {
                    throw $e;
                }
                // This candidate is no longer free — try the next one.
            }
        }

        abort(409, 'This time is no longer available. Please choose another slot.');
    }
}
