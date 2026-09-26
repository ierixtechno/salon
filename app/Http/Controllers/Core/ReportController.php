<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Expense;
use App\Domain\Core\Models\ExpenseCategory;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\InvoiceLine;
use App\Domain\Core\Models\Payment;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Support\BuildPaymentLedger;
use App\Domain\Platform\Models\Module;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every report is a live aggregate query over already-tenant-scoped
 * models (CLAUDE.md §14) — no summary/materialized tables yet. That's a
 * deliberate MVP choice for a fresh platform with no meaningful data
 * volume; §55's "summary tables, scheduled aggregation" is the documented
 * next step once a tenant's history is large enough for these queries to
 * matter for performance, not something to build ahead of need.
 *
 * Scope: this phase covers Sales, Appointments, Module Performance
 * (revenue/bookings per Salon/Beauty/Spa — see the sidebar module
 * sections) and Expenses. CLAUDE.md §14 also lists Purchasing, Tax
 * (beyond the tax total already on the Sales report), Commission,
 * Membership, Packages, Loyalty, Marketing, and standalone Employee
 * reports — Commission already has its own ledger/UI (Phase 9) and the
 * rest are deliberately deferred rather than built shallow across seven
 * more domains in one pass.
 */
class ReportController extends Controller
{
    public function sales(Request $request): View|StreamedResponse
    {
        $branches = $this->accessibleBranches();
        $branch = $this->resolveBranch($request, $branches);
        $branchIds = $this->branchIds($branch, $branches);
        [$from, $to] = $this->resolveDateRange($request, $branch);

        $base = fn () => Invoice::whereIn('branch_id', $branchIds)
            ->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('finalized_at', [$from, $to]);

        $totals = $base()->selectRaw('COALESCE(SUM(grand_total),0) as revenue, COALESCE(SUM(tax_total),0) as tax, COALESCE(SUM(discount_total),0) as discount, COUNT(*) as invoice_count')->first();

        $byDay = $base()->selectRaw('DATE(finalized_at) as day, SUM(grand_total) as revenue, COUNT(*) as invoice_count')
            ->groupBy('day')->orderBy('day')->get();

        $byBranch = collect();
        if (! $branch) {
            $rows = $base()->selectRaw('branch_id, SUM(grand_total) as revenue, COUNT(*) as invoice_count')->groupBy('branch_id')->get();
            $byBranch = $rows->map(fn ($row) => (object) [
                'branch_name' => $branches->firstWhere('id', $row->branch_id)?->name ?? 'Unknown',
                'revenue' => $row->revenue,
                'invoice_count' => $row->invoice_count,
            ])->sortByDesc('revenue')->values();
        }

        $byMethod = Payment::whereHas('invoice', fn ($q) => $q->whereIn('branch_id', $branchIds)
            ->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('finalized_at', [$from, $to]))
            ->selectRaw('method, SUM(amount) as amount, COUNT(*) as payment_count')
            ->groupBy('method')->orderByDesc('amount')->get();

        if ($request->query('export') === 'csv') {
            return $this->streamCsv('sales-report.csv', ['Date', 'Revenue', 'Invoices'], $byDay->map(fn ($row) => [$row->day, $row->revenue, $row->invoice_count]));
        }

        return view('core.reports.sales', compact('branches', 'branch', 'from', 'to', 'totals', 'byDay', 'byBranch', 'byMethod'));
    }

    public function appointments(Request $request): View|StreamedResponse
    {
        $branches = $this->accessibleBranches();
        $branch = $this->resolveBranch($request, $branches);
        $branchIds = $this->branchIds($branch, $branches);
        [$from, $to] = $this->resolveDateRange($request, $branch);

        $base = fn () => Appointment::whereIn('branch_id', $branchIds)->whereBetween('starts_at', [$from, $to]);

        $total = $base()->count();
        $byStatus = $base()->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $noShowRate = $total > 0 ? round((($byStatus['no_show'] ?? 0) / $total) * 100, 1) : 0.0;

        $employeeRows = $base()->where('status', 'completed')->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as completed_count')->groupBy('user_id')->orderByDesc('completed_count')->limit(10)->get();
        $employeeNames = User::whereIn('id', $employeeRows->pluck('user_id'))->pluck('name', 'id');
        $byEmployee = $employeeRows->map(fn ($row) => (object) ['name' => $employeeNames[$row->user_id] ?? 'Unknown', 'completed_count' => $row->completed_count]);

        $serviceRows = $base()->selectRaw('service_id, COUNT(*) as booking_count')->groupBy('service_id')->orderByDesc('booking_count')->limit(10)->get();
        $serviceNames = Service::whereIn('id', $serviceRows->pluck('service_id'))->pluck('name', 'id');
        $byService = $serviceRows->map(fn ($row) => (object) ['name' => $serviceNames[$row->service_id] ?? 'Unknown', 'booking_count' => $row->booking_count]);

        $byBranch = collect();
        if (! $branch) {
            $rows = $base()->selectRaw('branch_id, COUNT(*) as count')->groupBy('branch_id')->get();
            $byBranch = $rows->map(fn ($row) => (object) [
                'branch_name' => $branches->firstWhere('id', $row->branch_id)?->name ?? 'Unknown',
                'count' => $row->count,
            ])->sortByDesc('count')->values();
        }

        if ($request->query('export') === 'csv') {
            return $this->streamCsv('appointments-report.csv', ['Status', 'Count'], collect($byStatus)->map(fn ($count, $status) => [$status, $count]));
        }

        return view('core.reports.appointments', compact('branches', 'branch', 'from', 'to', 'total', 'byStatus', 'noShowRate', 'byEmployee', 'byService', 'byBranch'));
    }

