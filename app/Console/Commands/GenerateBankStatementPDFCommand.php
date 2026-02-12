<?php

namespace App\Console\Commands;

use App\Events\GenerateBankStatementProcessed;
use App\Helper\DateHelper;
use App\Helper\PdfHelper;
use App\Models\AccountDocument;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Setting;
use App\Models\Winner;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;
use App\Models\LotteryTicket;
use Storage;

class GenerateBankStatementPDFCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-bank-statement-pdf-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startedAt = new \DateTime();
        Log::info('Generate bank statement PDF command started at: ' . $startedAt->format('Y-m-d H:i:s'));

        $pointSubMonth = Setting::where('key', 'point_sub_month')->first()->value ?? 1;
        $currentDate = now()->subMonths($pointSubMonth);
        $month = $currentDate->month;
        $year = $currentDate->year;

        // Get all customers with accounts and active participants
        $customers = Customer::with([
            'accounts.branch',
            // 'accounts.participants' => function ($query) {
            //     $query->whereHas('event', fn($q) => $q->where('status', Event::STATUS_ACTIVE))
            //         ->whereHas('lotteryTickets');
            // },
            'accounts.participants',
            'accounts.participants.lotteryTickets',
            'accounts.pointHistories'
        ])
            ->whereHas('accounts.participants', function ($query) {
                $query->whereHas('event', fn($q) => $q->where('status', Event::STATUS_ACTIVE))
                    ->whereHas('lotteryTickets');
            })
            ->get();

        Log::info("Total Customers Processed: {$customers->count()}");

        $documents = [];
        foreach ($customers ?? [] as $customer) {
            $totalPoints = 0;
            $totalPointCustomers = [];
            $allCoupons = [];

            Log::info('Aggregating data for customer: ' . $customer->name . ' (CIF: ' . $customer->cif . ')');

            $tempAggregated = [];
            // Phase 1: Aggregation across all accounts
            foreach ($customer->accounts as $account) {
                $accNo = $account->account_number;
                if (!isset($totalPointCustomers[$accNo])) {
                    $totalPointCustomers[$accNo] = [
                        'penambahan' => 0,
                        'pengurangan' => 0
                    ];
                }

                // Map tickets by month/year for this account
                $ticketsByPeriod = [];
                foreach ($account->participants as $participant) {
                    foreach ($participant->lotteryTickets as $lt) {
                        $ticketsByPeriod["{$lt->year}_{$lt->month}"] = $lt;
                    }
                }

                foreach ($account->pointHistories as $history) {
                    $monthSortKey = sprintf("%04d_%02d", $history->year, $history->month);
                    if (!isset($tempAggregated[$monthSortKey])) {
                        $tempAggregated[$monthSortKey] = [
                            'month' => $history->month,
                            'penambahan' => 0,
                            'pengurangan' => 0,
                            'nomor' => [],
                            'keterangan' => [],
                        ];
                    }

                    $penambahan = 0;
                    $pengurangan = 0;

                    if ($history->type == \App\Models\PointHistory::POINT_TYPE_EARN) {
                        $penambahan = (int) $history->points;
                        $totalPointCustomers[$accNo]['penambahan'] += $penambahan;
                    } else if ($history->type == \App\Models\PointHistory::POINT_TYPE_EXPIRED) {
                        $pengurangan = (int) abs($history->points);
                        $totalPointCustomers[$accNo]['pengurangan'] += $pengurangan;
                    } else {
                        // Handle other types like ADJUSTMENT
                        $val = (int) $history->points;
                        if ($val > 0) {
                            $penambahan = $val;
                            $totalPointCustomers[$accNo]['penambahan'] += $penambahan;
                        } else {
                            $pengurangan = abs($val);
                            $totalPointCustomers[$accNo]['pengurangan'] += $pengurangan;
                        }
                    }

                    $tempAggregated[$monthSortKey]['penambahan'] += $penambahan;
                    $tempAggregated[$monthSortKey]['pengurangan'] += $pengurangan;

                    // Get ticket range if available
                    $ticket = $ticketsByPeriod["{$history->year}_{$history->month}"] ?? null;
                    if ($ticket && $ticket->range_start && $ticket->range_end) {
                        $rangeDesc = "{$ticket->range_start} s/d {$ticket->range_end}";
                        if (!in_array($rangeDesc, $tempAggregated[$monthSortKey]['nomor'])) {
                            $tempAggregated[$monthSortKey]['nomor'][] = $rangeDesc;
                        }
                    }

                    $tempAggregated[$monthSortKey]['keterangan'][] = $history->description ?? "{$history->type} {$history->points} KUPON";
                }
            }

            // Phase 1.5: Finalize allCoupons by month
            ksort($tempAggregated);
            $runningSaldo = 0;
            foreach ($tempAggregated as $item) {
                $monthLabel = isset(DateHelper::MONTHS[$item['month']]) ? DateHelper::MONTHS[$item['month']] : 'N/A';

                $runningSaldo += ($item['penambahan'] - $item['pengurangan']);
                if ($runningSaldo < 0) {
                    $runningSaldo = 0; // Reset to 0 if negative
                }

                $allCoupons[] = [
                    'periode' => $monthLabel,
                    'penambahan' => number_format($item['penambahan'], 0, ',', '.'),
                    'pengurangan' => number_format($item['pengurangan'], 0, ',', '.'),
                    'nomor' => implode('<br>', array_unique($item['nomor'])),
                    'saldo' => number_format($runningSaldo, 0, ',', '.'),
                    'keterangan' => implode('<br>', array_unique($item['keterangan'])),
                ];
            }

            // Phase 1.6: Calculate Final Total Points and Descriptions
            $totalPoints = 0;
            $totalPointDescriptions = "";
            foreach ($totalPointCustomers as $accountNumber => $tp) {
                $netAcc = $tp['penambahan'] - $tp['pengurangan'];
                if ($netAcc < 0) {
                    $netAcc = 0; // Reset to 0 if negative
                }
                $totalPoints += $netAcc;

                if ($tp['penambahan'] > 0) {
                    $totalPointDescriptions .= "REK {$accountNumber} BERTAMBAH {$tp['penambahan']} KUPON<br>";
                }
                if ($tp['pengurangan'] > 0) {
                    $totalPointDescriptions .= "REK {$accountNumber} BERKURANG {$tp['pengurangan']} KUPON<br>";
                }
            }

            if (empty($allCoupons)) {
                continue;
            }

            $monthName = DateHelper::MONTHS[$month];

            // Phase 2: PDF Generation per account
            foreach ($customer->accounts as $account) {
                // We use the first participant of THIS specific account for the header if available
                $participant = $account->participants->first();

                $data = [
                    'account_number' => $account->account_number,
                    'branch' => $account->branch->branch_name ?? 'N/A',
                    'customer_name' => $customer->name,
                    'period' => "01 {$monthName} s/d " . Carbon::create($year, $month)->endOfMonth()->format('d') . " {$monthName} {$year}",
                    'cif_number' => $customer->cif,
                    'coupons' => $allCoupons,
                    'showSuccessMessage' => true,
                    'monthName' => $monthName,
                    'year' => $year,
                    'month' => $month,
                    'current_date' => $currentDate,
                    'totalPoints' => $totalPoints,
                    'totalPointDescriptions' => $totalPointDescriptions
                ];

                Log::info("Generating PDF for Account: {$account->account_number} (CIF: {$customer->cif})");
                $urlPdf = $this->processTicket($data);
                $filename = basename($urlPdf);

                $documents[] = [
                    'customer_id' => $customer->id,
                    'account_id' => $account->id,
                    'type' => AccountDocument::TYPE_ESTATEMENT,
                    'filename' => $filename,
                    'path' => $urlPdf,
                    'file_description' => "Bank Statement CIF: {$customer->cif}, Acc: {$account->account_number}, {$monthName} {$year}",
                    'period' => $currentDate->format('Y-m-d'),
                    'is_merged' => false,
                    'status' => AccountDocument::STATUS_ACTIVE,
                    'document_type' => AccountDocument::TYPE_ESTATEMENT,
                    'metadata' => json_encode([
                        'month' => $month,
                        'year' => $year,
                        'account_number' => $account->account_number,
                        'branch' => $account->branch->branch_name ?? 'N/A',
                        'customer_name' => $customer->name,
                        'date_of_birth' => $customer->date_of_birth ?? null,
                        'account_company_book' => $account->branch?->company_book ?? null,
                        'period' => "01 {$monthName} s/d " . Carbon::create($year, $month)->endOfMonth()->format('d') . " {$monthName} {$year}",
                        'cif_number' => $customer->cif,
                        'coupons' => $allCoupons,
                        'showSuccessMessage' => true,
                        'monthName' => $monthName,
                        'current_date' => $currentDate
                    ])
                ];
            }
        }

        $result = AccountDocument::upsert(array_values($documents), ['customer_id', 'account_id', 'document_type'], ['path', 'filename', 'file_description', 'metadata']);
        Log::info("Total Documents Inserted: {$result}");
        if ($result > 0) {
            $mergePdfBankStatement = (bool) Setting::where('key', 'merge_pdf_bank_statement')->first()->value ?? false;
            Log::info("Merge PDF Bank Statement: {$mergePdfBankStatement}");
            if (count($documents) > 0) {
                foreach ($documents as $document) {
                    Log::info("Processing Document: {$document['filename']}");
                    $pathDocument = $document['path'];
                    $metaData = json_decode($document['metadata'], true);
                    if ($mergePdfBankStatement) {
                        Log::info("Processing Merge PDF Bank Statement: {$document['filename']}");
                        $this->processMerge($pathDocument, $metaData['date_of_birth'], $metaData['account_company_book'], $metaData['account_number'], $metaData['year'], $metaData['month']);
                    } else {
                        Log::info("Processing Upload PDF Bank Statement: {$document['filename']}");
                        $path = env('CORE_T24_PATH_STATEMENT');
                        // $finalFilename = 5310234905.20251001.20251031.1.pdf
                        $currentDate = Carbon::parse($metaData['current_date']);
                        $startOfMonth = $currentDate->startOfMonth()->format('Ymd');
                        $endOfMonth = $currentDate->endOfMonth()->format('Ymd');
                        $finalFilename = $metaData['account_number'] . '.' . $startOfMonth . '.' . $endOfMonth . '.1.pdf';
                        $fullPath = sprintf('%s/%s/%s', $path, $metaData['year'] . str_pad($metaData['month'], 2, '0', STR_PAD_LEFT), $metaData['account_company_book']);
                        $this->uploadBankStatement($finalFilename, $path, $fullPath, $pathDocument);
                    }
                }
            }
        }

        $finishedAt = new \DateTime();
        Log::info('Generate bank statement PDF finished: ' . $finishedAt->format('Y-m-d H:i:s'));

        $duration = $finishedAt->diff($startedAt);
        Log::info('Generate bank statement PDF duration: ' . $duration->format('%H:%I:%S'));

        if ($this->tncPath && file_exists($this->tncPath)) {
            unlink($this->tncPath);
        }
    }

    private ?string $tncPath = null;

    /**
     * @param array $data
     * @return string
     */
    public function processTicket(array $data)
    {
        Log::info('Processing Convert Ticket To PDF');

        $path = storage_path('app/public/bank-statements');
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        // 1. Generate Terms & Conditions once if not already done
        if (!$this->tncPath) {
            $tncFilename = "term_conditions_temp.pdf";
            PdfHelper::writeAndSave('pdf.term-conditions', [], $path, $tncFilename);
            $this->tncPath = $path . '/' . $tncFilename;
        }

        // 2. Generate Bank Statement
        $statementFilename = "statement_temp_{$data['account_number']}.pdf";
        PdfHelper::writeAndSave('pdf.bank-statement-1', $data, $path, $statementFilename);
        $statementPath = $path . '/' . $statementFilename;

        // 3. Merge them
        $finalFilename = "{$data['account_number']}_{$data['year']}_{$data['month']}.pdf";
        $finalPath = $path . '/' . $finalFilename;

        PdfHelper::mergePdf(
            [$statementPath, $this->tncPath],
            ['all', 'all'],
            $finalPath
        );

        // 4. Cleanup statement temp file (keep TNC for reuse)
        if (file_exists($statementPath)) {
            unlink($statementPath);
        }

        Log::info('Processing Convert Ticket To PDF Finished');

        return $finalPath;
    }

    /**
     * Summary of processMerge
     * @param string $bankStatementPath
     * @param string $dateOfBirth
     * @return void
     */
    private function processMerge(string $bankStatementPath, ?string $dateOfBirth = null, string $companyBook, string $accountNumber, int $year, int $month)
    {
        Log::info('Processing merge pdf bank statements');

        $pdfPasswordUser = null;
        if ($dateOfBirth) {
            $pdfPasswordUser = Carbon::parse($dateOfBirth)->format('dmy');
            Log::info('Using password date of birth: ' . $pdfPasswordUser);
        } else {
            Log::info('Not using password date of birth: ' . $dateOfBirth);
        }

        event(new GenerateBankStatementProcessed($year, $month, $companyBook, $accountNumber, $bankStatementPath, $pdfPasswordUser));

        Log::info('Finished processing merge pdf bank statements');
    }

    private function uploadBankStatement(string $finalFilename, string $pathT24, string $bankStatementPath, string $fullPath)
    {
        $disk = Storage::disk('core_t24_sftp');

        // Extract pattern from filename (e.g. ID0010300.20251001.20251031)
        $parts = explode('.', $finalFilename);
        if (count($parts) < 4) {
            Log::error("Invalid finalFilename format: {$finalFilename}");
            return;
        }
        $pattern = "{$parts[0]}.{$parts[1]}.{$parts[2]}";

        // List files in the specific directory
        $files = $disk->files($bankStatementPath);

        $maxSequence = 0;
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_BASENAME);

            // Check if file matches the pattern
            if (strpos($filename, $pattern) === 0) {
                $fileParts = explode('.', $filename);
                // filename format: pattern.{sequence}.pdf
                // parts index: 0.1.2.3.4
                if (count($fileParts) >= 4) {
                    $sequence = (int) $fileParts[3];
                    if ($sequence > $maxSequence) {
                        $maxSequence = $sequence;
                    }
                }
            }
        }

        $nextSequence = $maxSequence + 1;
        $newFilename = "{$bankStatementPath}/{$pattern}.{$nextSequence}.pdf";

        Log::info("Uploading bank statement to T24 SFTP: {$newFilename}");

        try {
            $disk->put($newFilename, file_get_contents($fullPath), [
                'visibility' => 'private',
                'directory_visibility' => 'private'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to upload bank statement: " . $e->getMessage());
        }
    }
}
