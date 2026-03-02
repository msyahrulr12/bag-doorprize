<x-filament-panels::page>
    <div class="flex flex-col gap-y-4 max-w-xl mx-auto items-center justify-center min-h-[50vh]">
        <div class="w-full bg-white p-8 rounded-xl shadow-xl border border-gray-100 dark:bg-gray-900">
            <h2 class="text-2xl font-bold mb-6 text-center text-primary-500">Change Password</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 text-center">
                For security reasons, you are required to change your password before continuing.
            </p>
            <form wire:submit="updatePassword">
                {{ $this->form }}

                <div class="mt-8 flex justify-center">
                    <x-filament::button type="submit" size="lg" class="w-full">
                        Update Password
                    </x-filament::button>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>