    public function modulePerformance(Request $request): View|StreamedResponse
    {
        $branches = $this->accessibleBranches();
        $branch = $this->resolveBranch($request, $branches);
        $branchIds = $this->branchIds($branch, $branches);
        [$from, $to] = $this->resolveDateRange($request, $branch);

        $modules = current_tenant()->tenantModules()->where('enabled', true)->with('module')->get()->pluck('module');

        $rows = $modules->map(function (Module $module) use ($branchIds, $from, $to) {
            $revenue = InvoiceLine::whereHas('service', fn ($q) => $q->where('module_id', $module->id))
                ->whereHas('invoice', fn ($q) => $q->whereIn('branch_id', $branchIds)
                    ->whereNotIn('status', ['draft', 'void'])
                    ->whereBetween('finalized_at', [$from, $to]))
                ->sum('line_total');

            $appointmentCount = Appointment::whereIn('branch_id', $branchIds)
                ->whereBetween('starts_at', [$from, $to])
                ->whereHas('service', fn ($q) => $q->where('module_id', $module->id))
                ->count();

            $completedCount = Appointment::whereIn('branch_id', $branchIds)
                ->whereBetween('starts_at', [$from, $to])
                ->whereHas('service', fn ($q) => $q->where('module_id', $module->id))
                ->where('status', 'completed')
                ->count();

            return (object) [
                'module' => $module,
                'revenue' => (float) $revenue,
                'appointment_count' => $appointmentCount,
                'completed_count' => $completedCount,
            ];
        })->sortByDesc('revenue')->values();

        if ($request->query('export') === 'csv') {
            return $this->streamCsv('module-performance-report.csv', ['Module', 'Revenue', 'Appointments', 'Completed'], $rows->map(fn ($row) => [$row->module->name, $row->revenue, $row->appointment_count, $row->completed_count]));
        }

        return view('core.reports.module-performance', compact('branches', 'branch', 'from', 'to', 'rows'));
    }

