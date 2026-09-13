@php
    $uri = app(\App\Domain\Platform\Support\BuildUpiPaymentUri::class)->for($quotation);
@endphp
@if ($uri)
    <div class="rounded-lg border border-gray-200 p-4 flex flex-col items-center text-center gap-2">
        <p class="text-sm font-medium text-gray-800">Or scan to pay via UPI</p>
        <canvas data-upi-qr data-upi-uri="{{ $uri }}" width="160" height="160" class="rounded"></canvas>
        <p class="text-xs text-gray-500">{{ config('platform.upi_payee_name') }} &middot; {{ config('platform.upi_vpa') }}</p>
        <p class="text-xs text-gray-400">GPay, PhonePe, Paytm or any UPI app. Payment isn't confirmed automatically here — it's recorded once received.</p>
    </div>
@endif
