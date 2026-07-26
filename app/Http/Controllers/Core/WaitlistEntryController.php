<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\WaitlistEntry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreWaitlistEntryRequest;
use App\Http\Requests\Core\UpdateWaitlistEntryRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class WaitlistEntryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(WaitlistEntry::class, 'waitlist_entry');
    }

    public function index(): View
    {
        return view('core.waitlist.index', [
            'entries' => WaitlistEntry::whereIn('status', ['waiting', 'notified'])
                ->with(['branch', 'customer', 'service'])
                ->orderBy('preferred_date')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('core.waitlist.create', [
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreWaitlistEntryRequest $request): RedirectResponse
    {
        WaitlistEntry::create($request->validated());

        return redirect()->route('waitlist.index')->with('status', 'Added to waitlist.');
    }

    public function edit(WaitlistEntry $waitlistEntry): View
    {
        return view('core.waitlist.edit', ['entry' => $waitlistEntry]);
    }

    public function update(UpdateWaitlistEntryRequest $request, WaitlistEntry $waitlistEntry): RedirectResponse
    {
        $waitlistEntry->update($request->validated());

        return redirect()->route('waitlist.index')->with('status', 'Waitlist entry updated.');
    }

    public function destroy(WaitlistEntry $waitlistEntry): RedirectResponse
    {
        $waitlistEntry->update(['status' => 'cancelled']);

        return redirect()->route('waitlist.index')->with('status', 'Removed from waitlist.');
    }

    /**
     * A waitlist entry never holds a firm slot — this just hands off to the
     * normal booking form pre-filled with the entry's customer/branch/
     * service. AppointmentController::store() marks the entry `booked` once
     * an appointment is actually created from it.
     */
    public function book(WaitlistEntry $waitlistEntry): RedirectResponse
    {
        $this->authorize('update', $waitlistEntry);

        return redirect()->route('appointments.create', [
            'branch_id' => $waitlistEntry->branch_id,
            'customer_id' => $waitlistEntry->customer_id,
            'service_id' => $waitlistEntry->service_id,
            'waitlist_entry_id' => $waitlistEntry->id,
        ]);
    }
}
