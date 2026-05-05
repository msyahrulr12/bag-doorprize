<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DatabaseRestoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:database-restore {file? : The backup file to restore}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore the database from a backup file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $directory = storage_path('app/backups');
        
        if (!is_dir($directory)) {
            $this->error("Backup directory not found at {$directory}");
            return 1;
        }

        $file = $this->argument('file');

        if (!$file) {
            $files = glob($directory . '/*.sql.gz');
            if (empty($files)) {
                $this->error("No backup files found in {$directory}");
                return 1;
            }

            // Sort by modified time descending
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            $choices = array_map(function($f) {
                return basename($f);
            }, $files);

            $file = $this->choice('Select a backup file to restore', $choices, 0);
        }

        $path = $directory . '/' . $file;

        if (!file_exists($path)) {
            $this->error("Backup file not found: {$path}");
            return 1;
        }

        if (!$this->confirm("Are you sure you want to restore the database using '{$file}'? This will overwrite current data!", false)) {
            $this->info('Restore cancelled.');
            return 0;
        }

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];
        $host = $config['host'];
        $port = $config['port'];

        $this->info("Starting restore...");

        // Check for psql
        $checkProcess = new Process(['which', 'psql']);
        $checkProcess->run();
        if (!$checkProcess->isSuccessful()) {
            $this->error('psql utility not found.');
            return 1;
        }

        // Safer way: just run the SQL script.
        // If the dump was created without --clean, it might not overwrite everything perfectly.
        // But for a rollback/restore in this context, it's usually what's needed.
        
        $command = sprintf(
            'gunzip -c %s | psql -h %s -p %s -U %s %s',
            escapeshellarg($path),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database)
        );

        $process = Process::fromShellCommandline($command);
        $process->setEnv(['PGPASSWORD' => $password]);
        $process->setTimeout(600); // 10 minutes

        try {
            $process->mustRun();
            $this->info("Database successfully restored from {$file}");
            return 0;
        } catch (ProcessFailedException $exception) {
            $this->error('Restore failed: ' . $exception->getMessage());
            return 1;
        }
    }
}
