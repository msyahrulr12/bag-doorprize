<x-filament-panels::page>
    <div class="space-y-6">
        @if ($customerId)
            @php
                $customer = \App\Models\Customer::with(['accounts', 'accounts.product', 'accounts.branch'])->find($customerId);
                $accountIds = $customer?->accounts->pluck('id')->toArray() ?? [];
            @endphp

            @if ($customer)
                <div class="flex items-center justify-between mb-4">
                    <x-filament::button wire:click="resetCustomer" color="gray" icon="heroicon-o-arrow-left">
                        Back to List
                    </x-filament::button>

                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        Report: {{ $customer->name }} ({{ $customer->cif }})
                    </h2>
                </div>

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
                                            <td class="p-3 border-b text-sm">{{ $customer->cif }}</td>
                                            <td class="p-3 border-b text-sm">{{ $account->account_number }}</td>
                                            <td class="p-3 border-b text-sm">{{ $account->product?->nama_produk ?? 'N/A' }}</td>
                                            <td class="p-3 border-b text-sm font-mono text-right">
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
                            @livewire(\App\Filament\Resources\Customers\Widgets\PointHistoryTable::class, ['account_ids' => $accountIds, 'showExport' => false], 'ph-' . $customerId)
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
                    class="p-6 bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700 text-center">
                    <p class="text-gray-500">Customer not found.</p>
                    <x-filament::button wire:click="resetCustomer" class="mt-4">Back to List</x-filament::button>
                </div>
            @endif
        @else
            <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                <div class="mb-4">
                    <h2 class="text-lg font-bold">Select a Customer to View Reports</h2>
                    <p class="text-sm text-gray-500">List of all active customers in your assigned branches.</p>
                </div>
                @livewire(\App\Filament\Pages\Widgets\CustomerReportingTable::class)
            </div>
        @endif
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