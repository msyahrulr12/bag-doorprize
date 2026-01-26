<?php

namespace App\Helper;

use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;

class PdfHelper
{
    public static function mergePdf(array $pdfFiles, array $pages, string $outputFilename): void
    {
        $oMerger = PDFMerger::init();
        foreach ($pdfFiles as $index => $pdfFile) {
            $oMerger->addPDF($pdfFile, $pages[$index]);
        }
        $oMerger->merge();
        $oMerger->save($outputFilename);
    }

    /**
     * @param string $page
     * @param array $data
     * @param null|string $userEncryption
     * @return string
     */
    public function write($page, $data, $paper = 'A4', $orientation = 'portrait', $userEncryption = null)
    {
        $pdf = DomPDF::loadView($page, $data)
            ->setPaper($paper, $orientation);

        if ($userEncryption) {
            $pdf->setEncryption($userEncryption, env('PDF_OWNER_PASSWORD', 'bag123!'), explode(',', env('PDF_USER_PERMISSIONS', 'print')));
        }

        return $pdf->output();
    }
}