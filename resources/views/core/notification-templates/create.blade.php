<x-app-layout>
    <x-slot name="header">New Notification Template</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('notification-templates.store') }}" class="space-y-4" x-data="{ channel: '{{ old('channel', 'email') }}' }">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" placeholder="e.g. Birthday Wishes" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="channel" value="Channel" />
                        <select id="channel" name="channel" x-model="channel" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach (\App\Domain\Core\Models\NotificationTemplate::CHANNELS as $channel)
                                <option value="{{ $channel }}" @selected(old('channel') === $channel)>{{ ucfirst($channel) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('channel')" class="mt-2" />
                    </div>

                    <div x-show="channel === 'email'">
                        <x-input-label for="subject" value="Subject" />
                        <x-text-input id="subject" class="block mt-1 w-full" type="text" name="subject" :value="old('subject')" />
                        <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="body" value="Message" />
                        <textarea id="body" name="body" rows="5" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" placeholder="Hi @{{customer_name}}, ...">{{ old('body') }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">Available placeholders: <code>@{{customer_name}}</code>, <code>@{{business_name}}</code></p>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Create template</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
