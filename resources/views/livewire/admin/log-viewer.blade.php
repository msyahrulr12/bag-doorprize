<div class="py-12" wire:poll.5s @if(!$autoRefresh) wire:poll.stop @endif>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <h2 class="text-2xl font-semibold text-gray-800">System Log Viewer</h2>
                    
                    <div class="flex flex-wrap items-center gap-3">
                        <select wire:model.live="logFile" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            @foreach($availableFiles as $file)
                                <option value="{{ $file }}">{{ $file }}</option>
                            @endforeach
                        </select>

                        <select wire:model.live="lines" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <option value="100">Last 100 lines</option>
                            <option value="500">Last 500 lines</option>
                            <option value="1000">Last 1000 lines</option>
                            <option value="5000">Last 5000 lines</option>
                        </select>

                        <button wire:click="clearLog" 
                                onclick="confirm('Are you sure you want to clear this log? This action cannot be undone.') || event.stopImmediatePropagation()"
                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring ring-red-300 disabled:opacity-25 transition ease-in-out duration-150">
                            Clear
                        </button>

                        <button wire:click="downloadLog" 
                                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                            Download
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <input type="text" wire:model.live.debounce.500ms="search" placeholder="Search in logs..." 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.live="autoRefresh" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-offset-0 focus:ring-indigo-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-600">Auto Refresh (5s)</span>
                        </label>
                    </div>
                </div>

                <div class="relative">
                    <div class="bg-gray-900 rounded-lg p-4 font-mono text-sm text-green-400 overflow-auto max-h-[600px] whitespace-pre-wrap">
                        {{ $logs }}
                    </div>
                    
                    <div class="mt-2 text-xs text-gray-500 flex justify-between">
                        <span>Viewing: {{ $logFile }}</span>
                        <span>Lines: {{ $lines }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
