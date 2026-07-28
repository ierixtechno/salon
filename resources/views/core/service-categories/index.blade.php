<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $moduleFilter ? $moduleFilter->name.' Categories' : 'Service Categories' }}</h2>
            @can('create', App\Domain\Core\Models\ServiceCategory::class)
                <a href="{{ route('service-categories.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">
                    + New category
                </a>
            @endcan
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
                                @unless ($moduleFilter)
                                    <th class="text-left px-4 py-2 font-medium">Module</th>
                                @endunless
                                <th class="text-left px-4 py-2 font-medium">Services</th>
                                <th class="text-left px-4 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($categories as $category)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('service-categories.edit', $category) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                                            {{ $category->name }}
                                        </a>
                                    </td>
                                    @unless ($moduleFilter)
                                        <td class="px-4 py-2 text-gray-500">{{ $category->module->name }}</td>
                                    @endunless
                                    <td class="px-4 py-2">{{ $category->services_count }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full {{ $category->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $category->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $moduleFilter ? 3 : 4 }}" class="px-4 py-6 text-center text-gray-500">No categories yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <a href="{{ route('services.index', $moduleFilter ? ['module' => $moduleFilter->code] : []) }}" class="inline-block mt-4 text-sm text-gray-500 hover:text-gray-700">View services &rarr;</a>
        </div>
    </div>
</x-app-layout>
