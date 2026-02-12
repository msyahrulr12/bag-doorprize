<?php

namespace App\Helper;

use Illuminate\Support\Facades\Process;
use Log;
use Mostafaznv\PdfOptimizer\Enums\PdfSettings;
use Mostafaznv\PdfOptimizer\Laravel\Facade\PdfOptimizer;
use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;

class PdfHelper
{
    public static function mergePdf(array $pdfFiles, array $pages, string $outputFilename, array $encrypt = []): void
    {
        $oMerger = PDFMerger::init();
        foreach ($pdfFiles as $index => $pdfFile) {
            try {
                $oMerger->addPDF($pdfFile, $pages[$index]);
            } catch (\Exception $e) {
                $oMerger->addString(file_get_contents($pdfFile), $pages[$index]);
            }
        }
        $oMerger->merge();
        $oMerger->save($outputFilename);

        if ((isset($encrypt['password']) && $encrypt['password'] != '')) {
            $password = $encrypt['password'];
            $userPassword = $encrypt['user_password'] ?? null;
            Log::info('User Password Placed: ' . $userPassword);
            $commandEncrypt = [
                "qpdf",
                "--encrypt",
                $password,
                $userPassword,
                "256",
                "--",
                $outputFilename,
                "--replace-input"
            ];

            $result = Process::run($commandEncrypt);
            if ($result->failed()) {
                throw new \Exception("Failed to encrypt PDF: " . $result->errorOutput());
            }
        }
    }

    /**
     * @param string $page
     * @param array $data
     * @param null|string $userEncryption
     * @return string
     */
    public static function write($page, $data, $paper = 'A4', $orientation = 'portrait', $userEncryption = null)
    {
        $pdf = DomPDF::loadView($page, $data)
            ->setPaper($paper, $orientation);

        if ($userEncryption) {
            $pdf->setEncryption($userEncryption, env('PDF_OWNER_PASSWORD', 'bag123!'), explode(',', env('PDF_USER_PERMISSIONS', 'print')));
        }

        return $pdf->output();
    }

    /**
     * @param string $page
     * @param array $data
     * @param string $path
     * @param string $filename
     * @param string $paper
     * @param string $orientation
     * @param null|string $userEncryption
     */
    public static function writeAndSave($page, $data, $path, $filename, $paper = 'A4', $orientation = 'portrait', $userEncryption = null)
    {
        $pdf = DomPDF::loadView($page, $data)
            ->setPaper($paper, $orientation);

        if ($userEncryption) {
            $pdf->setEncryption($userEncryption, env('PDF_OWNER_PASSWORD', 'bag123!'), explode(',', env('PDF_USER_PERMISSIONS', 'print')));
        }

        return $pdf->save($path . '/' . $filename);
    }

    public static function compress($filename, $disk = 'local')
    {
        $result = PdfOptimizer::fromDisk($disk)
            ->open($filename)
            ->toDisk($disk)
            ->settings(PdfSettings::SCREEN)
            ->optimize($filename, $filename);

        return $result->status;
    }
}