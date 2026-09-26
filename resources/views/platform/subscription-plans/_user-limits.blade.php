@php
    $usersIncluded = old('users_included', $plan->users_included ?? '');
    $perBranch = old('users_per_additional_branch', $plan->users_per_additional_branch ?? 0);
@endphp

<div class="mt-4 rounded-lg border border-gray-200 p-4">
    <p class="text-sm font-medium text-gray-800">Users (employees)</p>
    <p class="text-xs text-gray-500 mt-0.5 mb-3">The most active users (owner and staff) a tenant on this plan can have. It grows with the branches they buy.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="users_included" value="Users with the included branches" />
            <input id="users_included" name="users_included" type="number" min="1" max="100000" value="{{ $usersIncluded }}" placeholder="Blank = unlimited"
                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm">
            <x-input-error :messages="$errors->get('users_included')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="users_per_additional_branch" value="Extra users per additional branch" />
            <input id="users_per_additional_branch" name="users_per_additional_branch" type="number" min="0" max="100000" value="{{ $perBranch }}"
                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm">
            <x-input-error :messages="$errors->get('users_per_additional_branch')" class="mt-1" />
        </div>
    </div>

    <p class="text-xs text-gray-500 mt-3">User limit by number of branches: <span id="user-preview" class="font-medium text-gray-700"></span></p>
</div>

<script>
    (function () {
        const $ = (id) => document.getElementById(id);
        const preview = () => {
            const base = $('users_included').value === '' ? null : parseInt($('users_included').value);
            const per = parseInt($('users_per_additional_branch').value || 0);
            const included = Math.max(1, parseInt($('branch_limit')?.value || 1));
            if (base === null) { $('user-preview').textContent = 'unlimited'; return; }
            const parts = [];
            for (let i = 0; i < 4; i++) {
                const branches = included + i;
                parts.push(`${branches} ${branches === 1 ? 'branch' : 'branches'} → ${base + i * per} users`);
            }
            $('user-preview').textContent = parts.join(' · ') + ' …';
        };
        ['users_included', 'users_per_additional_branch', 'branch_limit'].forEach((id) => $(id)?.addEventListener('input', preview));
        preview();
    })();
</script>
