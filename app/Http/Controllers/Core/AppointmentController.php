<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\BookAppointment;
use App\Domain\Core\Actions\CancelAppointment;
use App\Domain\Core\Actions\CheckInAppointment;
use App\Domain\Core\Actions\CompleteAppointment;
use App\Domain\Core\Actions\MarkAppointmentNoShow;
use App\Domain\Core\Actions\RecordServiceConsumption;
use App\Domain\Core\Actions\RescheduleAppointment;
use App\Domain\Core\Actions\StartAppointmentService;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Resource;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Core\Models\StockMovement;
use App\Domain\Core\Models\WaitlistEntry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CancelAppointmentRequest;
use App\Http\Requests\Core\RescheduleAppointmentRequest;
use App\Http\Requests\Core\StoreAppointmentRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    public function __construct()
    {
        // Only index/create/store/show exist as standard resource methods
        // here — checkIn/start/complete/noShow/cancel/reschedule are custom
        // actions authorized individually below (there is deliberately no
        // edit/update/destroy: an appointment is never freeform-edited or
        // deleted, only moved through its lifecycle — CLAUDE.md §32/§48).
        $this->authorizeResource(Appointment::class, 'appointment');
    }

    public function index(Request $request): View
    {
        $branch = $this->resolveBranch($request);
        $date = $request->date('date') ?? now($branch?->effectiveTimezone() ?? 'UTC')->startOfDay();

        $appointments = $branch
            ? Appointment::where('branch_id', $branch->id)
                ->whereDate('starts_at', $date->copy()->setTimezone($branch->effectiveTimezone())->toDateString())
                ->whereNotIn('status', ['cancelled'])
                ->with(['customer', 'service', 'employee', 'resource'])
                ->orderBy('starts_at')
                ->get()
            : collect();

        return view('core.appointments.index', [
            'branches' => $this->accessibleBranches(),
            'branch' => $branch,
            'date' => $date,
            'appointments' => $appointments,
        ]);
    }

    public function create(Request $request): View
    {
        $branch = $this->resolveBranch($request);

        return view('core.appointments.create', [
            'branches' => $this->accessibleBranches(),
            'branch' => $branch,
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
            'categories' => ServiceCategory::where('is_active', true)
                ->with(['services' => fn ($q) => $q->where('is_active', true)->with(['variants', 'capableEmployees'])])
                ->orderBy('name')
                ->get(),
            'resources' => $branch ? Resource::where('branch_id', $branch->id)->where('is_active', true)->orderBy('name')->get() : collect(),
            'prefill' => $request->only(['customer_id', 'service_id', 'waitlist_entry_id']),
        ]);
    }

    public function store(StoreAppointmentRequest $request, BookAppointment $action): RedirectResponse
    {
        $branch = Branch::findOrFail($request->validated('branch_id'));
        $service = Service::findOrFail($request->validated('service_id'));

        $appointment = $action->execute(
            branch: $branch,
            customer: Customer::findOrFail($request->validated('customer_id')),
            service: $service,
            variant: $request->validated('service_variant_id') ? $service->variants()->findOrFail($request->validated('service_variant_id')) : null,
            employee: User::findOrFail($request->validated('user_id')),
            resource: $request->validated('resource_id') ? Resource::findOrFail($request->validated('resource_id')) : null,
            startsAt: Carbon::parse($request->validated('starts_at'), $branch->effectiveTimezone()),
            source: $request->validated('source') ?? 'staff',
            notes: $request->validated('notes'),
            createdBy: Auth::guard('web')->id(),
        );

        if ($request->validated('waitlist_entry_id')) {
            WaitlistEntry::whereKey($request->validated('waitlist_entry_id'))->update(['status' => 'booked']);
        }

        return redirect()->route('appointments.show', $appointment)->with('status', 'Appointment booked.');
    }

    public function show(Appointment $appointment): View
    {
        $appointment->load(['customer', 'service.consumables', 'serviceVariant', 'employee', 'resource', 'branch']);

        return view('core.appointments.show', [
            'appointment' => $appointment,
            'consumptionRecorded' => StockMovement::where('reference_type', 'appointment_consumption')
                ->where('reference_id', $appointment->id)
                ->exists(),
        ]);
    }

    public function checkIn(Appointment $appointment, CheckInAppointment $action): RedirectResponse
    {
        $this->authorize('update', $appointment);
        $action->execute($appointment);

        return back()->with('status', 'Customer checked in.');
    }

    public function start(Appointment $appointment, StartAppointmentService $action): RedirectResponse
    {
        $this->authorize('update', $appointment);
        $action->execute($appointment);

        return back()->with('status', 'Service started.');
    }

    public function complete(Appointment $appointment, CompleteAppointment $action): RedirectResponse
    {
        $this->authorize('update', $appointment);
        $action->execute($appointment);

        return back()->with('status', 'Appointment completed.');
    }

    public function noShow(Appointment $appointment, MarkAppointmentNoShow $action): RedirectResponse
    {
        $this->authorize('update', $appointment);
        $action->execute($appointment);

        return back()->with('status', 'Marked as no-show.');
    }

    /**
     * A manual, staff-triggered stock deduction for this appointment's
     * service — deliberately not automatic on complete() (see
     * RecordServiceConsumption's docblock). Gated by inventory.adjust
     * since this is fundamentally a stock action, not an appointment
     * lifecycle one.
     */
    public function recordConsumption(Appointment $appointment, RecordServiceConsumption $action): RedirectResponse
    {
        $this->authorize('view', $appointment);
        abort_unless(Auth::guard('web')->user()->can('inventory.adjust'), 403);

        $action->execute($appointment, Auth::guard('web')->id());

        return back()->with('status', 'Product usage recorded.');
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment, CancelAppointment $action): RedirectResponse
    {
        $action->execute($appointment, $request->validated('reason'));

        return back()->with('status', 'Appointment cancelled.');
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment, RescheduleAppointment $action): RedirectResponse
    {
        $action->execute($appointment, Carbon::parse($request->validated('starts_at'), $appointment->branch->effectiveTimezone()));

        return back()->with('status', 'Appointment rescheduled.');
    }

    private function resolveBranch(Request $request): ?Branch
    {
        $accessible = $this->accessibleBranches();

        if ($request->filled('branch_id')) {
            $requested = $accessible->firstWhere('id', (int) $request->integer('branch_id'));
            if ($requested) {
                return $requested;
            }
        }

        return $accessible->first();
    }

    private function accessibleBranches(): Collection
    {
        $user = Auth::guard('web')->user();

        return Branch::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Branch $branch) => $user->canAccessBranch($branch))
            ->values();
    }
}
