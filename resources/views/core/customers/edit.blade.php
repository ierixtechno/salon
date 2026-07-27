<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $customer->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @unless ($customer->isErased())
                <div class="flex flex-wrap gap-4 text-sm">
                    @can('viewAny', App\Domain\Core\Models\Package::class)
                        <a href="{{ route('customers.packages.index', $customer) }}" class="text-indigo-600 hover:text-indigo-800">Packages</a>
                    @endcan
                    @can('viewAny', App\Domain\Core\Models\MembershipPlan::class)
                        <a href="{{ route('customers.memberships.index', $customer) }}" class="text-indigo-600 hover:text-indigo-800">Memberships</a>
                    @endcan
                    @can('wallet.view')
                        <a href="{{ route('customers.wallet.show', $customer) }}" class="text-indigo-600 hover:text-indigo-800">Wallet</a>
                    @endcan
                    @can('loyalty.view')
                        <a href="{{ route('customers.loyalty.show', $customer) }}" class="text-indigo-600 hover:text-indigo-800">Loyalty</a>
                    @endcan
                </div>
            @endunless

            @if ($customer->isErased())
                <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                    This customer's personal data was erased on {{ $customer->erased_at->toFormattedDateString() }}. The record is kept only so future financial/appointment history stays intact.
                </div>
            @else
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-4">Profile</h3>

                    <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="name" value="Full name" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $customer->name)" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="phone" value="Phone" />
                                <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone" :value="old('phone', $customer->phone)" />
                            </div>
                            <div>
                                <x-input-label for="email" value="Email" />
                                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $customer->email)" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="date_of_birth" value="Date of birth" />
                                <x-text-input id="date_of_birth" class="block mt-1 w-full" type="date" name="date_of_birth" :value="old('date_of_birth', optional($customer->date_of_birth)->format('Y-m-d'))" />
                            </div>
                            <div>
                                <x-input-label for="gender" value="Gender (optional)" />
                                <x-text-input id="gender" class="block mt-1 w-full" type="text" name="gender" :value="old('gender', $customer->gender)" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="tags" value="Tags" />
                            <x-text-input id="tags" class="block mt-1 w-full" type="text" name="tags" :value="old('tags', implode(', ', $customer->tags ?? []))" />
                            <p class="text-xs text-gray-500 mt-1">Comma-separated.</p>
                        </div>

                        <div>
                            <x-input-label for="source" value="Source" />
                            <x-text-input id="source" class="block mt-1 w-full" type="text" name="source" :value="old('source', $customer->source)" />
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Save</x-primary-button>
                        </div>
                    </form>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-1">Consent</h3>
                    <p class="text-xs text-gray-500 mb-4">Recorded as a dated history, not just a yes/no toggle — required under India's DPDP Act.</p>

                    <div class="flex flex-wrap gap-3 mb-4">
                        @foreach (\App\Domain\Core\Models\CustomerConsent::PURPOSES as $purpose)
                            <form method="POST" action="{{ route('customers.consent.store', $customer) }}">
                                @csrf
                                <input type="hidden" name="purpose" value="{{ $purpose }}">
                                <input type="hidden" name="granted" value="{{ $consents->firstWhere('purpose', $purpose)?->granted ? '0' : '1' }}">
                                <button type="submit" class="text-xs px-3 py-1.5 rounded-md border {{ $consents->firstWhere('purpose', $purpose)?->granted ? 'bg-green-50 border-green-300 text-green-800' : 'bg-gray-50 border-gray-300 text-gray-600' }}">
                                    {{ str($purpose)->replace('_', ' ')->headline() }}:
                                    {{ $consents->firstWhere('purpose', $purpose)?->granted ? 'Granted — click to revoke' : 'Not granted — click to grant' }}
                                </button>
                            </form>
                        @endforeach
                    </div>

                    @if ($consents->isNotEmpty())
                        <div class="text-xs text-gray-500 space-y-1">
                            @foreach ($consents as $consent)
                                <div>{{ $consent->created_at->format('d M Y, H:i') }} — {{ str($consent->purpose)->headline() }}: {{ $consent->granted ? 'granted' : 'revoked' }}{{ $consent->recordedBy ? ' by '.$consent->recordedBy->name : '' }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ((current_tenant()->hasModuleEnabled('salon') && auth()->user()->can('salon-consultations.view'))
                        || (current_tenant()->hasModuleEnabled('beauty') && auth()->user()->can('beauty-consultations.view'))
                        || (current_tenant()->hasModuleEnabled('spa') && auth()->user()->can('spa-consultations.view')))
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-medium text-gray-900 mb-4">Consultations</h3>
                        <div class="flex flex-wrap gap-3">
                            @if (current_tenant()->hasModuleEnabled('salon') && auth()->user()->can('salon-consultations.view'))
                                <a href="{{ route('salon.profile.edit', $customer) }}" class="text-sm px-3 py-1.5 rounded-md border bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">Hair profile &amp; consultations</a>
                            @endif
                            @if (current_tenant()->hasModuleEnabled('beauty') && auth()->user()->can('beauty-consultations.view'))
                                <a href="{{ route('beauty.profile.edit', $customer) }}" class="text-sm px-3 py-1.5 rounded-md border bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">Skin profile &amp; consultations</a>
                            @endif
                            @if (current_tenant()->hasModuleEnabled('spa') && auth()->user()->can('spa-consultations.view'))
                                <a href="{{ route('spa.profile.edit', $customer) }}" class="text-sm px-3 py-1.5 rounded-md border bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">Spa profile &amp; consultations</a>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-4">Notes</h3>

                    <form method="POST" action="{{ route('customers.notes.store', $customer) }}" class="mb-4">
                        @csrf
                        <textarea name="body" rows="2" placeholder="Add a note..."
                            class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                        <div class="flex justify-end mt-2">
                            <x-primary-button>Add note</x-primary-button>
                        </div>
                    </form>

                    <div class="space-y-3">
                        @forelse ($notes as $note)
                            <div class="text-sm border-t border-gray-100 pt-3 first:border-0 first:pt-0">
                                <p class="text-gray-800">{{ $note->body }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $note->user?->name ?? 'Unknown' }} — {{ $note->created_at->diffForHumans() }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No notes yet.</p>
                        @endforelse
                    </div>
                </div>

                @can('deactivate', $customer)
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-medium text-gray-900 mb-1">Deactivate</h3>
                        <p class="text-xs text-gray-500 mb-4">Keeps all data — just marks this customer inactive.</p>
                        <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Deactivate this customer?')">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>Deactivate</x-danger-button>
                        </form>
                    </div>
                @endcan

                @can('erase', $customer)
                    <div class="bg-white shadow-sm rounded-lg p-6 border border-red-200">
                        <h3 class="font-medium text-red-900 mb-1">Erase personal data (DPDP request)</h3>
                        <p class="text-xs text-red-700 mb-4">Permanently removes name, contact details, and notes. This cannot be undone. Use this only for a genuine data-erasure request from the customer.</p>
                        <form method="POST" action="{{ route('customers.erase', $customer) }}" onsubmit="return confirm('This permanently erases this customer\'s personal data and cannot be undone. Continue?')">
                            @csrf
                            <x-danger-button>Erase personal data</x-danger-button>
                        </form>
                    </div>
                @endcan
            @endif
        </div>
    </div>
</x-app-layout>
