<div class="relative min-h-screen flex flex-col items-center justify-center p-4 md:p-8" x-data="{ 
        drawing: @entangle('isDrawing'), 
        showWinner: false,
        number: 'XXXXXXXXX',
        placeholderName: 'SEARCHING...',
        placeholderBranch: 'RANDOMIZING...',
        counter: null,
        stopRequested: false,
        checkStopInterval: null,
        isReady: false,
        triggerWin() {
            this.showWinner = true;
            confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#2d7a8e', '#FFFFFF', '#64748b']
            });
        },
        start() {
            this.drawing = true;
            this.showWinner = false;
            this.number = '000000000';
            this.stopRequested = false;
            this.isReady = false;
            
            const names = ['Wibowo', 'Sari', 'Pratama', 'Lestari', 'Budi', 'Putri', 'Hidayat', 'Santoso', 'Kurniawan', 'Mulyani'];
            const branches = ['JAKARTA', 'SURABAYA', 'BANDUNG', 'MEDAN', 'MAKASSAR', 'SEMARANG', 'PALEMBANG'];

            if (this.counter) clearInterval(this.counter);
            this.counter = setInterval(() => {
                let randomNum = '';
                const chars = '0123456789';
                for (let i = 0; i < 9; i++) {
                    randomNum += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                this.number = randomNum;
                this.placeholderName = names[Math.floor(Math.random() * names.length)] + ' *** ' + names[Math.floor(Math.random() * names.length)];
                this.placeholderBranch = branches[Math.floor(Math.random() * branches.length)];
            }, 50);

            $wire.performDraw().then(() => {
                this.isReady = true;
            }).catch(err => {
                this.finish();
                console.error('Drawing error:', err);
            });

            if (this.checkStopInterval) clearInterval(this.checkStopInterval);
            this.checkStopInterval = setInterval(() => {
                if (this.stopRequested && this.isReady) {
                    this.finish();
                }
            }, 100);
        },
        stop() {
            this.stopRequested = true;
        },
        finish() {
            clearInterval(this.checkStopInterval);
            clearInterval(this.counter);
            
            const winner = $wire.get('pendingWinner');
            if (winner && winner.lucky_number) {
                this.number = winner.lucky_number;
            } else {
                this.number = 'XXXXXXXXX';
            }
            
            this.drawing = false;
            $wire.finishDrawing();
        }
     }" x-on:trigger-animation.window="start()"
    x-on:winner-confirmed.window="showWinner = false; number = 'XXXXXXXXX';">
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
    <div wire:ignore wire:key="public-drawing-{{ now()->timestamp }}"
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
                    <div x-show="drawing"
                        class="w-full max-w-2xl bg-slate-50 border-2 border-dashed border-slate-200 rounded-3xl p-8 flex flex-col md:flex-row items-center gap-8 animate-pulse">
                        <div class="bg-slate-200 p-4 rounded-2xl border border-white/20">
                            <x-heroicon-o-gift class="w-16 h-16 text-slate-400" />
                        </div>
                        <div class="flex-1 text-center md:text-left">
                            <div class="text-xs font-black uppercase tracking-widest mb-1 text-slate-400">Randomizing
                                Winners...</div>
                            <h2 class="text-4xl font-black mb-2 text-slate-300" x-text="placeholderName"></h2>
                            <div class="flex flex-wrap gap-2 text-sm mb-2">
                                <span class="bg-slate-200 px-3 py-1 rounded-full text-slate-400 font-mono"
                                    x-text="placeholderBranch"></span>
                                <span
                                    class="bg-slate-200 px-3 py-1 rounded-full text-slate-400 font-mono text-center">Checking
                                    Eligibility...</span>
                            </div>
                        </div>
                        <div class="flex flex-col items-center gap-4">
                            <button x-on:click="stop()"
                                class="group relative px-16 py-6 bg-gradient-to-br from-red-500 to-rose-700 text-white font-black text-2xl rounded-[2.5rem] shadow-[0_20px_60px_rgba(225,29,72,0.5)] transition-all transform hover:scale-110 active:scale-95 overflow-hidden ring-8 ring-rose-500/10">
                                <div
                                    class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-300">
                                </div>
                                <div
                                    class="absolute -inset-x-20 inset-y-0 bg-white/30 skew-x-12 -translate-x-full group-hover:translate-x-[200%] transition-transform duration-1000 ease-in-out">
                                </div>
                                <span class="relative flex items-center gap-3">
                                    <div class="relative">
                                        <x-heroicon-s-bolt class="w-8 h-8" />
                                        <div class="absolute inset-0 bg-white rounded-full blur animate-ping opacity-50">
                                        </div>
                                    </div>
                                    STOP & REVEAL
                                </span>
                            </button>
                            <div
                                class="flex items-center gap-2 text-rose-600 animate-pulse bg-rose-50 px-4 py-1.5 rounded-full border border-rose-100 shadow-sm">
                                <span class="block w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                                <span class="text-[10px] font-black uppercase tracking-[0.3em] font-sans">Awaiting Manual
                                    Stop</span>
                            </div>
                        </div>
                    </div>

                    <button x-show="!drawing" wire:click="startDrawing" wire:loading.attr="disabled"
                        class="group relative px-12 py-5 bg-[#2d7a8e] hover:bg-[#256678] text-white font-black text-xl rounded-2xl shadow-[0_10px_40px_rgba(45,122,142,0.3)] transition-all hover:scale-105 active:scale-95 disabled:opacity-50 disabled:pointer-events-none">
                        <span class="flex items-center gap-2">
                            <x-heroicon-s-bolt class="w-6 h-6" />
                            START DRAWING
                        </span>
                    </button>
                @else
                    <div x-init="triggerWin()"
                        class="w-full max-w-2xl bg-gradient-to-br from-[#2d7a8e] to-[#256678] rounded-3xl p-8 shadow-2xl shadow-[#2d7a8e]/40 text-white flex flex-col md:flex-row items-center gap-8 animate-winner-card">
                        <div class="bg-white/20 p-4 rounded-2xl border border-white/20">
                            <x-heroicon-o-trophy class="w-16 h-16 text-white" />
                        </div>
                        <div class="flex-1 text-center md:text-left">
                            <div class="flex items-center justify-center md:justify-start gap-2 mb-4">
                                <div
                                    class="px-4 py-1 bg-yellow-400 text-[#2d7a8e] rounded-full text-[10px] font-black uppercase tracking-[0.2em] shadow-lg shadow-yellow-400/20">
                                    {{ count($pendingWinners) > 1 ? 'Multi-Winner Reveal' : 'Winner Detected' }}
                                </div>
                                @if(count($pendingWinners) <= 1)
                                    <div
                                        class="px-4 py-1 bg-white/10 backdrop-blur-md rounded-full text-[10px] font-black uppercase tracking-[0.2em] border border-white/20">
                                        {{ $winner['branch_name'] ?? 'N/A' }}
                                    </div>
                                @endif
                            </div>

                            @if(count($pendingWinners) <= 1)
                                <h2
                                    class="text-6xl font-black mb-4 tracking-tighter leading-tight text-white drop-shadow-xl animate-in slide-in-from-left duration-700">
                                    {{ $winner['participant']['participant_name'] }}
                                </h2>
                                <div class="flex items-center gap-3 justify-center md:justify-start">
                                    <div
                                        class="bg-black/20 flex items-center px-4 py-2 rounded-xl backdrop-blur-md border border-white/10">
                                        <span class="text-yellow-400 font-mono font-black text-2xl mr-2">#</span>
                                        <span
                                            class="text-2xl font-mono font-black tracking-widest">{{ $winner['lucky_number'] }}</span>
                                    </div>
                                </div>
                            @else
                                <div
                                    class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[350px] overflow-y-auto pr-3 custom-scrollbar animate-in fade-in duration-1000">
                                    @foreach($pendingWinners as $idx => $w)
                                        <div
                                            class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10 flex flex-col gap-1 hover:bg-white/20 transition-all group relative overflow-hidden">
                                            <div
                                                class="absolute -right-2 -top-2 opacity-[0.05] group-hover:opacity-[0.1] transition-opacity">
                                                <x-heroicon-o-trophy class="w-16 h-16 text-white" />
                                            </div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <span
                                                    class="text-[8px] font-black bg-yellow-400 text-[#2d7a8e] px-1.5 py-0.5 rounded shadow-sm">{{ $idx + 1 }}</span>
                                                <span
                                                    class="text-[9px] font-bold text-white/50 uppercase tracking-widest font-mono">CIF:
                                                    {{ $w['cif'] }}</span>
                                            </div>
                                            <div
                                                class="text-sm font-black text-white truncate group-hover:text-yellow-200 transition-colors uppercase">
                                                {{ $w['participant']['participant_name'] }}</div>
                                            <div class="flex items-center justify-between mt-2 pt-2 border-t border-white/5">
                                                <span
                                                    class="text-[9px] font-bold text-white/40 uppercase truncate max-w-[100px]">{{ $w['branch_name'] }}</span>
                                                <span
                                                    class="text-xs font-black font-mono text-yellow-300 drop-shadow-[0_0_10px_rgba(253,224,71,0.3)]">{{ $w['lucky_number'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <style>
                                    .custom-scrollbar::-webkit-scrollbar {
                                        width: 5px;
                                    }

                                    .custom-scrollbar::-webkit-scrollbar-track {
                                        background: rgba(255, 255, 255, 0.05);
                                        border-radius: 10px;
                                    }

                                    .custom-scrollbar::-webkit-scrollbar-thumb {
                                        background: rgba(255, 255, 255, 0.2);
                                        border-radius: 10px;
                                    }
                                </style>
                            @endif
                        </div>
                        @if($isPreview)
                            <div class="flex flex-col gap-3 min-w-[180px]">
                                <button wire:click="confirmWinners"
                                    class="group/btn relative bg-yellow-400 text-[#2d7a8e] px-8 py-4 rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-yellow-300 transition-all shadow-[0_10px_30px_rgba(250,204,21,0.3)] hover:-translate-y-1 active:translate-y-0 flex items-center justify-center gap-2">
                                    <x-heroicon-s-check-circle class="w-5 h-5" />
                                    CONFIRM ALL
                                </button>
                                <button wire:click="resetWinners"
                                    onclick="return confirm('Are you sure you want to reset and redraw?')"
                                    class="bg-white/10 text-white/80 px-8 py-3 rounded-2xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-white/20 transition-all border border-white/10 hover:text-white flex items-center justify-center gap-2">
                                    <x-heroicon-s-arrow-path class="w-4 h-4" />
                                    RESET & REDRAW
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

                    <div class="flex items-center gap-3 w-full md:w-auto overflow-x-auto pb-2 md:pb-0">
                        <div class="flex space-x-2 mr-2">
                            <button wire:click="exportCsv"
                                class="flex items-center gap-2 px-3 py-2 bg-green-50 text-green-700 rounded-lg text-[10px] font-black uppercase hover:bg-green-100 transition-colors border border-green-100 shadow-sm">
                                <x-heroicon-o-document-text class="w-3.5 h-3.5" />
                                CSV
                            </button>
                            <button wire:click="exportExcel"
                                class="flex items-center gap-2 px-3 py-2 bg-emerald-50 text-emerald-700 rounded-lg text-[10px] font-black uppercase hover:bg-emerald-100 transition-colors border border-emerald-100 shadow-sm">
                                <x-heroicon-o-table-cells class="w-3.5 h-3.5" />
                                EXCEL
                            </button>
                        </div>

                        @if(!$isPreview && $eventPrize->remaining_quantity > 0)
                            <button wire:click="startDrawing"
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
                                                        <span class="text-sm font-black text-slate-800">{{ $winner['name'] }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-center">
                                                    <div class="flex flex-col items-center">
                                                        <span
                                                            class="inline-block px-2 py-1 bg-[#2d7a8e]/5 text-[#2d7a8e] rounded-lg font-black font-mono text-sm border border-[#2d7a8e]/10 shadow-sm leading-none mb-1">
                                                            {{ $winner['lucky_number'] }}
                                                        </span>
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