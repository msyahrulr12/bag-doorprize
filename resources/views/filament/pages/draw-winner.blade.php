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
                    :disabled="$remainingQuantity == 0">
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

    @if($isDrawing)
        <div wire:ignore wire:key="admin-draw-{{ now()->timestamp }}" x-data="{
            digits: '00000000',
            target: '{{ $pendingWinner['lucky_number'] }}',
            placeholderName: 'SEARCHING...',
            placeholderBranch: 'RANDOMIZING...',
            isStopping: false,
            init() {
                let interval = setInterval(() => {
                    if (this.isStopping) return;
                    this.digits = Math.floor(Math.random() * 99999999).toString().padStart(8, '0');

                    const names = ['Wibowo', 'Sari', 'Pratama', 'Lestari', 'Budi', 'Putri', 'Hidayat', 'Santoso', 'Kurniawan', 'Mulyani', 'Setiawan', 'Ramadhan', 'Wijaya', 'Utami'];
                    const branches = ['JAKARTA', 'SURABAYA', 'BANDUNG', 'MEDAN', 'MAKASSAR', 'SEMARANG', 'PALEMBANG', 'MALANG', 'BEKASI', 'TANGERANG'];
                    this.placeholderName = names[Math.floor(Math.random() * names.length)] + ' *** ' + names[Math.floor(Math.random() * names.length)];
                    this.placeholderBranch = branches[Math.floor(Math.random() * branches.length)];
                }, 50);
            },
            stop() {
                let self = this;
                self.isStopping = true;
                self.digits = self.target;
                setTimeout(() => {
                    $wire.finishDrawing();
                }, 1500); 
            }
        }"
            class="mt-12 bg-gray-950 rounded-[3rem] shadow-2xl p-12 text-center relative overflow-hidden border border-white/10">

            <!-- Abstract Background -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none opacity-30">
                <div class="absolute -top-24 -left-24 w-96 h-96 bg-primary-500/20 rounded-full blur-[120px]"></div>
                <div
                    class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-primary-500/10 rounded-full blur-[160px]">
                </div>
                <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-blue-500/10 rounded-full blur-[120px]"></div>
            </div>

            <div class="relative z-10">
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary-500/10 rounded-full border border-primary-500/20 mb-10">
                    <span class="relative flex h-3 w-3">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-primary-500"></span>
                    </span>
                    <span class="text-primary-400 font-bold text-[10px] uppercase tracking-[0.4em]">Weighted Randomization
                        Engine Active</span>
                </div>

                <div class="flex justify-center items-center gap-2 md:gap-4 mb-12">
                    <template x-for="(digit, index) in digits.split('')" :key="index">
                        <div
                            class="w-12 h-18 md:w-16 md:h-24 bg-gradient-to-b from-gray-800 to-gray-900 rounded-2xl border border-white/20 flex items-center justify-center shadow-[0_10px_30px_rgba(0,0,0,0.5)]">
                            <span class="text-4xl md:text-6xl font-black font-mono text-white tracking-tighter"
                                x-text="digit"></span>
                        </div>
                    </template>
                </div>

                <!-- Searching Info Card -->
                <div x-show="!isStopping"
                    class="mb-12 animate-pulse bg-white/5 p-8 rounded-3xl border border-white/10 inline-block min-w-[350px] backdrop-blur-md">
                    <div class="text-[10px] font-black uppercase text-primary-400 tracking-[0.3em] mb-3">Analyzing Eligible
                        Participants</div>
                    <div class="text-3xl font-black text-white mb-2" x-text="placeholderName"></div>
                    <div class="inline-block px-4 py-1.5 bg-primary-500/20 rounded-full text-primary-300 text-[10px] font-black uppercase tracking-[0.2em] border border-primary-500/30"
                        x-text="placeholderBranch"></div>
                </div>

                <div class="max-w-xs mx-auto">
                    <x-filament::button x-show="!isStopping" x-on:click="stop()" color="primary" size="xl"
                        class="w-full font-black py-4 shadow-xl hover:scale-105 transition-all bg-primary-600">
                        <span class="flex items-center justify-center gap-2">
                            <x-heroicon-s-bolt class="w-5 h-5" />
                            STOP & REVEAL
                        </span>
                    </x-filament::button>

                    <div x-show="isStopping" class="flex flex-col items-center gap-3 animate-pulse">
                        <x-filament::loading-indicator class="w-8 h-8 text-primary-500" />
                        <span class="text-primary-400 font-black text-xs uppercase tracking-[0.4em]">Target Found</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(!$isDrawing && $winner)
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
                        <div
                            class="px-4 py-1 bg-white/20 backdrop-blur-md text-white rounded-full text-sm font-bold uppercase tracking-widest border border-white/10">
                            Branch: {{ $winner['participant']->account->branch->branch_name ?? 'N/A' }}
                        </div>
                    </div>
                    <h3 class="text-3xl font-black mb-1">CONGRATULATIONS!</h3>
                    <p class="text-white/80 mb-6 text-lg">
                        @if(empty($pendingWinners) || count($pendingWinners) <= 1)
                            The ticket winner is <span
                                class="text-white font-mono bg-white/20 px-2 py-1 rounded">#{{ $winner['lucky_number'] }}</span>
                        @else
                            <span
                                class="text-white font-black bg-white/20 px-3 py-1 rounded text-2xl animate-pulse">{{ count($pendingWinners) }}
                                Winners Found</span>
                        @endif
                    </p>
                    <div class="space-y-6 mt-6">
                        <div class="text-5xl font-black tracking-tighter leading-tight drop-shadow-lg">
                            @if(empty($pendingWinners) || count($pendingWinners) <= 1)
                                {{ $winner['participant']->participant_name }}
                            @else
                                <div class="text-2xl font-bold opacity-90">First Winner:
                                    {{ $winner['participant']->participant_name }}
                                </div>
                                <div class="text-sm opacity-70 italic mt-2">(See full list in the table below)</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-4 justify-center md:justify-start">
                            <x-filament::link wire:click="exportCsv" color="success"
                                class="bg-white/10 px-4 py-2 rounded-xl border border-white/20 text-xs font-black uppercase tracking-widest hover:bg-white/20 cursor-pointer backdrop-blur-md">
                                <span class="flex items-center gap-2">
                                    <x-heroicon-o-document-text class="w-4 h-4 text-green-400" />
                                    CSV
                                </span>
                            </x-filament::link>
                            <x-filament::link wire:click="exportExcel" color="success"
                                class="bg-white/10 px-4 py-2 rounded-xl border border-white/20 text-xs font-black uppercase tracking-widest hover:bg-white/20 cursor-pointer backdrop-blur-md">
                                <span class="flex items-center gap-2">
                                    <x-heroicon-o-table-cells class="w-4 h-4 text-emerald-400" />
                                    EXCEL
                                </span>
                            </x-filament::link>
                        </div>
                    </div>
                </div>

                @if ($isPreview)
                    <div class="flex flex-col gap-3 min-w-[220px]">
                        <x-filament::button wire:click="confirmWinners" color="white" size="xl"
                            class="group relative overflow-hidden font-black text-primary-700 hover:text-primary-800 shadow-xl transition-all hover:-translate-y-1">
                            <span class="relative z-10 flex items-center justify-center gap-2">
                                <x-heroicon-s-check-circle class="w-6 h-6" />
                                CONFIRM ALL
                            </span>
                            <div class="absolute inset-0 bg-yellow-300 opacity-0 group-hover:opacity-10 transition-opacity">
                            </div>
                        </x-filament::button>

                        <div class="flex flex-col gap-2 mt-2">
                            <x-filament::button wire:click="resetWinners" color="danger" variant="outline"
                                class="w-full font-bold py-2 border border-white/20 hover:bg-red-500/10 text-white">
                                <span class="flex items-center gap-1.5 justify-center">
                                    <x-heroicon-s-arrow-path class="w-4 h-4" />
                                    RESET & REDRAW
                                </span>
                            </x-filament::button>

                            <x-filament::button wire:click="clearWinner" color="white" variant="link"
                                class="text-white/60 hover:text-white font-bold py-1 text-[10px] uppercase tracking-widest">
                                <span class="flex items-center gap-1.5 justify-center">
                                    CLOSE VIEW
                                </span>
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Decoration Icons -->
            <x-heroicon-o-sparkles class="absolute top-4 right-10 w-24 h-24 text-white/5 rotate-12" />
            <x-heroicon-o-star class="absolute -bottom-8 -left-8 w-40 h-40 text-white/5 -rotate-12" />
        </div>
    @endif

    @if (!empty($winners))
        <div class="mt-8 space-y-4" x-data="{}"
            x-on:scroll-to-results.window="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-users class="w-6 h-6 text-primary-500" />
                    Generated Winners ({{ $this->paginatedWinners->total() }})
                </h3>
                <div class="flex items-center gap-4">
                    <div class="text-sm text-gray-500 font-medium italic uppercase tracking-widest text-[10px]">
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
                                                        <span
                                                            class="text-[10px] text-gray-500 font-mono mt-0.5">{{ $winner['cif'] }}
                                                            •
                                                            {{ $winner['account']['account_number'] ?? 'N/A' }}</span>
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