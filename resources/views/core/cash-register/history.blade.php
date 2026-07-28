<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cash Register History</h2>
            @if ($branches->isNotEmpty())
                <form method="GET" class="flex items-center gap-2">
                    <select name="branch_id" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">All branches</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($branch?->id === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-4">
            <a href="{{ route('cash-register.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to register</a>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Branch</th>
                                <th class="text-left px-4 py-2 font-medium">Opened</th>
                                <th class="text-left px-4 py-2 font-medium">Closed</th>
                                <th class="text-left px-4 py-2 font-medium">Expected</th>
                                <th class="text-left px-4 py-2 font-medium">Actual</th>
                                <th class="text-left px-4 py-2 font-medium">Difference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($sessions as $s)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700">{{ $s->branch->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $s->opened_at->format('d M, h:i A') }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $s->closed_at->format('d M, h:i A') }}</td>
                                    <td class="px-4 py-2 text-gray-500">₹{{ number_format($s->expected_closing, 2) }}</td>
                                    <td class="px-4 py-2 text-gray-500">₹{{ number_format($s->actual_closing, 2) }}</td>
                                    <td class="px-4 py-2 {{ (float) $s->difference !== 0.0 ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                        ₹{{ number_format($s->difference, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No closed sessions yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $sessions->links() }}
        </div>
    </div>
</x-app-layout>
