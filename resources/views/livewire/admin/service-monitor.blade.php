<div class="py-12" wire:poll.10s="refreshStatus">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800">Service Monitor</h2>
                        <p class="text-sm text-gray-500">Real-time status of system components</p>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-gray-400">Last updated: {{ $lastUpdate }}</span>
                        <button wire:click="refreshStatus" class="p-2 text-gray-600 hover:text-indigo-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($services as $key => $service)
                        <div class="border rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow" wire:key="service-{{ $key }}">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg {{ $service['status'] === 'running' ? 'bg-green-100 text-green-600' : ($service['status'] === 'error' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600') }}">
                                        @if($key === 'database' || $key === 't24_database')
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7zm0 5h16m-16 5h16M4 7h16"></path></svg>
                                        @elseif($key === 'octane')
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                        @elseif($key === 'queue')
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                        @else
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path></svg>
                                        @endif
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-900">{{ $service['name'] }}</h3>
                                        <div class="flex items-center gap-1">
                                            <div class="w-2 h-2 rounded-full {{ $service['status'] === 'running' ? 'bg-green-500' : ($service['status'] === 'error' ? 'bg-red-500' : 'bg-yellow-500') }}"></div>
                                            <span class="text-xs uppercase font-bold {{ $service['status'] === 'running' ? 'text-green-600' : ($service['status'] === 'error' ? 'text-red-600' : 'text-yellow-600') }}">
                                                {{ $service['status'] }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                @if($service['can_restart'])
                                    <div class="flex gap-2">
                                        @if($key === 'octane')
                                            <button wire:click="restartOctane" wire:loading.attr="disabled" class="text-xs bg-indigo-50 text-indigo-600 px-2 py-1 rounded hover:bg-indigo-100 transition-colors disabled:opacity-50">
                                                <span wire:loading.remove wire:target="restartOctane">Reload</span>
                                                <span wire:loading wire:target="restartOctane">Reloading...</span>
                                            </button>
                                        @elseif($key === 'queue')
                                            <button wire:click="restartQueue" wire:loading.attr="disabled" class="text-xs bg-indigo-50 text-indigo-600 px-2 py-1 rounded hover:bg-indigo-100 transition-colors disabled:opacity-50">
                                                <span wire:loading.remove wire:target="restartQueue">Restart</span>
                                                <span wire:loading wire:target="restartQueue">Restarting...</span>
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs font-mono text-gray-600 break-words leading-relaxed overflow-hidden" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                                    {{ $service['details'] }}
                                </p>
                            </div>

                            @if($key === 'supervisor' && !empty($service['processes']))
                                <div class="mt-4 space-y-2 border-t pt-4">
                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Managed Processes</h4>
                                    @foreach($service['processes'] as $proc)
                                        <div class="flex items-center justify-between bg-white border rounded-lg p-2 text-sm shadow-sm">
                                            <div class="flex items-center gap-2 overflow-hidden">
                                                <div class="w-1.5 h-1.5 rounded-full {{ in_array($proc['status'], ['running', 'starting']) ? 'bg-green-500' : 'bg-red-500' }}"></div>
                                                <span class="font-medium text-gray-700 truncate" title="{{ $proc['name'] }}">{{ $proc['name'] }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] text-gray-400 hidden sm:inline">{{ $proc['uptime'] }}</span>
                                                <div class="flex gap-1">
                                                    @if($proc['status'] === 'running')
                                                        <button wire:click="stopSupervisorProcess('{{ $proc['name'] }}')" class="p-1 text-gray-400 hover:text-red-500 transition-colors" title="Stop">
                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z" clip-rule="evenodd"></path></svg>
                                                        </button>
                                                    @else
                                                        <button wire:click="startSupervisorProcess('{{ $proc['name'] }}')" class="p-1 text-gray-400 hover:text-green-500 transition-colors" title="Start">
                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>
                                                        </button>
                                                    @endif
                                                    <button wire:click="restartSupervisorProcess('{{ $proc['name'] }}')" class="p-1 text-gray-400 hover:text-indigo-500 transition-colors" title="Restart">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-12 bg-blue-50 rounded-xl p-6 border border-blue-100">
                    <div class="flex gap-4">
                        <div class="text-blue-500 mt-1">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div>
                            <h4 class="font-semibold text-blue-900">Process Management Note</h4>
                            <p class="text-sm text-blue-700 mt-1">
                                For system-level services like Nginx or PostgreSQL, restarting from this interface requires `sudo` permissions without a password for the web user. Currently, only application-level reloads (Octane, Queue Workers) are enabled for direct execution.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
