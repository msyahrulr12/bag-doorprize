<?php

namespace App\Console\Commands;

use App\Models\AccountDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RemoveSftpStatementCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:remove-sftp-statement-command {--account_numbers= : Comma separated account numbers} {--month= : Month of the statement} {--year= : Year of the statement} {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes uploaded bank statement files from SFTP T24 based on account_documents table.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountNumbers = $this->option('account_numbers');
        $month = $this->option('month');
        $year = $this->option('year');
        $force = $this->option('force');

        $query = AccountDocument::where('has_stored_to_sftp', true)
            ->whereNotNull('file_path_t24');

        if ($accountNumbers) {
            $accList = array_map('trim', explode(',', $accountNumbers));
            $query->whereHas('account', function ($q) use ($accList) {
                $q->whereIn('account_number', $accList);
            });
        }

        if ($month && $year) {
            $query->where(function ($q) use ($month, $year) {
                // If it is correctly stored as JSON
                $q->where(function ($q1) use ($month, $year) {
                    $q1->where('metadata->month', (int) $month)
                       ->where('metadata->year', (int) $year);
                })
                // If it was double-encoded as a string (fallback)
                ->orWhere('metadata', 'LIKE', '%"month":' . (int)$month . '%')
                ->orWhere('metadata', 'LIKE', '%"month":"' . (int)$month . '"%');
            })->where(function ($q) use ($year) {
                $q->where('metadata->year', (int) $year)
                  ->orWhere('metadata', 'LIKE', '%"year":' . (int)$year . '%')
                  ->orWhere('metadata', 'LIKE', '%"year":"' . (int)$year . '"%');
            });
        } elseif ($month || $year) {
            $this->error('Please provide both month and year if you want to filter by period.');
            return 1;
        }

        $documents = $query->get();
        $total = $documents->count();

        if ($total === 0) {
            $this->info('No documents found matching the criteria.');
            return 0;
        }

        $this->info("Found {$total} documents to remove from SFTP.");

        if (!$force && !$this->confirm('Are you sure you want to delete these files from SFTP and update the database?', false)) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $disk = Storage::disk('core_t24_sftp');
        $successCount = 0;
        $failCount = 0;

        foreach ($documents as $doc) {
            $path = $doc->file_path_t24;

            try {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                    $this->info("Deleted: {$path}");
                } else {
                    $this->warn("File not found on SFTP: {$path}");
                }

                $doc->update([
                    'has_stored_to_sftp' => false,
                    'file_name_t24' => null,
                    'file_path_t24' => null,
                ]);

                $successCount++;
            } catch (\Exception $e) {
                $this->error("Failed to delete {$path}: " . $e->getMessage());
                Log::error("SFTP Deletion Error for doc ID {$doc->id}: " . $e->getMessage());
                $failCount++;
            }
        }

        $this->info("Operation completed.");
        $this->info("Successfully processed: {$successCount}");
        if ($failCount > 0) {
            $this->error("Failed: {$failCount}");
        }

        return 0;
    }
}
