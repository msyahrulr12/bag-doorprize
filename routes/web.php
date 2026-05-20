<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::get('/draw/{uuid}', \App\Livewire\Public\GrandDrawing::class)->name('public.draw');
Route::get('/draw-bulk/{uuid}', \App\Livewire\Public\BulkDrawing::class)->name('public.draw-bulk');
Route::view('/term-condition', 'pdf.term-conditions')->name('public.term-condition');
Route::get('/admin/logs', \App\Livewire\Admin\LogViewer::class)->name('admin.logs');
Route::get('/admin/services', \App\Livewire\Admin\ServiceMonitor::class)->name('admin.services');
Route::get('/admin/deployment', \App\Livewire\Admin\DeploymentManager::class)->name('admin.deployment');
Route::view('/locked', 'locked')->name('locked');

// Route::get('/test-pdf', function () {
//     $coupons = [];
//     for ($i = 1; $i <= 12; $i++) {
//         $coupons[] = [
//             'periode' => 'Bulan ' . $i,
//             'penambahan' => '10',
//             'pengurangan' => '0',
//             'nomor' => 'A' . $i . ' s/d A' . ($i + 10),
//             'saldo' => 10 * $i,
//             'keterangan' => 'Keterangan panjang yang mungkin wrap ke multiple lines untuk row ke ' . $i,
//         ];
//     }

//     $data = [
//         'type' => 'png',
//         'data' => 'dummy',
//         'branch' => 'TEST BRANCH',
//         'customer_name' => 'JOHN DOE MULTIPAGE',
//         'period' => '01 Jan s/d 31 Jan 2026',
//         'cif_number' => '12345678',
//         'coupons' => $coupons,
//         'showSuccessMessage' => true,
//         'monthName' => 'Januari',
//         'year' => 2026,
//         'totalPoints' => 600,
//         'totalPointDescriptions' => 'Test',
//     ];

//     $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.bank-statement-1', $data)->setPaper('a4', 'portrait');
//     // return $pdf->download('bank-statement-multipage.pdf');
//     return $pdf->stream('bank-statement-multipage.pdf');
// });