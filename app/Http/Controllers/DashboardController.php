<?php

namespace App\Http\Controllers;

use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\BranchStock;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Every section is gated by the viewer's own permissions (CLAUDE.md §9) —
 * a Staff account without invoices.view never triggers the revenue query
 * at all, not just a hidden card. All aggregates are scoped to "today"/
 * "this month" (never lifetime — CLAUDE.md §55) and reuse already-indexed
 * (tenant_id, branch_id, ...) columns the rest of the app queries by.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::guard('web')->user();
        $branches = $this->accessibleBranches();
        $branch = $this->resolveBranch($request, $branches);

        $todaysAppointments = collect();
        $appointmentCounts = ['upcoming' => 0, 'completed' => 0];
        if ($branch && $user->can('appointments.view')) {
            $todaysAppointments = Appointment::where('branch_id', $branch->id)
                ->whereDate('starts_at', now($branch->effectiveTimezone())->toDateString())
                ->whereNotIn('status', ['cancelled'])
                ->with(['customer', 'service', 'employee'])
                ->orderBy('starts_at')
                ->get();

            $appointmentCounts = [
                'upcoming' => $todaysAppointments->whereIn('status', ['pending', 'confirmed', 'checked_in', 'in_service'])->count(),
                'completed' => $todaysAppointments->where('status', 'completed')->count(),
            ];
        }

        $todaysSales = null;
        if ($branch && $user->can('invoices.view')) {
            $todaysSales = Invoice::where('branch_id', $branch->id)
                ->whereNotIn('status', ['draft', 'void'])
                ->whereDate('finalized_at', now($branch->effectiveTimezone())->toDateString())
                ->sum('grand_total');
        }

        $newCustomersThisMonth = null;
        if ($user->can('customers.view')) {
            $newCustomersThisMonth = Customer::where('created_at', '>=', now()->startOfMonth())->count();
        }

        $lowStockItems = collect();
        if ($branch && $user->can('inventory.view')) {
            $lowStockItems = BranchStock::where('branch_id', $branch->id)
                ->with('product')
                ->get()
                ->filter->isLowStock()
                ->values();
        }

        return view('dashboard', [
            'branches' => $branches,
            'branch' => $branch,
            'todaysAppointments' => $todaysAppointments,
            'appointmentCounts' => $appointmentCounts,
            'todaysSales' => $todaysSales,
            'newCustomersThisMonth' => $newCustomersThisMonth,
            'lowStockItems' => $lowStockItems,
        ]);
    }

    private function resolveBranch(Request $request, Collection $accessible): ?Branch
    {
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
