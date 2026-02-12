<div class="relative min-h-screen flex flex-col items-center justify-center p-4 md:p-8" x-data="{ 
        drawing: @entangle('isDrawing'), 
        showWinners: false,
        triggerWin() {
            this.showWinners = true;
            confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#2d7a8e', '#FFFFFF', '#64748b']
            });
        }
     }" x-on:winner-confirmed.window="showWinners = false;">
    <!-- Abstract Background -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-[#2d7a8e]/10 rounded-full blur-[120px]"></div>
        <div
            class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-[#2d7a8e]/5 rounded-full blur-[160px]">
        </div>
        <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-blue-50 rounded-full blur-[120px]"></div>
    </div>

    <!-- Header Section -->
    <div class="relative z-10 text-center mb-12">
        @if($eventPrize->prize->prize_image)
            <div class="mb-8 relative inline-block">
                <div class="absolute inset-0 bg-[#2d7a8e]/20 rounded-[2rem] blur-2xl transform rotate-3"></div>
                <img src="{{ Storage::url($eventPrize->prize->prize_image) }}" alt="{{ $eventPrize->prize->prize_name }}"
                    class="relative z-10 w-64 h-64 md:w-80 md:h-80 object-contain rounded-[2rem] shadow-2xl border-4 border-white/50 backdrop-blur-sm transform transition-transform hover:scale-105 duration-500">
            </div>
        @endif

        <div class="relative">
            <div
                class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#2d7a8e]/5 border border-[#2d7a8e]/10 backdrop-blur-md mb-4 animate-bounce-subtle">
                <span class="relative flex h-2 w-2">
                    <span
                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#2d7a8e] opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-[#2d7a8e]"></span>
                </span>
                <span
                    class="text-xs font-bold tracking-widest uppercase text-[#2d7a8e]">{{ $eventPrize->event->event_name }}</span>
            </div>
            <h1 class="text-4xl md:text-6xl font-black tracking-tighter text-slate-900 mb-2 drop-shadow-sm">
                {{ $eventPrize->prize->prize_name }}
            </h1>
            <p class="text-lg text-slate-500 font-medium italic opacity-75">Bulk Drawing Session</p>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="relative z-10 w-full max-w-5xl">
        @if ($batchId)
            <!-- Loading State -->
            <div wire:poll.2000ms="checkBatchStatus"
                class="bg-white border border-slate-200 rounded-[2.5rem] p-12 md:p-20 shadow-2xl shadow-[#2d7a8e]/10 flex flex-col items-center">
                <div class="mb-8 relative">
                    <div class="absolute inset-0 bg-[#2d7a8e]/20 rounded-full blur-2xl animate-pulse"></div>
                    <svg class="animate-spin h-20 w-20 text-[#2d7a8e] relative z-10" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                </div>

                <h2 class="text-3xl font-black text-slate-900 mb-2 uppercase text-center">
                    {{ $isStopping || $stopTriggeredAt ? 'Finalizing Results' : 'Picking Lucky Winners' }}
                </h2>
                <p class="text-slate-500 mb-8 font-medium">
                    {{ $isStopping || $stopTriggeredAt ? 'Please wait, we are finishing the last few winners...' : "Please wait, we are selecting $totalToProcess winners for you." }}
                </p>

                <div class="w-full max-w-md">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black uppercase text-[#2d7a8e] tracking-widest">{{ $processedCount }}
                            OF {{ $totalToProcess }} READY</span>
                        <span
                            class="text-[10px] font-black uppercase text-slate-400 tracking-widest">{{ round($processPercentage) }}%</span>
                    </div>
                    <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden shadow-inner">
                        <div class="h-full bg-gradient-to-r from-[#2d7a8e] to-[#256678] transition-all duration-500 rounded-full"
                            style="width: {{ $processPercentage }}%"></div>
                    </div>
                </div>

                @if (!$isStopping)
                    <div class="mt-8">
                        <button wire:click="cancelBatch" :disabled="$processPercentage < 100"
                            class="px-6 py-2 bg-red-50 text-red-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-red-100 hover:bg-red-100 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                            STOP DRAWING
                        </button>
                    </div>
                @endif

                <div class="mt-12 flex items-center gap-2 px-4 py-2 bg-slate-50 rounded-full border border-slate-100">
                    <span class="animate-pulse w-2 h-2 bg-[#2d7a8e] rounded-full"></span>
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        {{ $isStopping || $stopTriggeredAt ? 'Wrapping up the drawing sequence' : 'Randomizing algorithms in progress' }}
                    </span>
                </div>
            </div>
        @elseif(empty($winners))
            <!-- Initial State / Drawing Area -->
            <div
                class="bg-white border border-slate-200 rounded-[2.5rem] p-12 md:p-20 shadow-2xl shadow-[#2d7a8e]/10 overflow-hidden flex flex-col items-center">
                <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
                    style="background-image: radial-gradient(#2d7a8e 1px, transparent 1px); background-size: 20px 20px;">
                </div>

                <div class="relative mb-12 flex items-center justify-center">
                    <div class="absolute inset-0 bg-[#2d7a8e]/20 rounded-full blur-3xl animate-pulse"></div>
                    <div
                        class="bg-white h-32 w-32 rounded-full border-4 border-[#2d7a8e]/20 flex items-center justify-center shadow-inner relative z-10">
                        <x-heroicon-o-users class="w-16 h-16 text-[#2d7a8e]" />
                    </div>
                </div>

                <div class="text-center mb-10">
                    <h2 class="text-3xl font-black text-slate-900 mb-4 uppercase">Ready to batch drawing</h2>
                    <p class="text-slate-500 max-w-md mx-auto">System will generate
                        <b>{{ $eventPrize->remaining_quantity }}</b> winners at once based on availability.
                    </p>
                </div>

                <button wire:click="draw" wire:loading.attr="disabled"
                    class="group relative px-16 py-6 bg-[#2d7a8e] hover:bg-[#256678] text-white font-black text-2xl rounded-2xl shadow-[0_10px_40px_rgba(45,122,142,0.3)] transition-all hover:scale-105 active:scale-95 disabled:opacity-50 disabled:pointer-events-none">
                    <span wire:loading.remove class="flex items-center gap-3">
                        <x-heroicon-s-bolt class="w-8 h-8" />
                        GENERATE WINNERS
                    </span>
                    <span wire:loading class="flex items-center gap-3">
                        <svg class="animate-spin h-8 w-8" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"
                                fill="none"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        GENERATING BATCH...
                    </span>
                </button>
            </div>
        @else
            <!-- Winners Found State -->
            <div x-init="triggerWin()"
                class="bg-white border border-slate-200 rounded-[2.5rem] p-6 md:p-10 shadow-2xl shadow-[#2d7a8e]/10 relative">
                <div
                    class="flex flex-col md:flex-row items-center justify-between mb-8 gap-4 bg-gradient-to-r from-slate-50 to-white p-6 rounded-3xl border border-slate-100">
                    <div class="flex items-center gap-4">
                        <div class="bg-[#2d7a8e] p-3 rounded-2xl text-white shadow-lg">
                            <x-heroicon-o-check-badge class="w-8 h-8" />
                        </div>
                        <div>
                            <h3 class="text-2xl font-black text-slate-900 uppercase">
                                {{ $isPreview ? 'Batch Winners Detected' : 'Confirmed Winners' }}
                            </h3>
                            <p class="text-slate-500 font-medium">
                                {{ $isPreview ? 'Successfully generated ' . $totalWinners . ' winners' : ($this->paginatedWinners->total() . ' winners recorded') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto">
                        @if ($isPreview && !$alreadyConfirmed)
                            <button wire:click="confirmWinner"
                                class="flex-1 md:flex-none px-10 py-4 bg-[#2d7a8e] text-white rounded-xl font-black text-sm uppercase tracking-wider hover:bg-[#256678] transition-all shadow-lg hover:-translate-y-0.5">
                                CONFIRM ALL
                            </button>
                            <button wire:click="draw"
                                class="flex-1 md:flex-none px-8 py-4 bg-slate-100 text-slate-600 rounded-xl font-black text-sm uppercase tracking-wider hover:bg-slate-200 transition-all text-center">
                                RE-DRAW
                            </button>
                        @elseif(!$isPreview && $eventPrize->remaining_quantity > 0)
                            <button wire:click="draw"
                                class="flex-1 md:flex-none px-10 py-4 bg-[#2d7a8e] text-white rounded-xl font-black text-sm uppercase tracking-wider hover:bg-[#256678] transition-all shadow-lg hover:-translate-y-0.5 flex items-center justify-center gap-2">
                                <x-heroicon-s-bolt class="w-5 h-5" />
                                DRAW AGAIN
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Winners Table Split into 2 columns -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @foreach($winners as $winnerChunk)
                        <div class="overflow-hidden border border-slate-100 rounded-3xl shadow-sm bg-white">
                            <div class="max-h-[50vh] overflow-y-auto scrollbar-thin">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 bg-slate-50 z-10">
                                        <tr>
                                            <th
                                                class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                Winner Info</th>
                                            <th
                                                class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">
                                                Ticket</th>
                                            <th
                                                class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                Branch</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        @foreach($winnerChunk as $winner)
                                            <tr class="hover:bg-slate-50/50 transition-colors animate-fade-in-up">
                                                <td class="px-4 py-4">
                                                    <div class="flex flex-col">
                                                        <span
                                                            class="text-sm font-black text-slate-800">{{ \App\Utils\MaskHelper::name($winner['name']) }}</span>
                                                        <span class="text-[10px] font-mono text-slate-400 tracking-tighter">
                                                            {{ \App\Utils\MaskHelper::mask($winner['cif']) }} •
                                                            {{ \App\Utils\MaskHelper::mask($winner['account']['account_number'] ?? $winner['branch_name'] ?? 'N/A') }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-center">
                                                    <div class="flex flex-col items-center">
                                                        <span
                                                            class="inline-block px-2 py-1 bg-[#2d7a8e]/5 text-[#2d7a8e] rounded-lg font-black font-mono text-sm border border-[#2d7a8e]/10 shadow-sm leading-none mb-1">
                                                            {{ $winner['lucky_number'] }}
                                                        </span>
                                                        <span
                                                            class="text-[9px] font-mono text-slate-300 tracking-tighter">{{ $winner['winning_number'] }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <span
                                                        class="text-[10px] font-bold text-slate-500 uppercase tracking-tight line-clamp-1">
                                                        {{ $winner['account']['branch']['branch_name'] ?? ($winner['branch_name'] ?? 'N/A') }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if(!$isPreview && $this->paginatedWinners->total() > 0)
                    <div class="mt-8 p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        {{ $this->paginatedWinners->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Footer Stats -->
    <div
        class="relative z-10 mt-12 flex flex-wrap justify-center gap-8 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
        <div class="flex items-center gap-2 px-6 py-2 bg-white/50 backdrop-blur rounded-full border border-slate-100">
            <span class="text-[#2d7a8e]">{{ $eventPrize->remaining_quantity }}</span>
            <span>Items Remaining</span>
        </div>
        <div class="flex items-center gap-2 px-6 py-2 bg-white/50 backdrop-blur rounded-full border border-slate-100">
            <span class="text-[#2d7a8e]">{{ number_format($eventPrize->min_points_required) }}</span>
            <span>Min Points Req</span>
        </div>
        <div class="flex items-center gap-2 px-6 py-2 bg-white/50 backdrop-blur rounded-full border border-slate-100">
            <span class="text-slate-800">{{ strtoupper($eventPrize->prize->tier) }}</span>
        </div>
    </div>

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
            animation: bounce-subtle 4s ease-in-out infinite;
        }

        @keyframes fade-in-up {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fade-in-up 0.4s ease-out forwards;
        }

        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }

        .scrollbar-thin::-webkit-scrollbar-track {
            background: transparent;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.addEventListener('success', event => {
            Swal.fire({
                title: 'SUCCESS',
                text: event.detail.message,
                icon: 'success',
                confirmButtonColor: '#2d7a8e',
                timer: 3000,
                timerProgressBar: true,
                background: '#ffffff',
                customClass: {
                    title: 'font-black tracking-tight',
                    popup: 'rounded-[2rem]'
                }
            });

            // Celebration!
            confetti({
                particleCount: 200,
                spread: 120,
                origin: { y: 0.6 },
                colors: ['#2d7a8e', '#facc15', '#6366f1']
            });
        });

        window.addEventListener('info', event => {
            Swal.fire({
                title: 'INFO',
                text: event.detail.message,
                icon: 'info',
                confirmButtonColor: '#2d7a8e',
                background: '#ffffff',
                customClass: {
                    title: 'font-black tracking-tight',
                    popup: 'rounded-[2rem]'
                }
            });
        });

        window.addEventListener('error', event => {
            Swal.fire({
                title: 'ERROR',
                text: event.detail.message,
                icon: 'error',
                confirmButtonColor: '#ef4444',
                background: '#ffffff',
                customClass: {
                    title: 'font-black tracking-tight',
                    popup: 'rounded-[2rem]'
                }
            });
        });
    </script>
</div>