<x-app-layout>
    <x-slot name="header">{{ $template->name }}</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('notification-templates.update', $template) }}" class="space-y-4" x-data="{ channel: '{{ old('channel', $template->channel) }}' }">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $template->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="channel" value="Channel" />
                        <select id="channel" name="channel" x-model="channel" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach (\App\Domain\Core\Models\NotificationTemplate::CHANNELS as $channel)
                                <option value="{{ $channel }}" @selected(old('channel', $template->channel) === $channel)>{{ ucfirst($channel) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('channel')" class="mt-2" />
                    </div>

                    <div x-show="channel === 'email'">
                        <x-input-label for="subject" value="Subject" />
                        <x-text-input id="subject" class="block mt-1 w-full" type="text" name="subject" :value="old('subject', $template->subject)" />
                        <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="body" value="Message" />
                        <textarea id="body" name="body" rows="5" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('body', $template->body) }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">Available placeholders: <code>@{{customer_name}}</code>, <code>@{{business_name}}</code></p>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $template->is_active))>
                        <span class="text-sm text-gray-700">Active</span>
                    </label>

                    <div class="flex justify-end">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-1">Deactivate template</h3>
                <p class="text-xs text-gray-500 mb-4">Existing campaigns/automations using it keep their history — this just hides it from new pickers.</p>
                <form method="POST" action="{{ route('notification-templates.destroy', $template) }}" onsubmit="return confirm('Deactivate this template?')">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>Deactivate</x-danger-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
