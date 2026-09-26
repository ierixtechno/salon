@props(['plan'])

{{-- Price block for a plan card: the charged price, and — when a promo is set —
     the regular price struck through with the discount percentage. --}}
@if ($plan->price == 0)
    <span class="text-3xl font-bold text-gray-900">Free</span>
@else
    @if ($plan->hasPromo())
        <div class="flex items-center gap-2 mb-0.5">
            <span class="text-base text-gray-400 line-through">&#8377;{{ number_format($plan->compare_at_price, 0) }}</span>
            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">{{ $plan->promoPercent() }}% OFF</span>
        </div>
    @endif
    <span class="text-3xl font-bold text-gray-900">&#8377;{{ number_format($plan->price, $plan->price == floor($plan->price) ? 0 : 2) }}</span>
    <span class="text-sm text-gray-500">/ {{ $plan->billing_interval === 'yearly' ? 'year' : 'month' }} + GST</span>
@endif
