@props(['plans', 'value' => null])

@php
    $planData = $plans->mapWithKeys(fn ($p) => [$p->id => [
        'price' => (float) $p->price,
        'included' => (int) $p->branch_limit,
        'extra' => (float) $p->additional_branch_price,
        'max' => $p->sellsExtraBranches() ? $p->maxBranches() : (int) $p->branch_limit,
    ]]);
@endphp

{{-- Shown only when the selected package sells extra branches. Reads the
     package from the form's `subscription_plan_id` field (radios or select).
     The total shown is informational; the server prices it (SubscriptionPlan::priceForBranches). --}}
<div id="branch-count-picker" class="hidden mt-4">
    <x-input-label for="branch_count" value="Number of branches" />
    <input id="branch_count" name="branch_count" type="number" min="1" value="{{ old('branch_count', $value) }}" disabled
        class="block mt-1 w-full sm:w-40 border-gray-300 rounded-md shadow-sm text-sm">
    <p id="branch-count-note" class="text-xs text-gray-600 mt-1"></p>
    <x-input-error :messages="$errors->get('branch_count')" class="mt-2" />
</div>

<script>
    (function () {
        const plans = @json($planData);
        const box = document.getElementById('branch-count-picker');
        const input = document.getElementById('branch_count');
        const note = document.getElementById('branch-count-note');
        const fields = () => Array.from(document.querySelectorAll('[name="subscription_plan_id"]'));
        const selectedId = () => {
            const all = fields();
            const radio = all.find((el) => el.type === 'radio' && el.checked);
            if (radio) return radio.value;
            const select = all.find((el) => el.tagName === 'SELECT');
            return select ? select.value : null;
        };
        const money = (n) => '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: 2 });
        const refresh = () => {
            const plan = plans[selectedId()];
            if (!plan || plan.extra <= 0) {
                box.classList.add('hidden');
                input.disabled = true;
                return;
            }
            box.classList.remove('hidden');
            input.disabled = false;
            input.min = plan.included;
            input.max = plan.max;
            let n = parseInt(input.value || plan.included);
            if (isNaN(n) || n < plan.included) n = plan.included;
            if (n > plan.max) n = plan.max;
            input.value = n;
            const total = plan.price + (n - plan.included) * plan.extra;
            note.textContent = `Includes ${plan.included}; each additional branch is ${money(plan.extra)}. Price for ${n}: ${money(total)} + GST.`;
        };
        fields().forEach((el) => el.addEventListener('change', refresh));
        input.addEventListener('input', refresh);
        refresh();
    })();
</script>
