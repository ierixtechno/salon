<x-platform-layout>
    <x-slot name="header">{{ $tenant->name }}</x-slot>

    <div class="flex items-center gap-3 mb-6">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-sm font-semibold">
            {{ strtoupper(substr($tenant->name, 0, 2)) }}
        </span>
        <div>
            <div class="font-semibold text-gray-900">{{ $tenant->name }}</div>
            <x-platform.status-badge :status="$tenant->status" />
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Status</h2>
            <form method="POST" action="{{ route('platform.tenants.status', $tenant) }}" class="flex flex-wrap items-center gap-3">
                @csrf
                @method('PATCH')
                <select name="status" class="border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['pending_payment', 'trial', 'active', 'suspended', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected($tenant->status === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition">
                    Update
                </button>
            </form>

            <dl class="mt-6 space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Timezone</dt><dd class="font-medium text-gray-900">{{ $tenant->timezone }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Currency</dt><dd class="font-medium text-gray-900">{{ $tenant->currency }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Trial ends</dt><dd class="font-medium text-gray-900">{{ $tenant->trial_ends_at?->toFormattedDateString() ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Plan</dt><dd class="font-medium text-gray-900">{{ $subscription?->plan?->name ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Contact &amp; billing details (for GST)</h2>
            <form method="POST" action="{{ route('platform.tenants.billing-state', $tenant) }}" class="space-y-3">
                @csrf
                @method('PATCH')
                <div class="flex flex-wrap items-center gap-3">
                    <select name="billing_state" class="border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select state&hellip;</option>
                        @foreach (config('india.states') as $state)
                            <option value="{{ $state }}" @selected($tenant->billing_state === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="gstin" value="{{ old('gstin', $tenant->gstin) }}" maxlength="15" placeholder="Tenant GSTIN (optional)"
                        class="uppercase border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition">
                        Update
                    </button>
                </div>
                <div class="flex items-center gap-3">
                    <label for="phone" class="text-sm text-gray-500 w-28">Mobile number</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $tenant->phone) }}" maxlength="10" placeholder="10-digit mobile"
                        class="border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <x-input-error :messages="$errors->get('phone')" />
                <x-input-error :messages="$errors->get('billing_state')" />
                <x-input-error :messages="$errors->get('gstin')" />
            </form>
            <p class="text-xs text-gray-500 mt-3">
                State is compared against the platform's own state ({{ config('platform.state') }}) to determine
                CGST+SGST (same state) vs IGST (different state) — must be set before a quotation can be created for
                this tenant. GSTIN, if provided, is shown as the recipient GSTIN on this tenant's invoices so they can
                claim GST.
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Branches &amp; users</h2>
            @if ($subscription)
                @php
                    $plan = $subscription->plan;
                    $owned = $subscription->currentBranchCount();
                    $userLimit = $tenant->userLimit();
                @endphp
                <dl class="space-y-2 text-sm mb-4">
                    <div class="flex justify-between"><dt class="text-gray-500">Branches</dt><dd class="font-medium text-gray-900">{{ $tenant->branches()->where('is_active', true)->count() }} in use of {{ $owned }} allowed</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Users</dt><dd class="font-medium text-gray-900">{{ $tenant->activeUserCount() }} in use of {{ $userLimit ?? 'unlimited' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">This plan sells extra branches</dt><dd class="font-medium text-gray-900">{{ $plan->sellsExtraBranches() ? '₹'.number_format($plan->additional_branch_price, 0).' each, up to '.$plan->maxBranches() : 'No' }}</dd></div>
                </dl>
                @if ($owned < $plan->maxBranches())
                    <form method="POST" action="{{ route('platform.tenants.branches', $tenant) }}" class="space-y-3">
                        @csrf
                        <div class="flex flex-wrap items-center gap-3">
                            <input type="number" name="additional" min="1" max="{{ $plan->maxBranches() - $owned }}" value="1" required
                                class="w-24 border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <span class="text-sm text-gray-500">additional branch(es)</span>
                            <button type="submit" name="mode" value="quote" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition">Create pro-rata quotation</button>
                            <button type="submit" name="mode" value="grant" onclick="return confirm('Add these branches free of charge?')" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Grant free</button>
                        </div>
                        <x-input-error :messages="$errors->get('additional')" />
                    </form>
                    <p class="text-xs text-gray-500 mt-3">
                        A quotation charges the extra branches for the rest of the current billing period; the tenant (or you, via Record payment) settles it and the branches unlock.
                        "Grant free" adds them immediately. The tenant's user limit rises with the branches.
                    </p>
                @else
                    <p class="text-sm text-gray-500">This tenant is at the maximum branches for {{ $plan->name }}. To give more, raise the plan's "Maximum branches" (or its extra-branch price) under Subscription Plans.</p>
                @endif
            @else
                <p class="text-sm text-gray-500">No active subscription yet — branches can be added once the tenant's first quotation is paid.</p>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">WhatsApp credits</h2>
            <p class="text-sm text-gray-600 mb-4">
                Current balance: <span class="font-semibold text-gray-900">{{ number_format($whatsappCreditBalance) }}</span>
                {{ Str::plural('credit', $whatsappCreditBalance) }}
            </p>
            <form method="POST" action="{{ route('platform.tenants.whatsapp-credits', $tenant) }}" class="space-y-3">
                @csrf
                <div class="flex flex-wrap items-center gap-3">
                    <input type="number" name="amount" min="1" placeholder="Credits to add" required
                        class="w-40 border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <input type="text" name="reason" placeholder="Reason (optional, e.g. purchase reference)" maxlength="255"
                        class="flex-1 min-w-[12rem] border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition">
                        Add credits
                    </button>
                </div>
                <x-input-error :messages="$errors->get('amount')" />
            </form>
            <p class="text-xs text-gray-500 mt-3">
                1 credit = 1 WhatsApp message sent. Add credits here after the tenant pays for them outside the app —
                there's no in-app checkout for this.
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Modules</h2>
            <form method="POST" action="{{ route('platform.tenants.modules', $tenant) }}">
                @csrf
                @method('PATCH')

                <div class="space-y-3">
                    @foreach ($modules as $module)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="modules[]" value="{{ $module->code }}"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                @checked($enabledModuleCodes->contains($module->code))>
                            <span class="text-sm text-gray-800">{{ $module->name }}</span>
                        </label>
                    @endforeach
                </div>

                <p class="text-xs text-gray-500 mt-3">Disabling a module never deletes historical data — it just turns off new activity in it.</p>

                <div class="flex justify-end mt-4">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition">
                        Save modules
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-platform-layout>
