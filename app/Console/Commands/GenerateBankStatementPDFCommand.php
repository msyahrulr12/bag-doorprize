<?php

namespace App\Console\Commands;

use App\Events\GenerateBankStatementProcessed;
use App\Helper\DateHelper;
use App\Helper\PdfHelper;
use App\Models\AccountDocument;
use App\Models\Setting;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;
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

        $monthName = DateHelper::MONTHS[$month];
        $mergePdfBankStatement = (bool) Setting::where('key', 'merge_pdf_bank_statement')->first()->value ?? false;
        $t24Path = env('CORE_T24_PATH_STATEMENT');

        $query = Customer::whereHas('accounts');
        Log::info("Total Customers to Process: " . $query->count());

        $query->with([
            'accounts',
            'accounts.branch',
            'accounts.participants',
            'accounts.participants.lotteryTickets',
            'accounts.pointHistories',
            'accounts.documents'
        ])
            ->chunkById(50, function ($customers) use ($month, $year, $currentDate, $monthName, $mergePdfBankStatement, $t24Path) {
                $chunkDocuments = [];

                foreach ($customers as $customer) {
                    $totalPoints = 0;
                    Log::info('Aggregating data for customer: ' . $customer->name . ' (CIF: ' . $customer->cif . ')');

                    $tempAggregated = [];
                    $totalPointCustomers = [];
                    foreach ($customer->accounts as $account) {
                        $accNo = $account->account_number;
                        if (!isset($totalPointCustomers[$accNo])) {
                            $totalPointCustomers[$accNo] = [
                                'penambahan' => 0,
                                'pengurangan' => 0
                            ];
                        }

                        $ticketsByPeriod = [];
                        foreach ($account->participants ?? [] as $participant) {
                            foreach ($participant->lotteryTickets as $lt) {
                                $ticketsByPeriod["{$lt->year}_{$lt->month}"] = $lt;
                            }
                        }

                        foreach ($account->pointHistories ?? [] as $history) {
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

                    ksort($tempAggregated);
                    $runningSaldo = 0;
                    $allCoupons = [];
                    foreach ($tempAggregated as $item) {
                        $monthLabel = isset(DateHelper::MONTHS[$item['month']]) ? DateHelper::MONTHS[$item['month']] : 'N/A';

                        $runningSaldo += ($item['penambahan'] - $item['pengurangan']);
                        if ($runningSaldo < 0) {
                            $runningSaldo = 0;
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

                    // Inject 0 point row if no history (User requirement)
                    if (empty($allCoupons)) {
                        $allCoupons[] = [
                            'periode' => $monthName . ' ' . $year,
                            'penambahan' => "0",
                            'pengurangan' => "0",
                            'nomor' => "-",
                            'saldo' => "0",
                            'keterangan' => "Point Perolehan 0",
                        ];
                    }

                    $totalPointsAggregate = 0;
                    $totalPointDescriptionsAggregate = "";
                    foreach ($totalPointCustomers as $accountNumber => $tp) {
                        $net = $tp['penambahan'] - $tp['pengurangan'];
                        if ($net < 0) {
                            $net = 0;
                        }
                        $totalPointsAggregate += $net;

                        if ($tp['penambahan'] > 0) {
                            $totalPointDescriptionsAggregate .= "REK {$accountNumber} BERTAMBAH {$tp['penambahan']} KUPON<br>";
                        }
                        if ($tp['pengurangan'] > 0) {
                            $totalPointDescriptionsAggregate .= "REK {$accountNumber} BERKURANG {$tp['pengurangan']} KUPON<br>";
                        }
                    }

                    foreach ($customer->accounts as $account) {
                        // User requirement: skip if already stored to SFTP for this period
                        $existingDoc = $account->documents
                            ->where('document_type', AccountDocument::TYPE_ESTATEMENT)
                            ->first();

                        if (
                            $existingDoc && $existingDoc->has_stored_to_sftp &&
                            $existingDoc->period && $existingDoc->period->format('Y-m') === $currentDate->format('Y-m')
                        ) {
                            Log::info("Skipping Account: {$account->account_number} (CIF: {$customer->cif}) - already stored to SFTP for this period.");
                            continue;
                        }

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
                            'totalPoints' => $totalPointsAggregate,
                            'totalPointDescriptions' => $totalPointDescriptionsAggregate
                        ];

                        Log::info("Generating PDF for Account: {$account->account_number} (CIF: {$customer->cif})");
                        $urlPdf = $this->processTicket($data);
                        $filename = basename($urlPdf);

                        $chunkDocuments[] = [
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
                            'has_stored_to_sftp' => false, // Reset flag on fresh generation
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

                // Save and process storage for this chunk
                if (!empty($chunkDocuments)) {
                    AccountDocument::upsert(array_values($chunkDocuments), ['customer_id', 'account_id', 'document_type'], ['path', 'filename', 'file_description', 'metadata', 'has_stored_to_sftp', 'period']);

                    foreach ($chunkDocuments as $doc) {
                        $metaData = json_decode($doc['metadata'], true);
                        if ($mergePdfBankStatement) {
                            $this->processMerge($doc['path'], $metaData['date_of_birth'], $metaData['account_company_book'], $metaData['account_number'], $metaData['year'], $metaData['month']);
                        } else {
                            $startOfMonth = Carbon::parse($metaData['current_date'])->startOfMonth()->format('Ymd');
                            $endOfMonth = Carbon::parse($metaData['current_date'])->endOfMonth()->format('Ymd');
                            $finalFilename = $metaData['account_number'] . '.' . $startOfMonth . '.' . $endOfMonth . '.1.pdf';
                            $fullPath = sprintf('%s/%s/%s', $t24Path, $metaData['year'] . str_pad($metaData['month'], 2, '0', STR_PAD_LEFT), $metaData['account_company_book']);

                            $upload = $this->uploadBankStatement($finalFilename, $t24Path, $fullPath, $doc['path']);

                            if ($upload != null) {
                                $filename = explode('/', $upload);

                                AccountDocument::where('customer_id', $doc['customer_id'])
                                    ->where('account_id', $doc['account_id'])
                                    ->where('document_type', AccountDocument::TYPE_ESTATEMENT)
                                    ->update([
                                        'has_stored_to_sftp' => true,
                                        'file_name_t24' => $filename[count($filename) - 1],
                                        'file_path_t24' => $upload
                                    ]);

                                if (file_exists($doc['path'])) {
                                    Log::info("Removing local PDF after successful upload: " . $doc['path']);
                                    unlink($doc['path']);
                                }
                            }
                        }
                    }
                }
            });

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

        // 3. Merge bank-statement with term and condition
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

    private function uploadBankStatement(string $finalFilename, string $pathT24, string $bankStatementPath, string $fullPath): ?string
    {
        $disk = Storage::disk('core_t24_sftp');

        // Extract pattern from filename (e.g. ID0010300.20251001.20251031)
        $parts = explode('.', $finalFilename);
        if (count($parts) < 4) {
            Log::error("Invalid finalFilename format: {$finalFilename}");
            return false;
        }
        $pattern = "{$parts[0]}.{$parts[1]}.{$parts[2]}";

        // List files in the specific directory
        try {
            $files = $disk->files($bankStatementPath);
        } catch (\Exception $e) {
            Log::error("Failed to list files in SFTP: " . $e->getMessage());
            $files = [];
        }

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

        // User requirement: if files is empty (sequence 1), cancel and mark failed.
        // Upload only if sequence > 1 (file exists).
        if ($nextSequence === 1) {
            Log::warning("Sequence is 1 (file does not exist on SFTP). Cancelling upload for {$finalFilename} as per requirement.");
            \App\Models\FailedUpload::create([
                'filename' => $finalFilename,
                'local_path' => $fullPath,
                'target_directory' => $bankStatementPath,
                'error_message' => "Sequence start from 1, cancel upload as per requirement.",
                'status' => 'failed',
                'metadata' => [
                    'pattern' => $pattern,
                    'next_sequence' => $nextSequence,
                    'original_filename' => $finalFilename
                ]
            ]);
            return false;
        }

        $newFilename = "{$bankStatementPath}/{$pattern}.{$nextSequence}.pdf";

        Log::info("Uploading bank statement to T24 SFTP: {$newFilename}");

        try {
            $disk->put($newFilename, file_get_contents($fullPath), [
                'visibility' => 'private',
                'directory_visibility' => 'private'
            ]);

            return $newFilename;
        } catch (\Exception $e) {
            Log::error("Failed to upload bank statement: " . $e->getMessage());
            \App\Models\FailedUpload::create([
                'filename' => basename($newFilename),
                'local_path' => $fullPath,
                'target_directory' => $bankStatementPath,
                'error_message' => $e->getMessage(),
                'status' => 'failed',
                'metadata' => [
                    'pattern' => $pattern,
                    'next_sequence' => $nextSequence,
                    'full_target_path' => $newFilename
                ]
            ]);
            return null;
        }
    }
}
