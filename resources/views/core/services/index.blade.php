<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $moduleFilter ? $moduleFilter->name.' Services' : 'Services' }}</h2>
            @can('create', App\Domain\Core\Models\Service::class)
                <a href="{{ route('services.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">
                    + New service
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
                                <th class="text-left px-4 py-2 font-medium">Category</th>
                                @unless ($moduleFilter)
                                    <th class="text-left px-4 py-2 font-medium">Module</th>
                                @endunless
                                <th class="text-left px-4 py-2 font-medium">Duration</th>
                                <th class="text-left px-4 py-2 font-medium">Price</th>
                                <th class="text-left px-4 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($services as $service)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('services.edit', $service) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                                            {{ $service->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $service->category->name }}</td>
                                    @unless ($moduleFilter)
                                        <td class="px-4 py-2 text-gray-500">{{ $service->module->name }}</td>
                                    @endunless
                                    <td class="px-4 py-2 text-gray-500">{{ $service->duration_minutes }} min</td>
                                    <td class="px-4 py-2 text-gray-700">{{ $service->tenant->currency }} {{ number_format($service->base_price, 2) }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full {{ $service->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $service->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $moduleFilter ? 5 : 6 }}" class="px-4 py-6 text-center text-gray-500">No services yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <a href="{{ route('service-categories.index', $moduleFilter ? ['module' => $moduleFilter->code] : []) }}" class="inline-block mt-4 text-sm text-gray-500 hover:text-gray-700">Manage categories &rarr;</a>
        </div>
    </div>
</x-app-layout>
