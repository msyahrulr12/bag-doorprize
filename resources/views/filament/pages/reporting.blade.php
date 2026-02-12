<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Form section --}}
        <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
            {{ $this->form }}
        </div>

        {{-- Content section --}}
        <div class="space-y-6">
            @php
                $activeTab = request()->query('tab') ?? 'customer-reporting';
            @endphp

            @if ($activeTab === 'customer-reporting')
                @if ($customerId)
                    @php
                        $customer = \App\Models\Customer::with('accounts')->find($customerId);
                        $accountIds = $customer?->accounts->pluck('id')->toArray() ?? [];
                    @endphp

                    <div class="grid grid-cols-1 gap-6">
                        <x-filament::section collapsible>
                            <x-slot name="heading">
                                <div class="flex items-center space-x-2">
                                    <x-heroicon-o-user class="w-5 h-5 text-primary-500" />
                                    <span>{{ $customer->name }} - Accounts</span>
                                </div>
                            </x-slot>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50 dark:bg-gray-900">
                                            <th class="p-3 border-b text-sm font-semibold">CIF</th>
                                            <th class="p-3 border-b text-sm font-semibold">Account Number</th>
                                            <th class="p-3 border-b text-sm font-semibold">Product</th>
                                            <th class="p-3 border-b text-sm font-semibold">Balance</th>
                                            <th class="p-3 border-b text-sm font-semibold">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($customer->accounts as $account)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors">
                                                <td class="p-3 border-b text-sm">{{ $account->customer->cif }}</td>
                                                <td class="p-3 border-b text-sm">{{ $account->account_number }}</td>
                                                <td class="p-3 border-b text-sm">{{ $account->product?->nama_produk ?? 'N/A' }}</td>
                                                <td class="p-3 border-b text-sm font-mono">
                                                    {{ number_format($account->current_balance, 2) }}
                                                </td>
                                                <td class="p-3 border-b text-sm">
                                                    <x-filament::badge :color="$account->status === 'ACTIVE' ? 'success' : 'danger'">
                                                        {{ $account->status }}
                                                    </x-filament::badge>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-filament::section>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <x-filament::section collapsible heading="Point History">
                                @livewire(\App\Filament\Resources\Customers\Widgets\PointHistoryTable::class, ['account_ids' => $accountIds], 'ph-' . $customerId)
                            </x-filament::section>

                            <x-filament::section collapsible heading="Lottery Tickets">
                                @livewire(\App\Filament\Resources\Events\Widgets\LotteryTicketTable::class, ['account_ids' => $accountIds], 'lt-' . $customerId)
                            </x-filament::section>
                        </div>

                        <x-filament::section collapsible heading="Participant Details">
                            @livewire(\App\Filament\Resources\Events\Widgets\ParticipantTable::class, ['account_ids' => $accountIds], 'pt-' . $customerId)
                        </x-filament::section>
                    </div>
                @else
                    <div
                        class="flex flex-col items-center justify-center py-20 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                        <x-heroicon-o-user-group class="w-16 h-16 text-gray-400 mb-4" />
                        <h3 class="text-lg font-medium text-gray-600 dark:text-gray-400">Please select a customer to view
                            reports</h3>
                    </div>
                @endif
            @elseif ($activeTab === 'event-reporting')
                @if ($eventId)
                    @php
                        $event = \App\Models\Event::find($eventId);
                    @endphp

                    <div class="grid grid-cols-1 gap-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <x-filament::section collapsible heading="Draw Sessions">
                                @livewire(\App\Filament\Resources\Events\Widgets\DrawSessionTable::class, ['event_id' => $eventId], 'ds-' . $eventId)
                            </x-filament::section>

                            <x-filament::section collapsible heading="Event Prizes">
                                @livewire(\App\Filament\Resources\Events\Widgets\EventPrizeTable::class, ['event_id' => $eventId], 'ep-' . $eventId)
                            </x-filament::section>
                        </div>

                        <x-filament::section collapsible heading="All Lottery Tickets">
                            @livewire(\App\Filament\Resources\Events\Widgets\LotteryTicketTable::class, ['event_id' => $eventId], 'lt-e-' . $eventId)
                        </x-filament::section>
                    </div>
                @else
                    <div
                        class="flex flex-col items-center justify-center py-20 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                        <x-heroicon-o-calendar-days class="w-16 h-16 text-gray-400 mb-4" />
                        <h3 class="text-lg font-medium text-gray-600 dark:text-gray-400">Please select an event to view reports
                        </h3>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <style>
        .fi-section {
            @apply shadow-lg border-none ring-1 ring-gray-950/5 dark:ring-white/10 !important;
        }

        .fi-section-header {
            @apply bg-gradient-to-r from-primary-50 to-transparent dark:from-primary-900/10 dark:to-transparent !important;
        }
    </style>
</x-filament-panels::page>