<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:database-backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup the application database (PostgreSQL)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting database backup...');

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if ($connection !== 'pgsql') {
            $this->error("Backup command only supports pgsql connection. Current: {$connection}");
            return 1;
        }

        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];
        $host = $config['host'];
        $port = $config['port'];

        $filename = "backup-" . now()->format('Y-m-d-His') . ".sql";
        $directory = storage_path('app/backups');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . '/' . $filename;

        // Check if pg_dump exists
        $checkProcess = new Process(['which', 'pg_dump']);
        $checkProcess->run();
        if (!$checkProcess->isSuccessful()) {
            $this->error('pg_dump utility not found. Please install PostgreSQL client tools.');
            return 1;
        }

        // Using pg_dump
        // --clean: Drop database objects before recreating them
        // --if-exists: Use IF EXISTS when dropping objects
        $command = sprintf(
            'pg_dump --clean --if-exists -h %s -p %s -U %s %s > %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($path)
        );

        $this->info("Backing up database '{$database}' to '{$filename}'...");

        $process = Process::fromShellCommandline($command);
        $process->setEnv(['PGPASSWORD' => $password]);
        $process->setTimeout(300); // 5 minutes

        try {
            $process->mustRun();
            $this->info("Backup successfully created: {$path}");
            
            // Compress the file to save space
            $this->info("Compressing backup...");
            $gzipProcess = Process::fromShellCommandline("gzip " . escapeshellarg($path));
            $gzipProcess->mustRun();
            
            $this->info("Backup compressed: {$path}.gz");
            return 0;
        } catch (ProcessFailedException $exception) {
            $this->error('Backup failed: ' . $exception->getMessage());
            return 1;
        }
    }
}
