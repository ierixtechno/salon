<x-platform-layout>
    <x-slot name="header">Notifications</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-3">
            @if ($notifications->contains(fn ($n) => $n->read_at === null))
                <form method="POST" action="{{ route('platform.notifications.read-all') }}" class="text-right">
                    @csrf
                    <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-800 underline">Mark all read</button>
                </form>
            @endif

            @forelse ($notifications as $notification)
                <a href="{{ route('platform.notifications.open', $notification) }}" class="block bg-white shadow-sm rounded-lg p-4 hover:bg-gray-50 {{ $notification->read_at ? 'opacity-60' : 'border-l-4 border-indigo-500' }}">
                    <p class="font-medium text-gray-900">{{ $notification->title }}</p>
                    @if ($notification->body)
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ \Illuminate\Support\Str::limit($notification->body, 300) }}</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                </a>
            @empty
                <div class="bg-white shadow-sm rounded-lg p-6 text-center text-gray-500 text-sm">No notifications yet.</div>
            @endforelse

            {{ $notifications->links() }}
        </div>
    </div>
</x-platform-layout>
