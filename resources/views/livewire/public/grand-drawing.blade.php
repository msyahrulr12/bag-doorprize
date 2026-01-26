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
            const winnerData = $wire.get('winner');
            
            if (!winnerData || !candidates) {
                drawing = false;
                return;
            }

            const finalLuckyNumber = winnerData.lucky_number;
            
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
            }, 3000);
        });
     " x-on:winner-confirmed.window="showWinner = false; number = 'WAITING';">
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
                            <h2 class="text-4xl font-black mb-2">{{ $winner['participant']['participant_name'] }}</h2>
                            <div class="flex flex-wrap gap-2 text-sm mb-2">
                                <span
                                    class="bg-black/20 px-3 py-1 rounded-full font-mono">{{ $winner['participant']['participant_cif'] }}</span>
                                <span
                                    class="bg-black/20 px-3 py-1 rounded-full font-mono">{{ $winner['participant']['account']['branch']['region'] ?? 'N/A' }}</span>
                            </div>
                            <div class="text-white font-bold">
                                Ticket: <span class="bg-white/20 px-2 py-0.5 rounded">{{ $winner['lucky_number'] }}</span>
                            </div>
                        </div>
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
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Footer / Remaining -->
    <div class="relative z-10 mt-8 flex gap-8 text-sm font-bold uppercase tracking-widest text-slate-400">
        <div class="flex items-center gap-2">
            <span class="text-[#2d7a8e]">{{ $eventPrize->remaining_quantity }}</span>
            <span>Items Remaining</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[#2d7a8e]">{{ number_format($eventPrize->min_points_required) }}</span>
            <span>Min Points Req</span>
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
    </style>

    <script>
        window.addEventListener('error', event => {
            alert(event.detail.message);
        });
    </script>
</div>