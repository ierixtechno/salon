<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Quotation {{ $quotation->quotation_number }}</h2>
    </x-slot>

    @php
        // No side menu for a locked tenant (layouts/app.blade.php), so this
        // is the whole screen — centre it rather than leave it in a corner.
        $lockedState = $subscriptionAccessState ?? null;
    @endphp

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 max-w-xl {{ $lockedState?->isLocked() ? 'mx-auto' : '' }}">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($quotation->status === 'pending' && $lockedState?->isLocked())
                <div class="mb-4 rounded-lg bg-indigo-50 border border-indigo-200 px-4 py-3 text-sm text-indigo-900">
                    @if ($lockedState->isPending())
                        <p class="font-semibold">Welcome to {{ config('platform.brand_name') }}! Your account is ready.</p>
                        <p class="mt-1">Pay this quotation to unlock it. Pay online with <span class="font-medium">Pay Now</span>, or by UPI below. If you pay another way (bank transfer, cash), your account unlocks as soon as we record the payment.</p>
                    @else
                        <p class="font-semibold">Your subscription has ended.</p>
                        <p class="mt-1">Pay this quotation to restore full access.</p>
                    @endif
                </div>
            @endif

            @php
                $statusStyles = [
                    'pending' => 'bg-amber-100 text-amber-800',
                    'paid' => 'bg-green-100 text-green-800',
                    'cancelled' => 'bg-gray-100 text-gray-600',
                ];
            @endphp

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">{{ $quotation->plan->name }}</h3>
                    <span class="text-xs px-2.5 py-1 rounded-full capitalize {{ $statusStyles[$quotation->status] ?? $statusStyles['cancelled'] }}">
                        {{ $quotation->status }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Modules included</p>
                        <p class="font-medium text-gray-900">{{ $quotation->plan->modules->pluck('name')->implode(', ') ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Received</p>
                        <p class="font-medium text-gray-900">{{ $quotation->created_at->format('d M Y, H:i') }}</p>
                    </div>
                </div>

                <dl class="text-sm text-gray-600 pt-4 border-t border-gray-100 space-y-1">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>₹{{ number_format($quotation->amount, 2) }}</dd></div>
                    @if ($quotation->igst_amount > 0)
                        <div class="flex justify-between"><dt>IGST ({{ number_format($quotation->gst_rate_percent, 2) }}%)</dt><dd>₹{{ number_format($quotation->igst_amount, 2) }}</dd></div>
                    @else
                        <div class="flex justify-between"><dt>CGST ({{ number_format($quotation->gst_rate_percent / 2, 2) }}%)</dt><dd>₹{{ number_format($quotation->cgst_amount, 2) }}</dd></div>
                        <div class="flex justify-between"><dt>SGST ({{ number_format($quotation->gst_rate_percent / 2, 2) }}%)</dt><dd>₹{{ number_format($quotation->sgst_amount, 2) }}</dd></div>
                    @endif
                    <div class="flex justify-between font-semibold text-gray-900 text-base"><dt>Total</dt><dd>₹{{ number_format($quotation->total_amount, 2) }}</dd></div>
                </dl>

                @if ($quotation->tenant->gstin)
                    <p class="text-xs text-gray-400">Your GSTIN: {{ $quotation->tenant->gstin }}</p>
                @endif

                @if ($quotation->notes)
                    <div class="text-sm">
                        <p class="text-gray-500">Notes from Ierix Techno</p>
                        <p class="text-gray-800">{{ $quotation->notes }}</p>
                    </div>
                @endif

                @if ($quotation->status === 'paid' && $quotation->invoice)
                    <div class="text-sm border-t border-gray-100 pt-4">
                        <a href="{{ route('billing.invoices.show', $quotation->invoice) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                            View invoice {{ $quotation->invoice->invoice_number }} &rarr;
                        </a>
                    </div>
                @endif

                @if ($quotation->status === 'pending')
                    <div id="payment-error" class="hidden text-sm rounded-md bg-red-50 border border-red-200 px-4 py-3 text-red-800"></div>

                    <div class="border-t border-gray-100 pt-5">
                        <button id="pay-now-btn" type="button"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-500">
                            Pay Now
                        </button>
                    </div>

                    <x-upi-qr-code :quotation="$quotation" />
                @endif

                <div class="pt-2">
                    <a href="{{ route('billing.quotations.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                        &larr; Back to quotations
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if ($quotation->status === 'pending')
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            document.getElementById('pay-now-btn').addEventListener('click', function () {
                const button = this;
                const errorBox = document.getElementById('payment-error');
                errorBox.classList.add('hidden');
                button.disabled = true;
                button.textContent = 'Please wait…';

                fetch(@json(route('billing.quotations.checkout', $quotation)), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'Accept': 'application/json',
                    },
                })
                    .then(async (response) => {
                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Unable to start payment.');
                        }
                        return data;
                    })
                    .then((data) => {
                        const rzp = new Razorpay({
                            key: data.key,
                            order_id: data.order_id,
                            amount: Math.round(parseFloat(data.amount) * 100),
                            currency: 'INR',
                            name: 'Ierix Techno — Subscription Payment',
                            description: @json($quotation->plan->name),
                            handler: function (response) {
                                const form = document.createElement('form');
                                form.method = 'POST';
                                form.action = @json(route('billing.quotations.confirm', $quotation));

                                const fields = {
                                    _token: @json(csrf_token()),
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_signature: response.razorpay_signature,
                                };
                                for (const [name, value] of Object.entries(fields)) {
                                    const input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.name = name;
                                    input.value = value;
                                    form.appendChild(input);
                                }
                                document.body.appendChild(form);
                                form.submit();
                            },
                            modal: {
                                ondismiss: function () {
                                    button.disabled = false;
                                    button.textContent = 'Pay Now';
                                },
                            },
                        });
                        rzp.open();
                        button.disabled = false;
                        button.textContent = 'Pay Now';
                    })
                    .catch((error) => {
                        errorBox.textContent = error.message;
                        errorBox.classList.remove('hidden');
                        button.disabled = false;
                        button.textContent = 'Pay Now';
                    });
            });
        </script>
    @endif
</x-app-layout>
