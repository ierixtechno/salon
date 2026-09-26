@php $compare = old('compare_at_price', $plan->compare_at_price ?? ''); @endphp

<div class="mt-4 rounded-lg border border-gray-200 p-4">
    <x-input-label for="compare_at_price" value="Regular price (optional promo)" />
    <x-text-input id="compare_at_price" class="block mt-1 w-full sm:w-56" type="number" step="0.01" min="0" name="compare_at_price" :value="$compare" placeholder="e.g. 1999" />
    <p class="text-xs text-gray-500 mt-1">
        To run an offer, put the normal price here and the price you actually charge in <strong>Price</strong> above (say regular 1999, price 999).
        Tenants then see the regular price struck through, the offer price, and the discount %. Leave blank for no promo.
    </p>
    <p class="text-xs text-green-700 mt-1" id="promo-preview"></p>
    <x-input-error :messages="$errors->get('compare_at_price')" class="mt-2" />
</div>

<script>
    (function () {
        const price = document.getElementById('price');
        const compare = document.getElementById('compare_at_price');
        const out = document.getElementById('promo-preview');
        const update = () => {
            const p = parseFloat(price.value), c = parseFloat(compare.value);
            out.textContent = c > 0 && p >= 0 && c > p ? `Shown as: ₹${c.toLocaleString('en-IN')} struck through, ₹${p.toLocaleString('en-IN')}, ${Math.round((1 - p / c) * 100)}% OFF` : '';
        };
        price?.addEventListener('input', update);
        compare?.addEventListener('input', update);
        update();
    })();
</script>
