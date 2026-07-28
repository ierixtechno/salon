<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Resources — {{ $branch->name }}</h2>
            @can('create', App\Domain\Core\Models\Resource::class)
                <a href="{{ route('branches.resources.create', $branch) }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">
                    + New resource
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

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Name</th>
                                <th class="text-left px-4 py-2 font-medium">Type</th>
                                <th class="text-left px-4 py-2 font-medium">Capacity</th>
                                <th class="text-left px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($resources as $resource)
                                <tr>
                                    <td class="px-4 py-2">{{ $resource->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ str($resource->type)->replace('_', ' ')->headline() }}</td>
                                    <td class="px-4 py-2">{{ $resource->capacity }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full {{ $resource->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $resource->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('resources.edit', $resource) }}" class="text-indigo-600 hover:text-indigo-800">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No resources yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <a href="{{ route('branches.edit', $branch) }}" class="inline-block mt-4 text-sm text-gray-500 hover:text-gray-700">&larr; Back to {{ $branch->name }}</a>
        </div>
    </div>
</x-app-layout>
