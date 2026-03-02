<?php

namespace App\Services;

use App\Helper\DateHelper;
use App\Helper\PdfHelper;
use App\Models\Account;
use App\Models\AccountDocument;
use App\Models\Customer;
use App\Models\LotteryTicket;
use App\Models\Setting;
use App\Models\Winner;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BankStatementService
{
    /**
     * Generate or update bank statement for a given account and period.
     */
    public function generateForAccount(int $accountId, int $month, int $year)
    {
        $account = Account::with(['customer', 'branch', 'participants', 'participants.lotteryTickets', 'pointHistories'])->findOrFail($accountId);
        $customer = $account->customer;
        $currentDate = Carbon::createFromDate($year, $month, 1);
        $monthName = DateHelper::MONTHS[$month];

        Log::info("Generating Bank Statement for Account: {$account->account_number} (Month: {$monthName} {$year})");

        // Prepare aggregation data similar to GenerateBankStatementPDFCommand
        $tempAggregated = [];
        $totalPointCustomers = [
            $account->account_number => [
                'penambahan' => 0,
                'pengurangan' => 0
            ]
        ];

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
                $totalPointCustomers[$account->account_number]['penambahan'] += $penambahan;
            } else if ($history->type == \App\Models\PointHistory::POINT_TYPE_EXPIRED) {
                $pengurangan = (int) abs($history->points);
                $totalPointCustomers[$account->account_number]['pengurangan'] += $pengurangan;
            } else {
                $val = (int) $history->points;
                if ($val > 0) {
                    $penambahan = $val;
                    $totalPointCustomers[$account->account_number]['penambahan'] += $penambahan;
                } else {
                    $pengurangan = abs($val);
                    $totalPointCustomers[$account->account_number]['pengurangan'] += $pengurangan;
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

        ksort($tempAggregated);
        $runningSaldo = 0;
        $allCoupons = [];
        foreach ($tempAggregated as $item) {
            if ($item['month'] > $month && $year >= Carbon::now()->year) {
                // Should we filter or not? In the command it looks everything is shown if it exists.
                // But for a specific period statement, maybe only show up to that period?
            }

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
        $net = $totalPointCustomers[$account->account_number]['penambahan'] - $totalPointCustomers[$account->account_number]['pengurangan'];
        $totalPointsAggregate = max(0, $net);
        if ($totalPointCustomers[$account->account_number]['penambahan'] > 0) {
            $totalPointDescriptionsAggregate .= "REK {$account->account_number} BERTAMBAH {$totalPointCustomers[$account->account_number]['penambahan']} KUPON<br>";
        }
        if ($totalPointCustomers[$account->account_number]['pengurangan'] > 0) {
            $totalPointDescriptionsAggregate .= "REK {$account->account_number} BERKURANG {$totalPointCustomers[$account->account_number]['pengurangan']} KUPON<br>";
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

        // Process PDF Generation
        $pdfPath = $this->generatePdfFile($data);
        $filename = basename($pdfPath);

        // Find existing or update version
        $existingDoc = AccountDocument::where('customer_id', $customer->id)
            ->where('account_id', $account->id)
            ->where('document_type', AccountDocument::TYPE_ESTATEMENT)
            ->first();

        if ($existingDoc) {
            $version = (int) ($existingDoc->version ?? 1) + 1;
            $existingDoc->update([
                'version' => $version,
                'is_latest' => true,
                'path' => $pdfPath,
                'filename' => $filename,
                'has_stored_to_sftp' => false, // Set to false to trigger re-upload if needed
                'period' => $currentDate->format('Y-m-d'),
                'metadata' => array_merge(json_decode($existingDoc->metadata, true) ?? [], $data)
            ]);
            return $existingDoc;
        } else {
            return AccountDocument::create([
                'customer_id' => $customer->id,
                'account_id' => $account->id,
                'type' => AccountDocument::TYPE_ESTATEMENT,
                'filename' => $filename,
                'path' => $pdfPath,
                'file_description' => "Bank Statement CIF: {$customer->cif}, Acc: {$account->account_number}, {$monthName} {$year}",
                'period' => $currentDate->format('Y-m-d'),
                'is_merged' => false,
                'status' => AccountDocument::STATUS_ACTIVE,
                'document_type' => AccountDocument::TYPE_ESTATEMENT,
                'version' => 1,
                'is_latest' => true,
                'metadata' => $data
            ]);
        }
    }

    private function generatePdfFile(array $data)
    {
        $path = storage_path('app/public/bank-statements');
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        // Generate TNC temp if needed
        $tncFilename = "term_conditions_temp.pdf";
        $tncPath = $path . '/' . $tncFilename;
        if (!file_exists($tncPath)) {
            PdfHelper::writeAndSave('pdf.term-conditions', [], $path, $tncFilename);
        }

        // Generate Bank Statement temp
        $statementFilename = "statement_temp_{$data['account_number']}.pdf";
        PdfHelper::writeAndSave('pdf.bank-statement-1', $data, $path, $statementFilename);
        $statementPath = $path . '/' . $statementFilename;

        // Merge bank-statement with term and condition
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

    public function resend(AccountDocument $document)
    {
        $document->load(['account', 'customer']);

        // 1. Regenerate
        $metadata = is_array($document->metadata) ? $document->metadata : json_decode($document->metadata, true);
        $month = (int) ($metadata['month'] ?? Carbon::parse($document->period)->month);
        $year = (int) ($metadata['year'] ?? Carbon::parse($document->period)->year);

        $updatedDoc = $this->generateForAccount($document->account_id, $month, $year);

        // 2. Upload to SFTP
        return $this->uploadToT24($updatedDoc);
    }

    public function uploadToT24(AccountDocument $doc)
    {
        $t24Path = env('CORE_T24_PATH_STATEMENT');
        $disk = Storage::disk('core_t24_sftp');

        $metadata = is_array($doc->metadata) ? $doc->metadata : json_decode($doc->metadata, true);
        $month = (int) ($metadata['month'] ?? Carbon::parse($doc->period)->month);
        $year = (int) ($metadata['year'] ?? Carbon::parse($doc->period)->year);
        $account = $doc->account;

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth()->format('Ymd');
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth()->format('Ymd');
        $patternFilename = $account->account_number . '.' . $startOfMonth . '.' . $endOfMonth;
        $finalFilenameBase = $patternFilename . '.1.pdf';

        $fullPathSftp = sprintf('%s/%s/%s', $t24Path, $year . str_pad($month, 2, '0', STR_PAD_LEFT), $account->branch?->company_book);

        // Logical check for sequence increment
        $files = [];
        try {
            $files = $disk->files($fullPathSftp);
        } catch (\Exception $e) {
            Log::error("Failed to list files in SFTP: " . $e->getMessage());
        }

        $maxSequence = 0;
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_BASENAME);
            if (strpos($filename, $patternFilename) === 0) {
                $fileParts = explode('.', $filename);
                if (count($fileParts) >= 4) {
                    $sequence = (int) $fileParts[3];
                    if ($sequence > $maxSequence)
                        $maxSequence = $sequence;
                }
            }
        }

        $nextSequence = max(1, $maxSequence + 1);
        $newFilenameSftp = "{$fullPathSftp}/{$patternFilename}.{$nextSequence}.pdf";

        Log::info("Uploading bank statement to T24 SFTP: {$newFilenameSftp}");

        try {
            $disk->put($newFilenameSftp, file_get_contents($doc->path), [
                'visibility' => 'private',
                'directory_visibility' => 'private'
            ]);

            $filenameOnly = basename($newFilenameSftp);
            $doc->update([
                'has_stored_to_sftp' => true,
                'file_name_t24' => $filenameOnly,
                'file_path_t24' => $newFilenameSftp,
                'status' => AccountDocument::STATUS_ACTIVE
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to upload bank statement: " . $e->getMessage());
            return false;
        }
    }
}
