<x-app-layout>
    <x-slot name="header">New Campaign</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                @if ($segments->isEmpty() || $templates->isEmpty())
                    <p class="text-sm text-gray-500">
                        You need at least one
                        @if ($segments->isEmpty()) <a href="{{ route('customer-segments.create') }}" class="text-indigo-600 hover:text-indigo-800">customer segment</a> @endif
                        @if ($segments->isEmpty() && $templates->isEmpty()) and @endif
                        @if ($templates->isEmpty()) <a href="{{ route('notification-templates.create') }}" class="text-indigo-600 hover:text-indigo-800">notification template</a> @endif
                        before creating a campaign.
                    </p>
                @else
                    <form method="POST" action="{{ route('campaigns.store') }}" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="name" value="Campaign name" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" placeholder="e.g. Spring Sale" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="segment_id" value="Audience" />
                            <select id="segment_id" name="segment_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach ($segments as $segment)
                                    <option value="{{ $segment->id }}" @selected(old('segment_id') == $segment->id)>{{ $segment->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('segment_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="template_id" value="Message template" />
                            <select id="template_id" name="template_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}" @selected(old('template_id') == $template->id)>{{ $template->name }} ({{ ucfirst($template->channel) }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('template_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="scheduled_at" value="Schedule for later (optional)" />
                            <x-text-input id="scheduled_at" class="block mt-1 w-full" type="datetime-local" name="scheduled_at" :value="old('scheduled_at')" />
                            <p class="text-xs text-gray-500 mt-1">Leave blank to save as a draft you send manually.</p>
                            <x-input-error :messages="$errors->get('scheduled_at')" class="mt-2" />
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Create campaign</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
