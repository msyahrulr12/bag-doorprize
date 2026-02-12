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
                    :disabled="$winner">
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
        <div x-data="{
                        digits: '00000000',
                        target: '{{ $pendingWinner['lucky_number'] }}',
                        isStopping: false,
                        init() {
                            let interval = setInterval(() => {
                                if (this.isStopping) return;
                                this.digits = Math.floor(Math.random() * 99999999).toString().padStart(8, '0');
                            }, 50);

                            setTimeout(() => {
                                if(!this.isStopping) this.stop();
                            }, 5000);
                        },
                        stop() {
                            this.isStopping = true;
                            this.digits = this.target;
                            setTimeout(() => {
                                $wire.finishDrawing();
                            }, 1500); 
                        }
                    }"
            class="mt-12 bg-gray-950 rounded-3xl shadow-2xl p-12 text-center relative overflow-hidden border border-white/10">
            <div class="relative z-10">
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary-500/10 rounded-full border border-primary-500/20 mb-8">
                    <span class="relative flex h-3 w-3">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-primary-500"></span>
                    </span>
                    <span class="text-primary-400 font-bold text-[10px] uppercase tracking-[0.3em]">Processing Draw
                        Sequence</span>
                </div>

                <div class="flex justify-center items-center gap-3 mb-10">
                    <template x-for="(digit, index) in digits.split('')" :key="index">
                        <div
                            class="w-14 h-20 bg-gradient-to-b from-gray-800 to-gray-900 rounded-xl border border-white/5 flex items-center justify-center shadow-lg">
                            <span class="text-5xl font-black font-mono text-white tracking-tighter" x-text="digit"></span>
                        </div>
                    </template>
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
                    <p class="text-white/80 mb-6 text-lg">The ticket winner is <span
                            class="text-white font-mono bg-white/20 px-2 py-1 rounded">#{{ $winner['lucky_number'] }}</span>
                    </p>
                    <p class="text-white/80 mb-6 text-lg">The ticket range <span
                            class="text-white font-mono bg-white/20 px-2 py-1 rounded">#{{ $winner['winning_number'] }}</span>
                        belongs to:</p>

                    <div class="space-y-1">
                        <div class="text-5xl font-black tracking-tight leading-tight">
                            {{ \App\Utils\MaskHelper::name($winner['participant']->participant_name) }}
                        </div>
                        <div class="flex flex-wrap justify-center md:justify-start gap-4 mt-4">
                            <div class="bg-black/20 px-3 py-1 rounded-lg backdrop-blur-sm border border-white/10">
                                <span class="text-white/60 text-xs font-bold uppercase block">CIF Number</span>
                                <span
                                    class="font-mono text-lg">{{ \App\Utils\MaskHelper::mask($winner['participant']->participant_cif) }}</span>
                            </div>
                            <div class="bg-black/20 px-3 py-1 rounded-lg backdrop-blur-sm border border-white/10">
                                <span class="text-white/60 text-xs font-bold uppercase block">Account</span>
                                <span
                                    class="font-mono text-lg">{{ \App\Utils\MaskHelper::mask($winner['participant']->participant_account_number) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($isPreview && $enableRedraw)
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