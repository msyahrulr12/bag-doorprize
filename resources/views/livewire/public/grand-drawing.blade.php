<div class="relative min-h-screen flex flex-col items-center justify-center p-4 md:p-8" x-data="{ 
        drawing: @entangle('isDrawing'), 
        showWinner: false,
        number: 'XXXXXXXXX',
        counter: null,
        triggerWin() {
            this.showWinner = true;
            confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#2d7a8e', '#FFFFFF', '#64748b']
            });
        }
     }" x-on:trigger-animation.window="
        drawing = true;
        showWinner = false;
        number = 'XXXXXXXXX';
        
        $wire.performDraw().then(() => {
            let index = 0;
            const candidates = $wire.get('candidates');
            const pendingWinner = $wire.get('pendingWinner');
            
            if (!pendingWinner || !candidates) {
                drawing = false;
                return;
            }

            const finalLuckyNumber = pendingWinner.lucky_number;
            
            counter = setInterval(() => {
                if (candidates.length > 0) {
                    number = candidates[index % candidates.length];
                    index++;
                }
            }, 80);

            setTimeout(() => {
                clearInterval(counter);
                number = finalLuckyNumber;
                drawing = false;
                $wire.finishDrawing();
            }, 3000);
        });
     " x-on:winner-confirmed.window="showWinner = false; number = 'XXXXXXXXX';">
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
                class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#2d7a8e]/5 border border-[#2d7a8e]/10 backdrop-blur-md mb-6 animate-bounce-subtle">
                <span class="relative flex h-2 w-2">
                    <span
                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#2d7a8e] opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-[#2d7a8e]"></span>
                </span>
                <span
                    class="text-xs font-bold tracking-widest uppercase text-[#2d7a8e]">{{ $eventPrize->event->event_name }}</span>
            </div>
            <h1 class="text-5xl md:text-7xl font-black tracking-tighter text-slate-900 mb-4">
                {{ $eventPrize->prize->prize_name }}
            </h1>
            <p class="text-lg text-slate-500 font-medium">Grand Drawing Session</p>
        </div>
    </div>

    <!-- Main Board -->
    <div
        class="relative z-10 w-full max-w-4xl bg-white border border-slate-200 rounded-[2.5rem] p-8 md:p-16 shadow-2xl shadow-[#2d7a8e]/10 overflow-hidden">
        <!-- Inner Pattern -->
        <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
            style="background-image: radial-gradient(#2d7a8e 1px, transparent 1px); background-size: 20px 20px;"></div>

        <div class="relative flex flex-col items-center gap-12">
            <!-- Lottery Number Display -->
            <div class="flex gap-2 md:gap-3 justify-center">
                <template x-for="(digit, index) in number.split('')" :key="index">
                    <div
                        class="w-10 h-16 md:w-16 md:h-24 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center shadow-sm">
                        <span
                            class="text-4xl md:text-6xl font-black font-mono transition-all duration-75 text-[#2d7a8e]"
                            x-text="digit"></span>
                    </div>
                </template>
            </div>

            <!-- Drawing Logic -->
            <div class="w-full flex flex-col items-center gap-6">
                @if(!$winner)
                    <button wire:click="startDrawing" wire:loading.attr="disabled"
                        class="group relative px-12 py-5 bg-[#2d7a8e] hover:bg-[#256678] text-white font-black text-xl rounded-2xl shadow-[0_10px_40px_rgba(45,122,142,0.3)] transition-all hover:scale-105 active:scale-95 disabled:opacity-50 disabled:pointer-events-none">
                        <span x-show="!drawing" class="flex items-center gap-2">
                            <x-heroicon-s-bolt class="w-6 h-6" />
                            START DRAWING
                        </span>
                        <span x-show="drawing" class="flex items-center gap-2">
                            <svg class="animate-spin h-6 w-6" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"
                                    fill="none"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            GENERATING...
                        </span>
                    </button>
                @else
                    <div x-init="triggerWin()"
                        class="w-full max-w-2xl bg-gradient-to-br from-[#2d7a8e] to-[#256678] rounded-3xl p-8 shadow-2xl shadow-[#2d7a8e]/40 text-white flex flex-col md:flex-row items-center gap-8 animate-winner-card">
                        <div class="bg-white/20 p-4 rounded-2xl border border-white/20">
                            <x-heroicon-o-trophy class="w-16 h-16 text-white" />
                        </div>
                        <div class="flex-1 text-center md:text-left">
                            <div class="text-xs font-black uppercase tracking-widest mb-1 opacity-80">Lucky Winner Detected
                            </div>
                            <h2 class="text-4xl font-black mb-2">
                                {{ \App\Utils\MaskHelper::name($winner['participant']['participant_name']) }}
                            </h2>
                            <div class="flex flex-wrap gap-2 text-sm mb-2">
                                <span
                                    class="bg-black/20 px-3 py-1 rounded-full font-mono">{{ \App\Utils\MaskHelper::mask($winner['participant']['participant_cif']) }}</span>
                                <span
                                    class="bg-black/20 px-3 py-1 rounded-full font-mono">{{ $winner['participant']['account']['branch']['branch_name'] ?? ($winner['branch_name'] ?? 'N/A') }}</span>
                            </div>
                            <div class="text-white font-bold">
                                Ticket: <span class="bg-white/20 px-2 py-0.5 rounded">{{ $winner['lucky_number'] }}</span>
                            </div>
                        </div>
                        @if($isPreview)
                            <div class="flex flex-col gap-2">
                                <button wire:click="confirmWinner"
                                    class="bg-white text-[#2d7a8e] px-6 py-3 rounded-xl font-black text-sm uppercase tracking-wider hover:bg-gray-100 transition-colors shadow-lg">
                                    CONFIRM
                                </button>
                                <button wire:click="startDrawing"
                                    class="bg-black/10 text-white px-6 py-3 rounded-xl font-black text-sm uppercase tracking-wider hover:bg-black/20 transition-colors border border-white/10 text-center">
                                    RETRY
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Winners Table Section -->
    <div class="relative z-10 w-full max-w-5xl mt-16">
        @if(!empty($winners))
            <div class="bg-white border border-slate-200 rounded-[2.5rem] p-6 md:p-10 shadow-2xl shadow-[#2d7a8e]/10">
                <div
                    class="flex flex-col md:flex-row items-center justify-between mb-8 gap-4 bg-gradient-to-r from-slate-50 to-white p-6 rounded-3xl border border-slate-100">
                    <div class="flex items-center gap-4">
                        <div class="bg-[#2d7a8e] p-3 rounded-2xl text-white shadow-lg">
                            <x-heroicon-o-trophy class="w-8 h-8" />
                        </div>
                        <div>
                            <h3 class="text-2xl font-black text-slate-900 uppercase">Confirmed Winners</h3>
                            <p class="text-slate-500 font-medium">{{ $this->paginatedWinners->total() }} winners recorded
                            </p>
                        </div>
                    </div>

                    @if(!$isPreview && $eventPrize->remaining_quantity > 0)
                        <button wire:click="startDrawing"
                            class="flex-1 md:flex-none px-10 py-4 bg-[#2d7a8e] text-white rounded-xl font-black text-sm uppercase tracking-wider hover:bg-[#256678] transition-all shadow-lg hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            <x-heroicon-s-bolt class="w-5 h-5" />
                            DRAW AGAIN
                        </button>
                    @endif
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
                                                            {{ \App\Utils\MaskHelper::mask($winner['account']['account_number'] ?? 'N/A') }}
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

    <!-- Footer / Remaining -->
    <div
        class="relative z-10 mt-12 flex flex-wrap justify-center gap-8 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
        <div
            class="flex items-center gap-2 px-6 py-2 bg-white/50 backdrop-blur rounded-full border border-slate-100 shadow-sm">
            <span class="text-[#2d7a8e]">{{ $eventPrize->remaining_quantity }}</span>
            <span>Items Remaining</span>
        </div>
        <div
            class="flex items-center gap-2 px-6 py-2 bg-white/50 backdrop-blur rounded-full border border-slate-100 shadow-sm">
            <span class="text-[#2d7a8e]">{{ number_format($eventPrize->min_points_required) }}</span>
            <span>Min Points Req</span>
        </div>
        <div
            class="flex items-center gap-2 px-6 py-2 bg-white text-[#2d7a8e] rounded-full border border-[#2d7a8e]/20 shadow-sm font-black">
            <span>{{ strtoupper($eventPrize->prize->tier) }}</span>
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

        @keyframes winner-card {
            0% {
                transform: scale(0.9) translateY(20px);
                opacity: 0;
            }

            100% {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        .animate-winner-card {
            animation: winner-card 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
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