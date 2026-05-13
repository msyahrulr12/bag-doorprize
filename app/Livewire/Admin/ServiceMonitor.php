<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ServiceMonitor extends Component
{
    public $services = [];
    public $lastUpdate;

    public function mount()
    {
        $this->refreshStatus();
    }

    public function refreshStatus()
    {
        $this->services = [
            'octane' => $this->checkOctane(),
            'database' => $this->checkDatabase(),
            't24_database' => $this->checkT24Database(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'minio' => $this->checkMinio(),
            'supervisor' => $this->checkSupervisor(),
        ];
        $this->lastUpdate = now()->format('H:i:s');
    }

    private function checkSupervisor()
    {
        $hasSupervisor = shell_exec("which supervisorctl");
        if (!$hasSupervisor) {
            return [
                'name' => 'Supervisor',
                'status' => 'stopped',
                'details' => 'supervisorctl command not found on this system.',
                'can_restart' => false,
                'processes' => [],
            ];
        }

        $output = shell_exec("supervisorctl status");
        if (str_contains($output, 'unix:///var/run/supervisor.sock no such file') || str_contains($output, 'error')) {
            return [
                'name' => 'Supervisor',
                'status' => 'stopped',
                'details' => trim($output) ?: 'Supervisor service is not running or socket is missing.',
                'can_restart' => false,
                'processes' => [],
            ];
        }

        $processes = [];
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            // Format usually: process_name  STATUS  uptime...
            $parts = preg_split('/\s+/', $line, 3);
            $processes[] = [
                'name' => $parts[0] ?? 'Unknown',
                'status' => strtolower($parts[1] ?? 'unknown'),
                'uptime' => $parts[2] ?? '',
            ];
        }

        return [
            'name' => 'Supervisor',
            'status' => 'running',
            'details' => count($processes) . ' processes managed.',
            'can_restart' => false, // We use per-process restart
            'processes' => $processes,
        ];
    }

    private function checkOctane()
    {
        $output = shell_exec("ps aux | grep octane | grep -v grep");
        $status = !empty($output) ? 'running' : 'stopped';
        
        return [
            'name' => 'Laravel Octane (FrankenPHP)',
            'status' => $status,
            'details' => $output ? trim($output) : 'No process found',
            'can_restart' => true,
        ];
    }

    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return [
                'name' => 'Main Database (PostgreSQL)',
                'status' => 'running',
                'details' => 'Connected successfully',
                'can_restart' => false,
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Main Database (PostgreSQL)',
                'status' => 'error',
                'details' => $e->getMessage(),
                'can_restart' => false,
            ];
        }
    }

    private function checkT24Database()
    {
        try {
            DB::connection('db_core_t24')->getPdo();
            return [
                'name' => 'T24 Database (Foundation MIS)',
                'status' => 'running',
                'details' => 'Connected successfully',
                'can_restart' => false,
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'T24 Database (Foundation MIS)',
                'status' => 'error',
                'details' => $e->getMessage(),
                'can_restart' => false,
            ];
        }
    }

    private function checkRedis()
    {
        $host = env('REDIS_HOST', '127.0.0.1');
        $port = env('REDIS_PORT', 6379);
        $fp = @fsockopen($host, $port, $errno, $errstr, 2);
        
        if ($fp) {
            fclose($fp);
            return [
                'name' => 'Redis Cache',
                'status' => 'running',
                'details' => "Listening on {$host}:{$port}",
                'can_restart' => false,
            ];
        } else {
            return [
                'name' => 'Redis Cache',
                'status' => 'stopped',
                'details' => "Could not connect to {$host}:{$port}",
                'can_restart' => false,
            ];
        }
    }

    private function checkQueue()
    {
        $output = shell_exec("ps aux | grep 'queue:work' | grep -v grep");
        $status = !empty($output) ? 'running' : 'stopped';
        
        return [
            'name' => 'Queue Worker',
            'status' => $status,
            'details' => $output ? trim($output) : 'No active workers found',
            'can_restart' => true,
        ];
    }

    private function checkMinio()
    {
        $url = env('AWS_ENDPOINT', 'http://localhost:9002');
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?: 80;
        
        $fp = @fsockopen($host, $port, $errno, $errstr, 2);
        if ($fp) {
            fclose($fp);
            return [
                'name' => 'MinIO (Object Storage)',
                'status' => 'running',
                'details' => "Available at {$url}",
                'can_restart' => false,
            ];
        } else {
            return [
                'name' => 'MinIO (Object Storage)',
                'status' => 'stopped',
                'details' => "Connection failed to {$url}",
                'can_restart' => false,
            ];
        }
    }

    public function restartOctane()
    {
        $artisan = base_path('artisan');
        // We run this in background so the current request can finish and send the success notification
        // before the octane workers are actually reloaded.
        shell_exec("php {$artisan} octane:reload > /dev/null 2>&1 &");
        Log::info("Octane reload signal sent from Service Monitor by " . auth()->user()?->name);
        $this->dispatch('success', message: 'Octane workers reloaded.');
        $this->refreshStatus();
    }

    public function restartQueue()
    {
        $artisan = base_path('artisan');
        $output = shell_exec("php {$artisan} queue:restart 2>&1");
        Log::info("Queue restart signal sent from Service Monitor by " . auth()->user()?->name . ". Output: " . $output);
        $this->dispatch('success', message: 'Queue restart signal sent.');
        $this->refreshStatus();
    }

    public function restartSupervisorProcess($name)
    {
        $escapedName = escapeshellarg($name);
        $output = shell_exec("supervisorctl restart {$escapedName} 2>&1");
        Log::info("Supervisor process {$name} restarted by " . auth()->user()?->name . ". Output: " . $output);
        $this->dispatch('success', message: "Process {$name} restart triggered.");
        $this->refreshStatus();
    }

    public function startSupervisorProcess($name)
    {
        $escapedName = escapeshellarg($name);
        $output = shell_exec("supervisorctl start {$escapedName} 2>&1");
        $this->dispatch('success', message: "Process {$name} start triggered.");
        $this->refreshStatus();
    }

    public function stopSupervisorProcess($name)
    {
        $escapedName = escapeshellarg($name);
        $output = shell_exec("supervisorctl stop {$escapedName} 2>&1");
        $this->dispatch('success', message: "Process {$name} stop triggered.");
        $this->refreshStatus();
    }

    public function render()
    {
        return view('livewire.admin.service-monitor')->layout('layouts.guest');
    }
}
