<?php

namespace App\Filament\Imports;

use App\Models\Product;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sk_produk')
                ->requiredMapping()
                ->rules(['required', 'max:6']),
            ImportColumn::make('kode_group_produk')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('group_produk')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('kode_produk')
                ->requiredMapping()
                ->rules(['required', 'max:6']),
            ImportColumn::make('nama_produk')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('nama_singkat_produk')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('kode_sub_produk')
                ->rules(['max:255']),
            ImportColumn::make('nama_sub_produk')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('gol_mas')
                ->rules(['max:255']),
            ImportColumn::make('date_time')
                ->rules(['date']),
            ImportColumn::make('batch_date')
                ->rules(['date']),
            ImportColumn::make('insert_date')
                ->rules(['date']),
        ];
    }

    public function resolveRecord(): Product
    {
        return Product::firstOrNew([
            'sk_produk' => $this->data['sk_produk'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your product import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
