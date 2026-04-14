<div class="relative min-h-screen flex flex-col items-center justify-center p-4 md:p-8" wire:key="bulk-draw-{{ $eventPrize->id }}-{{ $this->paginatedWinners->total() }}-{{ $isPreview ? 'preview' : 'confirmed' }}" x-data="{ 
        drawing: @entangle('isDrawing'), 
        isStopping: @entangle('isStopping'),
        isReadyToReveal: @entangle('isReadyToReveal'),
        showWinners: false,
        number: 'XXXXXXXXX',
        placeholders: [],
        stopRequested: false,
        checkStopInterval: null,
        counter: null,
        triggerWin() {
            this.showWinners = true;
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
                    const names = names.length > 0 ? names : ['Wibowo', 'Sari', 'Pratama', 'Lestari', 'Budi', 'Putri'];
                    const branches = branches.length > 0 ? branches : ['JAKARTA', 'SURABAYA', 'BANDUNG', 'MEDAN'];
                    const count = total > 0 ? total : 1;
                    this.placeholders = Array.from({length: count}).map(() => ({
                        name: names[Math.floor(Math.random() * names.length)] + ' *** ' + names[Math.floor(Math.random() * names.length)],
                        branch: branches[Math.floor(Math.random() * branches.length)],
                        lucky_number: Math.floor(Math.random() * 99999999).toString().padStart(8, '0')
                    }));
                    if (this.placeholders[0]) {
                        this.number = this.placeholders[0].lucky_number;
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
            <div
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-[#2d7a8e]/5 rounded-full blur-[160px]">
            </div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-blue-50 rounded-full blur-[120px]"></div>
        </div>
    @endif

    <!-- Header Section -->
    <div class="relative z-10 text-center mb-12">
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

                <button wire:click="startDrawing" wire:loading.attr="disabled"
                    class="group relative px-16 py-6 bg-[#2d7a8e] hover:bg-[#256678] text-white font-black text-2xl rounded-2xl shadow-[0_10px_40px_rgba(45,122,142,0.3)] transition-all hover:scale-105 active:scale-95 disabled:opacity-50 disabled:pointer-events-none">
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

    <div class="relative z-10 w-full max-w-2xl">
        @if(empty($winners))
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
                        <b>{{ $totalDataToProcess }}</b> of <b>{{ $eventPrize->remaining_quantity }}</b> winners at once based on availability.
                    </p>
                </div>

                @if (!$isDrawing)
                    <button wire:click="startDraw" wire:loading.attr="disabled"
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
                @endif
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

                    <div class="flex items-center gap-3 w-full md:w-auto overflow-x-auto pb-2 md:pb-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <button wire:click="startDrawing"
                                class="flex-1 md:flex-none px-14 py-4 bg-[#2d7a8e] text-white rounded-xl font-black text-sm uppercase tracking-wider hover:bg-[#256678] transition-all shadow-lg hover:-translate-y-0.5 whitespace-nowrap">
                                DRAW AGAIN
                            </button>
                            <button wire:click="resetWinners"
                                onclick="return confirm('Are you sure you want to reset and redraw?')"
                                class="flex-1 md:flex-none px-10 py-4 bg-red-50 text-red-600 rounded-xl font-black text-sm uppercase tracking-wider hover:bg-red-100 transition-all text-center border border-red-100 whitespace-nowrap">
                                RESET & REDRAW
                            </button>
                        </div>               
                    </div>
                </div>

                @if($isSingleDrawingMode && !empty($winners))
                    @php $singleWinner = collect($winners)->flatten(1)->first(); @endphp
                    @if($singleWinner)
                        <!-- Grand Style Result for Single Winner -->
                        <div
                            class="w-full max-w-2xl mx-auto bg-gradient-to-br from-[#2d7a8e] to-[#256678] rounded-[2.5rem] p-10 shadow-2xl text-white text-center animate-winner-card overflow-hidden relative">
                            <div class="absolute inset-0 opacity-10 pointer-events-none"
                                style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 20px 20px;">
                            </div>
                            <div class="relative z-10 flex flex-col items-center">
                                <div
                                    class="mb-6 inline-flex p-5 bg-white/20 rounded-[2rem] backdrop-blur-md border border-white/30 shadow-inner">
                                    <x-heroicon-o-gift class="w-16 h-16" />
                                </div>
                                <div class="text-[10px] font-black uppercase tracking-[0.4em] text-white/60 mb-2">Congratulations
                                </div>
                                <h2 class="text-4xl md:text-6xl font-black mb-4 tracking-tighter drop-shadow-lg">
                                    {{ $singleWinner['name'] }}
                                </h2>
                                <div class="text-xl font-bold opacity-80 mb-10 uppercase tracking-widest">
                                    {{ $singleWinner['branch_name'] }}
                                </div>

                                <div
                                    class="flex flex-col items-center bg-black/20 px-10 py-6 rounded-3xl backdrop-blur-lg border border-white/10 shadow-2xl">
                                    <span class="text-xs font-black opacity-60 uppercase tracking-[0.3em] mb-4">Winning Ticket
                                        Number</span>
                                    <div class="flex gap-2">
                                        @foreach(str_split($singleWinner['lucky_number'] ?? $singleWinner['winning_number']) as $d)
                                            <div
                                                class="w-10 h-14 md:w-12 md:h-16 bg-white/10 rounded-xl border border-white/20 flex items-center justify-center">
                                                <span class="text-2xl md:text-4xl font-black font-mono tracking-tight">{{ $d }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </div>

    <!-- Main Content Area -->
    <div class="relative z-10 w-full min-w-10xl mt-10">
        @if ($isDrawing)
            <!-- Loading State with Infinite Shuffling Animation -->
            <div wire:poll.1500ms="checkBatchStatus"
                class="bg-white border border-slate-200 rounded-[2.5rem] p-10 md:p-16 shadow-2xl shadow-[#2d7a8e]/10 flex flex-col items-center relative overflow-hidden">

                <!-- Inner Pattern -->
                <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
                    style="background-image: radial-gradient(#2d7a8e 1px, transparent 1px); background-size: 20px 20px;">
                </div>

                <div class="mb-10 relative">
                    <div class="absolute inset-0 bg-[#2d7a8e]/20 rounded-full blur-3xl animate-pulse"></div>
                    <div class="relative z-10 flex gap-2 md:gap-3 justify-center scale-75 md:scale-100">
                        <template x-for="(digit, index) in (placeholders[0]?.lucky_number || '00000000').split('')"
                            :key="index">
                            <div
                                class="w-10 h-16 md:w-14 md:h-20 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center shadow-sm">
                                <span class="text-3xl md:text-5xl font-black font-mono text-[#2d7a8e]"
                                    x-text="digit"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="relative z-10 text-center uppercase tracking-tighter mb-8">
                    <h2 class="text-3xl md:text-4xl font-black text-slate-900 mb-2 uppercase text-center">
                        {{ $isStopping ? 'Finalizing Results' : 'System is Drawing' }}
                    </h2>
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 bg-[#2d7a8e]/10 rounded-full border border-[#2d7a8e]/20">
                        <span class="animate-ping w-1.5 h-1.5 bg-[#2d7a8e] rounded-full"></span>
                        <span class="text-[10px] font-black text-[#2d7a8e] uppercase tracking-widest">
                            {{ $isStopping ? 'Wrapping up the drawing sequence' : 'Real-time Weighted Randomization' }}
                        </span>
                    </div>
                </div>

                <div class="relative z-10 w-full max-w-3xl">
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
                    <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden shadow-inner mb-8">
                        <div class="h-full bg-gradient-to-r from-[#2d7a8e] to-[#256678] transition-all duration-500 rounded-full"
                            style="width: {{ $processPercentage }}%"></div>
                    </div>

                    <!-- Placeholder Animation -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-8">
                        <template x-for="(p, i) in placeholders" :key="i">
                            <div
                                class="bg-white border border-slate-100 rounded-2xl p-3 flex items-center gap-3 shadow-sm opacity-60 animate-fade-in-up">
                                <div
                                    class="w-10 h-10 bg-slate-50 rounded-lg flex items-center justify-center shrink-0 border border-slate-100">
                                    <x-heroicon-o-sparkles class="w-5 h-5 text-[#2d7a8e]/40" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5 truncate"
                                        x-text="p.branch"></div>
                                    <h4 class="font-black text-slate-600 text-[10px] truncate" x-text="p.name"></h4>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-xs font-black font-mono text-[#2d7a8e] tracking-tighter"
                                        x-text="p.lucky_number"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                @if (!$isStopping)
                    <div class="relative z-10 mt-4">
                        <button x-on:click="stop()"
                            class="group relative px-16 py-5 bg-red-600 text-white rounded-2xl text-xl font-black uppercase tracking-widest shadow-[0_10px_40px_rgba(220,38,38,0.4)] hover:bg-red-700 transition-all hover:scale-105 active:scale-95 flex items-center gap-4">
                            <x-heroicon-s-bolt class="w-7 h-7 group-hover:animate-bounce" />
                            STOP DRAWING
                        </button>
                    </div>
                @else
                    <div class="relative z-10 mt-4 flex items-center gap-3 text-slate-400 font-black uppercase text-xs animate-pulse">
                         <x-filament::loading-indicator class="w-4 h-4" />
                         Finalizing...
                    </div>
                @endif
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
                        <b>{{ $totalDataToProcess }}</b> of <b>{{ $eventPrize->remaining_quantity }}</b> winners at once based on availability.
                    </p>
                </div>

                <button wire:click="startDraw" wire:loading.attr="disabled"
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

                    <div class="flex items-center gap-3 w-full md:w-auto overflow-x-auto pb-2 md:pb-0">
                        <div class="flex space-x-2 mr-2">
                            <button wire:click="exportCsv"
                                class="group flex items-center gap-2 px-3 py-2 bg-green-50 text-green-700 rounded-lg text-[10px] font-black uppercase hover:bg-green-100 transition-all active:scale-95 border border-green-100 shadow-sm">
                                <x-heroicon-o-document-text class="w-3.5 h-3.5" />
                                CSV
                            </button>
                            <button wire:click="exportExcel"
                                class="group flex items-center gap-2 px-3 py-2 bg-emerald-50 text-emerald-700 rounded-lg text-[10px] font-black uppercase hover:bg-emerald-100 transition-all active:scale-95 border border-emerald-100 shadow-sm">
                                <x-heroicon-o-table-cells class="w-3.5 h-3.5" />
                                EXCEL
                            </button>
                        </div>
                    </div>
                </div>

                @if($isSingleDrawingMode && !empty($winners))
                    @php $singleWinner = collect($winners)->flatten(1)->first(); @endphp
                    @if($singleWinner)
                        <!-- Grand Style Result for Single Winner -->
                        <div
                            class="w-full max-w-2xl mx-auto bg-gradient-to-br from-[#2d7a8e] to-[#256678] rounded-[2.5rem] p-10 shadow-2xl text-white text-center animate-winner-card overflow-hidden relative">
                            <div class="absolute inset-0 opacity-10 pointer-events-none"
                                style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 20px 20px;">
                            </div>
                            <div class="relative z-10 flex flex-col items-center">
                                <div
                                    class="mb-6 inline-flex p-5 bg-white/20 rounded-[2rem] backdrop-blur-md border border-white/30 shadow-inner">
                                    <x-heroicon-o-gift class="w-16 h-16" />
                                </div>
                                <div class="text-[10px] font-black uppercase tracking-[0.4em] text-white/60 mb-2">Congratulations
                                </div>
                                <h2 class="text-4xl md:text-6xl font-black mb-4 tracking-tighter drop-shadow-lg">
                                    {{ $singleWinner['name'] }}
                                </h2>
                                <div class="text-xl font-bold opacity-80 mb-10 uppercase tracking-widest">
                                    {{ $singleWinner['branch_name'] }}
                                </div>

                                <div
                                    class="flex flex-col items-center bg-black/20 px-10 py-6 rounded-3xl backdrop-blur-lg border border-white/10 shadow-2xl">
                                    <span class="text-xs font-black opacity-60 uppercase tracking-[0.3em] mb-4">Winning Ticket
                                        Number</span>
                                    <div class="flex gap-2">
                                        @foreach(str_split($singleWinner['lucky_number'] ?? $singleWinner['winning_number']) as $d)
                                            <div
                                                class="w-10 h-14 md:w-12 md:h-16 bg-white/10 rounded-xl border border-white/20 flex items-center justify-center">
                                                <span class="text-2xl md:text-4xl font-black font-mono tracking-tight">{{ $d }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    <!-- Winners Table Split into 3 columns -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        @foreach($winners as $chunkIndex => $winnerChunk)
                            <div class="overflow-hidden border border-slate-100 rounded-3xl shadow-sm bg-white">
                                <div class="max_h-[50vh] overflow-y-auto sync-scroll scrollbar-thin">
                                    <table 
                                        class="w-full text-left border-collapse"
                                        x-ref="table-{{ $chunkIndex }}"
                                        @scroll="syncScroll"
                                        >
                                        <thead class="sticky top-0 bg-slate-50 z-10">
                                            <tr>
                                                <th
                                                    class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                    Winner Info</th>
                                                <th
                                                    class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">
                                                    Ticket</th>
                                                <!-- <th
                                                    class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                    Branch</th> -->
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            @foreach($winnerChunk as $winner)
                                                <tr class="hover:bg-slate-50/50 transition-colors animate-fade-in-up">
                                                    <td class="px-2 py-1">
                                                        <div class="flex flex-col">
                                                            <span class="text-[8px] font-black text-slate-800">{{ isset($winner['name']) ? $winner['name'] : $winner['participant']['participant_name'] }}</span>
                                                            <span
                                                            class="text-[8px] font-bold text-slate-500 uppercase tracking-tight">
                                                            {{ $winner['account']['branch']['branch_name'] ?? ($winner['branch_name'] ?? 'N/A') }}
                                                        </span>
                                                        </div>
                                                    </td>
                                                    <td class="px-2 py-1 text-center">
                                                        <div class="flex flex-col items-center">
                                                            <span
                                                                class="inline-block px-2 py-1 bg-[#2d7a8e]/5 text-[#2d7a8e] rounded-lg font-black font-mono text-[8px] border border-[#2d7a8e]/10 shadow-sm leading-none mb-1">
                                                                {{ $winner['lucky_number'] }}
                                                            </span>

                                                        </div>
                                                    </td>
                                                    <!-- <td class="px-4 py-4">
                                                        <span
                                                            class="text-[10px] font-bold text-slate-500 uppercase tracking-tight text-xs">
                                                            {{ $winner['account']['branch']['branch_name'] ?? ($winner['branch_name'] ?? 'N/A') }}
                                                        </span>
                                                    </td> -->
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

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