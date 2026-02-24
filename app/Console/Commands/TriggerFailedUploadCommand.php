<?php

namespace App\Console\Commands;

use App\Models\FailedUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TriggerFailedUploadCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:trigger-failed-upload-command {--id= : Retry a specific failed upload if provided}';

    protected $description = 'Retry failed bank statement uploads from the failed_uploads table';

    public function handle()
    {
        $failedUploads = \App\Models\FailedUpload::where('status', 'failed');

        if ($this->option('id')) {
            $failedUploads->where('id', $this->option('id'));
        }

        $records = $failedUploads->get();

        if ($records->isEmpty()) {
            $this->info('No failed uploads found.');
            return;
        }

        $this->info("Processing {$records->count()} failed upload(s)...");

        $disk = \Storage::disk('core_t24_sftp');

        foreach ($records as $record) {
            /** @var FailedUpload $record */
            $this->info("Retrying: {$record->filename}");

            if (!file_exists($record->local_path)) {
                $this->error("Local file not found: {$record->local_path}");
                continue;
            }

            try {
                // Determine sequence again (it might have changed since last fail)
                $pattern = $record->metadata['pattern'] ?? null;
                if (!$pattern) {
                    $parts = explode('.', $record->filename);
                    $pattern = "{$parts[0]}.{$parts[1]}.{$parts[2]}";
                }

                $files = $disk->files($record->target_directory);
                $maxSequence = 0;
                foreach ($files as $file) {
                    $filename = pathinfo($file, PATHINFO_BASENAME);
                    if (strpos($filename, $pattern) === 0) {
                        $fileParts = explode('.', $filename);
                        if (count($fileParts) >= 4) {
                            $sequence = (int) $fileParts[3];
                            if ($sequence > $maxSequence) {
                                $maxSequence = $sequence;
                            }
                        }
                    }
                }

                $nextSequence = $maxSequence + 1;

                // Re-check sequence 1 requirement if it was a "cancel" failure
                if ($record->error_message === "Sequence start from 1, cancel upload as per requirement." && $nextSequence === 1) {
                    $this->warn("Sequence is still 1. Skipping as per requirement.");
                    continue;
                }

                $newFilename = "{$record->target_directory}/{$pattern}.{$nextSequence}.pdf";

                $disk->put($newFilename, file_get_contents($record->local_path), [
                    'visibility' => 'private',
                    'directory_visibility' => 'private'
                ]);

                $record->update([
                    'status' => 'success',
                    'error_message' => null,
                    'metadata' => array_merge($record->metadata ?? [], ['retried_at' => now()->toDateTimeString(), 'final_sequence' => $nextSequence])
                ]);

                $this->info("Successfully uploaded: {$newFilename}");

            } catch (\Exception $e) {
                $this->error("Failed to upload {$record->filename}: " . $e->getMessage());
                $record->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'metadata' => array_merge($record->metadata ?? [], ['last_attempt_at' => now()->toDateTimeString()])
                ]);
            }
        }
    }
}
