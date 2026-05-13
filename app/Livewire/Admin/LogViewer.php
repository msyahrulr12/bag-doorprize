<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\File;

class LogViewer extends Component
{
    public $logFile = 'laravel.log';
    public $lines = 100;
    public $search = '';
    public $autoRefresh = false;

    protected $queryString = ['logFile', 'lines'];

    public function getLogsProperty()
    {
        $path = storage_path("logs/{$this->logFile}");
        
        if (!File::exists($path)) {
            return "Log file not found at: {$path}";
        }

        // Use tail to get the last N lines efficiently
        $escapedPath = escapeshellarg($path);
        $command = "tail -n {$this->lines} {$escapedPath}";
        
        if ($this->search) {
            $escapedSearch = escapeshellarg($this->search);
            $command .= " | grep -i {$escapedSearch}";
        }

        $output = shell_exec($command);

        return $output ?: "No logs found or file is empty.";
    }

    public function clearLog()
    {
        $path = storage_path("logs/{$this->logFile}");
        if (File::exists($path)) {
            File::put($path, '');
            $this->dispatch('success', message: "Log {$this->logFile} cleared.");
        }
    }

    public function downloadLog()
    {
        $path = storage_path("logs/{$this->logFile}");
        if (File::exists($path)) {
            return response()->download($path);
        }
    }

    public function render()
    {
        return view('livewire.admin.log-viewer', [
            'logs' => $this->logs,
            'availableFiles' => $this->getAvailableFiles(),
        ])->layout('layouts.guest');
    }

    private function getAvailableFiles()
    {
        $files = File::files(storage_path('logs'));
        return collect($files)
            ->filter(fn($file) => $file->getExtension() === 'log')
            ->map(fn($file) => $file->getFilename())
            ->toArray();
    }
}
