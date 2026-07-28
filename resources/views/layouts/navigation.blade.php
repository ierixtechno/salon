@php
    $navItemBase = 'group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors duration-150';
    $navItemActive = 'bg-indigo-500/15 text-indigo-300';
    $navItemInactive = 'text-slate-300 hover:bg-white/5 hover:text-white';
    $groupLabel = 'px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500';
@endphp

<nav class="flex-1 px-3 py-4 space-y-1">
    <a href="{{ route('dashboard') }}" class="{{ $navItemBase }} {{ request()->routeIs('dashboard') ? $navItemActive : $navItemInactive }}">
        @if (request()->routeIs('dashboard'))
            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
        @endif
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        {{ __('Dashboard') }}
    </a>

    @if (Auth::user()->can('viewAny', App\Domain\Core\Models\Appointment::class) || Auth::user()->can('viewAny', App\Domain\Core\Models\Invoice::class) || Auth::user()->can('viewAny', App\Domain\Core\Models\Customer::class))
        <p class="{{ $groupLabel }}">Sales &amp; Scheduling</p>
    @endif

    @can('viewAny', App\Domain\Core\Models\Appointment::class)
        <a href="{{ route('appointments.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('appointments.*') || request()->routeIs('waitlist.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('appointments.*') || request()->routeIs('waitlist.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 18.75h16.5A1.5 1.5 0 0021.75 17.25V6.75a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5v10.5a1.5 1.5 0 001.5 1.5z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75h18" />
            </svg>
            {{ __('Appointments') }}
        </a>
    @endcan

    @can('viewAny', App\Domain\Core\Models\Invoice::class)
        <a href="{{ route('invoices.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('invoices.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('invoices.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182C10.55 7.72 11.275 7.5 12 7.5c.768 0 1.536.219 2.121.659L15 8.818" />
            </svg>
            {{ __('Invoices') }}
        </a>
    @endcan

    @can('viewAny', App\Domain\Core\Models\Customer::class)
        <a href="{{ route('customers.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('customers.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('customers.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
            </svg>
            {{ __('Customers') }}
        </a>
    @endcan

    @if (Auth::user()->can('viewAny', App\Domain\Core\Models\Product::class) || Auth::user()->can('viewAny', App\Domain\Core\Models\Supplier::class))
        <p class="{{ $groupLabel }}">Catalog &amp; Inventory</p>
    @endif

    @can('viewAny', App\Domain\Core\Models\Product::class)
        <a href="{{ route('products.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('products.*') || request()->routeIs('product-categories.*') || request()->routeIs('inventory.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('products.*') || request()->routeIs('product-categories.*') || request()->routeIs('inventory.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
            </svg>
            {{ __('Products') }}
        </a>
    @endcan

    @can('viewAny', App\Domain\Core\Models\Supplier::class)
        <a href="{{ route('suppliers.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('suppliers.*') || request()->routeIs('purchase-orders.*') || request()->routeIs('purchase-returns.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('suppliers.*') || request()->routeIs('purchase-orders.*') || request()->routeIs('purchase-returns.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v11.177m0-11.177L12.75 9m3.75-1.427L16.5 9m-3.75 0h-6.75V6a2.25 2.25 0 012.25-2.25h4.5a2.25 2.25 0 012.25 2.25v3z" />
            </svg>
            {{ __('Suppliers') }}
        </a>
    @endcan

    {{--
        One section per business vertical — only shown when that module is
        both enabled for the tenant (current_tenant()->hasModuleEnabled(),
        CLAUDE.md §7) and the user has services.view. Each module's
        Services link reuses the shared services.index/service-categories
        routes with a ?module= filter (display-only, not a security
        boundary — Service is already tenant-scoped) rather than
        duplicating the controller per vertical.
    --}}
    @if (current_tenant()->hasModuleEnabled('salon') && Auth::user()->can('viewAny', App\Domain\Core\Models\Service::class))
        <p class="{{ $groupLabel }}">Salon</p>
        @php $salonActive = (request()->routeIs('services.*') || request()->routeIs('service-categories.*')) && request()->query('module') === 'salon'; @endphp
        <a href="{{ route('services.index', ['module' => 'salon']) }}" class="{{ $navItemBase }} {{ $salonActive ? $navItemActive : $navItemInactive }}">
            @if ($salonActive)
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.169.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
            </svg>
            {{ __('Services') }}
        </a>
    @endif

    @if (current_tenant()->hasModuleEnabled('beauty') && Auth::user()->can('viewAny', App\Domain\Core\Models\Service::class))
        <p class="{{ $groupLabel }}">Beauty Parlour</p>
        @php $beautyActive = (request()->routeIs('services.*') || request()->routeIs('service-categories.*')) && request()->query('module') === 'beauty'; @endphp
        <a href="{{ route('services.index', ['module' => 'beauty']) }}" class="{{ $navItemBase }} {{ $beautyActive ? $navItemActive : $navItemInactive }}">
            @if ($beautyActive)
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.169.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
            </svg>
            {{ __('Services') }}
        </a>
    @endif

    @if (current_tenant()->hasModuleEnabled('spa') && Auth::user()->can('viewAny', App\Domain\Core\Models\Service::class))
        <p class="{{ $groupLabel }}">Spa</p>
        @php $spaActive = (request()->routeIs('services.*') || request()->routeIs('service-categories.*')) && request()->query('module') === 'spa'; @endphp
        <a href="{{ route('services.index', ['module' => 'spa']) }}" class="{{ $navItemBase }} {{ $spaActive ? $navItemActive : $navItemInactive }}">
            @if ($spaActive)
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.169.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
            </svg>
            {{ __('Services') }}
        </a>
    @endif

    @if (Auth::user()->can('viewAny', App\Domain\Core\Models\Package::class) || Auth::user()->can('gift-cards.view'))
        <p class="{{ $groupLabel }}">Loyalty &amp; Offers</p>
    @endif

    @can('viewAny', App\Domain\Core\Models\Package::class)
        <a href="{{ route('packages.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('packages.*') || request()->routeIs('membership-plans.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('packages.*') || request()->routeIs('membership-plans.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H4.5a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1014.625 7.5H12V4.875zM12 4.875A2.625 2.625 0 109.375 7.5H12V4.875zM3.375 7.5h17.25c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125H3.375c-.621 0-1.125-.504-1.125-1.125v-1.5c0-.621.504-1.125 1.125-1.125z" />
            </svg>
            {{ __('Packages') }}
        </a>
    @endcan

    @can('gift-cards.view')
        <a href="{{ route('gift-cards.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('gift-cards.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('gift-cards.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v10.5A2.25 2.25 0 005.25 19.5z" />
            </svg>
            {{ __('Gift Cards') }}
        </a>
    @endcan

    @if (Auth::user()->can('expenses.view') || Auth::user()->can('expense-categories.manage') || Auth::user()->can('cash-register.view'))
        <p class="{{ $groupLabel }}">Finance</p>
    @endif

    @if (Auth::user()->can('expenses.view') || Auth::user()->can('expense-categories.manage'))
        <a href="{{ Auth::user()->can('expenses.view') ? route('expenses.index') : route('expense-categories.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('expenses.*') || request()->routeIs('expense-categories.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('expenses.*') || request()->routeIs('expense-categories.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5A1.125 1.125 0 0122.875 9.375v9A2.625 2.625 0 0120.25 21H3.75A2.625 2.625 0 011.125 18.375v-9A1.125 1.125 0 012.25 8.25zM2.25 8.25V6.108c0-1.135.845-2.098 1.976-2.192a48.424 48.424 0 011.123-.08m0 0a49.05 49.05 0 019.302 0m-9.302 0a3 3 0 00-2.86 2.06m14.02-2.06a3 3 0 012.86 2.06m0 0c.083.174.153.353.212.537" />
            </svg>
            {{ __('Expenses') }}
        </a>
    @endif

    @can('cash-register.view')
        <a href="{{ route('cash-register.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('cash-register.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('cash-register.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75h19.5v10.5H2.25V6.75zM12 15a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" />
            </svg>
            {{ __('Cash Register') }}
        </a>
    @endcan

    @if (Auth::user()->can('marketing.campaigns.view') || Auth::user()->can('marketing.templates.manage') || Auth::user()->can('marketing.segments.manage'))
        <p class="{{ $groupLabel }}">Marketing</p>
    @endif

    @can('marketing.campaigns.view')
        <a href="{{ route('campaigns.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('campaigns.*') || request()->routeIs('campaign-automations.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('campaigns.*') || request()->routeIs('campaign-automations.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73s-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46" />
            </svg>
            {{ __('Campaigns') }}
        </a>
    @endcan

    @can('marketing.segments.manage')
        <a href="{{ route('customer-segments.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('customer-segments.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('customer-segments.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
            {{ __('Segments') }}
        </a>
    @endcan

    @can('marketing.templates.manage')
        <a href="{{ route('notification-templates.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('notification-templates.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('notification-templates.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
            </svg>
            {{ __('Templates') }}
        </a>
    @endcan

    @can('reports.view')
        <p class="{{ $groupLabel }}">Reports</p>
        <a href="{{ route('reports.sales') }}" class="{{ $navItemBase }} {{ request()->routeIs('reports.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('reports.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
            {{ __('Reports') }}
        </a>
    @endcan

    @if (Auth::user()->can('viewAny', App\Domain\Core\Models\Branch::class) || Auth::user()->can('viewAny', App\Domain\Core\Models\EmployeeProfile::class) || Auth::user()->can('tenant.settings.manage') || Auth::user()->can('data-export.request'))
        <p class="{{ $groupLabel }}">Organization</p>
    @endif

    @can('viewAny', App\Domain\Core\Models\Branch::class)
        <a href="{{ route('branches.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('branches.*') || request()->routeIs('resources.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('branches.*') || request()->routeIs('resources.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
            </svg>
            {{ __('Branches') }}
        </a>
    @endcan

    @can('viewAny', App\Domain\Core\Models\EmployeeProfile::class)
        <a href="{{ route('employees.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('employees.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('employees.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />
            </svg>
            {{ __('Employees') }}
        </a>
    @endcan

    @can('tenant.settings.manage')
        <a href="{{ route('settings.organization.edit') }}" class="{{ $navItemBase }} {{ request()->routeIs('settings.organization.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('settings.organization.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            {{ __('Settings') }}
        </a>
    @endcan

    @can('data-export.request')
        <a href="{{ route('data-exports.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('data-exports.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('data-exports.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            {{ __('Data Export') }}
        </a>
    @endcan

    @if (Auth::user()->can('attendance.view') || Auth::user()->can('leave.view') || Auth::user()->can('leave-types.manage') || Auth::user()->can('commission.manage') || Auth::user()->can('commission.view'))
        <p class="{{ $groupLabel }}">Workforce</p>
    @endif

    @can('attendance.view')
        <a href="{{ route('attendance.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('attendance.index') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('attendance.index'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
            </svg>
            {{ __('Attendance') }}
        </a>
    @endcan

    @if (Auth::user()->can('leave.view') || Auth::user()->can('leave-types.manage'))
        <a href="{{ Auth::user()->can('leave.view') ? route('leave.index') : route('leave-types.index') }}" class="{{ $navItemBase }} {{ request()->routeIs('leave.index') || request()->routeIs('leave-types.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('leave.index') || request()->routeIs('leave-types.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            {{ __('Leave') }}
        </a>
    @endcan

    @if (Auth::user()->can('commission.manage') || Auth::user()->can('commission.view'))
        <a href="{{ Auth::user()->can('commission.manage') ? route('commission.rules') : route('commission.ledger') }}" class="{{ $navItemBase }} {{ request()->routeIs('commission.*') ? $navItemActive : $navItemInactive }}">
            @if (request()->routeIs('commission.*'))
                <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
            @endif
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ __('Commission') }}
        </a>
    @endcan

    <p class="{{ $groupLabel }}">My Workspace</p>

    <a href="{{ route('notifications.my') }}" class="{{ $navItemBase }} {{ request()->routeIs('notifications.my') ? $navItemActive : $navItemInactive }}">
        @if (request()->routeIs('notifications.my'))
            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
        @endif
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        {{ __('My Notifications') }}
    </a>

    <a href="{{ route('attendance.my') }}" class="{{ $navItemBase }} {{ request()->routeIs('attendance.my') ? $navItemActive : $navItemInactive }}">
        @if (request()->routeIs('attendance.my'))
            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
        @endif
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{ __('My Attendance') }}
    </a>

    <a href="{{ route('leave.my') }}" class="{{ $navItemBase }} {{ request()->routeIs('leave.my') ? $navItemActive : $navItemInactive }}">
        @if (request()->routeIs('leave.my'))
            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
        @endif
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        {{ __('My Leave') }}
    </a>

    <a href="{{ route('commission.my') }}" class="{{ $navItemBase }} {{ request()->routeIs('commission.my') ? $navItemActive : $navItemInactive }}">
        @if (request()->routeIs('commission.my'))
            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-indigo-400"></span>
        @endif
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{ __('My Commission') }}
    </a>
</nav>
