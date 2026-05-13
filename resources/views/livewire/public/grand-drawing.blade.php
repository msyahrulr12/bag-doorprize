<div class="relative min-h-screen flex flex-col items-center justify-center p-4 md:p-8" wire:key="grand-draw-{{ $eventPrize->id }}-{{ $this->paginatedWinners->total() }}-{{ $isPreview ? 'preview' : 'confirmed' }}" x-data="{
        drawing: @entangle('isDrawing'),
        isStopping: @entangle('isStopping'),
        isReadyToReveal: @entangle('isReadyToReveal'),
        showWinner: false,
        number: 'XXXXXXXXX',
        placeholders: [],
        stopRequested: false,
        checkStopInterval: null,
        counter: null,
        triggerWin() {
            this.showWinner = true;
            confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#2d7a8e', '#FFFFFF', '#64748b']
            });
        },
        init() {
            const names = @js($randomData['names'] ?? []);
            const branches = @js($randomData['branches'] ?? []);
            const total = @js($totalDataToProcess);

            if (this.counter) clearInterval(this.counter);
            this.counter = setInterval(() => {
                if (this.drawing && !this.isStopping) {
                    const fallbackNames = names.length > 0 ? names : ['Wibowo', 'Sari', 'Pratama', 'Lestari', 'Budi', 'Putri'];
                    const fallbackBranches = branches.length > 0 ? branches : ['JAKARTA', 'SURABAYA', 'BANDUNG', 'MEDAN'];
                    const count = total > 0 ? total : 1;
                    const items = Array.from({length: count}).map(() => ({
                        name: fallbackNames[Math.floor(Math.random() * fallbackNames.length)] + ' *** ' + fallbackNames[Math.floor(Math.random() * fallbackNames.length)],
                        branch: fallbackBranches[Math.floor(Math.random() * fallbackBranches.length)],
                        lucky_number: Math.floor(Math.random() * 99999999).toString().padStart(9, '0')
                    }));
                    this.placeholders = [
                        items.filter((_, i) => i % 3 === 0),
                        items.filter((_, i) => i % 3 === 1),
                        items.filter((_, i) => i % 3 === 2),
                    ];
                    if (this.placeholders[0] && this.placeholders[0][0]) {
                        this.number = this.placeholders[0][0].lucky_number;
                    }
                }
            }, 80);
        },
        start() {
            this.stopRequested = false;
            
            if (this.checkStopInterval) clearInterval(this.checkStopInterval);
            this.checkStopInterval = setInterval(() => {
                // Only allow finish if user requested stop AND the background job is done (ReadyToReveal)
                if (this.stopRequested && this.isReadyToReveal) {
                    this.finish();
                }
            }, 100);
        },
        stop() {
            if (!this.drawing) return;
            this.stopRequested = true;
            $wire.stopDrawing(); // Signal backend to cancel if still processing
        },
        finish() {
            clearInterval(this.checkStopInterval);
            this.drawing = false;
            $wire.finishDrawing();
        },
        syncScroll(e) {
            const scrollAmount = e.target.scrollTop;
            const tables = [this.$refs['table-0'], this.$refs['table-1'], this.$refs['table-2']];
            tables.forEach((table, index) => {
                if (table != e.target) {
                    table.scrollTop = scrollAmount;
                }
            });
        }
    }"
    x-on:trigger-animation.window="start()"
    x-on:winner-confirmed.window="showWinner = false; number = 'XXXXXXXXX';">

    @if($isDrawing && $batchId)
        <div wire:poll.1500ms="checkBatchStatus"></div>
    @endif

    <!-- Background Section -->
    @if($eventPrize->event->public_draw_background)
        <div class="fixed inset-0 z-0">
            <img src="{{ Storage::url($eventPrize->event->public_draw_background) }}" class="w-full h-full">
            <div class="absolute inset-0"></div>
        </div>
    @else
        <!-- Abstract Background -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-[#2d7a8e]/10 rounded-full blur-[120px]"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-[#2d7a8e]/5 rounded-full blur-[160px]"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-blue-50 rounded-full blur-[120px]"></div>
        </div>
    @endif

    <!-- Header Section -->
    <div class="relative z-10 text-center mb-5">
        @if($eventPrize->prize->prize_image)
            <div class="mb-8 relative inline-block">
                <div class="absolute inset-0 bg-[#2d7a8e]/20 rounded-[2rem] blur-2xl transform rotate-3"></div>
                <img src="{{ Storage::url($eventPrize->prize->prize_image) }}" alt="{{ $eventPrize->prize->prize_name }}"
                    class="relative z-10 w-10 h-10 md:w-60 md:h-60 object-contain rounded-full shadow-2xl border-4 border-white/50 backdrop-blur-sm transform transition-transform hover:scale-105 duration-500">
            </div>
        @endif

        <div class="relative">
            <h1 class="text-4xl md:text-4xl font-black tracking-tighter text-slate-900 mb-2 drop-shadow-sm">
                {{ $eventPrize->prize->prize_name }}
            </h1>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="relative z-10 w-full max-w-xl">
        @if (!$winner && empty($winners))
            <!-- Initial State -->
            <div class="bg-white border border-slate-200 rounded-[2.5rem] p-12 md:p-20 shadow-2xl shadow-[#2d7a8e]/10 overflow-hidden flex flex-col items-center relative">
                <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
                    style="background-image: radial-gradient(#2d7a8e 1px, transparent 1px); background-size: 20px 20px;"></div>

                <div class="relative mb-12 flex items-center justify-center">
                    <div class="absolute inset-0 bg-[#2d7a8e]/20 rounded-full blur-3xl animate-pulse"></div>
                    <div class="bg-white h-32 w-32 rounded-full border-4 border-[#2d7a8e]/20 flex items-center justify-center shadow-inner relative z-10">
                        <x-heroicon-o-trophy class="w-16 h-16 text-[#2d7a8e]" />
                    </div>
                </div>

                <div class="text-center mb-10">
                    <h2 class="text-3xl font-black text-slate-900 mb-4 uppercase">Ready to Draw</h2>
                    <p class="text-slate-500 max-w-md mx-auto">System will draw
                        <b>{{ $totalDataToProcess }}</b> of <b>{{ $eventPrize->remaining_quantity }}</b> winner(s) this round.
                    </p>
                </div>

                <button 
                    wire:click="startDrawing"
                    wire:loading.attr="disabled"
                    class="group relative px-16 py-6 bg-[#2d7a8e] hover:bg-[#256678] text-white font-black text-2xl rounded-2xl shadow-[0_10px_40px_rgba(45,122,142,0.3)] transition-all hover:scale-105 active:scale-95 disabled:opacity-50 disabled:pointer-events-none"
                    @disabled($isDrawing)>
                    <span wire:loading.remove class="flex items-center gap-3">
                        <x-heroicon-s-bolt class="w-8 h-8" />
                        START DRAWING
                    </span>
                    <span wire:loading class="flex items-center gap-3">
                        <svg class="animate-spin h-8 w-8" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        PREPARING...
                    </span>
                </button>
            </div>
        @else
            <!-- Winners Found State -->
            <div x-init="triggerWin()"
                class="bg-white border border-slate-200 rounded-[2.5rem] p-6 md:p-10 shadow-2xl shadow-[#2d7a8e]/10 relative">
                <div class="flex flex-col md:flex-row items-center justify-between mb-8 gap-4 bg-gradient-to-r from-slate-50 to-white p-6 rounded-3xl border border-slate-100">
                    <div class="flex items-center gap-4">
                        <div class="bg-[#2d7a8e] p-3 rounded-2xl text-white shadow-lg">
                            <x-heroicon-o-trophy class="w-8 h-8" />
                        </div>
                        <div>
                            <h3 class="text-2xl font-black text-slate-900 uppercase">
                                {{ $isPreview ? 'Winners Detected' : 'Confirmed Winners' }}
                            </h3>
                            <p class="text-slate-500 font-medium">
                                @if ($isPreview)
                                    {{ count($pendingWinners) }} winner(s) staged for review
                                @else
                                    {{ $this->paginatedWinners->total() }} winners recorded
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto pb-2 md:pb-0">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($eventPrize && $this->availableQuantity > 0)
                                <button wire:click="startDrawing"
                                    class="flex-1 md:flex-none px-10 py-4 bg-[#2d7a8e] text-white rounded-xl font-black text-sm uppercase tracking-wider hover:bg-[#256678] transition-all shadow-lg hover:-translate-y-0.5 flex items-center justify-center gap-2">
                                    <x-heroicon-s-bolt class="w-5 h-5" />
                                    DRAW AGAIN
                                </button>
                            @endif

                            @if($drawSessionId && (!empty($winners) || $this->paginatedWinners->total() > 0))
                                <button wire:click="resetWinners"
                                    onclick="return confirm('Are you sure you want to reset and redraw current results? Information will be restored to remaining quantity.')"
                                    class="flex-1 md:flex-none px-10 py-4 bg-red-50 text-red-600 rounded-xl font-black text-sm uppercase tracking-wider hover:bg-red-100 transition-all text-center border border-red-100 whitespace-nowrap">
                                    RESET & REDRAW
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($isDrawing)
        <!-- Loading State with Infinite Shuffling Animation -->
        <div class="bg-white mt-10 border border-slate-200 rounded-[2.5rem] p-10 md:p-16 shadow-2xl shadow-[#2d7a8e]/10 flex flex-col items-center relative overflow-hidden w-full max-w-[100rem]">

            <!-- Inner Pattern -->
            <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
                style="background-image: radial-gradient(#2d7a8e 1px, transparent 1px); background-size: 20px 20px;"></div>

            <!-- Scrolling Lucky Number -->
            <div class="mb-10 relative">
                <div class="absolute inset-0 bg-[#2d7a8e]/20 rounded-full blur-3xl animate-pulse"></div>
                <div class="relative z-10 flex gap-2 md:gap-3 justify-center scale-75 md:scale-100">
                    <template x-for="(digit, index) in (placeholders[0]?.lucky_number || '000000000').split('')" :key="index">
                        <div class="w-10 h-16 md:w-14 md:h-20 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center shadow-sm">
                            <span class="text-3xl md:text-5xl font-black font-mono text-[#2d7a8e]" x-text="digit"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Title -->
            <div class="relative z-10 text-center uppercase tracking-tighter mb-8">
                <h2 class="text-3xl md:text-4xl font-black text-slate-900 mb-2 uppercase text-center">
                    {{ $isStopping ? 'Finalizing Results' : 'System is Drawing' }}
                </h2>
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-[#2d7a8e]/10 rounded-full border border-[#2d7a8e]/20">
                    <span class="animate-ping w-1.5 h-1.5 bg-[#2d7a8e] rounded-full"></span>
                    <span class="text-[10px] font-black text-[#2d7a8e] uppercase tracking-widest">
                        Real-time Weighted Randomization
                    </span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="relative z-10 w-full max-w-3xl mb-8">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <x-filament::loading-indicator class="w-4 h-4 text-[#2d7a8e]" />
                        <span class="text-[10px] font-black uppercase text-[#2d7a8e] tracking-widest">
                            {{ $processedCount }} / {{ $totalToProcess }} WINNERS PREPARED
                        </span>
                    </div>
                    <span
                        class="text-[10px] font-black uppercase text-slate-400 tracking-widest">{{ round($processPercentage) }}%</span>
                </div>
                <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden shadow-inner flex">
                    <div class="h-full bg-gradient-to-r from-[#2d7a8e] to-[#256678] transition-all duration-500 rounded-full"
                        style="width: {{ $processPercentage }}%"></div>
                </div>
            </div>

            <!-- Placeholder Tables -->
            <div class="relative z-10 w-full max-w-10xl">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8 w-full">
                    <template x-for="(chunk, cIndex) in placeholders" :key="cIndex">
                        <div class="overflow-hidden border border-slate-100 rounded-3xl shadow-sm bg-white opacity-50">
                            <div class="max-h-[50vh] overflow-y-auto sync-scroll scrollbar-hide" :x-ref="'table-' + cIndex" @scroll="syncScroll">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 bg-slate-50 z-10">
                                        <tr>
                                            <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">Winner Info</th>
                                            <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">Ticket</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <template x-for="(p, i) in chunk" :key="i">
                                            <tr class="hover:bg-slate-50/50 transition-colors">
                                                <td class="px-2 py-1">
                                                    <div class="flex flex-col">
                                                        <span class="text-[8px] font-black text-slate-800" x-text="p.name"></span>
                                                        <span class="text-[8px] font-bold text-slate-500 uppercase tracking-tight" x-text="p.branch"></span>
                                                    </div>
                                                </td>
                                                <td class="px-2 py-1 text-center">
                                                    <span class="inline-block px-2 py-1 bg-[#2d7a8e]/5 text-[#2d7a8e] rounded-lg font-black font-mono text-[8px] border border-[#2d7a8e]/10 shadow-sm leading-none" x-text="p.lucky_number"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Stop Button -->
            <div class="relative z-10 mt-4">
                <button x-on:click="stop()"
                    class="group relative px-16 py-5 bg-red-600 text-white rounded-2xl text-xl font-black uppercase tracking-widest shadow-[0_10px_40px_rgba(220,38,38,0.4)] hover:bg-red-700 transition-all hover:scale-105 active:scale-95 flex items-center gap-4">
                    <x-heroicon-s-bolt class="w-7 h-7 group-hover:animate-bounce" />
                    STOP DRAWING
                </button>
                <p class="text-center text-[10px] text-slate-400 uppercase tracking-widest font-black mt-3 animate-pulse">
                    Click to stop and reveal winners
                </p>
            </div>
        </div>
    @elseif (!empty($winners))
        <div class="relative z-10 w-full max-w-[100rem] mt-10">
            <!-- Winners Found State -->
            <div x-init="triggerWin()"
                class="bg-white border border-slate-200 rounded-[2.5rem] p-6 md:p-10 shadow-2xl shadow-[#2d7a8e]/10 relative">
                <div class="flex flex-col md:flex-row items-center justify-between mb-8 gap-4 bg-gradient-to-r from-slate-50 to-white p-6 rounded-3xl border border-slate-100">
                    <div class="flex items-center gap-4">
                        <div class="bg-[#2d7a8e] p-3 rounded-2xl text-white shadow-lg">
                            <x-heroicon-o-trophy class="w-8 h-8" />
                        </div>
                        <div>
                            <h3 class="text-2xl font-black text-slate-900 uppercase">
                                {{ $isPreview ? 'Winners Detected' : 'Confirmed Winners' }}
                            </h3>
                            <p class="text-slate-500 font-medium">
                                @if ($isPreview)
                                    {{ count($pendingWinners) }} winner(s) detected for review
                                @else
                                    {{ $this->paginatedWinners->total() }} winners recorded
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto overflow-x-auto pb-2 md:pb-0">
                        <div class="flex space-x-2 mr-2">
                            <button wire:click="exportCsv" wire:loading.attr="disabled"
                                class="flex items-center gap-2 px-3 py-2 bg-green-50 text-green-700 rounded-lg text-[10px] font-black uppercase hover:bg-green-100 transition-colors border border-green-100 shadow-sm relative overflow-hidden group">
                                <x-heroicon-o-document-text class="w-3.5 h-3.5 group-hover:scale-110 transition-transform" wire:loading.remove wire:target="exportCsv" />
                                <x-filament::loading-indicator class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="exportCsv" />
                                <span wire:loading.remove wire:target="exportCsv">CSV</span>
                                <span wire:loading wire:target="exportCsv">...</span>
                            </button>
                            <button wire:click="exportExcel" wire:loading.attr="disabled"
                                class="flex items-center gap-2 px-3 py-2 bg-emerald-50 text-emerald-700 rounded-lg text-[10px] font-black uppercase hover:bg-emerald-100 transition-colors border border-emerald-100 shadow-sm relative overflow-hidden group">
                                <x-heroicon-o-table-cells class="w-3.5 h-3.5 group-hover:scale-110 transition-transform" wire:loading.remove wire:target="exportExcel" />
                                <x-filament::loading-indicator class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="exportExcel" />
                                <span wire:loading.remove wire:target="exportExcel">EXCEL</span>
                                <span wire:loading wire:target="exportExcel">...</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Winners Table Split into 3 columns -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    @foreach($winners as $key => $winnerChunk)
                        <div class="overflow-hidden border border-slate-100 rounded-3xl shadow-sm bg-white">
                            <div 
                                class="max-h-[50vh] overflow-y-auto sync-scroll scrollbar-hide" 
                                x-ref="table-{{ $key }}"
                                @scroll="syncScroll">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 bg-slate-50 z-10">
                                        <tr>
                                            <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">Winner Info</th>
                                            <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">Ticket</th>
                                            <!-- <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">Branch</th> -->
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        @foreach($winnerChunk as $w)
                                            <tr class="hover:bg-slate-50/50 transition-colors">
                                                <td class="px-2 py-1">
                                                    <div class="flex flex-col">
                                                        <span class="text-[8px] font-black text-slate-800">{{ $w['name'] ?? ($w['participant']['participant_name'] ?? 'N/A') }}</span>
                                                        <span class="text-[8px] font-bold text-slate-500 uppercase tracking-tight">
                                                        {{ $w['branch_name'] ?? ($w['account']['branch']['branch_name'] ?? 'N/A') }}
                                                    </span>
                                                    </div>
                                                </td>
                                                <td class="px-2 py-1 text-center">
                                                    <span class="inline-block px-2 py-1 bg-[#2d7a8e]/5 text-[#2d7a8e] rounded-lg font-black font-mono text-[8px] border border-[#2d7a8e]/10 shadow-sm leading-none">
                                                        {{ $w['lucky_number'] ?? ($w['winning_number'] ?? 'N/A') }}
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
        </div>
    @endif

    <!-- Footer / Remaining -->
    <div class="relative z-10 mt-12 flex flex-wrap justify-center gap-8 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
        <div class="flex items-center gap-2 px-6 py-2 bg-white/50 backdrop-blur rounded-full border border-slate-100 shadow-sm">
            <span class="text-[#2d7a8e]">{{ $eventPrize->remaining_quantity }}</span>
            <span>Items Remaining</span>
        </div>
        <div class="flex items-center gap-2 px-6 py-2 bg-white/50 backdrop-blur rounded-full border border-slate-100 shadow-sm">
            <span class="text-[#2d7a8e]">{{ number_format($eventPrize->min_points_required) }}</span>
            <span>Min Points Req</span>
        </div>
        <div class="flex items-center gap-2 px-6 py-2 bg-white text-[#2d7a8e] rounded-full border border-[#2d7a8e]/20 shadow-sm font-black">
            <span>{{ strtoupper($eventPrize->prize->tier) }}</span>
        </div>
    </div>

    <style>
        @keyframes bounce-subtle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        .animate-bounce-subtle { animation: bounce-subtle 4s ease-in-out infinite; }

        @keyframes fade-in-up {
            0% { opacity: 0; transform: translateY(8px); }
            100% { opacity: 0.6; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fade-in-up 0.3s ease forwards; }

        .scrollbar-thin::-webkit-scrollbar { width: 6px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
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
                customClass: { title: 'font-black tracking-tight', popup: 'rounded-[2rem]' }
            });
            confetti({ particleCount: 200, spread: 120, origin: { y: 0.6 }, colors: ['#2d7a8e', '#facc15', '#6366f1'] });
        });
        window.addEventListener('error', event => {
            Swal.fire({
                title: 'ERROR',
                text: event.detail.message,
                icon: 'error',
                confirmButtonColor: '#ef4444',
                background: '#ffffff',
                customClass: { title: 'font-black tracking-tight', popup: 'rounded-[2rem]' }
            });
        });
    </script>
</div>