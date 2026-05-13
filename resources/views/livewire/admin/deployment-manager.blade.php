<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800">Deployment Manager</h2>
                        <p class="text-sm text-gray-500">Upload package and trigger production deployment</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Upload Section -->
                    <div class="lg:col-span-1">
                        <form wire:submit.prevent="deploy" class="space-y-6">
                            <div 
                                x-data="{ isDragging: false, uploading: false, progress: 0 }"
                                x-on:livewire-upload-start="uploading = true"
                                x-on:livewire-upload-finish="uploading = false"
                                x-on:livewire-upload-error="uploading = false"
                                x-on:livewire-upload-progress="progress = $event.detail.progress"
                                x-on:dragover.prevent="isDragging = true"
                                x-on:dragleave.prevent="isDragging = false"
                                x-on:drop.prevent="
                                    isDragging = false;
                                    const files = $event.dataTransfer.files;
                                    if (files.length > 0) {
                                        @this.upload('package', files[0]);
                                    }
                                "
                                :class="{ 'border-indigo-500 bg-indigo-50': isDragging, 'border-gray-300': !isDragging }"
                                class="relative border-2 border-dashed rounded-xl p-8 text-center transition-all duration-200 ease-in-out"
                            >
                                <input type="file" wire:model="package" id="package" class="hidden">
                                <label for="package" class="cursor-pointer block">
                                    <svg :class="{ 'text-indigo-500 scale-110': isDragging, 'text-gray-400': !isDragging }" class="w-12 h-12 mx-auto mb-4 transition-all duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700">
                                        {{ $package ? $package->getClientOriginalName() : 'Click or Drop .tar.gz here' }}
                                    </span>
                                </label>

                                <!-- Progress Bar Overlay -->
                                <div x-show="uploading" x-transition class="absolute inset-0 bg-white/90 rounded-xl flex flex-col items-center justify-center p-6">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                                        <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-300" :style="'width: ' + progress + '%'"></div>
                                    </div>
                                    <span class="text-xs font-bold text-indigo-600" x-text="progress + '% Uploading...'"></span>
                                </div>

                                @error('package') <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <button type="submit" 
                                    wire:loading.attr="disabled"
                                    class="w-full bg-indigo-600 text-white font-bold py-3 px-6 rounded-xl shadow-lg hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 {{ $isDeploying ? 'opacity-50 cursor-not-allowed' : '' }}">
                                <span wire:loading.remove wire:target="deploy">Start Deployment</span>
                                <span wire:loading wire:target="deploy">Processing...</span>
                                <svg wire:loading wire:target="deploy" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        </form>

                        <div class="mt-8 bg-yellow-50 rounded-xl p-5 border border-yellow-100">
                            <h4 class="text-sm font-bold text-yellow-800 mb-2 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                Important Pre-requisites
                            </h4>
                            <ul class="text-xs text-yellow-700 space-y-2 list-disc pl-4">
                                <li>The web user must have passwordless <code>sudo</code> access to <code>{{ base_path('deploy-main.sh') }}</code>.</li>
                                <li>The directory <code>/home/sysadmin/bagi-hoki-main/</code> must be writable by the web user.</li>
                                <li>The script must have execution permissions (<code>chmod +x</code>).</li>
                            </ul>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <div class="bg-gray-900 rounded-xl p-4 h-[400px] flex flex-col shadow-inner mb-8">
                            <div class="flex justify-between items-center mb-2 px-2">
                                <span class="text-xs font-mono text-gray-500 uppercase tracking-widest">Deployment Output</span>
                                <div class="flex gap-1">
                                    <div class="w-2 h-2 rounded-full bg-red-500"></div>
                                    <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                                    <div class="w-2 h-2 rounded-full bg-green-500"></div>
                                </div>
                            </div>
                            <div id="deployment-output" class="flex-1 overflow-y-auto font-mono text-sm text-green-400 p-2 whitespace-pre-wrap leading-relaxed">
                                {{ $output ?: 'Waiting for deployment to start...' }}
                            </div>
                        </div>

                        <!-- History Section -->
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Deployment History</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="bg-gray-50 text-gray-500 font-medium">
                                            <th class="px-6 py-3">Date</th>
                                            <th class="px-6 py-3">Package</th>
                                            <th class="px-6 py-3">User</th>
                                            <th class="px-6 py-3 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @forelse($deploymentHistory as $item)
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ $item['timestamp'] }}</td>
                                                <td class="px-6 py-4 font-mono text-xs text-gray-500">{{ $item['package'] }}</td>
                                                <td class="px-6 py-4 text-gray-600">{{ $item['user'] }}</td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $item['status'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                        {{ ucfirst($item['status']) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-6 py-10 text-center text-gray-400 italic">No deployment history found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('scroll-to-bottom', () => {
            const el = document.getElementById('deployment-output');
            el.scrollTop = el.scrollHeight;
        });
    </script>
</div>
