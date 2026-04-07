<?php

namespace App\Jobs;

use App\Events\GenerateBankStatementProcessed;
use App\Helper\DateHelper;
use App\Helper\PdfHelper;
use App\Models\AccountDocument;
use App\Models\Customer;
use App\Models\PointHistory;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateBankStatementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public ?array $limitAccountNumbers = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $customerId,
        private int $month,
        private int $year,
        private string $monthName,
        private Carbon $currentDate,
        private bool $mergePdfBankStatement,
        private ?string $t24Path = null,
        ?array $limitAccountNumbers = null
    ) {
        $this->limitAccountNumbers = $limitAccountNumbers;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $baseComparisonYear = Setting::where('key', 'base_comparison_year')->first()->value ?? 2026;
        $baseComparisonMonth = Setting::where('key', 'base_comparison_month')->first()->value ?? 1;

        $limitAccountNumbers = $this->limitAccountNumbers;

        $customer = Customer::with([
            'accounts',
            'accounts.branch',
            'accounts.participants',
            'accounts.participants.lotteryTickets',
            'accounts.pointHistories' => function ($query) use ($baseComparisonYear, $baseComparisonMonth) {
                // FILTER: Only same year as requested and exclude base comparison period
                $query->where('year', $this->year)
                    ->where(function ($q) use ($baseComparisonYear, $baseComparisonMonth) {
                    $q->where('year', '!=', (int) $baseComparisonYear)
                        ->orWhere('month', '!=', (int) $baseComparisonMonth);
                });
            },
            'accounts.documents'
        ])->find($this->customerId);

        if (!$customer) {
            Log::warning("Customer ID {$this->customerId} not found for bank statement generation.");
            return;
        }

        $totalPointCustomers = [];
        $tempAggregated = [];

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

                if ($history->type == PointHistory::POINT_TYPE_EARN) {
                    $penambahan = (int) $history->points;
                    $totalPointCustomers[$accNo]['penambahan'] += $penambahan;
                } else if ($history->type == PointHistory::POINT_TYPE_EXPIRED) {
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
        $allCoupons = [];
        foreach ($tempAggregated as $item) {
            $monthLabel = DateHelper::MONTHS[$item['month']] ?? 'N/A';

            $rowNet = ($item['penambahan'] - $item['pengurangan']);
            $allCoupons[] = [
                'periode' => $monthLabel,
                'penambahan' => number_format($item['penambahan'], 0, ',', '.'),
                'pengurangan' => number_format($item['pengurangan'], 0, ',', '.'),
                'nomor' => implode('<br>', array_unique($item['nomor'])),
                'saldo' => number_format($rowNet, 0, ',', '.'),
                'keterangan' => implode('<br>', array_unique($item['keterangan'])),
            ];
        }

        if (empty($allCoupons)) {
            $allCoupons[] = [
                'periode' => $this->monthName . ' ' . $this->year,
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
            
            // Simplified description logic: REK xxxxx {formatted_net} KUPON
            $totalPointDescriptionsAggregate .= "REK {$accountNumber} " . number_format($net, 0, ',', '.') . " KUPON<br>";
            
            if ($net > 0) {
                $totalPointsAggregate += $net;
            }
        }

        foreach ($customer->accounts as $account) {
            if ($limitAccountNumbers && !in_array($account->account_number, $limitAccountNumbers)) {
                continue;
            }
            $existingDoc = $account->documents
                ->where('document_type', AccountDocument::TYPE_ESTATEMENT)
                ->first();

            if (
                $existingDoc && $existingDoc->has_stored_to_sftp &&
                $existingDoc->period && $existingDoc->period->format('Y-m') === $this->currentDate->format('Y-m')
            ) {
                Log::info("Skipping Account: {$account->account_number} (CIF: {$customer->cif}) - already stored to SFTP for this period.");
                continue;
            }

            $data = [
                'account_number' => $account->account_number,
                'branch' => $account->branch->branch_name ?? 'N/A',
                'customer_name' => $customer->name,
                'period' => "01 {$this->monthName} s/d " . Carbon::create($this->year, $this->month)->endOfMonth()->format('d') . " {$this->monthName} {$this->year}",
                'cif_number' => $customer->cif,
                'coupons' => $allCoupons,
                'showSuccessMessage' => true,
                'monthName' => $this->monthName,
                'year' => $this->year,
                'month' => $this->month,
                'current_date' => $this->currentDate,
                'totalPoints' => number_format($totalPointsAggregate, 0, ',', '.'),
                'totalPointDescriptions' => $totalPointDescriptionsAggregate
            ];

            Log::info("Generating PDF for Account: {$account->account_number} (CIF: {$customer->cif})");
            $urlPdf = $this->processTicket($data);
            $filename = basename($urlPdf);

            $docData = [
                'customer_id' => $customer->id,
                'account_id' => $account->id,
                'type' => AccountDocument::TYPE_ESTATEMENT,
                'filename' => $filename,
                'path' => $urlPdf,
                'file_description' => "Bank Statement CIF: {$customer->cif}, Acc: {$account->account_number}, {$this->monthName} {$this->year}",
                'period' => $this->currentDate->format('Y-m-d'),
                'is_merged' => false,
                'status' => AccountDocument::STATUS_ACTIVE,
                'document_type' => AccountDocument::TYPE_ESTATEMENT,
                'has_stored_to_sftp' => false,
                'metadata' => json_encode([
                    'month' => $this->month,
                    'year' => $this->year,
                    'account_number' => $account->account_number,
                    'branch' => $account->branch->branch_name ?? 'N/A',
                    'customer_name' => $customer->name,
                    'date_of_birth' => $customer->date_of_birth ?? null,
                    'account_company_book' => $account->branch?->company_book ?? null,
                    'period' => "01 {$this->monthName} s/d " . Carbon::create($this->year, $this->month)->endOfMonth()->format('d') . " {$this->monthName} {$this->year}",
                    'cif_number' => $customer->cif,
                    'coupons' => $allCoupons,
                    'showSuccessMessage' => true,
                    'monthName' => $this->monthName,
                    'current_date' => $this->currentDate
                ])
            ];

            AccountDocument::updateOrCreate(
                ['customer_id' => $customer->id, 'account_id' => $account->id, 'document_type' => AccountDocument::TYPE_ESTATEMENT],
                $docData
            );

            if ($this->mergePdfBankStatement) {
                $this->processMerge($urlPdf, $customer->date_of_birth, $account->branch?->company_book, $account->account_number, $this->year, $this->month);
            } else {
                $startOfMonth = $this->currentDate->startOfMonth()->format('Ymd');
                $endOfMonth = $this->currentDate->endOfMonth()->format('Ymd');
                $finalFilename = $account->account_number . '.' . $startOfMonth . '.' . $endOfMonth . '.1.pdf';
                $fullPathSFTP = sprintf('%s/%s/%s', $this->t24Path, $this->year . str_pad($this->month, 2, '0', STR_PAD_LEFT), $account->branch?->company_book);

                $upload = $this->uploadBankStatement($finalFilename, $this->t24Path, $fullPathSFTP, $urlPdf);

                if ($upload != null) {
                    $filenameSFTP = explode('/', $upload);

                    AccountDocument::where('customer_id', $customer->id)
                        ->where('account_id', $account->id)
                        ->where('document_type', AccountDocument::TYPE_ESTATEMENT)
                        ->update([
                            'has_stored_to_sftp' => true,
                            'file_name_t24' => $filenameSFTP[count($filenameSFTP) - 1],
                            'file_path_t24' => $upload
                        ]);

                    if (file_exists($urlPdf)) {
                        Log::info("Removing local PDF after successful upload: " . $urlPdf);
                        unlink($urlPdf);
                    }
                }
            }
        }
    }

    private function processTicket(array $data)
    {
        $path = storage_path('app/public/bank-statements');
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        $tncFilename = "term_conditions_temp.pdf";
        $tncPath = $path . '/' . $tncFilename;
        if (!file_exists($tncPath)) {
            PdfHelper::writeAndSave('pdf.term-conditions', [], $path, $tncFilename);
        }

        $statementFilename = "statement_temp_{$data['account_number']}.pdf";
        PdfHelper::writeAndSave('pdf.bank-statement-1', $data, $path, $statementFilename);
        $statementPath = $path . '/' . $statementFilename;

        $finalFilename = "{$data['account_number']}_{$data['year']}_{$data['month']}.pdf";
        $finalPath = $path . '/' . $finalFilename;

        PdfHelper::mergePdf(
            [$statementPath, $tncPath],
            ['all', 'all'],
            $finalPath
        );

        if (file_exists($statementPath)) {
            unlink($statementPath);
        }

        return $finalPath;
    }

    private function processMerge(string $bankStatementPath, ?string $dateOfBirth = null, string $companyBook, string $accountNumber, int $year, int $month)
    {
        $pdfPasswordUser = null;
        if ($dateOfBirth) {
            $pdfPasswordUser = Carbon::parse($dateOfBirth)->format('dmy');
        }
        event(new GenerateBankStatementProcessed($year, $month, $companyBook, $accountNumber, $bankStatementPath, $pdfPasswordUser));
    }

    private function uploadBankStatement(string $finalFilename, string $pathT24, string $bankStatementPath, string $fullPath): ?string
    {
        $disk = Storage::disk('core_t24_sftp');
        $parts = explode('.', $finalFilename);
        if (count($parts) < 4)
            return null;
        $pattern = "{$parts[0]}.{$parts[1]}.{$parts[2]}";

        try {
            $files = $disk->files($bankStatementPath);
        } catch (\Exception $e) {
            $files = [];
        }

        $maxSequence = 0;
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_BASENAME);
            if (strpos($filename, $pattern) === 0) {
                $fileParts = explode('.', $filename);
                if (count($fileParts) >= 4) {
                    $sequence = (int) $fileParts[3];
                    if ($sequence > $maxSequence)
                        $maxSequence = $sequence;
                }
            }
        }

        $nextSequence = $maxSequence + 1;
        if ($nextSequence === 1) {
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
            return null;
        }

        $newFilename = "{$bankStatementPath}/{$pattern}.{$nextSequence}.pdf";

        try {
            $disk->put($newFilename, file_get_contents($fullPath), [
                'visibility' => 'public',
                'directory_visibility' => 'public'
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
