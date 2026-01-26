<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Prize Info Card -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-800">
            <h2 class="text-2xl font-bold mb-4 flex items-center gap-2 text-gray-800 dark:text-white">
                <div class="p-2 bg-primary-50 dark:bg-primary-900/50 rounded-lg">
                    <x-heroicon-o-gift class="w-6 h-6 text-primary-500" />
                </div>
                Prize Details
            </h2>

            <div class="space-y-4">
                <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-gray-800">
                    <span class="text-gray-500 dark:text-gray-400">Prize Name</span>
                    <span
                        class="font-bold text-gray-900 dark:text-white text-lg">{{ $eventPrize->prize->prize_name }}</span>
                </div>
                <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-gray-800">
                    <span class="text-gray-500 dark:text-gray-400">Tier</span>
                    <span
                        class="px-3 py-1 bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 rounded-full text-xs font-bold uppercase tracking-wider">
                        {{ \App\Models\Prize::PRIZE_TIER[$eventPrize->prize->tier] ?? 'Common' }}
                    </span>
                </div>
                <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-gray-800">
                    <span class="text-gray-500 dark:text-gray-400">Min. Points Required</span>
                    <div class="flex items-center gap-2">
                        <span
                            class="font-black text-2xl text-primary-600 dark:text-primary-400">{{ number_format($eventPrize->min_points_required) }}</span>
                        <span class="text-xs text-gray-400 uppercase font-bold">Points</span>
                    </div>
                </div>
                <div class="flex justify-between items-center py-3">
                    <span class="text-gray-500 dark:text-gray-400">Availability</span>
                    <div class="flex flex-col items-end">
                        <span class="font-bold text-gray-900 dark:text-white">{{ $eventPrize->remaining_quantity }}
                            /
                            {{ $eventPrize->total_quantity }} left</span>
                        <div class="w-24 h-2 bg-gray-100 dark:bg-gray-800 rounded-full mt-1 overflow-hidden">
                            @php $percentage = ($eventPrize->total_quantity > 0) ? ($eventPrize->remaining_quantity / $eventPrize->total_quantity) * 100 : 0; @endphp
                            <div class="h-full bg-primary-500 transition-all duration-500"
                                style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Drawing Form Card -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-800">
            <h2 class="text-2xl font-bold mb-4 flex items-center gap-2 text-gray-800 dark:text-white">
                <div class="p-2 bg-warning-50 dark:bg-warning-900/50 rounded-lg">
                    <x-heroicon-o-magnifying-glass-circle class="w-6 h-6 text-warning-500" />
                </div>
                Winner Selection
            </h2>

            <form wire:submit.prevent="draw" class="space-y-6">
                {{ $this->form }}

                <x-filament::button type="submit" size="xl" color="primary"
                    class="w-full shadow-lg shadow-primary-500/30 font-bold py-4" wire:loading.attr="disabled">
                    <span wire:loading.remove class="flex items-center gap-2">
                        <x-heroicon-s-bolt class="w-5 h-5" />
                        GENERATE WINNER
                    </span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        GENERATING...
                    </span>
                </x-filament::button>
            </form>

            <div
                class="mt-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-xs text-gray-500 dark:text-gray-400 flex items-start gap-2">
                <x-heroicon-o-information-circle class="w-4 h-4 mt-0.5 shrink-0" />
                <p>System uses a weighted random algorithm (Jawa 50%, Sumatera 20%, Sulawesi 20%, Others 10%) to
                    pick a
                    fair winner while ensuring all eligibility rules are met.</p>
            </div>
        </div>
    </div>

    @if($batchId)
        <div wire:poll.2000ms="checkBatchStatus"
            class="mt-8 bg-white dark:bg-gray-900 rounded-xl shadow-sm p-8 border-2 border-primary-500/20 text-center animate-pulse">
            <div class="max-w-md mx-auto">
                <div
                    class="mb-4 inline-flex items-center justify-center p-4 bg-primary-50 dark:bg-primary-900/30 rounded-full">
                    <svg class="animate-spin h-10 w-10 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                </div>
                <h3 class="text-xl font-black text-gray-900 dark:text-white mb-2 uppercase tracking-tight">System is Drawing
                    Winners</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-6">Processing batch #{{ $batchId }}. Currently
                    {{ $processedCount }} of {{ $totalToProcess }} generated.
                </p>

                <div class="w-full h-3 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                    @php $progress = ($totalToProcess > 0) ? ($processedCount / $totalToProcess) * 100 : 0; @endphp
                    <div class="h-full bg-primary-600 transition-all duration-500" style="width: {{ $progress }}%"></div>
                </div>
                <p class="mt-2 text-[10px] font-black text-primary-600 uppercase tracking-widest">{{ round($progress) }}%
                    Complete</p>
            </div>
        </div>
    @endif

    @if($winners)
        <div x-data="{ show: true, winners: {{ json_encode($winners) }} }" x-show="show"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="mt-8 bg-gradient-to-br from-primary-600 via-primary-700 to-indigo-800 rounded-2xl shadow-2xl p-8 text-white relative overflow-hidden">
            <!-- Background Decorations -->
            <div class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 -ml-16 -mb-16 w-64 h-64 bg-primary-400/20 rounded-full blur-3xl"></div>

            <div class="relative z-10 flex flex-col md:flex-row items-center gap-8">
                <div class="bg-white/20 p-6 rounded-3xl backdrop-blur-xl border border-white/30 shadow-2xl animate-pulse">
                    <x-heroicon-o-trophy class="w-24 h-24 text-yellow-300 drop-shadow-[0_0_15px_rgba(253,224,71,0.5)]" />
                </div>

                <div class="flex-1 text-center md:text-left">
                    <div class="flex items-center justify-center md:justify-start gap-2 mb-4">
                        <div
                            class="px-4 py-1 bg-yellow-400 text-primary-900 rounded-full text-sm font-black uppercase tracking-widest shadow-lg">
                            Winner Detected
                        </div>
                    </div>
                    <h3 class="text-3xl font-black mb-1">CONGRATULATIONS!</h3>
                </div>

                @if (!$alreadyConfirmed)
                    <div class="flex flex-col gap-3 min-w-[220px]">
                        <x-filament::button wire:click="confirmWinner" color="white" size="xl"
                            class="group relative overflow-hidden font-black text-primary-700 hover:text-primary-800 shadow-xl transition-all hover:-translate-y-1">
                            <span class="relative z-10 flex items-center justify-center gap-2">
                                <x-heroicon-s-check-circle class="w-6 h-6" />
                                CONFIRM WINNER
                            </span>
                            <div class="absolute inset-0 bg-yellow-300 opacity-0 group-hover:opacity-10 transition-opacity">
                            </div>
                        </x-filament::button>

                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <x-filament::button wire:click="draw" color="white" variant="link"
                                class="text-white bg-white/10 hover:bg-white/20 font-bold border border-white/20 py-2">
                                <span class="flex items-center gap-1.5 justify-center">
                                    <x-heroicon-o-arrow-path class="w-4 h-4" />
                                    RE-DRAW
                                </span>
                            </x-filament::button>

                            <x-filament::button wire:click="clearWinner" color="white" variant="link"
                                class="text-white bg-white/10 hover:bg-white/20 font-bold border border-white/20 py-2">
                                <span class="flex items-center gap-1.5 justify-center">
                                    <x-heroicon-o-x-mark class="w-4 h-4" />
                                    CANCEL
                                </span>
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Decoration Icons -->
            <x-heroicon-o-sparkles class="absolute top-4 right-10 w-24 h-24 text-white/5 rotate-12" />
            <x-heroicon-o-star class="absolute -bottom-8 -left-8 w-40 h-40 text-white/5 -rotate-12" />

            <div></div>
        </div>

        <div class="mt-8 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-users class="w-6 h-6 text-primary-500" />
                    Generated Winners ({{ count($winners) }})
                </h3>
                <div class="text-sm text-gray-500 font-medium">
                    Batch Preview - Click Confirm to save
                </div>
            </div>

            <div
                class="overflow-hidden bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800 rounded-xl">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/50">
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-wider">Customer Info
                            </th>
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-wider text-center">
                                Lucky Number</th>
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-wider">Ticket Details
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($winners as $winner)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-900 dark:text-white">{{ $winner['name'] }}</span>
                                        <span class="text-xs text-gray-500 font-mono">{{ $winner['cif'] }} •
                                            {{ $winner['account']['account_number'] }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        class="inline-block px-4 py-2 bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 rounded-lg font-black font-mono text-lg shadow-sm border border-primary-100 dark:border-primary-800/50">
                                        {{ $winner['lucky_number'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-gray-700 dark:text-gray-300">Range:
                                            {{ $winner['winning_number'] }}</span>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span
                                                class="text-[10px] px-2 py-0.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded uppercase font-bold tracking-tighter">Points:
                                                {{ number_format($winner['ticket']['total_points']) }}</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <style>
        @keyframes bounce-subtle {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        .animate-bounce-subtle {
            animation: bounce-subtle 3s ease-in-out infinite;
        }
    </style>
</x-filament-panels::page>