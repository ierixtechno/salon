<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Customer Segments</h2>
            <a href="{{ route('customer-segments.create') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">
                + New segment
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Name</th>
                                <th class="text-left px-4 py-2 font-medium">Type</th>
                                <th class="text-left px-4 py-2 font-medium">Matches</th>
                                <th class="text-left px-4 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($segments as $segment)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('customer-segments.edit', $segment) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                                            {{ $segment->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ str($segment->type)->replace('_', ' ')->headline() }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $counts[$segment->id] }} customers</td>
                                    <td class="px-4 py-2">
                                        <form method="POST" action="{{ route('customer-segments.destroy', $segment) }}" onsubmit="return confirm('Delete this segment?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No segments yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
