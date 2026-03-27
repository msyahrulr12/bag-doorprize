<?php

namespace App\Console\Commands;

use App\Helper\DateHelper;
use App\Models\Setting;
use App\Models\Customer;
use App\Jobs\GenerateBankStatementJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateBankStatementPDFCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-bank-statement-pdf-command {--account_numbers= : List nomor rekening yang akan dilakukan testing. Pemisahnya koma (,). Ex: 1234,5678,91011}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatches background jobs to generate bank statement PDFs for customers.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startedAt = now();
        Log::info('Generate bank statement PDF command started at: ' . $startedAt->format('Y-m-d H:i:s'));

        $accountNumbersOptions = $this->option('account_numbers');

        $pointSubMonth = Setting::where('key', 'point_sub_month')->first()->value ?? 1;
        $currentDate = now()->subMonths($pointSubMonth);
        $month = $currentDate->month;
        $year = $currentDate->year;

        $monthName = DateHelper::MONTHS[$month];
        $mergePdfBankStatement = (bool) (Setting::where('key', 'merge_pdf_bank_statement')->first()->value ?? false);
        $t24Path = env('CORE_T24_PATH_STATEMENT');

        $query = Customer::query();
        $limitAccountNumbers = null;

        if ($accountNumbersOptions && $accountNumbersOptions !== '') {
            // Trim both whitespace and any surrounding quotes that might have been passed from the shell
            $limitAccountNumbers = array_map(function ($item) {
                return trim($item, " \t\n\r\0\x0B\"'");
            }, explode(',', $accountNumbersOptions));

            $this->info("Filtering by " . count($limitAccountNumbers) . " account numbers.");
            Log::info("Filter active for accounts: " . implode(', ', $limitAccountNumbers));

            $query->whereHas('accounts', function ($subQuery) use ($limitAccountNumbers) {
                $subQuery->whereIn('account_number', $limitAccountNumbers);
            });
        } else {
            $this->info("No account filter provided. Processing all active customers.");
            $query->has('accounts');
        }

        $totalCustomers = $query->count();

        if ($totalCustomers === 0) {
            $this->info("No customers found for the given criteria.");
            Log::info("Command finished: 0 customers matched the filter.");
            return 0;
        }

        $this->info("Total Customers to process: " . $totalCustomers);
        Log::info("Total Customers to process: " . $totalCustomers);

        $query->orderBy('id')->chunk(100, function ($customers) use ($month, $year, $monthName, $currentDate, $mergePdfBankStatement, $t24Path, $limitAccountNumbers) {
            foreach ($customers as $customer) {
                GenerateBankStatementJob::dispatch(
                    $customer->id,
                    $month,
                    $year,
                    $monthName,
                    $currentDate,
                    $mergePdfBankStatement,
                    $t24Path,
                    $limitAccountNumbers // Pass the filter to the job
                )->onQueue('reports');
            }
        });

        $finishedAt = now();
        Log::info('Generate bank statement PDF command finished dispatching at: ' . $finishedAt->format('Y-m-d H:i:s'));
        Log::info('Duration for dispatching: ' . $finishedAt->diff($startedAt)->format('%H:%I:%S'));
    }
}
