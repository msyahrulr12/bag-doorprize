<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Barryvdh\DomPDF\Facade\Pdf as DomPDF;

$coupons = [];
for ($i=1; $i<=60; $i++) {
    $coupons[] = [
        'periode' => 'Bulan '.$i,
        'penambahan' => '10',
        'pengurangan' => '0',
        'nomor' => 'A'.$i.' s/d A'.($i+10),
        'saldo' => 10 * $i,
        'keterangan' => 'Keterangan panjang yang mungkin wrap ke multiple lines untuk row ke '.$i,
    ];
}

$data = [
    'type' => 'png',
    'data' => 'dummy', 
    'branch' => 'TEST BRANCH',
    'customer_name' => 'JOHN DOE MULTIPAGE',
    'period' => '01 Jan s/d 31 Jan 2026',
    'cif_number' => '12345678',
    'coupons' => $coupons,
    'showSuccessMessage' => true,
    'monthName' => 'Januari',
    'year' => 2026,
    'totalPoints' => 600,
    'totalPointDescriptions' => 'Test',
];

$pdf = DomPDF::loadView('pdf.bank-statement-1', $data)
            ->setPaper('A4', 'portrait');

$pdf->save(storage_path('app/public/test-multipage.pdf'));
echo "Saved multipage PDF\n";
