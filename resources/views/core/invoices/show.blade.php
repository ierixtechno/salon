<x-app-layout>
    <x-slot name="header">{{ $invoice->invoice_number ?? 'Draft Sale' }} — {{ $invoice->customer_name }}</x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <a href="{{ route('invoices.index', ['branch_id' => $invoice->branch_id]) }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to invoices</a>

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-gray-900">{{ $invoice->invoice_number ?? 'Draft #'.$invoice->id }}</h3>
                    <span class="text-xs px-2 py-1 rounded-full border bg-gray-50 text-gray-700 border-gray-200">
                        {{ str($invoice->status)->replace('_', ' ')->headline() }}
                    </span>
                </div>
                <dl class="text-sm text-gray-600 grid grid-cols-2 gap-y-1">
                    <dt class="text-gray-400">Customer</dt><dd>{{ $invoice->customer_name }}{{ $invoice->customer_phone ? ' — '.$invoice->customer_phone : '' }}</dd>
                    <dt class="text-gray-400">Branch</dt><dd>{{ $invoice->branch->name }}</dd>
                    @if ($invoice->finalized_at)
                        <dt class="text-gray-400">Finalized</dt><dd>{{ $invoice->finalized_at->format('d M Y, h:i A') }}</dd>
                    @endif
                    @if ($invoice->notes)
                        <dt class="text-gray-400">Notes</dt><dd>{{ $invoice->notes }}</dd>
                    @endif
                </dl>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-4">Items</h3>

                <div class="divide-y divide-gray-100">
                    @forelse ($invoice->lines as $line)
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $line->description }} @if($line->quantity > 1) &times;{{ $line->quantity }} @endif</p>
                                <p class="text-xs text-gray-500">
                                    {{ $line->taxable_value }} taxable
                                    @if($line->discount_amount > 0) &middot; {{ $line->discount_amount }} discount @endif
                                    &middot; CGST {{ $line->cgst_amount }} + SGST {{ $line->sgst_amount }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="text-sm font-medium text-gray-900">{{ $line->line_total }}</span>
                                @if ($invoice->status === 'draft')
                                    <form method="POST" action="{{ route('invoices.lines.destroy', [$invoice, $line]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Remove</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 py-3">No items yet.</p>
                    @endforelse
                </div>

                <dl class="text-sm text-gray-600 mt-4 pt-4 border-t border-gray-100 space-y-1">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ $invoice->subtotal }}</dd></div>
                    @if ($invoice->discount_total > 0)
                        <div class="flex justify-between"><dt>Discount</dt><dd>-{{ $invoice->discount_total }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt>CGST</dt><dd>{{ $invoice->cgst_total }}</dd></div>
                    <div class="flex justify-between"><dt>SGST</dt><dd>{{ $invoice->sgst_total }}</dd></div>
                    <div class="flex justify-between font-semibold text-gray-900 text-base"><dt>Total</dt><dd>{{ $invoice->grand_total }}</dd></div>
                    @if ($invoice->payments->isNotEmpty())
                        <div class="flex justify-between"><dt>Paid</dt><dd>{{ $invoice->totalPaid() }}</dd></div>
                    @endif
                    @if ($invoice->refunds->isNotEmpty())
                        <div class="flex justify-between text-red-600"><dt>Refunded</dt><dd>-{{ $invoice->totalRefunded() }}</dd></div>
                    @endif
                </dl>
            </div>

            @if ($invoice->status === 'draft')
                @can('update', $invoice)
                    <div class="bg-white shadow-sm rounded-lg p-6"
                        x-data="{
                            mode: 'appointment',
                            categoryless: {{ \Illuminate\Support\Js::from($services->map(fn ($s) => [
                                'id' => $s->id, 'name' => $s->name,
                                'variants' => $s->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name]),
                            ])) }},
                            serviceId: '',
                            get service() { return this.categoryless.find(s => s.id == this.serviceId) },
                        }">
                        <h3 class="font-medium text-gray-900 mb-4">Add item</h3>

                        @if ($completedAppointments->isNotEmpty())
                            <div class="flex gap-4 mb-4 text-sm">
                                <label class="flex items-center gap-1.5"><input type="radio" x-model="mode" value="appointment"> From a completed appointment</label>
                                <label class="flex items-center gap-1.5"><input type="radio" x-model="mode" value="standalone"> Walk-in / other service</label>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('invoices.lines.store', $invoice) }}" class="space-y-4" x-show="mode === 'appointment'">
                            @csrf
                            <div>
                                <x-input-label for="appointment_id" value="Completed appointment" />
                                <select id="appointment_id" name="appointment_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach ($completedAppointments as $appointment)
                                        <option value="{{ $appointment->id }}">
                                            {{ $appointment->service->name }} — {{ $appointment->starts_at->timezone($invoice->branch->effectiveTimezone())->format('d M, h:i A') }} — {{ $appointment->price }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('appointment_id')" class="mt-2" />
                            </div>
                            <div class="flex justify-end">
                                <x-primary-button>Add item</x-primary-button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('invoices.lines.store', $invoice) }}" class="space-y-4" x-show="mode === 'standalone'" @if($completedAppointments->isEmpty()) x-init="mode = 'standalone'" @endif>
                            @csrf
                            <div>
                                <x-input-label for="service_id" value="Service" />
                                <select id="service_id" name="service_id" x-model="serviceId" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Select&hellip;</option>
                                    <template x-for="s in categoryless" :key="s.id">
                                        <option :value="s.id" x-text="s.name"></option>
                                    </template>
                                </select>
                                <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
                            </div>
                            <div x-show="service?.variants?.length">
                                <x-input-label for="service_variant_id" value="Variant (optional)" />
                                <select id="service_variant_id" name="service_variant_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Standard</option>
                                    <template x-for="v in (service?.variants ?? [])" :key="v.id">
                                        <option :value="v.id" x-text="v.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="quantity" value="Quantity" />
                                    <x-text-input id="quantity" class="block mt-1 w-full" type="number" name="quantity" min="1" max="20" value="1" />
                                </div>
                                <div>
                                    <x-input-label for="discount_amount" value="Discount (optional)" />
                                    <x-text-input id="discount_amount" class="block mt-1 w-full" type="number" step="0.01" min="0" name="discount_amount" value="0" />
                                </div>
                            </div>
                            @if ($usableMemberships->isNotEmpty())
                                <div>
                                    <x-input-label for="customer_membership_id" value="Apply membership discount (optional)" />
                                    <select id="customer_membership_id" name="customer_membership_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                        <option value="">None</option>
                                        @foreach ($usableMemberships as $membership)
                                            <option value="{{ $membership->id }}">{{ $membership->membershipPlan->name }} ({{ $membership->membershipPlan->discount_percent }}% off)</option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Replaces the manual discount above — only applies if this membership covers the selected service/branch.</p>
                                    <x-input-error :messages="$errors->get('customer_membership_id')" class="mt-2" />
                                </div>
                            @endif
                            <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
                            <div class="flex justify-end">
                                <x-primary-button>Add item</x-primary-button>
                            </div>
                        </form>
                    </div>

                    <div class="flex items-center justify-between">
                        <form method="POST" action="{{ route('invoices.discard', $invoice) }}" onsubmit="return confirm('Discard this draft sale?')">
                            @csrf
                            @method('DELETE')
                            <x-danger-button type="submit">Discard draft</x-danger-button>
                        </form>

                        <form method="POST" action="{{ route('invoices.checkout', $invoice) }}">
                            @csrf
                            <x-primary-button :disabled="$invoice->lines->isEmpty()">Finalize sale</x-primary-button>
                        </form>
                    </div>
                @endcan
            @endif

            @if (in_array($invoice->status, ['finalized', 'partially_paid']) && auth()->user()->can('payments.create'))
                <div class="bg-white shadow-sm rounded-lg p-6" x-data="{ idempotencyKey: crypto.randomUUID() }">
                    <h3 class="font-medium text-gray-900 mb-4">Record payment</h3>
                    <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="idempotency_key" :value="idempotencyKey">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="method" value="Method" />
                                <select id="method" name="method" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach (\App\Domain\Core\Models\Payment::MANUAL_METHODS as $method)
                                        <option value="{{ $method }}">{{ str($method)->replace('_', ' ')->headline() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('method')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="amount" value="Amount" />
                                <x-text-input id="amount" class="block mt-1 w-full" type="number" step="0.01" min="0.01" name="amount" required />
                                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="tip_amount" value="Tip (optional)" />
                                <x-text-input id="tip_amount" class="block mt-1 w-full" type="number" step="0.01" min="0" name="tip_amount" value="0" />
                            </div>
                            <div>
                                <x-input-label for="reference" value="Reference (optional)" />
                                <x-text-input id="reference" class="block mt-1 w-full" type="text" name="reference" placeholder="e.g. UPI txn ID" />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Record payment</x-primary-button>
                        </div>
                    </form>

                    <div class="mt-6 pt-6 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <form method="POST" action="{{ route('invoices.redeem-wallet', $invoice) }}" x-data="{ idempotencyKey: crypto.randomUUID() }" class="space-y-2">
                            @csrf
                            <input type="hidden" name="idempotency_key" :value="idempotencyKey">
                            <p class="text-xs text-gray-500">Wallet balance: {{ $walletBalance }}</p>
                            <x-text-input class="block w-full text-sm" type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" required />
                            <x-secondary-button type="submit" class="w-full justify-center">Pay with wallet</x-secondary-button>
                        </form>

                        @if ($businessProfile?->loyaltyEnabled())
                            <form method="POST" action="{{ route('invoices.redeem-loyalty', $invoice) }}" x-data="{ idempotencyKey: crypto.randomUUID() }" class="space-y-2">
                                @csrf
                                <input type="hidden" name="idempotency_key" :value="idempotencyKey">
                                <p class="text-xs text-gray-500">Points balance: {{ $loyaltyBalance }}</p>
                                <x-text-input class="block w-full text-sm" type="number" min="1" name="points" placeholder="Points" required />
                                <x-secondary-button type="submit" class="w-full justify-center">Redeem points</x-secondary-button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('invoices.redeem-gift-card', $invoice) }}" x-data="{ idempotencyKey: crypto.randomUUID() }" class="space-y-2">
                            @csrf
                            <input type="hidden" name="idempotency_key" :value="idempotencyKey">
                            <x-text-input class="block w-full text-sm" type="text" name="code" placeholder="Gift card code" required />
                            <x-text-input class="block w-full text-sm" type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" required />
                            <x-secondary-button type="submit" class="w-full justify-center">Redeem gift card</x-secondary-button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($invoice->status === 'finalized' && $invoice->payments->isEmpty())
                @can('void', $invoice)
                    <div class="bg-white shadow-sm rounded-lg p-6 border border-red-200">
                        <h3 class="font-medium text-red-900 mb-4">Void invoice</h3>
                        <form method="POST" action="{{ route('invoices.void', $invoice) }}" onsubmit="return confirm('Void this invoice?')" class="space-y-3">
                            @csrf
                            <textarea name="reason" rows="2" placeholder="Reason (optional)"
                                class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-danger-button>Void invoice</x-danger-button>
                        </form>
                    </div>
                @endcan
            @endif

            @if (in_array($invoice->status, ['paid', 'partially_paid']) && auth()->user()->can('refunds.create'))
                <div class="bg-white shadow-sm rounded-lg p-6 border border-red-200">
                    <h3 class="font-medium text-red-900 mb-4">Issue refund</h3>
                    <form method="POST" action="{{ route('invoices.refunds.store', $invoice) }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="refund_method" value="Method" />
                                <select id="refund_method" name="method" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach (\App\Domain\Core\Models\Refund::METHODS as $method)
                                        <option value="{{ $method }}">{{ str($method)->replace('_', ' ')->headline() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('method')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="refund_amount" value="Amount" />
                                <x-text-input id="refund_amount" class="block mt-1 w-full" type="number" step="0.01" min="0.01" name="amount" required />
                                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="refund_reason" value="Reason (optional)" />
                            <textarea id="refund_reason" name="reason" rows="2"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('reason') }}</textarea>
                        </div>
                        <div class="flex justify-end">
                            <x-danger-button>Issue refund</x-danger-button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($invoice->payments->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-4">Payment history</h3>
                    <div class="divide-y divide-gray-100 text-sm">
                        @foreach ($invoice->payments as $payment)
                            <div class="py-2 flex justify-between">
                                <span>
                                    {{ str($payment->method)->replace('_', ' ')->headline() }}{{ $payment->reference ? ' — '.$payment->reference : '' }}
                                    @if ($payment->points_redeemed) ({{ $payment->points_redeemed }} points) @endif
                                </span>
                                <span>{{ $payment->amount }}{{ $payment->tip_amount > 0 ? ' (+'.$payment->tip_amount.' tip)' : '' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($invoice->refunds->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-4">Refund history</h3>
                    <div class="divide-y divide-gray-100 text-sm">
                        @foreach ($invoice->refunds as $refund)
                            <div class="py-2 flex justify-between">
                                <span>{{ str($refund->method)->replace('_', ' ')->headline() }}{{ $refund->reason ? ' — '.$refund->reason : '' }}</span>
                                <span>{{ $refund->amount }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
