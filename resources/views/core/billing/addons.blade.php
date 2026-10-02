<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add branches &amp; staff</h2>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 max-w-4xl space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if ($blocked)
                <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    {{ $blocked }}
                    @if ($pending)
                        <a href="{{ route('billing.quotations.show', $pending) }}" class="font-semibold underline">Open quotation {{ $pending->quotation_number }}</a>
                    @else
                        <a href="{{ route('billing.quotations.index') }}" class="font-semibold underline">View quotations</a>
                    @endif
                </div>
            @else
                <p class="text-sm text-gray-600">
                    Prices follow your current plan, <span class="font-medium">{{ $plan->name }}</span>. You pay only for the {{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} left in this billing period now (plus GST);
                    from your next renewal the full price is added to your {{ $cycle }}ly bill.
                </p>

                {{-- Branches --}}
                <div class="bg-white shadow-sm rounded-lg p-6" x-data="{ n: 1, price: {{ $branchToday }}, full: {{ $branchPrice }} }">
                    <h3 class="font-semibold text-gray-900">Branches</h3>
                    <p class="text-sm text-gray-600 mt-1">You have {{ $ownedBranches }} {{ \Illuminate\Support\Str::plural('branch', $ownedBranches) }}.</p>
                    @if ($branchRoom > 0)
                        <p class="text-sm text-gray-600">Each extra branch: &#8377;{{ number_format($branchPrice, 0) }} per {{ $cycle }} + GST. You can add up to {{ $branchRoom }} more.</p>
                        <form method="POST" action="{{ route('billing.branches.add') }}" class="mt-4 flex flex-wrap items-end gap-4">
                            @csrf
                            <div>
                                <label for="branches_additional" class="block text-xs text-gray-500 mb-1">Branches to add</label>
                                <input id="branches_additional" name="additional" type="number" min="1" max="{{ $branchRoom }}" x-model.number="n" required class="w-28 border-gray-300 rounded-md shadow-sm text-sm">
                            </div>
                            <p class="text-sm text-gray-700">Pay now: <span class="font-semibold">&#8377;<span x-text="(Math.max(1, n || 1) * price).toFixed(2)"></span></span> + GST
                                <span class="text-gray-500">&middot; then &#8377;<span x-text="(Math.max(1, n || 1) * full).toFixed(0)"></span> more per {{ $cycle }}</span></p>
                            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Get quotation</button>
                        </form>
                    @else
                        <p class="text-sm text-gray-500 mt-2">
                            @if ($plan->sellsExtraBranches())
                                You are at the maximum number of branches for this plan.
                            @else
                                Extra branches are not sold on this plan.
                            @endif
                            <a href="{{ route('billing.plans.index') }}" class="font-semibold text-indigo-600 underline">See plans with more branches</a>
                        </p>
                    @endif
                    <x-input-error :messages="$errors->get('additional')" class="mt-2" />
                </div>

                {{-- Employees --}}
                <div class="bg-white shadow-sm rounded-lg p-6" x-data="{ n: 1, price: {{ $employeeToday }}, full: {{ $employeePrice }} }">
                    <h3 class="font-semibold text-gray-900">Employees</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        @if ($userLimit === null)
                            Your plan has no employee limit.
                        @else
                            Your plan allows {{ $userLimit }} {{ \Illuminate\Support\Str::plural('user', $userLimit) }} ({{ $userCount }} in use), including {{ $extraOwned }} you bought separately.
                        @endif
                    </p>
                    @if ($sellsEmployees && $employeeRoom > 0)
                        <p class="text-sm text-gray-600">Each extra employee: &#8377;{{ number_format($employeePrice, 0) }} per {{ $cycle }} + GST, no extra branch needed.@if ($plan->max_users) The plan maximum is {{ $plan->max_users }} employees.@endif</p>
                        <form method="POST" action="{{ route('billing.employees.add') }}" class="mt-4 flex flex-wrap items-end gap-4">
                            @csrf
                            <div>
                                <label for="employees_additional" class="block text-xs text-gray-500 mb-1">Employees to add</label>
                                <input id="employees_additional" name="additional" type="number" min="1" max="{{ min($employeeRoom, 1000) }}" x-model.number="n" required class="w-28 border-gray-300 rounded-md shadow-sm text-sm">
                            </div>
                            <p class="text-sm text-gray-700">Pay now: <span class="font-semibold">&#8377;<span x-text="(Math.max(1, n || 1) * price).toFixed(2)"></span></span> + GST
                                <span class="text-gray-500">&middot; then &#8377;<span x-text="(Math.max(1, n || 1) * full).toFixed(0)"></span> more per {{ $cycle }}</span></p>
                            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Get quotation</button>
                        </form>
                    @elseif ($sellsEmployees)
                        <p class="text-sm text-gray-500 mt-2">You are at the maximum number of employees for this plan.</p>
                    @elseif ($userLimit !== null)
                        <p class="text-sm text-gray-500 mt-2">
                            Extra employees are not sold on this plan. Adding a branch raises the limit, or
                            <a href="{{ route('billing.plans.index') }}" class="font-semibold text-indigo-600 underline">see plans with more employees</a>.
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