    public function expenses(Request $request): View|StreamedResponse
    {
        $branches = $this->accessibleBranches();
        $branch = $this->resolveBranch($request, $branches);
        $branchIds = $this->branchIds($branch, $branches);
        [$from, $to] = $this->resolveDateRange($request, $branch);

        $base = fn () => Expense::whereIn('branch_id', $branchIds)
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()]);

        $totals = $base()->selectRaw('COALESCE(SUM(amount),0) as amount, COALESCE(SUM(tax_amount),0) as tax, COUNT(*) as expense_count')->first();

        $categoryRows = $base()->selectRaw('expense_category_id, SUM(amount) as amount, COUNT(*) as expense_count')->groupBy('expense_category_id')->orderByDesc('amount')->get();
        $categoryNames = ExpenseCategory::whereIn('id', $categoryRows->pluck('expense_category_id'))->pluck('name', 'id');
        $byCategory = $categoryRows->map(fn ($row) => (object) ['name' => $categoryNames[$row->expense_category_id] ?? 'Unknown', 'amount' => $row->amount, 'expense_count' => $row->expense_count]);

        $byBranch = collect();
        if (! $branch) {
            $rows = $base()->selectRaw('branch_id, SUM(amount) as amount, COUNT(*) as expense_count')->groupBy('branch_id')->get();
            $byBranch = $rows->map(fn ($row) => (object) [
                'branch_name' => $branches->firstWhere('id', $row->branch_id)?->name ?? 'Unknown',
                'amount' => $row->amount,
                'expense_count' => $row->expense_count,
            ])->sortByDesc('amount')->values();
        }

        if ($request->query('export') === 'csv') {
            return $this->streamCsv('expenses-report.csv', ['Category', 'Amount', 'Count'], $byCategory->map(fn ($row) => [$row->name, $row->amount, $row->expense_count]));
        }

        return view('core.reports.expenses', compact('branches', 'branch', 'from', 'to', 'totals', 'byCategory', 'byBranch'));
    }

    /**
     * Every rupee in and out, in one chronological register (see
     * BuildPaymentLedger). A one-year cap keeps the read-time assembly
     * bounded; use the CSV export for accountants.
     */
    public function ledger(Request $request, BuildPaymentLedger $builder): View|StreamedResponse
    {
        $branches = $this->accessibleBranches();
        $branch = $this->resolveBranch($request, $branches);
        $branchIds = $this->branchIds($branch, $branches);
        [$from, $to] = $this->resolveDateRange($request, $branch);
        abort_if($from->diffInDays($to) > 366, 422, 'Choose a date range of one year or less.');

        $direction = in_array($request->query('direction'), ['in', 'out'], true) ? $request->query('direction') : 'all';
        $method = in_array($request->query('method'), ['cash', 'card', 'upi', 'bank_transfer'], true) ? $request->query('method') : null;
        $timezone = $branch?->effectiveTimezone() ?? current_tenant()->timezone;

        $entries = $builder->execute(
            $from,
            $to,
            $branchIds,
            $branches->pluck('name', 'id'),
            includeBranchless: ! $branch && (bool) Auth::guard('web')->user()->all_branches,
            timezone: $timezone,
        )
            ->when($direction === 'in', fn ($e) => $e->filter(fn ($row) => $row['in'] > 0))
            ->when($direction === 'out', fn ($e) => $e->filter(fn ($row) => $row['out'] > 0))
            ->when($method, fn ($e) => $e->filter(fn ($row) => $row['method'] === $method))
            ->values();

        $totalIn = round($entries->sum('in'), 2);
        $totalOut = round($entries->sum('out'), 2);
        $byMethod = $entries->groupBy('method')->map(fn ($rows, $m) => (object) [
            'method' => $m, 'in' => round($rows->sum('in'), 2), 'out' => round($rows->sum('out'), 2),
        ])->sortBy('method')->values();

        if ($request->query('export') === 'csv') {
            return $this->streamCsv('payment-ledger.csv', ['Date & time', 'Type', 'Reference', 'Party', 'Method', 'Branch', 'Money in', 'Money out'], $entries->map(fn ($row) => [
                $row['at']->copy()->timezone($timezone)->format('Y-m-d H:i'), $row['source'], $row['reference'], $row['party'], $row['method'], $row['branch'],
                $row['in'] > 0 ? number_format($row['in'], 2, '.', '') : '', $row['out'] > 0 ? number_format($row['out'], 2, '.', '') : '',
            ]));
        }

        $perPage = 50;
        $page = max(1, (int) $request->integer('page', 1));
        $paginator = new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values(),
            $entries->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('core.reports.ledger', [
            'branches' => $branches, 'branch' => $branch, 'from' => $from, 'to' => $to,
            'entries' => $paginator, 'totalIn' => $totalIn, 'totalOut' => $totalOut, 'byMethod' => $byMethod,
            'direction' => $direction, 'method' => $method, 'timezone' => $timezone,
        ]);
    }

    /**
     * `null` means "all accessible branches" — a cross-tenant/unauthorized
     * branch_id silently falls back to that rather than erroring, since it
     * only ever narrows to the user's own accessible set, never expands it.
     */
    private function resolveBranch(Request $request, Collection $accessible): ?Branch
    {
        if ($request->filled('branch_id')) {
            return $accessible->firstWhere('id', (int) $request->integer('branch_id'));
        }

        return null;
    }

    private function branchIds(?Branch $branch, Collection $accessible): array
    {
        return $branch ? [$branch->id] : $accessible->pluck('id')->all();
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

    private function resolveDateRange(Request $request, ?Branch $branch): array
    {
        $timezone = $branch?->effectiveTimezone() ?? current_tenant()->timezone;

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'), $timezone)->startOfDay()
            : now($timezone)->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'), $timezone)->endOfDay()
            : now($timezone)->endOfDay();

        abort_if($from->gt($to), 422, 'The "from" date cannot be after the "to" date.');

        return [$from, $to];
    }

    private function streamCsv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $header);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
