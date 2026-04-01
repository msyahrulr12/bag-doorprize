<x-filament-panels::page x-data="{
    drawing: @entangle('batchId'),
    isStopping: @entangle('isStopping'),
    placeholders: [],
    init() {
        setInterval(() => {
            if (this.drawing && !this.isStopping) {
                const names = ['Wibowo', 'Sari', 'Pratama', 'Lestari', 'Budi', 'Putri', 'Hidayat', 'Santoso', 'Kurniawan', 'Mulyani', 'Setiawan', 'Ramadhan', 'Wijaya', 'Utami'];
                const branches = ['JAKARTA', 'SURABAYA', 'BANDUNG', 'MEDAN', 'MAKASSAR', 'SEMARANG', 'PALEMBANG', 'MALANG', 'BEKASI', 'TANGERANG'];
                this.placeholders = Array.from({length: 6}).map(() => ({
                    name: names[Math.floor(Math.random() * names.length)] + ' *** ' + names[Math.floor(Math.random() * names.length)],
                    branch: branches[Math.floor(Math.random() * branches.length)],
                    lucky_number: Math.floor(Math.random() * 99999999).toString().padStart(8, '0')
                }));
            }
        }, 80);
    }
}">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Prize Info Card -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-800">
            <h2 class="text-2xl font-bold mb-4 flex items-center gap-2 text-gray-800 dark:text-white">
                <div class="p-2 bg-primary-50 dark:bg-primary-900/50 rounded-lg">
                    <x-heroicon-o-gift class="w-6 h-6 text-primary-500" />
                </div>
                Prize Details
            </h2>

            @if($eventPrize->prize->prize_image)
                <div class="mb-6 relative group overflow-hidden rounded-2xl border border-gray-100 dark:border-gray-800">
                    <img src="{{ Storage::url($eventPrize->prize->prize_image) }}"
                        class="w-full h-48 object-contain bg-gray-50 dark:bg-gray-800 transition-transform duration-500 group-hover:scale-110">
                </div>
            @endif

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
                    class="w-full shadow-lg shadow-primary-500/30 font-bold py-4" wire:loading.attr="disabled"
                    :disabled="count($winners ?? []) == $remainingQuantity">
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

    @if($batchId || $isStopping)
        <div wire:poll.2000ms="checkBatchStatus"
            class="mt-8 bg-white dark:bg-gray-900 rounded-[2rem] shadow-2xl border-2 border-primary-500/10 text-center relative overflow-hidden p-8 md:p-12">
            
            <!-- Abstract Pattern -->
            <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
                style="background-image: radial-gradient(theme('colors.primary.500') 1px, transparent 1px); background-size: 20px 20px;"></div>

            <div class="max-w-4xl mx-auto relative z-10">
                @if($isStopping)
                    <div
                        class="mb-8 inline-flex items-center justify-center p-6 bg-warning-50 dark:bg-warning-900/30 rounded-full animate-bounce">
                        <x-heroicon-o-arrow-path class="w-12 h-12 text-warning-600 animate-spin" />
                    </div>
                    <h3 class="text-3xl font-black text-gray-900 dark:text-white mb-2 uppercase tracking-tight">Finalizing Results</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-8 font-bold text-xs uppercase tracking-[0.2em]">Wrapping up the drawing sequence...</p>
                @else
                    <div class="mb-10 relative">
                        <div class="absolute inset-0 bg-primary-500/10 rounded-full blur-3xl animate-pulse"></div>
                        <div class="relative z-10 flex gap-2 md:gap-3 justify-center scale-90 md:scale-100">
                            <template x-for="(digit, index) in (placeholders[0]?.lucky_number || '00000000').split('')" :key="index">
                                <div class="w-12 h-16 md:w-16 md:h-24 bg-gray-950 rounded-2xl border border-white/20 flex items-center justify-center shadow-2xl">
                                    <span class="text-4xl md:text-6xl font-black font-mono text-white tracking-tighter" x-text="digit"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <h3 class="text-2xl font-black text-gray-900 dark:text-white mb-2 uppercase tracking-tight">System is Drawing Winners</h3>
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-primary-500/10 rounded-full border border-primary-500/20 mb-8">
                        <span class="animate-ping w-2 h-2 bg-primary-500 rounded-full"></span>
                        <span class="text-[10px] font-black text-primary-500 uppercase tracking-widest leading-none">Weighted Randomization Active</span>
                    </div>

                    <div class="w-full max-w-2xl mx-auto">
                        <div class="flex items-center justify-between mb-2">
                             <div class="flex items-center gap-2">
                                 <x-filament::loading-indicator class="w-4 h-4 text-primary-600" />
                                 <span class="text-[10px] font-black uppercase text-primary-600 tracking-widest">
                                    {{ $processedCount }} / {{ $totalToProcess }} READY
                                 </span>
                             </div>
                             @php $progress = ($totalToProcess > 0) ? ($processedCount / $totalToProcess) * 100 : 0; @endphp
                             <span class="text-[10px] font-black uppercase text-gray-400 tracking-widest">{{ round($progress) }}%</span>
                        </div>
                        <div class="w-full h-3 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden shadow-inner mb-10">
                            <div class="h-full bg-primary-600 transition-all duration-500" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>

                    <!-- Placeholder Animation Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-10">
                        <template x-for="(p, i) in placeholders" :key="i">
                            <div class="bg-gray-50/50 dark:bg-gray-800/30 border border-dashed border-gray-200 dark:border-gray-700 rounded-xl p-3 flex items-center gap-3 opacity-60 animate-fade-in-up">
                                <div class="w-10 h-10 bg-white dark:bg-gray-800 rounded-lg flex items-center justify-center shrink-0 border border-gray-100 dark:border-gray-700 shadow-sm">
                                    <x-heroicon-o-sparkles class="w-5 h-5 text-primary-400" />
                                </div>
                                <div class="flex-1 text-left min-w-0">
                                    <div class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-0.5 truncate" x-text="p.branch"></div>
                                    <h4 class="font-black text-gray-600 dark:text-gray-400 text-[10px] truncate" x-text="p.name"></h4>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-xs font-black font-mono text-primary-600 tracking-tighter" x-text="p.lucky_number"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                @endif

                @if($batchStatus !== 'CANCELLED')
                    <div class="mt-4">
                            <x-filament::button wire:click="stopDrawing" color="danger" variant="outline" size="sm"
                                class="font-extrabold uppercase tracking-tighter shadow-sm hover:shadow-md transition-all transition-transform active:scale-95">
                                <span class="flex items-center gap-2">
                                    <x-heroicon-o-stop-circle class="w-4 h-4" />
                                    STOP DRAWING
                                </span>
                            </x-filament::button>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif

    @if(!$isStopping && $winners)
        <div x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
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
                    <h3 class="text-3xl font-black mb-1 leading-none tracking-tighter">CONGRATULATIONS!</h3>
                    <div class="flex items-center gap-3 justify-center md:justify-start mt-4">
                        <x-filament::link wire:click="exportCsv" color="success"
                            class="text-[10px] font-black uppercase tracking-widest bg-white/10 px-3 py-1.5 rounded-lg border border-white/20 hover:bg-white/20 cursor-pointer">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-document-text class="w-3.5 h-3.5 text-green-400" />
                                CSV
                            </span>
                        </x-filament::link>
                        <x-filament::link wire:click="exportExcel" color="success"
                            class="text-[10px] font-black uppercase tracking-widest bg-white/10 px-3 py-1.5 rounded-lg border border-white/20 hover:bg-white/20 cursor-pointer">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-table-cells class="w-3.5 h-3.5 text-emerald-400" />
                                EXCEL
                            </span>
                        </x-filament::link>
                    </div>
                </div>

                @if ($isPreview && $enableRedraw && !$alreadyConfirmed)
                    <div class="flex flex-col gap-3 min-w-[240px]">
                        <x-filament::button wire:click="confirmWinner" color="warning" size="lg"
                            class="w-full font-black !text-gray-950 !bg-yellow-400 hover:!bg-yellow-500 shadow-xl transition-all active:scale-95 rounded-xl border-none py-3">
                            <span class="flex items-center justify-center gap-3">
                                <x-heroicon-s-check-badge class="w-6 h-6" />
                                CONFIRM ALL
                            </span>
                        </x-filament::button>

                        <div class="flex flex-col gap-2">
                            <x-filament::button wire:click="resetWinners" color="danger" variant="outline"
                                class="w-full font-bold py-2 border border-white/20 hover:bg-red-500/10 text-white rounded-xl">
                                <span class="flex items-center gap-1.5 justify-center">
                                    <x-heroicon-s-arrow-path class="w-4 h-4" />
                                    RESET & REDRAW
                                </span>
                            </x-filament::button>

                            <x-filament::button wire:click="clearWinner" color="white" variant="link"
                                class="text-white/60 hover:text-white font-bold py-1 text-[10px] uppercase tracking-widest">
                                <span class="flex items-center gap-1.5 justify-center">
                                    CANCEL
                                </span>
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-8 pt-8 border-t border-white/10">
                @if($isSingleDrawingMode && !empty($winners))
                    @php $singleWinner = collect($winners)->flatten(1)->first(); @endphp
                    @if($singleWinner)
                        <!-- Grand Style Result for Single Winner -->
                        <div class="w-full max-w-2xl mx-auto bg-gray-950 rounded-[3rem] p-12 shadow-2xl border border-white/10 text-white text-center animate-winner-card overflow-hidden relative mb-4">
                            <!-- Pattern -->
                            <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 20px 20px;"></div>
                            
                            <div class="relative z-10 flex flex-col items-center">
                                <div class="mb-8 inline-flex p-6 bg-white/10 rounded-[2.5rem] backdrop-blur-md border border-white/20 shadow-inner">
                                     <x-heroicon-o-gift class="w-20 h-20 text-yellow-400" />
                                </div>
                                <div class="text-[10px] font-black uppercase tracking-[0.5em] text-white/50 mb-4">The Chosen Winner</div>
                                <h2 class="text-4xl md:text-6xl font-black mb-4 tracking-tighter drop-shadow-[0_10px_20px_rgba(0,0,0,0.5)] uppercase">
                                    {{ $singleWinner['name'] }}
                                </h2>
                                <div class="text-xl font-bold opacity-60 mb-10 uppercase tracking-widest">{{ $singleWinner['branch_name'] }}</div>
                                
                                <div class="flex flex-col items-center bg-white/5 px-12 py-8 rounded-[2rem] backdrop-blur-lg border border-white/10 shadow-2xl">
                                    <span class="text-xs font-black opacity-40 uppercase tracking-[0.3em] mb-6">Lucky Ticket Sequence</span>
                                    <div class="flex gap-2">
                                        @foreach(str_split($singleWinner['lucky_number'] ?? $singleWinner['winning_number'] ?? '00000000') as $d)
                                            <div class="w-10 h-14 md:w-14 md:h-20 bg-gray-900 rounded-2xl border border-white/20 flex items-center justify-center shadow-lg">
                                                <span class="text-2xl md:text-5xl font-black font-mono tracking-tight text-white">{{ $d }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="mt-8 text-[10px] font-mono text-white/30 uppercase tracking-[0.2em]">
                                        CIF: {{ $singleWinner['cif'] }} • ACC: {{ $singleWinner['account']['account_number'] ?? $singleWinner['account_number'] ?? 'N/A' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Decoration Icons -->
            <x-heroicon-o-sparkles class="absolute top-4 right-10 w-24 h-24 text-white/5 rotate-12" />
            <x-heroicon-o-star class="absolute -bottom-8 -left-8 w-40 h-40 text-white/5 -rotate-12" />

            <div></div>
        </div>

        <div class="mt-8 space-y-4" x-data="{}"
            x-on:scroll-to-results.window="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-users class="w-6 h-6 text-primary-500" />
                    Generated Winners ({{ $totalWinners }})
                </h3>
                <div class="flex items-center gap-4">
                    <x-filament::button wire:click="exportCsv" color="success" icon="heroicon-o-arrow-down-tray" size="sm"
                        class="font-bold">
                        EXPORT CSV
                    </x-filament::button>
                    <div class="text-sm text-gray-500 font-medium italic">
                        {{ $isPreview ? 'Preview results - not yet saved' : 'Confirmed winners' }}
                    </div>
                </div>
            </div>

            <div class="relative min-h-[400px]">
                <!-- Table Loading Overlay -->
                <div wire:loading wire:target="gotoPage,nextPage,previousPage"
                    class="absolute inset-0 z-50 bg-white/50 dark:bg-gray-900/50 backdrop-blur-[2px] rounded-xl flex items-center justify-center transition-opacity duration-300">
                    <div class="flex flex-col items-center gap-3">
                        <x-filament::loading-indicator class="w-10 h-10 text-primary-600" />
                        <span
                            class="text-sm font-bold text-primary-700 dark:text-primary-400 uppercase tracking-widest animate-pulse">
                            Refreshing winners...
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 transition-opacity duration-300"
                    wire:loading.class="opacity-50 pointer-events-none" wire:target="gotoPage,nextPage,previousPage">
                    @foreach ($winners as $winnerChunk)
                        <div class="space-y-4">
                            <div
                                class="overflow-hidden bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800 rounded-xl hover:shadow-md transition-shadow">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50 dark:bg-gray-800/50">
                                            <th class="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">
                                                Customer Info
                                            </th>
                                            <th
                                                class="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider text-center">
                                                Lucky Number
                                            </th>
                                            <th class="px-4 py-3 text-[10px] font-black text-gray-400 uppercase tracking-wider">
                                                Branch / Ticket
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach ($winnerChunk as $winner)
                                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-col">
                                                        <span
                                                            class="font-bold text-xs text-gray-900 dark:text-white leading-tight">{{ $winner['name'] }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                                    <span
                                                        class="inline-block px-2 py-0.5 bg-primary-50 dark:bg-primary-900/10 text-primary-700 dark:text-primary-400 rounded-md font-black font-mono text-[11px] shadow-sm border border-primary-100/50 dark:border-primary-800/30">
                                                        {{ $winner['lucky_number'] ?? $winner['winning_number'] }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-col">
                                                        <div
                                                            class="mb-1.5 pb-1.5 border-b border-gray-100 dark:border-gray-800 items-center">
                                                            <span
                                                                class="text-[10px] font-black text-primary-600 dark:text-primary-400 uppercase tracking-tighter">{{ $winner['account']['branch']['branch_name'] ?? ($winner['branch_name'] ?? 'N/A') }}</span>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if (!$isPreview && $this->paginatedWinners->total() > 0)
                <div class="mt-8 p-4 bg-gray-50 dark:bg-gray-800/20 rounded-xl border border-gray-100 dark:border-gray-800/50">
                    <x-filament::pagination :paginator="$this->paginatedWinners" />
                </div>
            @endif
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