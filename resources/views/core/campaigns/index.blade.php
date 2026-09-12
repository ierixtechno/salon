<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Campaigns</h2>
            @can('marketing.campaigns.create')
                <a href="{{ route('campaigns.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-500">
                    + New campaign
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

            <div class="mb-4 flex items-center gap-2 rounded-md bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700">
                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-6l-4 4v-4z" />
                </svg>
                <span>
                    WhatsApp credits remaining: <span class="font-semibold text-gray-900">{{ number_format($whatsappCreditBalance) }}</span>
                    — 1 credit per WhatsApp message sent. Contact us to purchase more.
                </span>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-indigo-100 text-indigo-800">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold text-base">Name</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Type</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Channel</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Recipients</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Status</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($campaigns as $campaign)
                                <tr>
                                    <td class="px-4 py-2 text-gray-800">{{ $campaign->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ str($campaign->type)->replace('_', ' ')->headline() }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ ucfirst($campaign->channel) }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $campaign->recipients_count }}</td>
                                    <td class="px-4 py-2">
                                        <span @class([
                                            'text-xs px-2 py-1 rounded-full',
                                            'bg-gray-100 text-gray-600' => $campaign->status === 'draft',
                                            'bg-indigo-100 text-indigo-700' => $campaign->status === 'scheduled',
                                            'bg-amber-100 text-amber-800' => $campaign->status === 'sending',
                                            'bg-green-100 text-green-800' => $campaign->status === 'sent',
                                            'bg-red-100 text-red-700' => $campaign->status === 'cancelled',
                                        ])>
                                            {{ ucfirst($campaign->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        @can('marketing.campaigns.send')
                                            @if (in_array($campaign->status, ['draft', 'scheduled']))
                                                <div class="flex items-center gap-2">
                                                    <form method="POST" action="{{ route('campaigns.send', $campaign) }}" onsubmit="return confirm('Send this campaign now?')">
                                                        @csrf
                                                        <button type="submit" class="text-xs px-3 py-1.5 rounded-md bg-indigo-600 text-white hover:bg-indigo-500">Send now</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('campaigns.cancel', $campaign) }}" onsubmit="return confirm('Cancel this campaign?')">
                                                        @csrf
                                                        <button type="submit" class="text-xs text-gray-500 hover:text-gray-700 underline">Cancel</button>
                                                    </form>
                                                </div>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No campaigns yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $campaigns->links() }}

            @can('marketing.automations.manage')
                <a href="{{ route('campaign-automations.index') }}" class="inline-block mt-4 text-sm text-gray-500 hover:text-gray-700">Automations &rarr;</a>
            @endcan
        </div>
    </div>
</x-app-layout>
