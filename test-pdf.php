<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use App\Models\Account;

// We will mock the data
$coupons = [];
for ($i = 1; $i <= 12; $i++) {
    $coupons[] = [
        'periode' => 'Bulan ' . $i,
        'penambahan' => '10',
        'pengurangan' => '0',
        'nomor' => 'A' . $i . ' s/d A' . ($i + 9),
        'saldo' => '10',
        'keterangan' => 'Keterangan transaksi untuk baris ke ' . $i . ' yang mungkin cukup panjang untuk mengetes wrapping.',
    ];
}

$data = [
    'type' => 'png',
    'data' => 'dummy', // Not really used in test but needed for view
    'branch' => 'TEST BRANCH',
    'customer_name' => 'JOHN DOE',
    'period' => '01 Jan s/d 31 Jan 2026',
    'cif_number' => '12345678',
    'coupons' => $coupons,
    'showSuccessMessage' => true,
    'monthName' => 'Januari',
    'year' => 2026,
    'totalPoints' => 120,
    'totalPointDescriptions' => 'Total 120 Kupon',
];

$pdf = DomPDF::loadView('pdf.bank-statement-1', $data)
            ->setPaper('A4', 'portrait');

$pdf->save(storage_path('app/public/test-statement.pdf'));

echo "PDF saved at: " . storage_path('app/public/test-statement.pdf') . "\n";
