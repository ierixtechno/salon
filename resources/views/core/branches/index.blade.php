<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Branches</h2>
            @can('create', App\Domain\Core\Models\Branch::class)
                <a href="{{ route('branches.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-500">
                    + New branch
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @can('tenant.billing.manage')
                <div class="mb-4 rounded-md bg-indigo-50 border border-indigo-100 px-4 py-3 text-sm text-indigo-900">
                    Your plan bills for <span class="font-medium">{{ $branchLimit }}</span> {{ \Illuminate\Support\Str::plural('branch', $branchLimit) }} ({{ $activeBranchCount }} active).
                </div>

                @if ($pendingReductionRequest)
                    <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                        Waiting on Super Admin: reduce from {{ $pendingReductionRequest->current_branch_count }} to {{ $pendingReductionRequest->requested_branch_count }} branches.
                    </div>
                @elseif ($branchLimit > 1)
                    <div class="mb-4 bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-medium text-gray-900 mb-1">Reduce your branch count</h3>
                        <p class="text-xs text-gray-500 mb-3">
                            Deactivate the branch(es) you no longer need first (open a branch &rarr; Danger Zone), then request the lower count here.
                            There's no refund for the current period — the reduced billing starts from your next renewal, once Super Admin approves.
                        </p>
                        <form method="POST" action="{{ route('branch-reduction-requests.store') }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label for="requested_branch_count" class="block text-xs text-gray-500 mb-1">New branch count</label>
                                <input type="number" id="requested_branch_count" name="requested_branch_count" min="1" max="{{ $branchLimit - 1 }}" required
                                    class="w-24 border-gray-300 rounded-md shadow-sm text-sm">
                            </div>
                            <div class="flex-1 min-w-[12rem]">
                                <label for="reason" class="block text-xs text-gray-500 mb-1">Reason (optional)</label>
                                <input type="text" id="reason" name="reason" maxlength="1000" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                            </div>
                            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Request</button>
                        </form>
                        <x-input-error :messages="$errors->get('requested_branch_count')" class="mt-2" />
                    </div>
                @endif
            @endcan

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-indigo-100 text-indigo-800">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold text-base">Name</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Code</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Resources</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($branches as $branch)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('branches.edit', $branch) }}" class="text-indigo-600 hover:text-indigo-800">
                                            {{ $branch->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $branch->code }}</td>
                                    <td class="px-4 py-2">{{ $branch->resources_count }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full {{ $branch->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No branches yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
