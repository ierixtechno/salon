<x-app-layout>
    <x-slot name="header">My Notifications</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-4">
            @forelse ($notifications as $notification)
                <div class="bg-white shadow-sm rounded-lg p-4 flex items-start justify-between gap-4 {{ $notification->read_at ? 'opacity-60' : '' }}">
                    <div>
                        @if ($notification->subject)
                            <p class="font-medium text-gray-900">{{ $notification->subject }}</p>
                        @endif
                        <p class="text-sm text-gray-600">{{ $notification->body }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                    @unless ($notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-800 underline whitespace-nowrap">Mark read</button>
                        </form>
                    @endunless
                </div>
            @empty
                <div class="bg-white shadow-sm rounded-lg p-6 text-center text-gray-500 text-sm">
                    No notifications yet.
                </div>
            @endforelse

            {{ $notifications->links() }}
        </div>
    </div>
</x-app-layout>
