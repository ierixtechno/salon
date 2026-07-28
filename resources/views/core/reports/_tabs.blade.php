@php
    $reportTabs = [
        'reports.sales' => 'Sales',
        'reports.appointments' => 'Appointments',
        'reports.module-performance' => 'Module Performance',
        'reports.expenses' => 'Expenses',
    ];
@endphp
<div class="flex flex-wrap gap-2 mb-6">
    @foreach ($reportTabs as $route => $label)
        <a href="{{ route($route, request()->only(['branch_id', 'from', 'to'])) }}"
            class="px-3 py-1.5 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
            {{ $label }}
        </a>
    @endforeach
</div>
