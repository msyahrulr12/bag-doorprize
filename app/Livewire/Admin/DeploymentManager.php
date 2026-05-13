<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DeploymentManager extends Component
{
    use WithFileUploads;

    public $package;
    public $isDeploying = false;
    public $output = '';
    public $deploymentHistory = [];

    public function mount()
    {
        $this->loadHistory();
    }

    private function loadHistory()
    {
        $path = storage_path('app/deployment-history.json');
        if (File::exists($path)) {
            $this->deploymentHistory = array_reverse(json_decode(File::get($path), true) ?? []);
        }
    }

    private function logDeployment($status, $details = '')
    {
        $path = storage_path('app/deployment-history.json');
        $history = File::exists($path) ? json_decode(File::get($path), true) : [];
        
        $history[] = [
            'timestamp' => now()->toDateTimeString(),
            'user' => auth()->user()?->name ?? 'System',
            'package' => $this->package ? $this->package->getClientOriginalName() : 'Unknown',
            'status' => $status,
            'details' => $details,
        ];

        // Keep only last 50 entries
        if (count($history) > 50) {
            array_shift($history);
        }

        File::put($path, json_encode($history, JSON_PRETTY_PRINT));
        $this->loadHistory();
    }

    public function deploy()
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $this->validate([
            'package' => 'required|file|mimes:gz,tar,tgz|max:204800', // 200MB limit
        ]);

        $this->isDeploying = true;
        $this->output = "Starting deployment process...\n";

        try {
            $today = now()->format('Y-m-d');
            $targetDir = "/home/sysadmin/bagi-hoki-main/{$today}";
            
            $this->output .= "Ensuring directory exists: {$targetDir}\n";
            // Note: This might fail if web user doesn't have permissions to /home/sysadmin
            if (!File::exists($targetDir)) {
                @mkdir($targetDir, 0775, true);
            }

            $this->output .= "Uploading package...\n";
            $fileName = "bag-doorprize-deploy.tar.gz";
            $this->package->storeAs($targetDir, $fileName);
            
            $this->output .= "Package uploaded to {$targetDir}/{$fileName}\n";
            $this->output .= "Executing deployment script with sudo...\n";

            $scriptPath = base_path('deploy-main.sh');
            
            // Execute the script and capture output
            // We use sudo -n (non-interactive)
            $command = "sudo -n {$scriptPath} 2>&1";
            
            $process = popen($command, 'r');
            while (!feof($process)) {
                $line = fgets($process);
                if ($line) {
                    $this->output .= $line;
                    $this->dispatch('scroll-to-bottom');
                }
            }
            $exitCode = pclose($process);

            if ($exitCode === 0) {
                $this->output .= "\nDeployment process finished successfully.";
                $this->logDeployment('success');
                $this->dispatch('success', message: 'Deployment completed.');
            } else {
                $this->output .= "\nDeployment process failed with exit code: {$exitCode}";
                $this->logDeployment('failed', "Exit code: {$exitCode}");
                $this->dispatch('error', message: 'Deployment failed.');
            }

        } catch (\Exception $e) {
            $this->output .= "\nERROR: " . $e->getMessage();
            $this->logDeployment('failed', $e->getMessage());
            Log::error("Deployment failed: " . $e->getMessage());
            $this->dispatch('error', message: 'Deployment failed.');
        }

        $this->isDeploying = false;
    }

    public function render()
    {
        return view('livewire.admin.deployment-manager')->layout('layouts.guest');
    }
}
