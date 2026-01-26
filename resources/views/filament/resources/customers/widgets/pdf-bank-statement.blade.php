<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">
            {{-- Form for selecting month and year --}}
            <form wire:submit.prevent="generatePdf">
                {{ $this->form }}
            </form>

            {{-- Generate Button (only show if PDF doesn't exist) --}}
            @if (!$this->pdfExists)
                <br>
                <div class="flex items-center gap-4">
                    <x-filament::button wire:click="generatePdf" color="primary" icon="heroicon-o-document-arrow-down">
                        Generate PDF
                    </x-filament::button>
                    <span class="text-sm text-gray-500">
                        No PDF found for the selected period. Click to generate.
                    </span>
                </div>
            @endif

            {{-- PDF Preview and Download (only show if PDF exists) --}}
            @if ($this->pdfExists)
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-check-circle" class="w-5 h-5 text-success-500" />
                            <span class="text-sm font-medium text-success-600">
                                PDF Generated Successfully
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <x-filament::button wire:click="generatePdf" color="gray" size="sm"
                                icon="heroicon-o-arrow-path">
                                Regenerate
                            </x-filament::button>
                            <x-filament::button wire:click="downloadPdf" color="primary" size="sm"
                                icon="heroicon-o-arrow-down-tray">
                                Download PDF
                            </x-filament::button>
                        </div>
                    </div>

                    {{-- PDF Preview --}}
                    <div class="border rounded-lg overflow-hidden bg-gray-50 dark:bg-gray-900">
                        <iframe src="{{ $this->pdfUrl }}" class="w-full h-[600px]" frameborder="0"></iframe>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>