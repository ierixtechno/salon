@php
    $included = old('branch_limit', $plan->branch_limit ?? 1);
    $extra = old('additional_branch_price', $plan->additional_branch_price ?? 0);
    $max = old('max_branches', $plan->max_branches ?? '');
@endphp

<div class="mt-4 rounded-lg border border-gray-200 p-4" id="branch-pricing">
    <p class="text-sm font-medium text-gray-800">Branches &amp; pricing</p>
    <p class="text-xs text-gray-500 mt-0.5 mb-3">The plan price above covers the included branches. Extra branches are charged per branch on top.</p>

    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs uppercase tracking-wider text-gray-500">
                <th class="py-1 pr-3 font-medium">Item</th>
                <th class="py-1 font-medium w-44">Price (&#8377;)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <tr>
                <td class="py-2 pr-3 text-gray-700">
                    <span class="inline-flex items-center gap-2">
                        <input id="branch_limit" name="branch_limit" type="number" min="1" max="1000" value="{{ $included }}" required
                            class="w-20 border-gray-300 rounded-md shadow-sm text-sm">
                        <span>branch(es) included in the plan price</span>
                    </span>
                    <x-input-error :messages="$errors->get('branch_limit')" class="mt-1" />
                </td>
                <td class="py-2 text-gray-500 text-xs">= the plan price above</td>
            </tr>
            <tr>
                <td class="py-2 pr-3 text-gray-700">Each additional branch</td>
                <td class="py-2">
                    <input id="additional_branch_price" name="additional_branch_price" type="number" step="0.01" min="0" max="999999.99" value="{{ $extra }}"
                        class="block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="0 = not offered">
                    <x-input-error :messages="$errors->get('additional_branch_price')" class="mt-1" />
                </td>
            </tr>
            <tr>
                <td class="py-2 pr-3 text-gray-700">Maximum branches in total <span class="text-xs text-gray-400">(optional)</span></td>
                <td class="py-2">
                    <input id="max_branches" name="max_branches" type="number" min="1" max="1000" value="{{ $max }}"
                        class="block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="No limit">
                    <x-input-error :messages="$errors->get('max_branches')" class="mt-1" />
                </td>
            </tr>
        </tbody>
    </table>

    <p class="text-xs text-gray-500 mt-3">Price a tenant pays: <span id="branch-preview" class="font-medium text-gray-700"></span></p>
</div>

<script>
    (function () {
        const $ = (id) => document.getElementById(id);
        const preview = () => {
            const base = parseFloat($('price')?.value || 0);
            const included = Math.max(1, parseInt($('branch_limit').value || 1));
            const extra = parseFloat($('additional_branch_price').value || 0);
            const parts = [`${included} ${included === 1 ? 'branch' : 'branches'} — ₹${base.toLocaleString('en-IN')}`];
            if (extra > 0) {
                for (let n = included + 1; n <= included + 3; n++) {
                    parts.push(`${n} — ₹${(base + (n - included) * extra).toLocaleString('en-IN')}`);
                }
                parts.push('…');
            } else {
                parts.push('(no additional branches offered)');
            }
            $('branch-preview').textContent = parts.join(' · ');
        };
        ['price', 'branch_limit', 'additional_branch_price'].forEach((id) => $(id)?.addEventListener('input', preview));
        preview();
    })();
</script>
