<?php

namespace App\Listeners;

use App\Events\GenerateBankStatementProcessed;
use App\Helper\PdfHelper;
use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Process;
use Storage;

class MergeBankStatement
{
    public $queue = 'bank-statement';

    /**
     * Create the event listener.
     */
    public function __construct()
    {

    }

    /**
     * Handle the event.
     */
    public function handle(GenerateBankStatementProcessed $event): void
    {
        if (!file_exists($event->bankStatementPdfPath)) {
            return;
        }

        $mergePdfBankStatement = (bool) Setting::where('key', 'merge_pdf_bank_statement')->first()->value ?? false;
        $disk = Storage::disk('core_t24_sftp');
        $path = env('CORE_T24_PATH_STATEMENT');
        $fullPath = sprintf('%s/%s/%s', $path, $event->year . str_pad($event->month, 2, '0', STR_PAD_LEFT), $event->accountBranch);
        $filename = $this->getFilename($event->accountNumber, $event->month, $event->year, false);
        $files = collect($disk->files($fullPath) ?? [])->filter(function ($path) use ($filename) {
            return strpos($path, $filename) !== false;
        })->map(function ($path) use ($mergePdfBankStatement) {
            return $path;
        });

        if (count($files) < 1) {
            return;
        }

        $mergedPath = 'public/bank-statements/';
        $tempPath = '/tmp';
        $successCount = 0;

        foreach ($files as $file) {
            $filenameExp = explode('/', $file);
            $filename = $filenameExp[count($filenameExp) - 1];
            Storage::disk('tmp')->put($filename, $disk->get($file), [
                'visibility' => 'public',
                'directory_visibility' => 'public'
            ]);

            // Decrypt using qpdf
            $passwordPdf = env('PDF_OWNER_PASSWORD', 'bag123!');
            $outputPdf = $tempPath . '/decrypted_' . $filename;
            \Illuminate\Support\Facades\Process::run("qpdf --decrypt --password=\"$passwordPdf\" \"$tempPath/$filename\" \"$outputPdf\"");
            chmod($outputPdf, 0777);

            PdfHelper::mergePdf(
                [
                    $outputPdf,
                    $event->bankStatementPdfPath,
                ],
                [
                    'all',
                    'all'
                ],
                $mergedPath . $filename,
                [
                    'password' => env('PDF_OWNER_PASSWORD', 'bag123!'),
                    'user_password' => $event->pdfPasswordUser
                ]
            );

            $putSuccess = $disk->put($file, file_get_contents($mergedPath . $filename), [
                'visibility' => 'private',
                'directory_visibility' => 'private'
            ]);

            if ($putSuccess) {
                $successCount++;
            }

            // Cleanup temp merged file
            if (file_exists($mergedPath . $filename)) {
                unlink($mergedPath . $filename);
            }
        }

        if ($successCount > 0) {
            // Update flag
            \App\Models\AccountDocument::whereHas('account', function ($q) use ($event) {
                $q->where('account_number', $event->accountNumber);
            })
                ->where('document_type', \App\Models\AccountDocument::TYPE_ESTATEMENT)
                ->update(['has_stored_to_sftp' => true]);

            // Remove local generated file as per requirement
            if (file_exists($event->bankStatementPdfPath)) {
                unlink($event->bankStatementPdfPath);
            }
        }
    }

    private function getFilename(string $accountNumber, int $month, int $year, bool $withExt = false)
    {
        $date = new \Carbon\Carbon($year . '-' . $month . '-01');
        return sprintf('%s.%s.%s%s', $accountNumber, $date->startOfMonth()->format('Ymd'), $date->endOfMonth()->format('Ymd'), $withExt ? '.pdf' : '');
    }
}
